<?php

namespace App\Jobs;

use App\Models\ImapSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        ImapSetting::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->lazyById()
            ->each(function (ImapSetting $setting): void {
                SyncUserEmailsJob::dispatch($setting->user_id);
            });
    }
}
