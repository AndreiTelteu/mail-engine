<?php

use App\DataTransferObjects\EmailData;
use App\DataTransferObjects\ImapConnection;
use App\Exceptions\Imap\ImapConnectionException;
use App\Exceptions\Imap\IndexingException;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\DatabaseEmailIndexingService;
use App\Services\ImapConnectionService;
use Carbon\CarbonImmutable;

test('email data extraction completeness property holds across random inputs', function () {
    // Feature: mail-indexer-searcher, Property 3: Email Data Extraction Completeness
    $service = new DatabaseEmailIndexingService(new class implements ImapConnectionService
    {
        public function testConnection(string $hostname, int $port, string $username, string $password, ?string $encryption): bool
        {
            return true;
        }

        public function connect(ImapSetting $settings): ImapConnection
        {
            return new ImapConnection(new stdClass, $settings);
        }

        public function getFolders(ImapConnection $connection): array
        {
            return [];
        }

        public function getMessageIds(ImapConnection $connection, string $folder): array
        {
            return [];
        }

        public function getEmail(ImapConnection $connection, string $messageId): EmailData
        {
            return new EmailData(
                messageId: $messageId,
                fromAddress: fake()->safeEmail(),
                fromName: fake()->boolean() ? fake()->name() : null,
                toAddresses: [
                    ['address' => fake()->safeEmail(), 'name' => fake()->firstName()],
                    ['address' => fake()->safeEmail(), 'name' => null],
                ],
                ccAddresses: fake()->boolean()
                    ? [['address' => fake()->safeEmail(), 'name' => fake()->firstName()]]
                    : [],
                subject: fake()->sentence(),
                date: CarbonImmutable::now()->subMinutes(random_int(1, 500)),
                bodyText: fake()->boolean() ? fake()->paragraph() : null,
                bodyHtml: fake()->boolean() ? '<p>'.fake()->sentence().'</p>' : null,
                attachments: [
                    [
                        'filename' => fake()->word().'.pdf',
                        'filetype' => 'application/pdf',
                        'content' => base64_encode(fake()->sentence()),
                    ],
                ],
            );
        }
    });

    $connection = makeFakeImapConnection();

    foreach (range(1, 20) as $iteration) {
        $messageId = "<property-{$iteration}@example.com>";
        $emailData = $service->extractEmailData($connection, $messageId);

        expect($emailData->messageId)->toBe($messageId)
            ->and($emailData->fromAddress)->not->toBe('')
            ->and($emailData->toAddresses)->not->toBeEmpty();

        foreach ($emailData->attachments as $attachment) {
            expect($attachment)
                ->toHaveKeys(['filename', 'filetype'])
                ->not->toHaveKey('content');
        }
    }
});

test('indexing idempotence property holds across repeated indexing attempts', function () {
    // Feature: mail-indexer-searcher, Property 4: Indexing Idempotence
    $user = User::factory()->create();
    $connection = makeFakeImapConnection();

    $service = new DatabaseEmailIndexingService(fakeImapServiceReturning(new EmailData(
        messageId: '<same@example.com>',
        fromAddress: 'sender@example.com',
        fromName: 'Sender',
        toAddresses: [['address' => 'recipient@example.com', 'name' => 'Recipient']],
        ccAddresses: [],
        subject: 'Idempotent email',
        date: CarbonImmutable::parse('2026-04-03 12:00:00'),
        bodyText: 'Body text',
        bodyHtml: '<p>Body text</p>',
        attachments: [['filename' => 'invoice.pdf', 'filetype' => 'application/pdf', 'content' => 'ignored']],
    )));

    foreach (range(1, random_int(2, 10)) as $attempt) {
        $email = $service->indexEmail($user, '<same@example.com>', 'INBOX', $connection);

        expect($email->message_id)->toBe('<same@example.com>');
    }

    expect(Email::query()->whereBelongsTo($user)->where('message_id', '<same@example.com>')->count())->toBe(1);

    $stored = Email::query()->whereBelongsTo($user)->where('message_id', '<same@example.com>')->firstOrFail();

    expect($stored->subject)->toBe('Idempotent email')
        ->and($stored->attachments)->toBe([['filename' => 'invoice.pdf', 'filetype' => 'application/pdf']]);
});

