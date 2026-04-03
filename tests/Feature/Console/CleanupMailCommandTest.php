<?php

use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\User;

test('cleanup command dry run preserves valid mail records', function () {
    $user = User::factory()->create();

    $validEmail = Email::create([
        'user_id' => $user->id,
        'message_id' => '<valid@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Valid email',
        'date' => now(),
        'attachments' => [],
    ]);

    $validSetting = ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'user@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);

    $this->artisan('mail:cleanup', ['--dry-run' => true])
        ->assertSuccessful();

    expect($validEmail->fresh())->not->toBeNull()
        ->and($validSetting->fresh())->not->toBeNull();
});

test('cleanup command reports when no orphaned mail records exist', function () {
    $this->artisan('mail:cleanup')
        ->assertSuccessful()
        ->expectsOutputToContain('No orphaned mail records were found.');
});
