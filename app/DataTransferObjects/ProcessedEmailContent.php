<?php

namespace App\DataTransferObjects;

readonly class ProcessedEmailContent
{
    public function __construct(
        public string $bodyCurrent,
        public string $bodyQuoted,
        public string $preview,
        public string $contentHash,
        public int $parserVersion,
    ) {}
}
