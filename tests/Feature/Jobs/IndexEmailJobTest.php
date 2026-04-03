<?php

use App\DataTransferObjects\ImapConnection;
use App\Exceptions\Imap\ImapConnectionException;
use App\Jobs\IndexEmailJob;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\EmailIndexingService;
use App\Services\ImapConnectionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Mockery;

test('index email job has the configured retry strategy', function () {
    $job = new IndexEmailJob(1, '<message@example.com>', 'INBOX');

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([60, 120, 240]);
});

test('index email job connects and indexes the requested message', function () {
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

    $indexingService = Mockery::mock(EmailIndexingService::class);
    $indexingService->shouldReceive('indexEmail')
        ->once()
        ->withArgs(fn (User $jobUser, string $messageId, string $folder, ImapConnection $jobConnection): bool => $jobUser->is($user)
            && $messageId === '<message@example.com>'
            && $folder === 'INBOX'
            && $jobConnection === $connection);

    (new IndexEmailJob($user->id, '<message@example.com>', 'INBOX'))->handle($imapService, $indexingService);

    expect($resource->disconnected)->toBeTrue();
});

test('index email job bubbles connection failures for retries', function () {
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

    $imapService = Mockery::mock(ImapConnectionService::class);
    $imapService->shouldReceive('connect')->once()->andThrow(new ImapConnectionException('Temporary outage'));

    $indexingService = Mockery::mock(EmailIndexingService::class);
    $indexingService->shouldNotReceive('indexEmail');

    expect(fn () => (new IndexEmailJob($user->id, '<message@example.com>', 'INBOX'))->handle($imapService, $indexingService))
        ->toThrow(ImapConnectionException::class, 'Temporary outage');
});

test('index email job fails when the user or settings are missing', function () {
    $imapService = Mockery::mock(ImapConnectionService::class);
    $indexingService = Mockery::mock(EmailIndexingService::class);

    expect(fn () => (new IndexEmailJob(999, '<missing@example.com>', 'INBOX'))->handle($imapService, $indexingService))
        ->toThrow(ModelNotFoundException::class);
});

test('index email job failed hook marks the stored email as failed', function () {
    Log::spy();

    $user = User::factory()->create();
    Email::create([
        'user_id' => $user->id,
        'message_id' => '<failed@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Failed',
        'date' => now(),
        'attachments' => [],
    ]);

    $job = new IndexEmailJob($user->id, '<failed@example.com>', 'INBOX');
    $job->failed(new RuntimeException('Mailbox unavailable'));

    $email = Email::query()
        ->where('user_id', $user->id)
        ->where('message_id', '<failed@example.com>')
        ->firstOrFail();

    expect($email->indexing_failed_at)->not->toBeNull()
        ->and($email->indexing_error)->toBe('Mailbox unavailable');

    Log::shouldHaveReceived('error')->once()->with('Email indexing failed', Mockery::on(
        fn (array $context): bool => $context['user_id'] === $user->id
            && $context['message_id'] === '<failed@example.com>'
            && $context['folder'] === 'INBOX'
            && $context['error'] === 'Mailbox unavailable'
    ));
});
