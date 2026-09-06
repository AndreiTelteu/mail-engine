<?php

namespace App\Jobs;

use App\Models\ImapSetting;
use App\Models\MailFolder;
use App\Models\SyncSession;
use App\Models\User;
use App\Services\ImapConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncUserEmailsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public ?int $syncSessionId = null,
    ) {}

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 1200;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 180, 600];

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(ImapConnectionService $imapService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            throw (new ModelNotFoundException)->setModel(User::class, [$this->userId]);
        }

        $syncSession = $this->syncSession($user);

        $setting = ImapSetting::query()
            ->whereBelongsTo($user)
            ->where('is_active', true)
            ->first();

        if (! $setting instanceof ImapSetting) {
            $syncSession->update(['status' => 'failed', 'completed_at' => now()]);

            return;
        }

        $syncSession->update([
            'status' => 'counting',
            'started_at' => now(),
        ]);

        $connection = null;

        try {
            $connection = $imapService->connect($setting);
            $folderStats = collect($imapService->getFolders($connection))
                ->mapWithKeys(function (string $folder) use ($connection, $imapService): array {
                    $status = $imapService->getFolderStatus($connection, $folder);

                    return [$folder => $status];
                });
            $mailFolders = $folderStats
                ->map(function ($status) use ($setting): MailFolder {
                    return MailFolder::query()->firstOrCreate(
                        ['imap_setting_id' => $setting->id, 'path' => $status->path],
                        [
                            'uid_validity' => $status->uidValidity,
                            'uid_next' => $status->uidNext,
                            'remote_message_count' => $status->messageCount,
                        ],
                    );
                })
                ->values();

            $syncSession->update([
                'folder_stats' => $folderStats->map(fn ($status): int => $status->messageCount)->all(),
                'total_remote_count' => $folderStats->sum(fn ($status): int => $status->messageCount),
                'total_to_sync' => 0,
                'synced_count' => 0,
                'failed_count' => 0,
                'pending_folder_jobs' => $mailFolders->count(),
                'status' => $mailFolders->isNotEmpty() ? 'syncing' : 'completed',
                'completed_at' => $mailFolders->isEmpty() ? now() : null,
            ]);

            foreach ($mailFolders as $mailFolder) {
                SyncFolderEmailsJob::dispatch($user->id, $mailFolder->id, $syncSession->id);
            }
        } catch (\Throwable $exception) {
            $syncSession->update(['status' => 'failed', 'completed_at' => now()]);

            Log::error('IMAP sync failed', [
                'user_id' => $this->userId,
                'sync_session_id' => $syncSession->id,
                'error' => $exception->getMessage(),
            ]);
        } finally {
            $connection?->disconnect();
        }
    }

    private function syncSession(User $user): SyncSession
    {
        if ($this->syncSessionId !== null) {
            $session = SyncSession::query()->find($this->syncSessionId);

            if ($session instanceof SyncSession) {
                return $session;
            }
        }

        return SyncSession::create(['user_id' => $user->id, 'status' => 'pending']);
    }
}
