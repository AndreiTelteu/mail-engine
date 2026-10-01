<?php

namespace App\Mcp\Tools;

use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\ImapConnectionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Description('Fetch one email attachment from IMAP by email ID and zero-based attachment index. Returns filename, MIME type, size, and base64-encoded file contents. Maximum 5 MiB.')]
#[Name('get-email-attachment')]
#[IsReadOnly]
class GetEmailAttachmentTool extends Tool
{
    public function handle(Request $request, ImapConnectionService $imap): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Authentication required.');
        }

        $input = $request->validate([
            'email_id' => ['required', 'integer', 'min:1'],
            'index' => ['required', 'integer', 'min:0'],
        ]);
        $email = Email::query()
            ->whereBelongsTo($user)
            ->with('mailFolder.imapSetting')
            ->find($input['email_id']);

        if (! $email instanceof Email || ! array_key_exists($input['index'], $email->attachments ?? [])) {
            return Response::error('Attachment not found.');
        }

        $setting = $email->mailFolder?->imapSetting;

        if (! $setting instanceof ImapSetting || $setting->user_id !== $user->id) {
            return Response::error('Attachment not found.');
        }

        $connection = null;

        try {
            $connection = $imap->connect($setting);
            $status = $imap->getFolderStatus($connection, $email->mailFolder->path);

            if ($status->uidValidity !== $email->uid_validity) {
                return Response::error('The mailbox has changed. Synchronize it before fetching attachments.');
            }

            $content = $imap->getAttachment($connection, $email->mailFolder->path, $email->imap_uid, $input['index']);

            if ($content === null) {
                return Response::error('Attachment no longer exists on the mail server.');
            }

            if (strlen($content) > 5 * 1024 * 1024) {
                return Response::error('Attachment exceeds the 5 MiB MCP response limit.');
            }

            $attachment = $email->attachments[$input['index']];
            $filetype = $attachment['filetype'] ?? null;

            return Response::json([
                'email_id' => $email->id,
                'index' => $input['index'],
                'filename' => $attachment['filename'] ?? null,
                'mime_type' => is_string($filetype) && str_contains($filetype, '/') ? $filetype : 'application/octet-stream',
                'size' => strlen($content),
                'content_base64' => base64_encode($content),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return Response::error('Unable to retrieve attachment from the mail server.');
        } finally {
            $connection?->disconnect();
        }
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'email_id' => $schema->integer()->description('Email ID returned by search-emails.')->required(),
            'index' => $schema->integer()->description('Zero-based attachment index returned by get-email.')->required(),
        ];
    }
}
