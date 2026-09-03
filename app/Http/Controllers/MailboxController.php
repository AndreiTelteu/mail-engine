<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\User;
use App\Services\EmailSearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MailboxController extends Controller
{
    public function index(Request $request, EmailSearchService $searchService): Response
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:250'],
            'folder' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $query = $validated['query'] ?? '';
        $folder = $validated['folder'] ?? null;
        $results = blank($query) && $folder === null
            ? $searchService->getRecent($user)
            : $searchService->search($user, $query, $folder);

        return Inertia::render('Mailbox', [
            'folders' => Email::query()
                ->whereBelongsTo($user)
                ->select('folder')
                ->distinct()
                ->orderBy('folder')
                ->pluck('folder')
                ->values(),
            'filters' => [
                'query' => $query,
                'folder' => $folder,
            ],
            'emails' => [
                'data' => collect($results->items())
                    ->map(fn (Email $email): array => [
                        'id' => $email->id,
                        ...$searchService->formatResult($email, $query ?: null),
                    ])
                    ->values(),
                'currentPage' => $results->currentPage(),
                'lastPage' => $results->lastPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function show(Request $request, int $emailId): Response
    {
        /** @var User $user */
        $user = $request->user();
        $email = Email::query()
            ->whereBelongsTo($user)
            ->findOrFail($emailId);

        return Inertia::render('Email', [
            'email' => [
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
            ],
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
