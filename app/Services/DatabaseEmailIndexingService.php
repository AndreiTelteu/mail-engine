<?php

namespace App\Services;

use App\DataTransferObjects\EmailData;
use App\Exceptions\Imap\IndexingException;
use App\Models\Email;
use App\Models\MailFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseEmailIndexingService implements EmailIndexingService
{
    public function __construct(
        protected EmailContentProcessor $contentProcessor,
    ) {}

    /**
     * @param  array<int, EmailData>  $emails
     * @return Collection<int, Email>
     */
    public function indexEmails(
        User $user,
        MailFolder $mailFolder,
        array $emails,
    ): Collection {
        if ($emails === []) {
            return new Collection;
        }

        try {
            return DB::transaction(function () use ($emails, $mailFolder, $user): Collection {
                $now = now();
                $records = collect($emails)
                    ->map(function (EmailData $email) use ($mailFolder, $now, $user): array {
                        $content = $this->contentProcessor->process($email);
                        $attachments = $this->sanitizeAttachments($email->attachments);

                        return [
                            'mail_folder_id' => $mailFolder->id,
                            'user_id' => $user->id,
                            'uid_validity' => $mailFolder->uid_validity,
                            'imap_uid' => $email->imapUid,
                            'message_id' => filled($email->messageId) ? $email->messageId : null,
                            'in_reply_to' => $email->inReplyTo,
                            'references' => $email->references,
                            'folder' => $mailFolder->path,
                            'from_address' => $email->fromAddress,
                            'from_name' => $email->fromName,
                            'to_addresses' => json_encode($email->toAddresses, JSON_THROW_ON_ERROR),
                            'cc_addresses' => json_encode($email->ccAddresses, JSON_THROW_ON_ERROR),
                            'subject' => $email->subject,
                            'date' => $email->date,
                            'body_text' => $email->bodyText,
                            'body_html' => $email->bodyHtml,
                            'body_current' => $content->bodyCurrent,
                            'body_quoted' => $content->bodyQuoted,
                            'preview' => $content->preview,
                            'attachments' => json_encode($attachments, JSON_THROW_ON_ERROR),
                            'attachment_count' => count($attachments),
                            'content_hash' => $content->contentHash,
                            'parser_version' => $content->parserVersion,
                            'indexing_failed_at' => null,
                            'indexing_error' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })
                    ->all();

                Email::withoutSyncingToSearch(function () use ($records): void {
                    Email::query()->upsert(
                        $records,
                        ['mail_folder_id', 'uid_validity', 'imap_uid'],
                        [
                            'message_id',
                            'in_reply_to',
                            'references',
                            'folder',
                            'from_address',
                            'from_name',
                            'to_addresses',
                            'cc_addresses',
                            'subject',
                            'date',
                            'body_text',
                            'body_html',
                            'body_current',
                            'body_quoted',
                            'preview',
                            'attachments',
                            'attachment_count',
                            'content_hash',
                            'parser_version',
                            'indexing_failed_at',
                            'indexing_error',
                            'updated_at',
                        ],
                    );
                });

                return Email::query()
                    ->whereBelongsTo($mailFolder)
                    ->where('uid_validity', $mailFolder->uid_validity)
                    ->whereIn('imap_uid', collect($emails)->pluck('imapUid'))
                    ->get();
            });
        } catch (Throwable $exception) {
            throw new IndexingException(
                "Failed to index a batch of emails for user [{$user->id}].",
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
