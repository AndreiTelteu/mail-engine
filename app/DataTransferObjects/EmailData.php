<?php

namespace App\DataTransferObjects;

use DateTimeInterface;

readonly class EmailData
{
    /**
     * @param  array<int, array{address:string, name:?string}>  $toAddresses
     * @param  array<int, array{address:string, name:?string}>  $ccAddresses
     * @param  array<int, array{filename:?string, filetype:?string}>  $attachments
     */
    public function __construct(
        public string $messageId,
        public string $fromAddress,
        public ?string $fromName,
        public array $toAddresses,
        public array $ccAddresses,
        public string $subject,
        public DateTimeInterface $date,
        public ?string $bodyText,
        public ?string $bodyHtml,
        public array $attachments,
        public int $imapUid = 0,
        public ?string $inReplyTo = null,
        public ?string $references = null,
    ) {}
}
