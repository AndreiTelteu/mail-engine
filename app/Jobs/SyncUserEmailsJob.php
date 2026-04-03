<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\SyncSession;
use App\Models\User;
use App\Services\ImapConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncUserEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $syncSessionId,
    ) {}

    public function handle(ImapConnectionService $imapService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            throw (new ModelNotFoundException)->setModel(User::class, [$this->userId]);
        }

        $syncSession = SyncSession::query()->find($this->syncSessionId);

        if (! $syncSession instanceof SyncSession) {
            throw (new ModelNotFoundException)->setModel(SyncSession::class, [$this->syncSessionId]);
        }

        $setting = ImapSetting::query()
            ->whereBelongsTo($user)
            ->where('is_active', true)
            ->first();

        if (! $setting instanceof ImapSetting) {
            $syncSession->update(['status' => 'failed']);

            return;
        }

        $syncSession->update([
            'status' => 'counting',
            'started_at' => now(),
        ]);

        $connection = null;

        try {
            $connection = $imapService->connect($setting);
            $folderStats = [];
            $totalRemoteCount = 0;
            $totalToSync = 0;

            $folders = $imapService->getFolders($connection);

            foreach ($folders as $folder) {
                $imapMessageIds = $imapService->getMessageIds($connection, $folder);
                $folderCount = count($imapMessageIds);
                $folderStats[$folder] = $folderCount;
                $totalRemoteCount += $folderCount;

                $indexedMessageIds = Email::query()
                    ->whereBelongsTo($user)
                    ->whereIn('message_id', $imapMessageIds)
                    ->pluck('message_id')
                    ->all();

                $unindexed = array_values(array_diff(
                    array_values(array_unique($imapMessageIds)),
                    array_values(array_unique($indexedMessageIds)),
                ));

                $totalToSync += count($unindexed);

                foreach ($unindexed as $messageId) {
                    IndexEmailJob::dispatch($user->id, $messageId, $folder, $this->syncSessionId);
                }
            }

            $syncSession->update([
                'folder_stats' => $folderStats,
                'total_remote_count' => $totalRemoteCount,
                'total_to_sync' => $totalToSync,
                'status' => $totalToSync > 0 ? 'syncing' : 'completed',
                'completed_at' => $totalToSync === 0 ? now() : null,
            ]);
        } catch (\Throwable $exception) {
            $syncSession->update(['status' => 'failed']);

            Log::error('IMAP sync failed', [
                'user_id' => $this->userId,
                'sync_session_id' => $this->syncSessionId,
                'error' => $exception->getMessage(),
            ]);
        } finally {
            $connection?->disconnect();
        }
    }
}
