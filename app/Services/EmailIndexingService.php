<?php

namespace App\Services;

use App\DataTransferObjects\EmailData;
use App\DataTransferObjects\ImapConnection;
use App\Models\Email;
use App\Models\User;

interface EmailIndexingService
{
    public function indexEmail(
        User $user,
        string $messageId,
        string $folder,
        ImapConnection $connection,
    ): Email;

    public function isEmailIndexed(User $user, string $messageId): bool;

    public function extractEmailData(ImapConnection $connection, string $messageId): EmailData;
}
