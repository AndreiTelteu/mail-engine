<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\User;
use App\Services\EmailSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MailboxController extends Controller
{
    public function index(Request $request, EmailSearchService $searchService): Response
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:250'],
            'folder' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $query = $validated['query'] ?? '';
        $folder = $validated['folder'] ?? null;
        $selectedEmailId = isset($validated['email'])
            ? (int) $validated['email']
            : null;
        $mailboxSummary = null;
        $getMailboxSummary = function () use (&$mailboxSummary, $user): array {
            return $mailboxSummary ??= $this->mailboxSummary($user);
        };

        return Inertia::render('Mailbox', [
            'folders' => fn (): array => $getMailboxSummary()['folders'],
            'indexedTotal' => fn (): int => $getMailboxSummary()['total'],
            'filters' => [
                'query' => $query,
                'folder' => $folder,
                'email' => $selectedEmailId,
            ],
            'selectedEmail' => function () use ($selectedEmailId, $user): ?array {
                if ($selectedEmailId === null) {
                    return null;
                }

                $selectedEmail = $this->emailDetailQuery($user)->find($selectedEmailId);

                return $selectedEmail instanceof Email
                    ? $this->emailDetail($selectedEmail)
                    : null;
            },
            'emails' => function () use ($folder, $query, $searchService, $user): array {
                $results = blank($query) && $folder === null
                    ? $searchService->getRecent($user)
                    : $searchService->search($user, $query, $folder);

                return [
                    'data' => collect($results->items())
                        ->map(fn (Email $email): array => [
                            'id' => $email->id,
                            'attachmentCount' => count($email->attachments ?? []),
                            ...$searchService->formatResult($email, $query ?: null),
                        ])
                        ->values(),
                    'currentPage' => $results->currentPage(),
                    'lastPage' => $results->lastPage(),
                    'total' => $results->total(),
                    'from' => $results->firstItem(),
                    'to' => $results->lastItem(),
                ];
            },
        ]);
    }

    /**
     * The user's folder counts and total, computed by one grouped query.
     *
     * @return array{folders: array<int, array{name: string, count: int}>, total: int}
     */
    private function mailboxSummary(User $user): array
    {
        $folders = Email::query()
            ->whereBelongsTo($user)
            ->selectRaw('folder, count(*) as aggregate')
            ->groupBy('folder')
            ->orderBy('folder')
            ->get()
            ->map(fn (Email $email): array => [
                'name' => $email->folder,
                'count' => (int) $email->getAttribute('aggregate'),
            ])
            ->values();

        return [
            'folders' => $folders->all(),
            'total' => $folders->sum('count'),
        ];
    }

    public function show(Request $request, int $emailId): Response
    {
        /** @var User $user */
        $user = $request->user();
        $email = $this->emailDetailQuery($user)->findOrFail($emailId);

        return Inertia::render('Email', ['email' => $this->emailDetail($email)]);
    }

    /**
     * @return array{id: int, folder: string, from: string, to: string, cc: string, subject: string, date: ?string, document: string, attachments: Collection<int, array{filename: string, filetype: string}>}
     */
    private function emailDetail(Email $email): array
    {
        return [
            'id' => $email->id,
            'folder' => $email->folder,
            'from' => $email->from_name
                ? "{$email->from_name} <{$email->from_address}>"
                : $email->from_address,
            'to' => $this->formatAddresses($email->to_addresses),
            'cc' => $this->formatAddresses($email->cc_addresses),
            'subject' => $email->subject ?: '(no subject)',
            'date' => $email->date?->format('M j, Y g:i A'),
            'document' => $this->iframeDocument($email),
            'attachments' => collect($email->attachments)
                ->filter(fn (array $attachment): bool => filled($attachment['filename'] ?? null)
                    || filled($attachment['filetype'] ?? null))
                ->map(fn (array $attachment): array => [
                    'filename' => $attachment['filename'] ?? 'unknown',
                    'filetype' => $attachment['filetype'] ?? 'unknown',
                ])
                ->values(),
        ];
    }

    private function emailDetailQuery(User $user): Builder
    {
        return Email::query()
            ->whereBelongsTo($user)
            ->select([
                'id',
                'user_id',
                'folder',
                'from_address',
                'from_name',
                'to_addresses',
                'cc_addresses',
                'subject',
                'date',
                'body_text',
                'body_html',
                'attachments',
            ]);
    }

    /**
     * @param  array<int, array{name?: string, address?: string}>|null  $addresses
     */
    private function formatAddresses(?array $addresses): string
    {
        return collect($addresses)
            ->map(fn (array $address): string => filled($address['name'] ?? null)
                ? "{$address['name']} <{$address['address']}>"
                : ($address['address'] ?? ''))
            ->filter()
            ->join(', ');
    }

    private function iframeDocument(Email $email): string
    {
        if (filled($email->body_html)) {
            return $this->wrapDocument($email->body_html);
        }

        return $this->wrapDocument('<pre style="margin:0;white-space:pre-wrap;word-break:break-word;font:14px/1.6 Arial,Helvetica,sans-serif;color:#111827;">'
            .e($email->body_text ?? '').'</pre>');
    }

    private function wrapDocument(string $content): string
    {
        $content = trim($content);

        if ($content === '' || preg_match('/<(?:!DOCTYPE|html|body)\b/i', $content) === 1) {
            return $content;
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><base target="_blank"><style>html,body{margin:0;padding:0;background:#fff}img,table{max-width:100%}</style></head><body>'
            .$content.'</body></html>';
    }
}
