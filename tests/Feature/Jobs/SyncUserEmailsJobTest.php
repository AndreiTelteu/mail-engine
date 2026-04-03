<?php

use App\DataTransferObjects\ImapConnection;
use App\Jobs\IndexEmailJob;
use App\Jobs\SyncUserEmailsJob;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\SyncSession;
use App\Models\User;
use App\Services\ImapConnectionService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Mockery;

test('sync user emails job counts folders and dispatches index jobs', function () {
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

    Email::create([
        'user_id' => $user->id,
        'message_id' => '<existing@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Existing',
        'date' => now(),
        'attachments' => [],
    ]);

    $syncSession = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $resource = new class
    {
        public bool $disconnected = false;

        public function disconnect(): void
        {
            $this->disconnected = true;
        }
    };

    $connection = new ImapConnection($resource, $setting);

    $imapService = Mockery::mock(ImapConnectionService::class);
    $imapService->shouldReceive('connect')->once()->andReturn($connection);
    $imapService->shouldReceive('getFolders')->once()->andReturn(['INBOX', 'Sent']);
    $imapService->shouldReceive('getMessageIds')->with($connection, 'INBOX')->andReturn(['<existing@example.com>', '<new@example.com>']);
    $imapService->shouldReceive('getMessageIds')->with($connection, 'Sent')->andReturn(['<sent@example.com>']);

    (new SyncUserEmailsJob($user->id, $syncSession->id))->handle($imapService);

    $syncSession->refresh();

    expect($syncSession->status)->toBe('syncing')
        ->and($syncSession->folder_stats)->toBe(['INBOX' => 2, 'Sent' => 1])
        ->and($syncSession->total_remote_count)->toBe(3)
        ->and($syncSession->total_to_sync)->toBe(2)
        ->and($syncSession->started_at)->not->toBeNull();

    Bus::assertDispatched(IndexEmailJob::class, fn (IndexEmailJob $job): bool => $job->messageId === '<new@example.com>'
        && $job->syncSessionId === $syncSession->id);
    Bus::assertDispatched(IndexEmailJob::class, fn (IndexEmailJob $job): bool => $job->messageId === '<sent@example.com>'
        && $job->syncSessionId === $syncSession->id);
    Bus::assertDispatchedTimes(IndexEmailJob::class, 2);

    expect($resource->disconnected)->toBeTrue();
});

test('sync user emails job marks session completed when all emails are already synced', function () {
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

    Email::create([
        'user_id' => $user->id,
        'message_id' => '<only@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Only email',
        'date' => now(),
        'attachments' => [],
    ]);

    $syncSession = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $resource = new class
    {
        public function disconnect(): void {}
    };

    $connection = new ImapConnection($resource, $setting);

    $imapService = Mockery::mock(ImapConnectionService::class);
    $imapService->shouldReceive('connect')->once()->andReturn($connection);
    $imapService->shouldReceive('getFolders')->once()->andReturn(['INBOX']);
    $imapService->shouldReceive('getMessageIds')->once()->andReturn(['<only@example.com>']);

    (new SyncUserEmailsJob($user->id, $syncSession->id))->handle($imapService);

    $syncSession->refresh();

    expect($syncSession->status)->toBe('completed')
        ->and($syncSession->total_to_sync)->toBe(0)
        ->and($syncSession->completed_at)->not->toBeNull();

    Bus::assertNotDispatched(IndexEmailJob::class);
});

test('sync user emails job marks session failed when no active imap settings', function () {
    $user = User::factory()->create();

    $syncSession = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $imapService = Mockery::mock(ImapConnectionService::class);

    (new SyncUserEmailsJob($user->id, $syncSession->id))->handle($imapService);

    $syncSession->refresh();

    expect($syncSession->status)->toBe('failed');
});

test('sync user emails job marks session failed on imap error', function () {
    Log::spy();

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

    $syncSession = SyncSession::create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $imapService = Mockery::mock(ImapConnectionService::class);
    $imapService->shouldReceive('connect')->once()->andThrow(new RuntimeException('Connection refused'));

    (new SyncUserEmailsJob($user->id, $syncSession->id))->handle($imapService);

    $syncSession->refresh();

    expect($syncSession->status)->toBe('failed');

    Log::shouldHaveReceived('error')->once()->with('IMAP sync failed', Mockery::on(
        fn (array $context): bool => $context['user_id'] === $user->id
            && $context['sync_session_id'] === $syncSession->id
    ));
});
