<?php

use App\DataTransferObjects\EmailData;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\DatabaseEmailIndexingService;
use Carbon\CarbonImmutable;
use Tests\Support\Fakes\ScriptedImapConnectionService;

test('database indexing integration stores email metadata without attachment content', function () {
    $user = User::factory()->create();
    $setting = indexingIntegrationSetting($user, 'indexer@example.com');

    $message = new EmailData(
        messageId: '<db-index@example.com>',
        fromAddress: 'sender@example.com',
        fromName: 'Sender',
        toAddresses: [['address' => $user->email, 'name' => $user->name]],
        ccAddresses: [['address' => 'copy@example.com', 'name' => 'Copy']],
        subject: 'Stored from integration test',
        date: CarbonImmutable::parse('2026-04-03 09:00:00'),
        bodyText: 'Body text',
        bodyHtml: '<p>Body text</p>',
        attachments: [['filename' => 'contract.pdf', 'filetype' => 'application/pdf', 'content' => 'secret-bytes']],
    );

    $imapService = new ScriptedImapConnectionService(mailboxes: [
        $setting->username => [
            'INBOX' => [
                $message->messageId => $message,
            ],
        ],
    ]);

    $service = new DatabaseEmailIndexingService($imapService);
    $connection = $imapService->connect($setting);

    $email = $service->indexEmail($user, $message->messageId, 'INBOX', $connection);

    expect($email->subject)->toBe('Stored from integration test')
        ->and($email->folder)->toBe('INBOX')
        ->and($email->attachments)->toBe([
            ['filename' => 'contract.pdf', 'filetype' => 'application/pdf'],
        ])
        ->and($imapService->disconnectCounts)->toBe([]);
});

test('database indexing integration isolates identical message ids per user', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $firstSetting = indexingIntegrationSetting($firstUser, 'first@example.com');
    $secondSetting = indexingIntegrationSetting($secondUser, 'second@example.com');

    $sharedMessageId = '<shared-message@example.com>';

    $imapService = new ScriptedImapConnectionService(mailboxes: [
        $firstSetting->username => [
            'INBOX' => [
                $sharedMessageId => new EmailData(
                    messageId: $sharedMessageId,
                    fromAddress: 'sender@example.com',
                    fromName: 'Sender',
                    toAddresses: [['address' => $firstUser->email, 'name' => $firstUser->name]],
                    ccAddresses: [],
                    subject: 'First user copy',
                    date: CarbonImmutable::parse('2026-04-03 10:00:00'),
                    bodyText: 'First body',
                    bodyHtml: null,
                    attachments: [],
                ),
            ],
        ],
        $secondSetting->username => [
            'Archive' => [
                $sharedMessageId => new EmailData(
                    messageId: $sharedMessageId,
                    fromAddress: 'sender@example.com',
                    fromName: 'Sender',
                    toAddresses: [['address' => $secondUser->email, 'name' => $secondUser->name]],
                    ccAddresses: [],
                    subject: 'Second user copy',
                    date: CarbonImmutable::parse('2026-04-03 11:00:00'),
                    bodyText: 'Second body',
                    bodyHtml: null,
                    attachments: [],
                ),
            ],
        ],
    ]);

    $service = new DatabaseEmailIndexingService($imapService);

    $firstEmail = $service->indexEmail($firstUser, $sharedMessageId, 'INBOX', $imapService->connect($firstSetting));
    $secondEmail = $service->indexEmail($secondUser, $sharedMessageId, 'Archive', $imapService->connect($secondSetting));

    expect($firstEmail->user_id)->toBe($firstUser->id)
        ->and($secondEmail->user_id)->toBe($secondUser->id)
        ->and($firstUser->emails()->where('message_id', $sharedMessageId)->count())->toBe(1)
        ->and($secondUser->emails()->where('message_id', $sharedMessageId)->count())->toBe(1);
});

function indexingIntegrationSetting(User $user, string $username): ImapSetting
{
    return ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => $username,
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);
}
