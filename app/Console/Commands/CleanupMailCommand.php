<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Models\ImapSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupMailCommand extends Command
{
    protected $signature = 'mail:cleanup {--dry-run : Report orphaned mail records without deleting them}';

    protected $description = 'Clean up orphaned mail records that no longer belong to an existing user.';

    public function handle(): int
    {
        $orphanedEmailIds = Email::query()
            ->doesntHave('user')
            ->pluck('id');

        $orphanedImapSettingIds = ImapSetting::query()
            ->doesntHave('user')
            ->pluck('id');

        $orphanedEmailsCount = $orphanedEmailIds->count();
        $orphanedImapSettingsCount = $orphanedImapSettingIds->count();

        if ($orphanedEmailsCount === 0 && $orphanedImapSettingsCount === 0) {
            $this->components->info('No orphaned mail records were found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info("Found {$orphanedEmailsCount} orphaned email record(s) and {$orphanedImapSettingsCount} orphaned IMAP setting record(s).");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($orphanedEmailIds, $orphanedImapSettingIds): void {
            if ($orphanedEmailIds->isNotEmpty()) {
                Email::query()->whereKey($orphanedEmailIds)->delete();
            }

            if ($orphanedImapSettingIds->isNotEmpty()) {
                ImapSetting::query()->whereKey($orphanedImapSettingIds)->delete();
            }
        });

        $this->components->info("Deleted {$orphanedEmailsCount} orphaned email record(s) and {$orphanedImapSettingsCount} orphaned IMAP setting record(s).");

        return self::SUCCESS;
    }
}
