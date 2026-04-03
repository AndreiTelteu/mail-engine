<?php

use App\DataTransferObjects\ImapConnection;
use App\Models\ImapSetting;
use App\Models\User;

test('imap connection disconnects the underlying resource when available', function () {
    $user = User::factory()->create();
    $setting = ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'user@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);

    $resource = new class
    {
        public bool $disconnected = false;

        public function disconnect(): void
        {
            $this->disconnected = true;
        }
    };

    $connection = new ImapConnection($resource, $setting);
    $connection->disconnect();

    expect($resource->disconnected)->toBeTrue();
});
