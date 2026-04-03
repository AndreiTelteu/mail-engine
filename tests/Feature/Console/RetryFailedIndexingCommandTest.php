<?php

use App\Jobs\IndexEmailJob;
use App\Models\Email;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

test('retry failed command only dispatches jobs for failed emails', function () {
    Bus::fake();

    $user = User::factory()->create();

    $failedEmail = Email::create([
        'user_id' => $user->id,
        'message_id' => '<failed@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Failed email',
        'date' => now(),
        'attachments' => [],
        'indexing_failed_at' => now(),
        'indexing_error' => 'Mailbox unavailable',
    ]);

    Email::create([
        'user_id' => $user->id,
        'message_id' => '<ok@example.com>',
        'folder' => 'Archive',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Healthy email',
        'date' => now(),
        'attachments' => [],
    ]);

    $this->artisan('mail:retry-failed')
        ->assertSuccessful()
        ->expectsOutputToContain('Queued 1 failed email indexing record(s) for retry.');

    Bus::assertDispatched(IndexEmailJob::class, fn (IndexEmailJob $job): bool => $job->userId === $user->id
        && $job->messageId === $failedEmail->message_id
        && $job->folder === $failedEmail->folder);

    Bus::assertDispatchedTimes(IndexEmailJob::class, 1);
});

test('retry failed command supports dry run mode', function () {
    Bus::fake();

    $user = User::factory()->create();

    Email::create([
        'user_id' => $user->id,
        'message_id' => '<dry-run@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Failed email',
        'date' => now(),
        'attachments' => [],
        'indexing_failed_at' => now(),
        'indexing_error' => 'Temporary outage',
    ]);

    $this->artisan('mail:retry-failed', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 failed email indexing record(s) ready to retry.');

    Bus::assertNotDispatched(IndexEmailJob::class);
});

test('retry failed command reports when there is nothing to retry', function () {
    Bus::fake();

    $this->artisan('mail:retry-failed')
        ->assertSuccessful()
        ->expectsOutputToContain('No failed email indexing records were found.');

    Bus::assertNotDispatched(IndexEmailJob::class);
});
