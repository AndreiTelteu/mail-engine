<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class EmailAttachmentController extends Controller
{
    public function download(Request $request, int $emailId, int $index, EmailAttachmentService $attachments): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $this->respond($attachments->fetch($user, $emailId, $index), false);
    }

    public function preview(Request $request, int $emailId, int $index, EmailAttachmentService $attachments): Response
    {
        /** @var User $user */
        $user = $request->user();
        $file = $attachments->fetch($user, $emailId, $index);

        abort_unless($attachments->isPreviewMimeType($file['mimeType']), 415);

        return $this->respond($file, true);
    }

    /**
     * @param  array{content: string, filename: string, mimeType: string}  $file
     */
    private function respond(array $file, bool $inline): Response
    {
        $response = response($file['content'])
            ->header('Content-Type', $inline ? $file['mimeType'] : 'application/octet-stream')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, no-store');
        $asciiFilename = preg_replace('/[^\x20-\x7e]|%/', '', Str::ascii($file['filename'])) ?: 'attachment';

        return $response->header(
            'Content-Disposition',
            $response->headers->makeDisposition($inline ? 'inline' : 'attachment', $file['filename'], $asciiFilename),
        );
    }
}
