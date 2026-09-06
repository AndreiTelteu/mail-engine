<?php

namespace App\DataTransferObjects;

readonly class MailFolderStatus
{
    public function __construct(
        public string $path,
        public int $uidValidity,
        public int $uidNext,
        public int $messageCount,
    ) {}
}
