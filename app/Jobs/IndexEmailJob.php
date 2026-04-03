<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\ImapSetting;
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

        $connection = null;

        try {
            $connection = $imapService->connect($settings);

            $indexingService->indexEmail(
                $user,
                $this->messageId,
                $this->folder,
                $connection,
            );
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

        Log::error('Email indexing failed', [
            'user_id' => $this->userId,
            'message_id' => $this->messageId,
            'folder' => $this->folder,
            'error' => $exception->getMessage(),
        ]);
    }
}
