<?php

namespace App\Console\Commands;

use App\Jobs\IndexEmailJob;
use App\Models\Email;
use Illuminate\Console\Command;

class RetryFailedIndexingCommand extends Command
{
    protected $signature = 'mail:retry-failed {--dry-run : Report failed emails without dispatching retry jobs}';

    protected $description = 'Retry email indexing jobs for stored emails marked as failed.';

    public function handle(): int
    {
        $failedEmails = Email::query()
            ->whereNotNull('indexing_failed_at')
            ->orderBy('id')
            ->get(['id', 'user_id', 'message_id', 'folder', 'indexing_failed_at']);

        if ($failedEmails->isEmpty()) {
            $this->components->info('No failed email indexing records were found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info("Found {$failedEmails->count()} failed email indexing record(s) ready to retry.");

            return self::SUCCESS;
        }

        foreach ($failedEmails as $email) {
            IndexEmailJob::dispatch($email->user_id, $email->message_id, $email->folder);
        }

        $this->components->info("Queued {$failedEmails->count()} failed email indexing record(s) for retry.");

        return self::SUCCESS;
    }
}
