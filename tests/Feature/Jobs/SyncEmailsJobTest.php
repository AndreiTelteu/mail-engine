<?php

use App\DataTransferObjects\ImapConnection;
use App\Jobs\IndexEmailJob;
use App\Jobs\SyncEmailsJob;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\EmailIndexingService;
use App\Services\ImapConnectionService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Mockery;

test('unindexed email identification property returns the exact set difference', function () {
    // Feature: mail-indexer-searcher, Property 2: Unindexed Email Identification
    $job = new SyncEmailsJob;

    foreach (range(1, 20) as $iteration) {
        $imapMessageIds = collect(range(1, random_int(3, 8)))
            ->map(fn (int $index): string => "imap-{$iteration}-{$index}")
            ->all();

        $indexedMessageIds = collect($imapMessageIds)
            ->shuffle()
            ->take(random_int(0, count($imapMessageIds)))
            ->values()
            ->all();

        $expected = array_values(array_diff($imapMessageIds, $indexedMessageIds));

        expect($job->identifyUnindexedMessageIds($imapMessageIds, $indexedMessageIds))
            ->toBe($expected);
    }
});

test('sync emails job queues index jobs for every unindexed message in every folder', function () {
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
    $imapService->shouldReceive('connect')->once()->withArgs(fn (ImapSetting $imapSetting): bool => $imapSetting->is($setting))->andReturn($connection);
    $imapService->shouldReceive('getFolders')->once()->with($connection)->andReturn(['INBOX', 'Sent']);
    $imapService->shouldReceive('getMessageIds')->once()->with($connection, 'INBOX')->andReturn(['<existing@example.com>', '<new@example.com>']);
    $imapService->shouldReceive('getMessageIds')->once()->with($connection, 'Sent')->andReturn(['<sent@example.com>']);

    $indexingService = Mockery::mock(EmailIndexingService::class);

    (new SyncEmailsJob)->handle($imapService, $indexingService);

    Bus::assertDispatched(IndexEmailJob::class, fn (IndexEmailJob $job): bool => $job->userId === $user->id
        && $job->messageId === '<new@example.com>'
        && $job->folder === 'INBOX');
    Bus::assertDispatched(IndexEmailJob::class, fn (IndexEmailJob $job): bool => $job->userId === $user->id
        && $job->messageId === '<sent@example.com>'
        && $job->folder === 'Sent');
    Bus::assertDispatchedTimes(IndexEmailJob::class, 2);

    expect($resource->disconnected)->toBeTrue();
});

test('sync emails job logs failures and continues processing other users', function () {
    Bus::fake();
    Log::spy();

    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $failingSetting = ImapSetting::create([
        'user_id' => $firstUser->id,
        'hostname' => 'imap.bad.example.com',
        'port' => 993,
        'username' => 'first@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);

    $workingSetting = ImapSetting::create([
        'user_id' => $secondUser->id,
        'hostname' => 'imap.good.example.com',
        'port' => 993,
        'username' => 'second@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);

    $connection = new ImapConnection(new class
    {
        public bool $disconnected = false;

        public function disconnect(): void
        {
            $this->disconnected = true;
        }
    }, $workingSetting);

    $imapService = Mockery::mock(ImapConnectionService::class);
    $imapService->shouldReceive('connect')->once()->withArgs(fn (ImapSetting $setting): bool => $setting->is($failingSetting))->andThrow(new RuntimeException('Connection refused'));
    $imapService->shouldReceive('connect')->once()->withArgs(fn (ImapSetting $setting): bool => $setting->is($workingSetting))->andReturn($connection);
    $imapService->shouldReceive('getFolders')->once()->with($connection)->andReturn(['INBOX']);
    $imapService->shouldReceive('getMessageIds')->once()->with($connection, 'INBOX')->andReturn(['<ok@example.com>']);

    $indexingService = Mockery::mock(EmailIndexingService::class);

    (new SyncEmailsJob)->handle($imapService, $indexingService);

    Log::shouldHaveReceived('error')->once()->with('IMAP connection failed during synchronization', Mockery::on(
        fn (array $context): bool => $context['user_id'] === $firstUser->id && $context['error'] === 'Connection refused'
    ));

    Bus::assertDispatched(IndexEmailJob::class, fn (IndexEmailJob $job): bool => $job->userId === $secondUser->id
        && $job->messageId === '<ok@example.com>'
        && $job->folder === 'INBOX');
});
