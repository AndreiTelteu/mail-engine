<?php

namespace App\Console\Commands;

use App\Jobs\SyncEmailsJob;
use Illuminate\Console\Command;

class SyncEmailsCommand extends Command
{
    protected $signature = 'mail:sync';

    protected $description = 'Dispatch the SyncEmailsJob to the queue.';

    public function handle(): int
    {
        SyncEmailsJob::dispatch();

        $this->components->info('SyncEmailsJob dispatched.');

        return self::SUCCESS;
    }
}
