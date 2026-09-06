<?php

use App\Jobs\SyncFolderEmailsJob;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\MailFolder;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

test('retry failed command queues one folder sync for failed messages in the same folder', function () {
    Bus::fake();
    [$user, $folder] = failedEmailFolder();

    foreach ([1, 2] as $uid) {
        Email::withoutSyncingToSearch(fn () => Email::create([
            'mail_folder_id' => $folder->id,
            'user_id' => $user->id,
            'uid_validity' => 1,
            'imap_uid' => $uid,
            'folder' => 'INBOX',
            'from_address' => 'sender@example.com',
            'to_addresses' => [],
            'cc_addresses' => [],
            'subject' => 'Failed email',
            'date' => now(),
            'body_current' => 'content',
            'body_quoted' => '',
            'preview' => 'content',
            'attachments' => [],
            'content_hash' => str_repeat((string) $uid, 64),
            'indexing_failed_at' => now(),
            'indexing_error' => 'Typesense unavailable',
        ]));
    }

    $this->artisan('mail:retry-failed')
        ->assertSuccessful()
        ->expectsOutputToContain('Queued 2 failed email indexing record(s) for retry.');

    Bus::assertDispatched(SyncFolderEmailsJob::class, fn (SyncFolderEmailsJob $job): bool => $job->userId === $user->id
        && $job->mailFolderId === $folder->id);
    Bus::assertDispatchedTimes(SyncFolderEmailsJob::class, 1);
});

test('retry failed command supports dry run mode', function () {
    Bus::fake();
    [$user, $folder] = failedEmailFolder();
    Email::withoutSyncingToSearch(fn () => Email::create([
        'mail_folder_id' => $folder->id,
        'user_id' => $user->id,
        'uid_validity' => 1,
        'imap_uid' => 1,
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Failed email',
        'date' => now(),
        'body_current' => 'content',
        'body_quoted' => '',
        'preview' => 'content',
        'attachments' => [],
        'content_hash' => str_repeat('a', 64),
        'indexing_failed_at' => now(),
        'indexing_error' => 'Typesense unavailable',
    ]));

    $this->artisan('mail:retry-failed', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 failed email indexing record(s) ready to retry.');

    Bus::assertNotDispatched(SyncFolderEmailsJob::class);
});

/**
 * @return array{User, MailFolder}
 */
function failedEmailFolder(): array
{
    $user = User::factory()->create();
    $setting = ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'mail@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);
    $folder = MailFolder::create([
        'imap_setting_id' => $setting->id,
        'path' => 'INBOX',
        'uid_validity' => 1,
    ]);

    return [$user, $folder];
}
