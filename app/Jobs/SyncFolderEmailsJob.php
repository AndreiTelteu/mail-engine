<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\MailFolder;
use App\Models\SyncSession;
use App\Models\User;
use App\Services\EmailIndexingService;
use App\Services\ImapConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncFolderEmailsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public int $uniqueFor = 1200;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 180, 600];

    public function __construct(
        public int $userId,
        public int $mailFolderId,
        public ?int $syncSessionId = null,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->mailFolderId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("mail-folder-sync:{$this->mailFolderId}"))->expireAfter($this->timeout),
        ];
    }

    public function handle(
        ImapConnectionService $imapService,
        EmailIndexingService $indexingService,
    ): void {
        $user = User::query()->find($this->userId);
        $mailFolder = MailFolder::query()->find($this->mailFolderId);

        if (! $user instanceof User || ! $mailFolder instanceof MailFolder) {
            throw (new ModelNotFoundException)->setModel(MailFolder::class, [$this->mailFolderId]);
        }

        $setting = ImapSetting::query()
            ->whereBelongsTo($user)
            ->whereKey($mailFolder->imap_setting_id)
            ->where('is_active', true)
            ->first();

        if (! $setting instanceof ImapSetting) {
            throw (new ModelNotFoundException)->setModel(ImapSetting::class, [$mailFolder->imap_setting_id]);
        }

        $connection = null;
        $completed = false;

        try {
            $connection = $imapService->connect($setting);
            $status = $imapService->getFolderStatus($connection, $mailFolder->path);

            if ($mailFolder->uid_validity !== null && $mailFolder->uid_validity !== $status->uidValidity) {
                $this->removeInvalidatedFolderEmails($mailFolder);
                $mailFolder->forceFill(['last_synced_uid' => 0])->save();
            }

            $mailFolder->forceFill([
                'uid_validity' => $status->uidValidity,
                'uid_next' => $status->uidNext,
                'remote_message_count' => $status->messageCount,
            ])->save();

            $this->indexBatches($connection, $imapService, $indexingService, $mailFolder, $user);
            $completed = true;
        } finally {
            $connection?->disconnect();

            if ($completed) {
                $this->markFolderComplete();
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($this->syncSessionId !== null) {
            SyncSession::query()
                ->whereKey($this->syncSessionId)
                ->increment('failed_count');
        }

        $this->markFolderComplete();

        Log::error('IMAP folder synchronization failed', [
            'user_id' => $this->userId,
            'mail_folder_id' => $this->mailFolderId,
            'sync_session_id' => $this->syncSessionId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function indexBatches(
        object $connection,
        ImapConnectionService $imapService,
        EmailIndexingService $indexingService,
        MailFolder $mailFolder,
        User $user,
    ): void {
        $batchSize = max(1, config('mail-indexer.sync_batch_size'));

        while (true) {
            $emails = $imapService->getEmailsAfterUid(
                $connection,
                $mailFolder->path,
                $mailFolder->last_synced_uid,
                $batchSize,
            );

            if ($emails === []) {
                break;
            }

            $indexedEmails = $indexingService->indexEmails($user, $mailFolder, $emails);
            $indexedEmails->searchableSync();
            $lastSyncedUid = max(collect($emails)->pluck('imapUid')->all());

            $mailFolder->forceFill([
                'last_synced_uid' => $lastSyncedUid,
                'last_synced_at' => now(),
            ])->save();

            $this->incrementProgress(count($emails));
            $mailFolder->refresh();
        }
    }

    private function removeInvalidatedFolderEmails(MailFolder $mailFolder): void
    {
        Email::query()
            ->whereBelongsTo($mailFolder)
            ->select(['id'])
            ->chunkById(config('mail-indexer.sync_batch_size'), function ($emails): void {
                $emails->unsearchableSync();
                Email::query()->whereKey($emails->modelKeys())->delete();
            });
    }

    private function incrementProgress(int $count): void
    {
        if ($this->syncSessionId === null) {
            return;
        }

        SyncSession::query()
            ->whereKey($this->syncSessionId)
            ->incrementEach([
                'total_to_sync' => $count,
                'synced_count' => $count,
            ]);
    }

    private function markFolderComplete(): void
    {
        if ($this->syncSessionId === null) {
            return;
        }

        $session = SyncSession::query()->find($this->syncSessionId);

        if (! $session instanceof SyncSession) {
            return;
        }

        if ($session->pending_folder_jobs > 0) {
            $session->decrement('pending_folder_jobs');
            $session->refresh();
        }

        $session->markCompleteIfDone();
    }
}
