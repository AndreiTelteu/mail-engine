<?php

namespace App\Services;

use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\User;
use finfo;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class EmailAttachmentService
{
    private const PREVIEW_MIME_TYPES = [
        'application/pdf',
        'image/avif',
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
    ];

    private const PREVIEW_EXTENSIONS = ['avif', 'gif', 'jpeg', 'jpg', 'pdf', 'png', 'txt', 'webp'];

    public function __construct(protected ImapConnectionService $imap) {}

    /**
     * @param  array{filename?: ?string, filetype?: ?string}  $attachment
     */
    public function canPreview(array $attachment): bool
    {
        $filetype = strtolower(trim($attachment['filetype'] ?? ''));
        $extension = strtolower(pathinfo($attachment['filename'] ?? '', PATHINFO_EXTENSION));

        return in_array($filetype, self::PREVIEW_MIME_TYPES, true)
            || in_array($extension, self::PREVIEW_EXTENSIONS, true);
    }

    /**
     * @return array{content: string, filename: string, mimeType: string}
     */
    public function fetch(User $user, int $emailId, int $index): array
    {
        $email = Email::query()
            ->whereBelongsTo($user)
            ->with('mailFolder.imapSetting')
            ->findOrFail($emailId);
        $attachment = $email->attachments[$index] ?? null;

        abort_unless(is_array($attachment), 404);

        $setting = $email->mailFolder?->imapSetting;

        abort_unless($setting instanceof ImapSetting && $setting->user_id === $user->id, 404);

        $connection = null;

        try {
            $connection = $this->imap->connect($setting);
            $folder = $email->mailFolder->path;

            if ($this->imap->getFolderStatus($connection, $folder)->uidValidity !== $email->uid_validity) {
                abort(409, 'The mailbox has changed. Synchronize it before opening this attachment.');
            }

            $content = $this->imap->getAttachment($connection, $folder, $email->imap_uid, $index);

            abort_if($content === null, 404);
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            abort(502, 'The attachment could not be retrieved from the mail server.');
        } finally {
            $connection?->disconnect();
        }

        $filename = basename(str_replace('\\', '/', (string) ($attachment['filename'] ?? '')));
        $filename = trim(preg_replace('/[\x00-\x1f\x7f]/', '', $filename) ?? '');

        if ($filename === '' || in_array($filename, ['.', '..'], true)) {
            $filename = "attachment-{$index}";
        }

        $detectedMimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($content);

        return [
            'content' => $content,
            'filename' => $filename,
            'mimeType' => is_string($detectedMimeType) ? $detectedMimeType : 'application/octet-stream',
        ];
    }

    public function isPreviewMimeType(string $mimeType): bool
    {
        return in_array($mimeType, self::PREVIEW_MIME_TYPES, true);
    }
}
