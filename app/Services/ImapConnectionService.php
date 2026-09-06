<?php

namespace App\Services;

use App\DataTransferObjects\EmailData;
use App\DataTransferObjects\ImapConnection;
use App\DataTransferObjects\MailFolderStatus;
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

    public function getFolderStatus(ImapConnection $connection, string $folder): MailFolderStatus;

    /**
     * @return array<int, EmailData>
     */
    public function getEmailsAfterUid(
        ImapConnection $connection,
        string $folder,
        int $afterUid,
        int $limit,
    ): array;

    public function getEmail(ImapConnection $connection, string $messageId): EmailData;
}
