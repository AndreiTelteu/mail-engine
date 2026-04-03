<?php

namespace Tests\Support\Fakes;

use App\DataTransferObjects\EmailData;
use App\DataTransferObjects\ImapConnection;
use App\Exceptions\Imap\ImapConnectionException;
use App\Models\ImapSetting;
use App\Services\ImapConnectionService;

class ScriptedImapConnectionService implements ImapConnectionService
{
    public array $testConnectionAttempts = [];

    public array $connectAttempts = [];

    public array $disconnectCounts = [];

    /**
     * @param  array<string, array<string, array<string, EmailData>>>  $mailboxes
     * @param  array<string, \Throwable>  $connectFailures
     */
    public function __construct(
        protected array $mailboxes = [],
        protected array $connectFailures = [],
        protected mixed $testConnectionHandler = null,
    ) {}

    public function testConnection(
        string $hostname,
        int $port,
        string $username,
        string $password,
        ?string $encryption,
    ): bool {
        $this->testConnectionAttempts[] = compact('hostname', 'port', 'username', 'password', 'encryption');

        if (is_callable($this->testConnectionHandler)) {
            return ($this->testConnectionHandler)($hostname, $port, $username, $password, $encryption);
        }

        return true;
    }

    public function connect(ImapSetting $settings): ImapConnection
    {
        $this->connectAttempts[] = $settings->username;

        if (array_key_exists($settings->username, $this->connectFailures)) {
            throw $this->connectFailures[$settings->username];
        }

        return new ImapConnection(
            new ScriptedImapResource($this, $settings->username),
            $settings,
        );
    }

    public function getFolders(ImapConnection $connection): array
    {
        return array_keys($this->mailboxesFor($connection));
    }

    public function getMessageIds(ImapConnection $connection, string $folder): array
    {
        return array_keys($this->mailboxesFor($connection)[$folder] ?? []);
    }

    public function getEmail(ImapConnection $connection, string $messageId): EmailData
    {
        foreach ($this->mailboxesFor($connection) as $messages) {
            if (array_key_exists($messageId, $messages)) {
                return $messages[$messageId];
            }
        }

        throw new ImapConnectionException("Email with message ID [{$messageId}] was not found.");
    }

    public function recordDisconnect(string $username): void
    {
        $this->disconnectCounts[$username] = ($this->disconnectCounts[$username] ?? 0) + 1;
    }

    /**
     * @return array<string, array<string, EmailData>>
     */
    protected function mailboxesFor(ImapConnection $connection): array
    {
        return $this->mailboxes[$connection->settings->username] ?? [];
    }
}

class ScriptedImapResource
{
    public function __construct(
        protected ScriptedImapConnectionService $service,
        protected string $username,
    ) {}

    public function disconnect(): void
    {
        $this->service->recordDisconnect($this->username);
    }
}
