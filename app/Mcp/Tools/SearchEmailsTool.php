<?php

namespace App\Mcp\Tools;

use App\Models\Email;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search or list indexed emails across folders. A text query is optional. Filter by email IDs, date or ISO 8601 timestamp range, choose ordering and a result limit, and request only needed fields with include_fields.')]
#[Name('search-emails')]
#[IsReadOnly]
class SearchEmailsTool extends Tool
{
    private const OUTPUT_COLUMNS = [
        'id' => 'id',
        'message_id' => 'message_id',
        'in_reply_to' => 'in_reply_to',
        'references' => 'references',
        'folder' => 'folder',
        'from_address' => 'from_address',
        'from_name' => 'from_name',
        'to' => 'to_addresses',
        'cc' => 'cc_addresses',
        'subject' => 'subject',
        'date' => 'date',
        'preview' => 'preview',
        'attachment_count' => 'attachment_count',
        'attachments' => 'attachments',
        'body_text' => 'body_text',
        'body_html' => 'body_html',
        'body_current' => 'body_current',
        'body_quoted' => 'body_quoted',
    ];

    private const DEFAULT_FIELDS = ['id', 'folder', 'from_address', 'from_name', 'to', 'cc', 'subject', 'date', 'preview', 'attachment_count'];

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Authentication required.');
        }

        $input = $request->validate([
            'query' => ['nullable', 'string', 'max:250'],
            'email_ids' => ['sometimes', 'array', 'list', 'min:1', 'max:100'],
            'email_ids.*' => ['integer', 'min:1', 'distinct'],
            'message_ids' => ['sometimes', 'array', 'list', 'min:1', 'max:100'],
            'message_ids.*' => ['string', 'max:255', 'distinct'],
            'folder' => ['nullable', 'string', 'max:255'],
            'has_attachment' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'from' => ['nullable', 'date_format:Y-m-d\TH:i:sP,Y-m-d\TH:i:s\Z'],
            'to' => ['nullable', 'date_format:Y-m-d\TH:i:sP,Y-m-d\TH:i:s\Z', 'after:from'],
            'person' => ['nullable', 'string', 'max:250'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by' => ['nullable', Rule::in(['date', 'id', 'subject', 'from_address'])],
            'order_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'include_fields' => ['sometimes', 'array', 'list', 'min:1'],
            'include_fields.*' => ['string', 'distinct', Rule::in(array_keys(self::OUTPUT_COLUMNS))],
        ]);

        $fields = $input['include_fields'] ?? self::DEFAULT_FIELDS;
        $columns = array_values(array_unique(array_merge(['id', 'date'], array_map(
            fn (string $field): string => self::OUTPUT_COLUMNS[$field],
            $fields,
        ))));

        $emails = Email::query()
            ->whereBelongsTo($user)
            ->select($columns);

        if (isset($input['email_ids'])) {
            $emails->whereIn('id', $input['email_ids']);
        }

        if (isset($input['message_ids'])) {
            $emails->whereIn('message_id', $input['message_ids']);
        }

        if (filled($input['folder'] ?? null)) {
            $emails->where('folder', $input['folder']);
        }

        if (array_key_exists('has_attachment', $input) && $input['has_attachment'] !== null) {
            $input['has_attachment']
                ? $emails->where('attachment_count', '>', 0)
                : $emails->where('attachment_count', 0);
        }

        if (isset($input['date_from'])) {
            $emails->where('date', '>=', CarbonImmutable::parse($input['date_from'])->startOfDay());
        }

        if (isset($input['date_to'])) {
            $emails->where('date', '<', CarbonImmutable::parse($input['date_to'])->addDay()->startOfDay());
        }

        if (isset($input['from'])) {
            $emails->where('date', '>=', CarbonImmutable::parse($input['from'])->utc());
        }

        if (isset($input['to'])) {
            $emails->where('date', '<', CarbonImmutable::parse($input['to'])->utc());
        }

        if (filled($input['person'] ?? null)) {
            $person = '%'.trim($input['person']).'%';
            $emails->where(function (Builder $query) use ($person): void {
                $query->whereLike('from_address', $person)
                    ->orWhereLike('from_name', $person)
                    ->orWhereLike('to_addresses', $person)
                    ->orWhereLike('cc_addresses', $person);
            });
        }

        if (filled($input['query'] ?? null)) {
            $term = '%'.trim($input['query']).'%';
            $emails->where(function (Builder $query) use ($term): void {
                $query->whereLike('subject', $term)
                    ->orWhereLike('body_text', $term)
                    ->orWhereLike('body_current', $term)
                    ->orWhereLike('body_quoted', $term)
                    ->orWhereLike('from_address', $term)
                    ->orWhereLike('from_name', $term)
                    ->orWhereLike('to_addresses', $term)
                    ->orWhereLike('cc_addresses', $term)
                    ->orWhereLike('attachments', $term);
            });
        }

        $orderBy = $input['order_by'] ?? 'date';
        $direction = $input['order_direction'] ?? 'desc';
        $page = $emails->orderBy($orderBy, $direction)
            ->when($orderBy !== 'id', fn (Builder $query) => $query->orderBy('id', $direction))
            ->paginate($input['limit'] ?? $input['per_page'] ?? 20, page: $input['page'] ?? 1);

        return Response::json([
            'emails' => $page->getCollection()->map(fn (Email $email): array => $this->selectedFields($email, $fields))->all(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function selectedFields(Email $email, array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            $result[$field] = match ($field) {
                'id' => $email->id,
                'message_id' => $email->message_id,
                'in_reply_to' => $email->in_reply_to,
                'references' => $email->references,
                'folder' => $email->folder,
                'from_address' => $email->from_address,
                'from_name' => $email->from_name,
                'to' => $email->to_addresses ?? [],
                'cc' => $email->cc_addresses ?? [],
                'subject' => $email->subject,
                'date' => $email->date?->toIso8601String(),
                'preview' => $email->preview,
                'attachment_count' => $email->attachment_count,
                'attachments' => collect($email->attachments)->values()->map(fn (array $attachment, int $index): array => [
                    'index' => $index,
                    'filename' => $attachment['filename'] ?? null,
                    'filetype' => $attachment['filetype'] ?? null,
                ])->all(),
                'body_text' => $email->body_text,
                'body_html' => $email->body_html,
                'body_current' => $email->body_current,
                'body_quoted' => $email->body_quoted,
            };
        }

        return $result;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Optional text in subject, body, participants, or attachment filename. Omit to list latest emails.'),
            'email_ids' => $schema->array()->items($schema->integer())->min(1)->max(100)->unique()->description('Local numeric email IDs returned by search-emails or sync-emails; use with include_fields to fetch fields for a list of messages.'),
            'message_ids' => $schema->array()->items($schema->string())->min(1)->max(100)->unique()->description('Optional RFC Message-ID header values; use email_ids for local numeric IDs.'),
            'folder' => $schema->string()->description('Exact folder path. Omit to search every folder.'),
            'has_attachment' => $schema->boolean()->description('True for emails with attachments, false for emails without them.'),
            'date_from' => $schema->string()->description('Inclusive earliest date, YYYY-MM-DD.'),
            'date_to' => $schema->string()->description('Inclusive latest date, YYYY-MM-DD.'),
            'from' => $schema->string()->description('Inclusive ISO 8601 timestamp with timezone, e.g. 2026-10-01T00:00:00+03:00. Use local midnight for today or yesterday.'),
            'to' => $schema->string()->description('Exclusive ISO 8601 timestamp with timezone, e.g. next local midnight. Combine with from for an exact hour or any period.'),
            'person' => $schema->string()->description('Part of a sender, To, or Cc name or email address.'),
            'page' => $schema->integer()->description('Page number, starting at 1.'),
            'per_page' => $schema->integer()->description('Results per page, from 1 to 100.'),
            'limit' => $schema->integer()->description('Maximum results in this page, from 1 to 100. Use page for more results.'),
            'order_by' => $schema->string()->enum(['date', 'id', 'subject', 'from_address'])->description('Sort field; defaults to date.'),
            'order_direction' => $schema->string()->enum(['asc', 'desc'])->description('Sort direction; defaults to desc (newest first).'),
            'include_fields' => $schema->array()->items($schema->string()->enum(array_keys(self::OUTPUT_COLUMNS)))
                ->min(1)->unique()->description('Only these fields are returned per email. Example: ["id", "attachments"]. Omit for the usual summary. Attachments contain index, filename, and filetype, never file content.'),
        ];
    }
}
