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

test('inline images are fetched by content ID and reject non-image attachments', function () {
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
    $image = Mockery::mock();
    $image->shouldReceive('getId')->once()->andReturn('<logo@example.test>');
    $image->shouldReceive('getContentType')->once()->andReturn('image/png');
    $image->shouldReceive('getContent')->once()->andReturn('image bytes');
    $document = Mockery::mock();
    $document->shouldReceive('getId')->once()->andReturn('document@example.test');
    $document->shouldReceive('getContentType')->once()->andReturn('application/pdf');
    $document->shouldNotReceive('getContent');
    $message = Mockery::mock();
    $message->shouldReceive('getAttachments')->once()->andReturn(collect([$image, $document]));
    $query = Mockery::mock();
    $query->shouldReceive('whereUid')->once()->with(42)->andReturnSelf();
    $query->shouldReceive('setFetchBody')->once()->with(true)->andReturnSelf();
    $query->shouldReceive('setFetchFlags')->once()->with(false)->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(collect([$message]));
    $folder = Mockery::mock();
    $folder->shouldReceive('messages')->once()->andReturn($query);
    $client = Mockery::mock();
    $client->shouldReceive('getFolderByPath')->once()->with('INBOX', true)->andReturn($folder);

    $images = app(WebklexImapConnectionService::class)->getInlineImages(
        new ImapConnection($client, $setting),
        'INBOX',
        42,
        ['logo@example.test', 'document@example.test'],
    );

    expect($images)->toBe(['logo@example.test' => ['mimeType' => 'image/png', 'content' => 'image bytes']]);
});
