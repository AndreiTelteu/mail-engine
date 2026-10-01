<?php

use App\DataTransferObjects\EmailSearchResult;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\MailFolder;
use App\Models\User;
use App\Services\ScoutEmailSearchService;
use Laravel\Scout\Builder as ScoutBuilder;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

test('search uses Typesense filters and returns its matching snippet without hydrating email bodies', function () {
    $user = User::factory()->create();
    $service = new ScoutEmailSearchService(function (ScoutBuilder $builder, int $page, int $perPage) use ($user): array {
        expect($builder->query)->toBe('quarterly report')
            ->and($page)->toBe(1)
            ->and($perPage)->toBe(20)
            ->and($builder->wheres)->toContain([
                'field' => 'user_id',
                'operator' => '=',
                'value' => $user->id,
            ])
            ->toContain([
                'field' => 'folder',
                'operator' => '=',
                'value' => 'Archive',
            ]);

        return [
            'found' => 51,
            'hits' => [[
                'document' => [
                    'id' => '44',
                    'from_name' => 'Acme',
                    'from_address' => 'billing@example.com',
                    'subject' => 'Quarterly report',
                    'preview' => 'The report is attached.',
                    'folder' => 'Archive',
                    'date' => now()->timestamp,
                    'attachment_count' => 1,
                ],
                'highlights' => [[
                    'field' => 'body_current',
                    'snippet' => 'Please review the <mark>quarterly report</mark> before Friday.',
                ]],
            ]],
        ];
    });

    $results = $service->search($user, 'quarterly report', 'Archive');
    $result = $results->items()[0];

    expect($results->total())->toBe(51)
        ->and($result)->toBeInstanceOf(EmailSearchResult::class)
        ->and($result->preview)->toContain('<mark>quarterly report</mark>')
        ->and($result->preview)->not->toContain('The report is attached.');
});

test('search fails explicitly when Typesense is unavailable', function () {
    $user = User::factory()->create();
    $service = new ScoutEmailSearchService(
        fn (): never => throw new RuntimeException('Connection refused'),
    );

    expect(fn () => $service->search($user, 'invoice'))
        ->toThrow(ServiceUnavailableHttpException::class, 'Typesense is unavailable');
});

test('search adds the attachment filter to the Typesense query', function () {
    $user = User::factory()->create();
    $service = new ScoutEmailSearchService(function (ScoutBuilder $builder): array {
        expect($builder->wheres)->toContain([
            'field' => 'attachment_count',
            'operator' => '>',
            'value' => 0,
        ]);

        return ['found' => 0, 'hits' => []];
    });

    expect($service->search($user, 'invoice', hasAttachments: true)->total())->toBe(0);
});

test('recent messages use the compact projection without loading email bodies', function () {
    config(['scout.driver' => null]);
    $user = User::factory()->create();
    $setting = ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => 'mail@example.com',
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);
    $folder = MailFolder::create([
        'imap_setting_id' => $setting->id,
        'path' => 'INBOX',
        'uid_validity' => 1,
    ]);
    $email = Email::withoutSyncingToSearch(fn () => Email::create([
        'mail_folder_id' => $folder->id,
        'user_id' => $user->id,
        'uid_validity' => 1,
        'imap_uid' => 1,
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Recent message',
        'date' => now(),
        'body_text' => str_repeat('unloaded body ', 1_000),
        'body_current' => str_repeat('unloaded body ', 1_000),
        'body_quoted' => '',
        'preview' => 'A compact preview',
        'attachments' => [],
        'content_hash' => str_repeat('a', 64),
    ]));

    $result = (new ScoutEmailSearchService)->getRecent($user)->items()[0];

    expect($result)->toBeInstanceOf(EmailSearchResult::class)
        ->and($result->id)->toBe($email->id)
        ->and($result->preview)->toBe('A compact preview');
});

test('recent messages can be limited to those with attachments', function () {
    config(['scout.driver' => null]);
    $user = User::factory()->create();
    $setting = ImapSetting::create([
        'user_id' => $user->id,
        'hostname' => 'imap.example.com',
        'port' => 993,
        'username' => $user->email,
        'password' => 'secret',
        'encryption' => 'ssl',
        'is_active' => true,
    ]);
    $folder = MailFolder::create([
        'imap_setting_id' => $setting->id,
        'path' => 'INBOX',
        'uid_validity' => 1,
    ]);

    foreach ([0, 1] as $attachmentCount) {
        Email::withoutSyncingToSearch(fn () => Email::create([
            'mail_folder_id' => $folder->id,
            'user_id' => $user->id,
            'uid_validity' => 1,
            'imap_uid' => $attachmentCount + 1,
            'folder' => 'INBOX',
            'from_address' => 'sender@example.com',
            'to_addresses' => [],
            'cc_addresses' => [],
            'subject' => 'Invoice',
            'date' => now(),
            'preview' => 'Invoice',
            'attachments' => $attachmentCount ? [['filename' => 'invoice.pdf', 'filetype' => 'application/pdf']] : [],
            'attachment_count' => $attachmentCount,
            'content_hash' => hash('sha256', (string) $attachmentCount),
        ]));
    }

    $results = (new ScoutEmailSearchService)->getRecent($user, hasAttachments: true);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->attachmentCount)->toBe(1);
});
