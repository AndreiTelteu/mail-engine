<?php

use App\DataTransferObjects\ImapConnection;
use App\Models\ImapSetting;
use App\Models\User;
use App\Services\WebklexImapConnectionService;

test('an attachment is fetched by exact folder and UID', function () {
    $user = User::factory()->create();
    $setting = ImapSetting::query()->create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.test',
        'port' => 993,
        'username' => $user->email,
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);
    $attachment = new class
    {
        public function getContent(): string
        {
            return 'actual attachment bytes';
        }
    };
    $message = Mockery::mock();
    $message->shouldReceive('getAttachments')->once()->andReturn(collect([$attachment]));
    $query = Mockery::mock();
    $query->shouldReceive('whereUid')->once()->with(42)->andReturnSelf();
    $query->shouldReceive('setFetchBody')->once()->with(true)->andReturnSelf();
    $query->shouldReceive('setFetchFlags')->once()->with(false)->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(collect([$message]));
    $folder = Mockery::mock();
    $folder->shouldReceive('messages')->once()->andReturn($query);
    $client = Mockery::mock();
    $client->shouldReceive('getFolderByPath')->once()->with('Archive/2026', true)->andReturn($folder);

    $content = app(WebklexImapConnectionService::class)->getAttachment(
        new ImapConnection($client, $setting),
        'Archive/2026',
        42,
        0,
    );

    expect($content)->toBe('actual attachment bytes');
});
