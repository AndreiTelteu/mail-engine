<?php

use App\DataTransferObjects\EmailData;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\MailFolder;
use App\Models\User;
use App\Services\DatabaseEmailIndexingService;
use App\Services\EmailContentProcessor;
use Carbon\CarbonImmutable;

test('batch indexing stores a searchable representation without losing the original message body', function () {
    config(['scout.driver' => null]);
    [$user, $folder] = mailFolderForIndexing();
    $service = new DatabaseEmailIndexingService(new EmailContentProcessor);

    $indexed = $service->indexEmails($user, $folder, [
        new EmailData(
            messageId: '<message@example.com>',
            fromAddress: 'sender@example.com',
            fromName: 'Sender',
            toAddresses: [['address' => 'recipient@example.com', 'name' => 'Recipient']],
            ccAddresses: [],
            subject: 'Quarterly report',
            date: CarbonImmutable::parse('2026-04-03 12:00:00'),
            bodyText: "Hello team,\n\nThe quarterly report is ready.\n\nOn Monday, Alice wrote:\n> Previous discussion",
            bodyHtml: '<p>Hello team,</p><p>The quarterly report is ready.</p>',
            attachments: [['filename' => 'report.pdf', 'filetype' => 'application/pdf', 'content' => 'ignored']],
            imapUid: 42,
            inReplyTo: '<parent@example.com>',
            references: '<root@example.com> <parent@example.com>',
        ),
    ]);

    $email = $indexed->sole();

    expect($email->mail_folder_id)->toBe($folder->id)
        ->and($email->uid_validity)->toBe(1)
        ->and($email->imap_uid)->toBe(42)
        ->and($email->body_text)->toContain('Previous discussion')
        ->and($email->body_current)->toContain('quarterly report')
        ->and($email->body_quoted)->toContain('Previous discussion')
        ->and($email->preview)->toContain('quarterly report')
        ->and($email->attachments)->toBe([['filename' => 'report.pdf', 'filetype' => 'application/pdf']])
        ->and($email->attachment_count)->toBe(1)
        ->and($email->content_hash)->toHaveLength(64);
});

test('batch indexing is idempotent by mailbox folder UIDVALIDITY and UID, not Message-ID', function () {
    config(['scout.driver' => null]);
    [$user, $folder] = mailFolderForIndexing();
    $service = new DatabaseEmailIndexingService(new EmailContentProcessor);

    $first = new EmailData(
        messageId: '<same@example.com>',
        fromAddress: 'sender@example.com',
        fromName: null,
        toAddresses: [],
        ccAddresses: [],
        subject: 'First version',
        date: now(),
        bodyText: 'first',
        bodyHtml: null,
        attachments: [],
        imapUid: 42,
    );
    $second = new EmailData(
        messageId: '<same@example.com>',
        fromAddress: 'sender@example.com',
        fromName: null,
        toAddresses: [],
        ccAddresses: [],
        subject: 'Updated version',
        date: now(),
        bodyText: 'updated',
        bodyHtml: null,
        attachments: [],
        imapUid: 42,
    );

    $service->indexEmails($user, $folder, [$first]);
    $service->indexEmails($user, $folder, [$second]);

    expect(Email::query()->count())->toBe(1)
        ->and(Email::query()->sole()->subject)->toBe('Updated version');
});

test('the content processor extracts readable text from html and removes invisible markup', function () {
    $processed = (new EmailContentProcessor)->process(new EmailData(
        messageId: '',
        fromAddress: 'sender@example.com',
        fromName: null,
        toAddresses: [],
        ccAddresses: [],
        subject: '',
        date: now(),
        bodyText: null,
        bodyHtml: '<style>.hidden { display: none; }</style><p>Visible text</p><script>tracking()</script><span hidden>Hidden text</span>',
        attachments: [],
        imapUid: 1,
    ));

    expect($processed->bodyCurrent)->toBe('Visible text')
        ->and($processed->preview)->toBe('Visible text');
});

/**
 * @return array{User, MailFolder}
 */
function mailFolderForIndexing(): array
{
    $user = User::factory()->create();
    $setting = ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'mail@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);
    $folder = MailFolder::create([
        'imap_setting_id' => $setting->id,
        'path' => 'INBOX',
        'uid_validity' => 1,
    ]);

    return [$user, $folder];
}
