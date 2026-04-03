<?php

use App\Models\Email;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

test('email searchable array contains the scout payload', function () {
    $user = User::factory()->create();
    $date = Carbon::parse('2026-04-03 12:34:56');

    $email = Email::create([
        'user_id' => $user->id,
        'message_id' => '<message-id@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'from_name' => 'Sender Name',
        'to_addresses' => [['address' => 'recipient@example.com', 'name' => 'Recipient']],
        'cc_addresses' => [['address' => 'cc@example.com', 'name' => 'Cc']],
        'subject' => 'Searchable subject',
        'date' => $date,
        'body_text' => 'Email body text',
        'body_html' => '<p>Email body text</p>',
        'attachments' => [['filename' => 'invoice.pdf', 'filetype' => 'application/pdf']],
    ]);

    expect($email->searchableAs())->toBe('emails')
        ->and($email->toSearchableArray())->toBe([
            'id' => $email->id,
            'user_id' => $user->id,
            'from_address' => 'sender@example.com',
            'from_name' => 'Sender Name',
            'subject' => 'Searchable subject',
            'body_text' => 'Email body text',
            'folder' => 'INBOX',
            'date' => $date->timestamp,
        ]);
});

test('email belongs to a user', function () {
    $email = new Email;
    $relation = $email->user();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated()::class)->toBe(User::class);
});

test('email json attributes are cast to arrays', function () {
    $user = User::factory()->create();

    $email = Email::create([
        'user_id' => $user->id,
        'message_id' => '<cast-test@example.com>',
        'folder' => 'Archive',
        'from_address' => 'sender@example.com',
        'to_addresses' => [['address' => 'recipient@example.com']],
        'cc_addresses' => [['address' => 'copy@example.com']],
        'subject' => 'Casting test',
        'date' => now(),
        'attachments' => [['filename' => 'note.txt', 'filetype' => 'text/plain']],
    ])->fresh();

    expect($email->to_addresses)->toBeArray()
        ->and($email->cc_addresses)->toBeArray()
        ->and($email->attachments)->toBeArray()
        ->and($email->to_addresses[0]['address'])->toBe('recipient@example.com')
        ->and($email->cc_addresses[0]['address'])->toBe('copy@example.com')
        ->and($email->attachments[0]['filename'])->toBe('note.txt');
});
