<?php

use App\Jobs\SyncEmailsJob;
use App\Jobs\SyncUserEmailsJob;
use App\Models\ImapSetting;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

test('scheduled sync dispatches one mailbox job for every active mailbox without opening IMAP connections', function () {
    Bus::fake();
    $activeUser = User::factory()->create();
    $inactiveUser = User::factory()->create();

    ImapSetting::create([
        'user_id' => $activeUser->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'active@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);
    ImapSetting::create([
        'user_id' => $inactiveUser->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'inactive@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => false,
    ]);

    (new SyncEmailsJob)->handle();

    Bus::assertDispatched(SyncUserEmailsJob::class, fn (SyncUserEmailsJob $job): bool => $job->userId === $activeUser->id);
    Bus::assertDispatchedTimes(SyncUserEmailsJob::class, 1);
});
