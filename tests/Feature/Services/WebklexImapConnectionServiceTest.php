<?php

use App\DataTransferObjects\ImapConnection;
use App\Exceptions\Imap\AuthenticationException;
use App\Exceptions\Imap\ImapConnectionException;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\WebklexImapConnectionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Config;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Connection\Protocols\Response;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\FolderCollection;
use Webklex\PHPIMAP\Support\MessageCollection;

test('test connection succeeds with valid credentials', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('disconnect')->once();

    $manager = Mockery::mock(ClientManager::class);
    $manager->shouldReceive('make')
        ->once()
        ->with(Mockery::on(fn (array $config): bool => $config['host'] === 'imap.example.com'
            && $config['port'] === 993
            && $config['username'] === 'mail@example.com'
            && $config['password'] === 'secret'
            && $config['encryption'] === 'ssl'
            && $config['timeout'] === 10))
        ->andReturn($client);

    $service = new WebklexImapConnectionService($manager);

    expect($service->testConnection('imap.example.com', 993, 'mail@example.com', 'secret', 'ssl'))
        ->toBeTrue();
});

test('test connection maps authentication failures to a domain exception', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('connect')->once()->andThrow(new AuthFailedException);
    $client->shouldReceive('disconnect')->once();

    $manager = Mockery::mock(ClientManager::class);
    $manager->shouldReceive('make')->once()->andReturn($client);

    $service = new WebklexImapConnectionService($manager);

    expect(fn () => $service->testConnection('imap.example.com', 993, 'mail@example.com', 'bad-secret', 'ssl'))
        ->toThrow(AuthenticationException::class, 'Invalid username or password. Please check your credentials.');
});

test('test connection maps ssl failures to a descriptive domain exception', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('connect')->once()->andThrow(new ConnectionFailedException('certificate verify failed'));
    $client->shouldReceive('disconnect')->once();

    $manager = Mockery::mock(ClientManager::class);
    $manager->shouldReceive('make')->once()->andReturn($client);

    $service = new WebklexImapConnectionService($manager);

    expect(fn () => $service->testConnection('imap.example.com', 993, 'mail@example.com', 'secret', 'ssl'))
        ->toThrow(ImapConnectionException::class, 'Secure connection failed. Please verify SSL/TLS settings.');
});

test('connect returns an imap connection dto using decrypted model credentials', function () {
    $user = User::factory()->create();
    $setting = ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'mail@example.com',
        'password' => 'secret',
        'encryption' => 'tls',
        'is_active' => true,
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('connect')->once();

    $manager = Mockery::mock(ClientManager::class);
    $manager->shouldReceive('make')
        ->once()
        ->with(Mockery::on(fn (array $config): bool => $config['password'] === 'secret' && $config['encryption'] === 'tls'))
        ->andReturn($client);

    $service = new WebklexImapConnectionService($manager);
    $connection = $service->connect($setting);

    expect($connection)->toBeInstanceOf(ImapConnection::class)
        ->and($connection->resource)->toBe($client)
        ->and($connection->settings->is($setting))->toBeTrue();
});

test('get folders returns folder paths from the connection resource', function () {
    $connection = makeImapConnectionWithResource(new class
    {
        public function getFolders(bool $hierarchical = false, ?string $parentFolder = null, bool $softFail = true): array
        {
            return [
                (object) ['path' => 'INBOX', 'name' => 'INBOX'],
                (object) ['path' => 'Sent', 'name' => 'Sent'],
            ];
        }
    });

    $service = new WebklexImapConnectionService(Mockery::mock(ClientManager::class));

    expect($service->getFolders($connection))->toBe(['INBOX', 'Sent']);
});

test('get folder status and incremental messages use IMAP UIDs', function () {
    $messages = [
        imapMessageWithUid('<first@example.com>', 1),
        imapMessageWithUid('<second@example.com>', 2),
    ];

    $connection = makeImapConnectionWithResource(fakeClientWithFolders([
        'INBOX' => $messages,
    ]));

    $service = new WebklexImapConnectionService(Mockery::mock(ClientManager::class));

    $status = $service->getFolderStatus($connection, 'INBOX');
    $emails = $service->getEmailsAfterUid($connection, 'INBOX', 1, 100);

    expect($status->uidValidity)->toBe(1)
        ->and($status->uidNext)->toBe(3)
        ->and($status->messageCount)->toBe(2)
        ->and(collect($emails)->pluck('imapUid')->all())->toBe([2]);
});

