<?php

namespace App\DataTransferObjects;

use App\Models\ImapSetting;

readonly class ImapConnection
{
    public function __construct(
        public mixed $resource,
        public ImapSetting $settings,
    ) {}

    public function disconnect(): void
    {
        if (is_object($this->resource) && method_exists($this->resource, 'disconnect')) {
            $this->resource->disconnect();
        }
    }
}
