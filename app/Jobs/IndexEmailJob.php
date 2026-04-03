<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\SyncSession;
use App\Models\SyncSessionLog;
use App\Models\User;
use App\Services\EmailIndexingService;
use App\Services\ImapConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class IndexEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 120, 240];

    public function __construct(
        public int $userId,
        public string $messageId,
        public string $folder,
        public ?int $syncSessionId = null,
    ) {}

    public function handle(
        ImapConnectionService $imapService,
        EmailIndexingService $indexingService,
    ): void {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            throw (new ModelNotFoundException)->setModel(User::class, [$this->userId]);
        }

        $settings = ImapSetting::query()
            ->whereBelongsTo($user)
            ->where('is_active', true)
            ->first();

        if (! $settings instanceof ImapSetting) {
            throw (new ModelNotFoundException)->setModel(ImapSetting::class, [$this->userId]);
        }

        if ($this->syncSessionId !== null && ! SyncSession::query()->where('id', $this->syncSessionId)->exists()) {
            return;
        }

        $connection = null;

        try {
            $connection = $imapService->connect($settings);

            $email = $indexingService->indexEmail(
                $user,
                $this->messageId,
                $this->folder,
                $connection,
            );

            $this->logSyncResult($email, 'success');
        } finally {
            $connection?->disconnect();
        }
    }

    public function failed(Throwable $exception): void
    {
        Email::query()
            ->where('user_id', $this->userId)
            ->where('message_id', $this->messageId)
            ->update([
                'indexing_failed_at' => now(),
                'indexing_error' => $exception->getMessage(),
            ]);

        $this->logSyncResult(null, 'failed', $exception->getMessage());

        Log::error('Email indexing failed', [
            'user_id' => $this->userId,
            'message_id' => $this->messageId,
            'folder' => $this->folder,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function logSyncResult(?Email $email, string $status, ?string $errorMessage = null): void
    {
        if ($this->syncSessionId === null) {
            return;
        }

        $toAddress = '';
        $subject = '';

        if ($email !== null) {
            $toAddress = collect($email->to_addresses ?? [])->first()['address'] ?? '';
            $subject = $email->subject ?? '';
        }

        SyncSessionLog::create([
            'sync_session_id' => $this->syncSessionId,
            'to_address' => mb_substr($toAddress, 0, 255),
            'subject' => mb_substr($subject, 0, 500),
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        $column = $status === 'success' ? 'synced_count' : 'failed_count';

        SyncSession::query()
            ->where('id', $this->syncSessionId)
            ->increment($column);

        $syncSession = SyncSession::query()->find($this->syncSessionId);

        if ($syncSession instanceof SyncSession) {
            $syncSession->markCompleteIfDone();
        }
    }
}