test('incremental messages resolve the exact server folder path', function (string $path, string $delimiter) {
    $message = imapMessageWithUid('<target@example.com>', 2);
    $client = Mockery::mock(Client::class.'[getFolders]', [Config::make()]);
    $folder = Mockery::mock(Folder::class.'[messages]', [$client, $path, $delimiter, []]);
    $client->shouldReceive('getFolders')->once()->andReturn(new FolderCollection([$folder]));

    $query = Mockery::mock(WhereQuery::class);
    $query->shouldReceive('setFetchBody')->with(true)->once()->andReturnSelf();
    $query->shouldReceive('setFetchFlags')->with(false)->once()->andReturnSelf();
    $query->shouldReceive('limit')->with(100)->once()->andReturnSelf();
    $query->shouldReceive('getByUidGreater')->with(1)->once()->andReturn(new MessageCollection([$message]));
    $folder->shouldReceive('messages')->once()->andReturn($query);

    $connection = makeImapConnectionWithResource($client);
    $service = new WebklexImapConnectionService(Mockery::mock(ClientManager::class));
    $emails = $service->getEmailsAfterUid($connection, $path, 1, 100);

    expect($emails)->toHaveCount(1)
        ->and($emails[0]->messageId)->toBe('<target@example.com>')
        ->and($emails[0]->imapUid)->toBe(2);
})->with([
    'inbox' => ['INBOX', '/'],
    'dot separator' => ['INBOX.Trash', '.'],
    'slash separator' => ['INBOX/Trash', '/'],
    'encoded server path' => ['INBOX/R&AOk-sum&AOk-', '/'],
]);

test('get email builds an email dto from the first matching message across folders', function () {
    $message = new class
    {
        public function getMessageId(): string
        {
            return '<target@example.com>';
        }

        public function getFrom(): array
        {
            return [['address' => 'sender@example.com', 'name' => 'Sender']];
        }

        public function getTo(): array
        {
            return [['address' => 'recipient@example.com', 'name' => 'Recipient']];
        }

        public function getCc(): array
        {
            return [['address' => 'copy@example.com', 'name' => 'Copy']];
        }

        public function getSubject(): string
        {
            return 'Important email';
        }

        public function getDate(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-04-03 10:00:00');
        }

        public function getTextBody(): string
        {
            return 'Plain body';
        }

        public function getHTMLBody(): string
        {
            return '<p>HTML body</p>';
        }

        public function getAttachments(): array
        {
            return [
                new class
                {
                    public function getFilename(): string
                    {
                        return 'invoice.pdf';
                    }

                    public function getContentType(): string
                    {
                        return 'application/pdf';
                    }
                },
            ];
        }
    };

    $connection = makeImapConnectionWithResource(fakeClientWithFolders([
        'INBOX' => [],
        'Archive' => [$message],
    ]));

    $service = new WebklexImapConnectionService(Mockery::mock(ClientManager::class));
    $email = $service->getEmail($connection, '<target@example.com>');

    expect($email->messageId)->toBe('<target@example.com>')
        ->and($email->fromAddress)->toBe('sender@example.com')
        ->and($email->fromName)->toBe('Sender')
        ->and($email->toAddresses)->toBe([['address' => 'recipient@example.com', 'name' => 'Recipient']])
        ->and($email->ccAddresses)->toBe([['address' => 'copy@example.com', 'name' => 'Copy']])
        ->and($email->subject)->toBe('Important email')
        ->and($email->bodyText)->toBe('Plain body')
        ->and($email->bodyHtml)->toBe('<p>HTML body</p>')
        ->and($email->attachments)->toBe([['filename' => 'invoice.pdf', 'filetype' => 'application/pdf']]);
});

