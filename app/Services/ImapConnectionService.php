<?php

namespace App\Services;

use App\DataTransferObjects\EmailData;
use App\DataTransferObjects\ImapConnection;
use App\Models\ImapSetting;

interface ImapConnectionService
{
    public function testConnection(
        string $hostname,
        int $port,
        string $username,
        string $password,
        ?string $encryption,
    ): bool;

    public function connect(ImapSetting $settings): ImapConnection;

    /**
     * @return array<int, string>
     */
    public function getFolders(ImapConnection $connection): array;

    /**
     * @return array<int, string>
     */
    public function getMessageIds(ImapConnection $connection, string $folder): array;

    public function getEmail(ImapConnection $connection, string $messageId): EmailData;
}