test('index email stores extracted metadata for plain text html and multipart messages', function (EmailData $emailData) {
    $user = User::factory()->create();
    $connection = makeFakeImapConnection();
    $service = new DatabaseEmailIndexingService(fakeImapServiceReturning($emailData));

    $email = $service->indexEmail($user, $emailData->messageId, 'Inbox/Subfolder', $connection);

    expect($email->user_id)->toBe($user->id)
        ->and($email->folder)->toBe('Inbox/Subfolder')
        ->and($email->from_address)->toBe($emailData->fromAddress)
        ->and($email->from_name)->toBe($emailData->fromName)
        ->and($email->to_addresses)->toBe($emailData->toAddresses)
        ->and($email->cc_addresses)->toBe($emailData->ccAddresses)
        ->and($email->subject)->toBe($emailData->subject)
        ->and($email->body_text)->toBe($emailData->bodyText)
        ->and($email->body_html)->toBe($emailData->bodyHtml)
        ->and($email->attachments)->toBe($emailData->attachments);
})->with([
    'plain text' => [
        new EmailData(
            messageId: '<plain@example.com>',
            fromAddress: 'plain@example.com',
            fromName: null,
            toAddresses: [['address' => 'to@example.com', 'name' => null]],
            ccAddresses: [],
            subject: 'Plain',
            date: CarbonImmutable::parse('2026-04-03 08:00:00'),
            bodyText: 'Plain body',
            bodyHtml: null,
            attachments: [],
        ),
    ],
    'html' => [
        new EmailData(
            messageId: '<html@example.com>',
            fromAddress: 'html@example.com',
            fromName: 'Html Sender',
            toAddresses: [['address' => 'to@example.com', 'name' => 'To']],
            ccAddresses: [],
            subject: 'Html',
            date: CarbonImmutable::parse('2026-04-03 09:00:00'),
            bodyText: null,
            bodyHtml: '<p>Html body</p>',
            attachments: [],
        ),
    ],
    'multipart with attachments' => [
        new EmailData(
            messageId: '<multipart@example.com>',
            fromAddress: 'multi@example.com',
            fromName: 'Multipart Sender',
            toAddresses: [['address' => 'to@example.com', 'name' => 'To']],
            ccAddresses: [['address' => 'cc@example.com', 'name' => 'Cc']],
            subject: 'Multipart',
            date: CarbonImmutable::parse('2026-04-03 10:00:00'),
            bodyText: 'Text body',
            bodyHtml: '<p>Text body</p>',
            attachments: [['filename' => 'note.txt', 'filetype' => 'text/plain']],
        ),
    ],
]);

test('is email indexed checks for an existing stored message', function () {
    $user = User::factory()->create();
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

    $service = new DatabaseEmailIndexingService(fakeImapServiceReturning(new EmailData(
        messageId: '<unused@example.com>',
        fromAddress: 'sender@example.com',
        fromName: null,
        toAddresses: [],
        ccAddresses: [],
        subject: 'Unused',
        date: now(),
        bodyText: null,
        bodyHtml: null,
        attachments: [],
    )));

    expect($service->isEmailIndexed($user, '<existing@example.com>'))->toBeTrue()
        ->and($service->isEmailIndexed($user, '<missing@example.com>'))->toBeFalse();
});

test('index email clears failure markers for an existing failed record', function () {
    $user = User::factory()->create();
    $connection = makeFakeImapConnection();

    $email = Email::create([
        'user_id' => $user->id,
        'message_id' => '<retry@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Retry me',
        'date' => now(),
        'attachments' => [],
        'indexing_failed_at' => now(),
        'indexing_error' => 'Temporary outage',
    ]);

    $service = new DatabaseEmailIndexingService(fakeImapServiceReturning(new EmailData(
        messageId: '<retry@example.com>',
        fromAddress: 'sender@example.com',
        fromName: null,
        toAddresses: [],
        ccAddresses: [],
        subject: 'Retry me',
        date: now(),
        bodyText: 'Recovered body',
        bodyHtml: null,
        attachments: [],
    )));

    $result = $service->indexEmail($user, '<retry@example.com>', 'INBOX', $connection);

    expect($result->is($email))->toBeTrue();

    $email->refresh();

    expect($email->indexing_failed_at)->toBeNull()
        ->and($email->indexing_error)->toBeNull();
});

test('extract email data wraps malformed email errors in an indexing exception', function () {
    $service = new DatabaseEmailIndexingService(new class implements ImapConnectionService
    {
        public function testConnection(string $hostname, int $port, string $username, string $password, ?string $encryption): bool
        {
            return true;
        }

        public function connect(ImapSetting $settings): ImapConnection
        {
            return new ImapConnection(new stdClass, $settings);
        }

        public function getFolders(ImapConnection $connection): array
        {
            return [];
        }

        public function getMessageIds(ImapConnection $connection, string $folder): array
        {
            return [];
        }

        public function getEmail(ImapConnection $connection, string $messageId): EmailData
        {
            throw new ImapConnectionException('Malformed email payload');
        }
    });

    expect(fn () => $service->extractEmailData(makeFakeImapConnection(), '<broken@example.com>'))
        ->toThrow(IndexingException::class, 'Failed to extract email data for message [<broken@example.com>].');
});

function fakeImapServiceReturning(EmailData $emailData): ImapConnectionService
{
    return new class($emailData) implements ImapConnectionService
    {
        public function __construct(private EmailData $emailData) {}

        public function testConnection(string $hostname, int $port, string $username, string $password, ?string $encryption): bool
        {
            return true;
        }

        public function connect(ImapSetting $settings): ImapConnection
        {
            return new ImapConnection(new stdClass, $settings);
        }

        public function getFolders(ImapConnection $connection): array
        {
            return ['INBOX'];
        }

        public function getMessageIds(ImapConnection $connection, string $folder): array
        {
            return [$this->emailData->messageId];
        }

        public function getEmail(ImapConnection $connection, string $messageId): EmailData
        {
            return new EmailData(
                messageId: $messageId,
                fromAddress: $this->emailData->fromAddress,
                fromName: $this->emailData->fromName,
                toAddresses: $this->emailData->toAddresses,
                ccAddresses: $this->emailData->ccAddresses,
                subject: $this->emailData->subject,
                date: $this->emailData->date,
                bodyText: $this->emailData->bodyText,
                bodyHtml: $this->emailData->bodyHtml,
                attachments: $this->emailData->attachments,
            );
        }
    };
}

function makeFakeImapConnection(): ImapConnection
{
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

    return new ImapConnection(new stdClass, $setting);
}