test('get email decodes mime encoded headers', function () {
    $message = new class
    {
        public function getMessageId(): string
        {
            return '<encoded@example.com>';
        }

        public function getFrom(): array
        {
            return [[
                'address' => 'sender@example.com',
                'name' => '=?UTF-8?B?Sm9obiBEb8Op?=',
            ]];
        }

        public function getTo(): array
        {
            return [];
        }

        public function getCc(): array
        {
            return [];
        }

        public function getSubject(): string
        {
            return 'ideaprint.ro =?UTF-8?B?4oCTIDggY3VyaWVyaSwgZsSDcsSD?= abonament';
        }

        public function getDate(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-04-03 10:00:00');
        }

        public function getTextBody(): string
        {
            return 'Plain body';
        }

        public function getHTMLBody(): string
        {
            return '<p>HTML body</p>';
        }

        public function getAttachments(): array
        {
            return [[
                'filename' => '=?UTF-8?B?aW52b2ljxIMucGRm?=',
                'filetype' => 'application/pdf',
            ]];
        }
    };

    $connection = makeImapConnectionWithResource(fakeClientWithFolders([
        'INBOX' => [$message],
    ]));

    $service = new WebklexImapConnectionService(Mockery::mock(ClientManager::class));
    $email = $service->getEmail($connection, '<encoded@example.com>');

    expect($email->subject)->toBe('ideaprint.ro – 8 curieri, fără abonament')
        ->and($email->fromName)->toBe('John Doé')
        ->and($email->attachments)->toBe([['filename' => 'invoică.pdf', 'filetype' => 'application/pdf']]);
});

test('incremental messages replace malformed UTF-8 without changing valid body characters', function (string $body) {
    $message = imapMessageWithUid('<malformed@example.com>', 1, $body, '<p>'.$body.'</p>');
    $connection = makeImapConnectionWithResource(fakeClientWithFolders(['INBOX' => [$message]]));
    $service = new WebklexImapConnectionService(Mockery::mock(ClientManager::class));

    $email = $service->getEmailsAfterUid($connection, 'INBOX', 0, 10)[0];

    expect(mb_check_encoding($email->bodyText, 'UTF-8'))->toBeTrue()
        ->and(mb_check_encoding($email->bodyHtml, 'UTF-8'))->toBeTrue()
        ->and($email->bodyText)->toContain('Bună', 'Jan')
        ->and(json_encode([$email->bodyText, $email->bodyHtml], JSON_THROW_ON_ERROR))->toBeString();
})->with([
    'invalid continuation byte' => ["Bună \x80\r\nJan"],
    'truncated multibyte sequence' => ["Bună \xC3\r\nJan"],
    'valid UTF-8' => ["Bună €\r\nJan"],
]);

test('malformed date headers use the server timestamp and do not block the batch', function (string $date, ?string $expectedDate, bool $fetchInternalDate) {
    $config = app(ClientManager::class)->getConfig();
    $protocol = Mockery::mock(ImapProtocol::class);

    if ($fetchInternalDate) {
        $response = Response::empty()->setResult([1 => '"25-Aug-2026'])->setResponse([
            "* 2 FETCH (UID 2 INTERNALDATE \"01-Jul-2026 01:00:00 +0000\")\r\n",
            "TAG1 OK Fetch completed\r\n",
        ]);

        if ($expectedDate !== null) {
            $response->addResponse("* 1 FETCH (INTERNALDATE \"25-Aug-2026 04:07:45 +0000\" UID 1)\r\n");
        }

        $protocol->shouldReceive('fetch')->once()->with('INTERNALDATE', [1])
            ->andReturn($response);
    } else {
        $protocol->shouldNotReceive('fetch');
    }

    $client = Mockery::mock(Client::class.'[openFolder,getFolderPath,getConnection]', [$config]);
    $client->shouldReceive('openFolder')->andReturn([]);
    $client->shouldReceive('getFolderPath')->andReturn('INBOX');
    $client->shouldReceive('getConnection')->andReturn($protocol);

    $message = Message::make(1, 1, $client, "Date: {$date}\r\nMessage-ID: <date@example.com>\r\nContent-Type: text/plain\r\n", 'Body', ['Seen']);
    $connection = makeImapConnectionWithResource(fakeClientWithFolders([
        'INBOX' => [$message, imapMessageWithUid('<next@example.com>', 2)],
    ]));
    $service = app(WebklexImapConnectionService::class);

    if ($expectedDate === null) {
        expect(fn () => $service->getEmailsAfterUid($connection, 'INBOX', 0, 25))
            ->toThrow(ImapConnectionException::class, 'The server did not return a message date for UID [1].');

        return;
    }

    $emails = $service->getEmailsAfterUid($connection, 'INBOX', 0, 25);

    expect($emails)->toHaveCount(2)
        ->and($emails[0]->date->format('Y-m-d H:i:s'))->toBe($expectedDate)
        ->and($emails[0]->bodyText)->toBe('Body')
        ->and($emails[1]->imapUid)->toBe(2);
})->with([
    'unparseable date' => ['not-a-date', '2026-08-25 04:07:45', true],
    'date containing other headers' => ['Tue, 25 Aug 2026 04:07:45 +0000 From: sender@example.com Reply-To: sender@example.com', '2026-08-25 04:07:45', true],
    'valid date' => ['Mon, 24 Aug 2026 10:00:00 +0000', '2026-08-24 10:00:00', false],
    'missing server timestamp does not use another message date' => ['not-a-date', null, true],
]);

