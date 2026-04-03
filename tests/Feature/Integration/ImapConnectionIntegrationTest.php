<?php

use App\Exceptions\Imap\AuthenticationException;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\WebklexImapConnectionService;
use Carbon\CarbonImmutable;
use Mockery;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\FolderCollection;
use Webklex\PHPIMAP\Support\MessageCollection;

afterEach(function () {
    Mockery::close();
});

test('imap integration connects to a mock server and retrieves folders and emails', function () {
    $user = User::factory()->create();
    $setting = ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'integration@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('disconnect')->once();
    $client->shouldReceive('getFolders')->twice()->andReturn(new FolderCollection(
        collect(integrationImapFolders())
            ->keys()
            ->map(fn (string $folder): object => (object) ['path' => $folder, 'name' => $folder])
            ->all(),
    ));
    $client->shouldReceive('getFolder')->with('INBOX')->twice()->andReturn(integrationImapFolder(integrationImapFolders()['INBOX']));

    $manager = Mockery::mock(ClientManager::class);
    $manager->shouldReceive('make')->once()->andReturn($client);

    $service = new WebklexImapConnectionService($manager);
    $connection = $service->connect($setting);

    $folders = $service->getFolders($connection);
    $messageIds = $service->getMessageIds($connection, 'INBOX');
    $email = $service->getEmail($connection, '<invoice@example.com>');

    $connection->disconnect();

    expect($folders)->toBe(['INBOX', 'Sent'])
        ->and($messageIds)->toBe(['<invoice@example.com>'])
        ->and($email->subject)->toBe('Quarterly invoice')
        ->and($email->fromAddress)->toBe('billing@example.com')
        ->and($email->toAddresses)->toBe([['address' => 'integration@example.com', 'name' => 'Integration User']])
        ->and($email->attachments)->toBe([['filename' => 'invoice.pdf', 'filetype' => 'application/pdf']]);
});

test('imap integration maps mock server authentication failures', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('connect')->once()->andThrow(new AuthFailedException);
    $client->shouldReceive('disconnect')->once();

    $manager = Mockery::mock(ClientManager::class);
    $manager->shouldReceive('make')->once()->andReturn($client);

    $service = new WebklexImapConnectionService($manager);

    expect(fn () => $service->testConnection('imap.example.com', 993, 'integration@example.com', 'wrong-secret', 'ssl'))
        ->toThrow(AuthenticationException::class, 'Invalid username or password. Please check your credentials.');
});

function integrationImapFolders(): array
{
    return [
        'INBOX' => [
            new class
            {
                public function getMessageId(): string
                {
                    return '<invoice@example.com>';
                }

                public function getFrom(): array
                {
                    return [['address' => 'billing@example.com', 'name' => 'Billing']];
                }

                public function getTo(): array
                {
                    return [['address' => 'integration@example.com', 'name' => 'Integration User']];
                }

                public function getCc(): array
                {
                    return [];
                }

                public function getSubject(): string
                {
                    return 'Quarterly invoice';
                }

                public function getDate(): CarbonImmutable
                {
                    return CarbonImmutable::parse('2026-04-03 10:00:00');
                }

                public function getTextBody(): string
                {
                    return 'Invoice body';
                }

                public function getHTMLBody(): string
                {
                    return '<p>Invoice body</p>';
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
            },
        ],
        'Sent' => [],
    ];
}

function integrationImapFolder(array $messages): object
{
    $folder = Mockery::mock(Folder::class);
    $query = Mockery::mock(WhereQuery::class);
    $query->shouldReceive('all')->andReturnSelf();
    $query->shouldReceive('messageId')->andReturnUsing(function (string $messageId) use (&$messages, $query) {
        $messages = array_values(array_filter(
            $messages,
            fn (object $message): bool => $message->getMessageId() === $messageId,
        ));

        return $query;
    });
    $query->shouldReceive('setFetchBody')->andReturnSelf();
    $query->shouldReceive('setFetchFlags')->andReturnSelf();
    $query->shouldReceive('get')->andReturnUsing(fn () => new MessageCollection($messages));
    $folder->shouldReceive('messages')->andReturn($query);

    return $folder;
}
