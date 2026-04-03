<?php

use App\DataTransferObjects\ImapConnection;
use App\Exceptions\Imap\AuthenticationException;
use App\Exceptions\Imap\ImapConnectionException;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\WebklexImapConnectionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Mockery;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

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

test('get message ids returns all message ids in a folder', function () {
    $messages = [
        new class
        {
            public function getMessageId(): string
            {
                return '<first@example.com>';
            }
        },
        new class
        {
            public function getMessageId(): string
            {
                return '<second@example.com>';
            }
        },
    ];

    $connection = makeImapConnectionWithResource(fakeClientWithFolders([
        'INBOX' => $messages,
    ]));

    $service = new WebklexImapConnectionService(Mockery::mock(ClientManager::class));

    expect($service->getMessageIds($connection, 'INBOX'))
        ->toBe(['<first@example.com>', '<second@example.com>']);
});

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

        public function getFolder(string $folder): ?object
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
                        public function __construct(private array $messages) {}

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

                        public function get(): Collection
                        {
                            return collect($this->messages);
                        }
                    };
                }
            };
        }
    };
}
