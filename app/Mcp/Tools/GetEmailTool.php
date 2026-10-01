<?php

namespace App\Mcp\Tools;

use App\Models\Email;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read an indexed email by ID. Use include_fields to request only needed fields, such as ["id", "subject", "body_text"]. Omit it for full content. Attachment indexes can be passed to get-email-attachment.')]
#[Name('get-email')]
#[IsReadOnly]
class GetEmailTool extends Tool
{
    private const OUTPUT_COLUMNS = [
        'id' => 'id',
        'folder' => 'folder',
        'message_id' => 'message_id',
        'from_address' => 'from_address',
        'from_name' => 'from_name',
        'to' => 'to_addresses',
        'cc' => 'cc_addresses',
        'subject' => 'subject',
        'date' => 'date',
        'body_text' => 'body_text',
        'body_html' => 'body_html',
        'attachments' => 'attachments',
    ];

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Authentication required.');
        }

        $input = $request->validate([
            'email_id' => ['required', 'integer', 'min:1'],
            'include_fields' => ['sometimes', 'array', 'list', 'min:1'],
            'include_fields.*' => ['string', 'distinct', Rule::in(array_keys(self::OUTPUT_COLUMNS))],
        ]);
        $fields = $input['include_fields'] ?? array_keys(self::OUTPUT_COLUMNS);
        $columns = array_values(array_unique(array_merge(['id'], array_map(
            fn (string $field): string => self::OUTPUT_COLUMNS[$field],
            $fields,
        ))));
        $email = Email::query()->whereBelongsTo($user)->select($columns)->find($input['email_id']);

        if (! $email instanceof Email) {
            return Response::error('Email not found.');
        }

        $result = [];

        foreach ($fields as $field) {
            $result[$field] = match ($field) {
                'id' => $email->id,
                'folder' => $email->folder,
                'message_id' => $email->message_id,
                'from_address' => $email->from_address,
                'from_name' => $email->from_name,
                'to' => $email->to_addresses ?? [],
                'cc' => $email->cc_addresses ?? [],
                'subject' => $email->subject,
                'date' => $email->date?->toIso8601String(),
                'body_text' => $email->body_text,
                'body_html' => $email->body_html,
                'attachments' => collect($email->attachments)->values()->map(fn (array $attachment, int $index): array => [
                    'index' => $index,
                    'filename' => $attachment['filename'] ?? null,
                    'filetype' => $attachment['filetype'] ?? null,
                ])->all(),
            };
        }

        return Response::json($result);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'email_id' => $schema->integer()->description('Email ID returned by search-emails.')->required(),
            'include_fields' => $schema->array()->items($schema->string()->enum(array_keys(self::OUTPUT_COLUMNS)))
                ->min(1)->unique()->description('Only these fields are returned. Example: ["id", "subject", "body_text"]. Omit for full email content.'),
        ];
    }
}
