<?php

use App\DataTransferObjects\ImapConnection;
use App\DataTransferObjects\MailFolderStatus;
use App\Jobs\SyncFolderEmailsJob;
use App\Jobs\SyncUserEmailsJob;
use App\Models\ImapSetting;
use App\Models\SyncSession;
use App\Models\User;
use App\Services\ImapConnectionService;
use Illuminate\Support\Facades\Bus;

test('mailbox sync creates folder cursors and dispatches one locked job per folder', function () {
    Bus::fake();
    $user = User::factory()->create();
    $setting = ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'user@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);
    $session = SyncSession::create(['user_id' => $user->id, 'status' => 'pending']);
    $connection = new ImapConnection(new class
    {
        public function disconnect(): void {}
    }, $setting);

    $imap = Mockery::mock(ImapConnectionService::class);
    $imap->shouldReceive('connect')->once()->andReturn($connection);
    $imap->shouldReceive('getFolders')->once()->andReturn(['INBOX', 'Archive']);
    $imap->shouldReceive('getFolderStatus')->once()->with($connection, 'INBOX')->andReturn(new MailFolderStatus('INBOX', 10, 101, 100));
    $imap->shouldReceive('getFolderStatus')->once()->with($connection, 'Archive')->andReturn(new MailFolderStatus('Archive', 11, 21, 20));

    (new SyncUserEmailsJob($user->id, $session->id))->handle($imap);

    $session->refresh();

    expect($session->status)->toBe('syncing')
        ->and($session->pending_folder_jobs)->toBe(2)
        ->and($session->total_remote_count)->toBe(120)
        ->and($session->folder_stats)->toBe(['INBOX' => 100, 'Archive' => 20]);
    Bus::assertDispatched(SyncFolderEmailsJob::class, fn (SyncFolderEmailsJob $job): bool => $job->userId === $user->id);
    Bus::assertDispatchedTimes(SyncFolderEmailsJob::class, 2);
});

test('mailbox sync marks the supplied session as failed when its IMAP connection cannot be opened', function () {
    $user = User::factory()->create();
    ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'user@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);
    $session = SyncSession::create(['user_id' => $user->id, 'status' => 'pending']);

    $imap = Mockery::mock(ImapConnectionService::class);
    $imap->shouldReceive('connect')->once()->andThrow(new RuntimeException('Connection refused'));

    (new SyncUserEmailsJob($user->id, $session->id))->handle($imap);

    expect($session->fresh()->status)->toBe('failed')
        ->and($session->fresh()->completed_at)->not->toBeNull();
});
