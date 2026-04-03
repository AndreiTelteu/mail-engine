<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\ImapSetting;
use App\Services\EmailIndexingService;
use App\Services\ImapConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        ImapConnectionService $imapService,
        EmailIndexingService $indexingService,
    ): void {
        ImapSetting::query()
            ->with('user')
            ->where('is_active', true)
            ->get()
            ->each(function (ImapSetting $setting) use ($imapService): void {
                $connection = null;

                try {
                    $connection = $imapService->connect($setting);

                    foreach ($imapService->getFolders($connection) as $folder) {
                        $imapMessageIds = $imapService->getMessageIds($connection, $folder);
                        $indexedMessageIds = Email::query()
                            ->whereBelongsTo($setting->user)
                            ->whereIn('message_id', $imapMessageIds)
                            ->pluck('message_id')
                            ->all();

                        foreach ($this->identifyUnindexedMessageIds($imapMessageIds, $indexedMessageIds) as $messageId) {
                            IndexEmailJob::dispatch($setting->user_id, $messageId, $folder);
                        }
                    }
                } catch (\Throwable $exception) {
                    Log::error('IMAP connection failed during synchronization', [
                        'user_id' => $setting->user_id,
                        'error' => $exception->getMessage(),
                    ]);
                } finally {
                    $connection?->disconnect();
                }
            });
    }

    /**
     * @param  array<int, string>  $imapMessageIds
     * @param  array<int, string>  $indexedMessageIds
     * @return array<int, string>
     */
    public function identifyUnindexedMessageIds(array $imapMessageIds, array $indexedMessageIds): array
    {
        return array_values(array_diff(array_values(array_unique($imapMessageIds)), array_values(array_unique($indexedMessageIds))));
    }
}