function makeImapConnectionWithResource(object $resource): ImapConnection
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

    return new ImapConnection($resource, $setting);
}

function imapMessageWithUid(string $messageId, int $uid, string $bodyText = '', string $bodyHtml = ''): object
{
    return new class($messageId, $uid, $bodyText, $bodyHtml)
    {
        public function __construct(
            private string $messageId,
            private int $uid,
            private string $bodyText,
            private string $bodyHtml,
        ) {}

        public function getMessageId(): string
        {
            return $this->messageId;
        }

        public function getUid(): int
        {
            return $this->uid;
        }

        public function getFrom(): array
        {
            return [];
        }

        public function getTo(): array
        {
            return [];
        }

        public function getCc(): array
        {
            return [];
        }

        public function getSubject(): string
        {
            return '';
        }

        public function getDate(): CarbonImmutable
        {
            return CarbonImmutable::now();
        }

        public function getTextBody(): string
        {
            return $this->bodyText;
        }

        public function getHTMLBody(): string
        {
            return $this->bodyHtml;
        }

        public function getAttachments(): array
        {
            return [];
        }
    };
}

/**
 * @param  array<string, array<int, object>>  $folders
 */
function fakeClientWithFolders(array $folders): object
{
    return new class($folders)
    {
        /**
         * @param  array<string, array<int, object>>  $folders
         */
        public function __construct(private array $folders) {}

        public function getFolders(bool $hierarchical = false, ?string $parentFolder = null, bool $softFail = true): array
        {
            return collect($this->folders)
                ->keys()
                ->map(fn (string $folder): object => (object) ['path' => $folder, 'name' => $folder])
                ->all();
        }

        public function getFolderByPath(string $folder, bool $utf7 = false): ?object
        {
            if (! array_key_exists($folder, $this->folders)) {
                return null;
            }

            return new class($this->folders[$folder])
            {
                /**
                 * @param  array<int, object>  $messages
                 */
                public function __construct(private array $messages) {}

                public function messages(): object
                {
                    return new class($this->messages)
                    {
                        /**
                         * @param  array<int, object>  $messages
                         */
                        public function __construct(
                            private array $messages,
                            private ?int $limit = null,
                        ) {}

                        public function all(): static
                        {
                            return $this;
                        }

                        public function messageId(string $messageId): static
                        {
                            $this->messages = array_values(array_filter(
                                $this->messages,
                                fn (object $message): bool => $message->getMessageId() === $messageId,
                            ));

                            return $this;
                        }

                        public function setFetchBody(bool $fetchBody): static
                        {
                            return $this;
                        }

                        public function setFetchFlags(bool $fetchFlags): static
                        {
                            return $this;
                        }

                        public function limit(int $limit): static
                        {
                            $this->limit = $limit;

                            return $this;
                        }

                        public function getByUidGreater(int $uid): Collection
                        {
                            return collect($this->messages)
                                ->filter(fn (object $message): bool => $message->getUid() > $uid)
                                ->take($this->limit)
                                ->values();
                        }

                        public function get(): Collection
                        {
                            return collect($this->messages);
                        }
                    };
                }
            };
        }

        public function folderStatus(string $folder): object
        {
            $messages = $this->folders[$folder] ?? [];
            $highestUid = collect($messages)->max(fn (object $message): int => $message->getUid()) ?: 0;

            return new class($highestUid, count($messages))
            {
                public function __construct(
                    private int $highestUid,
                    private int $messageCount,
                ) {}

                public function validatedData(): array
                {
                    return [
                        'uidvalidity' => 1,
                        'uidnext' => $this->highestUid + 1,
                        'messages' => $this->messageCount,
                    ];
                }
            };
        }
    };
}
