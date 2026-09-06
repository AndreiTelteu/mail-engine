<?php

use App\DataTransferObjects\EmailData;
use App\DataTransferObjects\ImapConnection;
use App\DataTransferObjects\MailFolderStatus;
use App\Jobs\SyncFolderEmailsJob;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\MailFolder;
use App\Models\SyncSession;
use App\Models\User;
use App\Services\EmailIndexingService;
use App\Services\ImapConnectionService;

test('a folder sync reuses one IMAP connection and indexes new messages in configured batches', function () {
    config(['mail-indexer.sync_batch_size' => 2, 'scout.driver' => null]);
    [$user, $setting, $folder] = mailFolderForSync();
    $session = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'syncing',
        'pending_folder_jobs' => 1,
    ]);
    $connection = new ImapConnection(new class
    {
        public function disconnect(): void {}
    }, $setting);
    $firstBatch = [
        emailDataForUid(1),
        emailDataForUid(2),
    ];
    $secondBatch = [
        emailDataForUid(3),
    ];

    $imap = Mockery::mock(ImapConnectionService::class);
    $imap->shouldReceive('connect')->once()->withArgs(fn (ImapSetting $imapSetting): bool => $imapSetting->is($setting))->andReturn($connection);
    $imap->shouldReceive('getFolderStatus')->once()->with($connection, 'INBOX')->andReturn(new MailFolderStatus('INBOX', 1, 4, 3));
    $imap->shouldReceive('getEmailsAfterUid')->once()->with($connection, 'INBOX', 0, 2)->andReturn($firstBatch);
    $imap->shouldReceive('getEmailsAfterUid')->once()->with($connection, 'INBOX', 2, 2)->andReturn($secondBatch);
    $imap->shouldReceive('getEmailsAfterUid')->once()->with($connection, 'INBOX', 3, 2)->andReturn([]);

    $indexing = Mockery::mock(EmailIndexingService::class);
    $indexing->shouldReceive('indexEmails')->once()->withArgs(fn (User $indexedUser, MailFolder $indexedFolder, array $emails): bool => $indexedUser->is($user)
        && $indexedFolder->is($folder)
        && $emails === $firstBatch)->andReturn((new Email)->newCollection([
            Email::make(['id' => 1]),
            Email::make(['id' => 2]),
        ]));
    $indexing->shouldReceive('indexEmails')->once()->withArgs(fn (User $indexedUser, MailFolder $indexedFolder, array $emails): bool => $indexedUser->is($user)
        && $indexedFolder->is($folder)
        && $emails === $secondBatch)->andReturn((new Email)->newCollection([
            Email::make(['id' => 3]),
        ]));

    (new SyncFolderEmailsJob($user->id, $folder->id, $session->id))->handle($imap, $indexing);

    $folder->refresh();
    $session->refresh();

    expect($folder->last_synced_uid)->toBe(3)
        ->and($session->total_to_sync)->toBe(3)
        ->and($session->synced_count)->toBe(3)
        ->and($session->status)->toBe('completed');
});

test('a UIDVALIDITY change removes stale folder documents before resetting its cursor', function () {
    config(['mail-indexer.sync_batch_size' => 10, 'scout.driver' => null]);
    [$user, $setting, $folder] = mailFolderForSync();
    $folder->update(['uid_validity' => 1, 'last_synced_uid' => 30]);
    $stale = Email::withoutSyncingToSearch(fn () => Email::create([
        'mail_folder_id' => $folder->id,
        'user_id' => $user->id,
        'uid_validity' => 1,
        'imap_uid' => 30,
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Stale',
        'date' => now(),
        'body_current' => 'stale',
        'body_quoted' => '',
        'preview' => 'stale',
        'attachments' => [],
        'content_hash' => str_repeat('a', 64),
    ]));
    $connection = new ImapConnection(new class
    {
        public function disconnect(): void {}
    }, $setting);

    $imap = Mockery::mock(ImapConnectionService::class);
    $imap->shouldReceive('connect')->once()->andReturn($connection);
    $imap->shouldReceive('getFolderStatus')->once()->andReturn(new MailFolderStatus('INBOX', 2, 1, 0));
    $imap->shouldReceive('getEmailsAfterUid')->once()->with($connection, 'INBOX', 0, 10)->andReturn([]);

    $indexing = Mockery::mock(EmailIndexingService::class);
    $indexing->shouldNotReceive('indexEmails');

    (new SyncFolderEmailsJob($user->id, $folder->id))->handle($imap, $indexing);

    expect($stale->fresh())->toBeNull()
        ->and($folder->fresh()->uid_validity)->toBe(2)
        ->and($folder->fresh()->last_synced_uid)->toBe(0);
});

test('a permanently failed folder sync marks its session as failed after all folders finish', function () {
    [$user, , $folder] = mailFolderForSync();
    $session = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'syncing',
        'pending_folder_jobs' => 1,
    ]);

    (new SyncFolderEmailsJob($user->id, $folder->id, $session->id))
        ->failed(new RuntimeException('The IMAP server is unavailable.'));

    expect($session->fresh())
        ->failed_count->toBe(1)
        ->status->toBe('failed')
        ->completed_at->not->toBeNull();
});

/**
 * @return array{User, ImapSetting, MailFolder}
 */
function mailFolderForSync(): array
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

    return [$user, $setting, $folder];
}

function emailDataForUid(int $uid): EmailData
{
    return new EmailData(
        messageId: "<{$uid}@example.com>",
        fromAddress: 'sender@example.com',
        fromName: null,
        toAddresses: [],
        ccAddresses: [],
        subject: "Message {$uid}",
        date: now(),
        bodyText: "Body {$uid}",
        bodyHtml: null,
        attachments: [],
        imapUid: $uid,
    );
}
