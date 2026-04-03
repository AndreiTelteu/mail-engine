<?php

namespace App\Services;

use App\DataTransferObjects\EmailData;
use App\DataTransferObjects\ImapConnection;
use App\Exceptions\Imap\IndexingException;
use App\Models\Email;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseEmailIndexingService implements EmailIndexingService
{
    public function __construct(
        protected ImapConnectionService $imapConnectionService,
    ) {}

    public function indexEmail(
        User $user,
        string $messageId,
        string $folder,
        ImapConnection $connection,
    ): Email {
        $existing = Email::query()
            ->whereBelongsTo($user)
            ->where('message_id', $messageId)
            ->first();

        if ($existing !== null) {
            if ($existing->indexing_failed_at !== null) {
                $existing->forceFill([
                    'indexing_failed_at' => null,
                    'indexing_error' => null,
                ])->save();
            }

            return $existing;
        }

        $emailData = $this->extractEmailData($connection, $messageId);

        try {
            return DB::transaction(function () use ($emailData, $folder, $messageId, $user): Email {
                return Email::query()->firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'message_id' => $messageId,
                    ],
                    [
                        'folder' => $folder,
                        'from_address' => $emailData->fromAddress,
                        'from_name' => $emailData->fromName,
                        'to_addresses' => $emailData->toAddresses,
                        'cc_addresses' => $emailData->ccAddresses,
                        'subject' => $emailData->subject,
                        'date' => $emailData->date,
                        'body_text' => $emailData->bodyText,
                        'body_html' => $emailData->bodyHtml,
                        'attachments' => $this->sanitizeAttachments($emailData->attachments),
                        'indexing_failed_at' => null,
                        'indexing_error' => null,
                    ],
                );
            });
        } catch (Throwable $exception) {
            throw new IndexingException(
                "Failed to index email [{$messageId}] for user [{$user->id}].",
                previous: $exception,
            );
        }
    }

    public function isEmailIndexed(User $user, string $messageId): bool
    {
        return Email::query()
            ->whereBelongsTo($user)
            ->where('message_id', $messageId)
            ->exists();
    }

    public function extractEmailData(ImapConnection $connection, string $messageId): EmailData
    {
        try {
            $emailData = $this->imapConnectionService->getEmail($connection, $messageId);

            return new EmailData(
                messageId: $emailData->messageId,
                fromAddress: $emailData->fromAddress,
                fromName: $emailData->fromName,
                toAddresses: $emailData->toAddresses,
                ccAddresses: $emailData->ccAddresses,
                subject: $emailData->subject,
                date: $emailData->date,
                bodyText: $emailData->bodyText,
                bodyHtml: $emailData->bodyHtml,
                attachments: $this->sanitizeAttachments($emailData->attachments),
            );
        } catch (Throwable $exception) {
            throw new IndexingException(
                "Failed to extract email data for message [{$messageId}].",
                previous: $exception,
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<int, array{filename:?string, filetype:?string}>
     */
    protected function sanitizeAttachments(array $attachments): array
    {
        return collect($attachments)
            ->map(fn (array $attachment): array => [
                'filename' => isset($attachment['filename']) ? (filled($attachment['filename']) ? (string) $attachment['filename'] : null) : null,
                'filetype' => isset($attachment['filetype']) ? (filled($attachment['filetype']) ? (string) $attachment['filetype'] : null) : null,
            ])
            ->values()
            ->all();
    }
}
