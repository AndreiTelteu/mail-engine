<?php

use App\Models\ImapSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('imap setting passwords are encrypted at rest and decrypt back to the original value', function () {
    // Feature: mail-indexer-searcher, Property 1: Password Encryption Round-Trip
    $user = User::factory()->create();

    foreach (range(1, 20) as $iteration) {
        $password = sprintf(
            'mail-pass-%d-%s-%s',
            $iteration,
            fake()->regexify('[A-Za-z0-9]{12}'),
            bin2hex(random_bytes(4)),
        );

        $setting = ImapSetting::create([
            'user_id' => $user->id,
            'hostname' => 'imap.example.com',
            'port' => 993,
            'username' => "user{$iteration}@example.com",
            'password' => $password,
            'encryption' => 'ssl',
            'is_active' => true,
        ]);

        expect($setting->getRawOriginal('password'))
            ->not->toBe($password);

        expect($setting->fresh()->decrypted_password)
            ->toBe($password);
    }
});

test('imap setting belongs to a user', function () {
    $setting = new ImapSetting;
    $relation = $setting->user();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated()::class)->toBe(User::class);
});
