<?php

use App\Models\Email;
use App\Models\User;
use App\Services\ScoutEmailSearchService;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder as ScoutBuilder;

test('search result data isolation property returns only the authenticated users emails', function () {
    // Feature: mail-indexer-searcher, Property 5: Search Result Data Isolation
    config(['scout.driver' => null]);

    foreach (range(1, 10) as $iteration) {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Email::create([
            'user_id' => $user->id,
            'message_id' => "<owned-{$iteration}@example.com>",
            'folder' => 'INBOX',
            'from_address' => 'owner@example.com',
            'to_addresses' => [],
            'cc_addresses' => [],
            'subject' => "project {$iteration}",
            'date' => now()->addSeconds($iteration),
            'body_text' => 'shared keyword',
            'attachments' => [],
        ]);

        Email::create([
            'user_id' => $otherUser->id,
            'message_id' => "<foreign-{$iteration}@example.com>",
            'folder' => 'INBOX',
            'from_address' => 'foreign@example.com',
            'to_addresses' => [],
            'cc_addresses' => [],
            'subject' => "project {$iteration}",
            'date' => now()->addSeconds($iteration),
            'body_text' => 'shared keyword',
            'attachments' => [],
        ]);

        $results = (new ScoutEmailSearchService)->search($user, 'project');

        expect($results->getCollection()->pluck('user_id')->unique()->all())
            ->toBe([$user->id]);
    }
});

test('search result formatting property includes from subject and preview', function () {
    // Feature: mail-indexer-searcher, Property 6: Search Result Formatting
    $service = new ScoutEmailSearchService;

    foreach (range(1, 10) as $iteration) {
        $email = Email::make([
            'from_address' => "sender{$iteration}@example.com",
            'from_name' => fake()->name(),
            'subject' => "Subject {$iteration}",
            'body_text' => "Preview body {$iteration}",
            'folder' => 'INBOX',
            'date' => CarbonImmutable::now(),
        ]);

        $formatted = $service->formatResult($email);

        expect($formatted['display'])->toContain($email->from_address)
            ->toContain($email->subject)
            ->toContain("Preview body {$iteration}");
    }
});

test('search term highlighting property wraps all term occurrences and preserves the original text', function () {
    // Feature: mail-indexer-searcher, Property 7: Search Term Highlighting
    $service = new ScoutEmailSearchService;
    $email = Email::make([
        'from_address' => 'alpha@example.com',
        'subject' => 'Alpha subject',
        'body_text' => 'alpha body alpha',
        'folder' => 'INBOX',
        'date' => CarbonImmutable::now(),
    ]);

    $plain = $service->formatResult($email);
    $highlighted = $service->formatResult($email, 'alpha');

    expect(substr_count(strtolower($highlighted['display']), '<mark>alpha</mark>'))->toBeGreaterThanOrEqual(3)
        ->and(str_replace(['<mark>', '</mark>'], '', $highlighted['display']))->toBe($plain['display']);
});

test('result fields escape mail content so highlights can be rendered as html', function () {
    $service = new ScoutEmailSearchService;
    $email = Email::make([
        'from_address' => 'attacker@example.com',
        'from_name' => '<script>alert(1)</script>',
        'subject' => 'Invoice <img src=x onerror=alert(1)>',
        'body_text' => 'Body with <b>markup</b> and invoice text',
        'folder' => 'INBOX',
        'date' => CarbonImmutable::now(),
    ]);

    $formatted = $service->formatResult($email, 'invoice');

    expect($formatted['from'])->not->toContain('<script>')
        ->and($formatted['from'])->toContain('&lt;script&gt;')
        ->and($formatted['subject'])->not->toContain('<img')
        ->and($formatted['preview'])->not->toContain('<b>')
        ->and($formatted['subject'])->toContain('<mark>Invoice</mark>')
        ->and($formatted['preview'])->toContain('<mark>invoice</mark>');
});

test('highlighting a term with html characters cannot inject markup', function () {
    $service = new ScoutEmailSearchService;
    $email = Email::make([
        'from_address' => 'sender@example.com',
        'subject' => 'Quarterly <report>',
        'body_text' => 'The <report> is attached',
        'folder' => 'INBOX',
        'date' => CarbonImmutable::now(),
    ]);

    $formatted = $service->formatResult($email, '<report>');

    expect($formatted['subject'])->toBe('Quarterly <mark>&lt;report&gt;</mark>');
});

test('folder filtering accuracy property returns only emails from the selected folder', function () {
    // Feature: mail-indexer-searcher, Property 10: Folder Filtering Accuracy
    config(['scout.driver' => null]);

    $user = User::factory()->create();
    $folders = ['INBOX', 'Sent', 'Archive'];

    foreach ($folders as $folder) {
        Email::create([
            'user_id' => $user->id,
            'message_id' => "<{$folder}@example.com>",
            'folder' => $folder,
            'from_address' => 'sender@example.com',
            'to_addresses' => [],
            'cc_addresses' => [],
            'subject' => 'foldered message',
            'date' => now(),
            'body_text' => 'folder keyword',
            'attachments' => [],
        ]);
    }

    $results = (new ScoutEmailSearchService)->search($user, 'folder', 'Sent');

    expect($results->getCollection()->pluck('folder')->unique()->all())
        ->toBe(['Sent']);
});

test('search uses scout builder with user and folder filters when available', function () {
    config(['scout.driver' => 'typesense']);

    $user = User::factory()->create();
    $email = Email::withoutSyncingToSearch(fn () => Email::create([
        'user_id' => $user->id,
        'message_id' => '<scout@example.com>',
        'folder' => 'Archive',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Scout result',
        'date' => now(),
        'body_text' => 'Matched via scout',
        'attachments' => [],
    ]));

    $service = new ScoutEmailSearchService(function (ScoutBuilder $builder, int $perPage) use ($email, $user) {
        expect($builder->query)->toBe('scout query')
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

        return paginatorFor([$email], $perPage);
    });

    $results = $service->search($user, 'scout query', 'Archive', 15);

    expect($results->getCollection()->pluck('id')->all())->toBe([$email->id]);
});

test('get recent returns emails in reverse chronological order', function () {
    config(['scout.driver' => null]);

    $user = User::factory()->create();

    $older = Email::create([
        'user_id' => $user->id,
        'message_id' => '<older@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Older',
        'date' => CarbonImmutable::parse('2026-04-03 08:00:00'),
        'attachments' => [],
    ]);

    $newer = Email::create([
        'user_id' => $user->id,
        'message_id' => '<newer@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Newer',
        'date' => CarbonImmutable::parse('2026-04-03 09:00:00'),
        'attachments' => [],
    ]);

    $results = (new ScoutEmailSearchService)->getRecent($user);

    expect($results->getCollection()->modelKeys())->toBe([$newer->id, $older->id]);
});

test('search falls back to mysql and flashes a warning when scout is unavailable', function () {
    config(['scout.driver' => 'typesense']);
    session()->start();

    $user = User::factory()->create();
    $match = Email::withoutSyncingToSearch(fn () => Email::create([
        'user_id' => $user->id,
        'message_id' => '<fallback@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Fallback result',
        'date' => now(),
        'body_text' => 'mysql fallback body',
        'attachments' => [],
    ]));

    $service = new ScoutEmailSearchService(fn () => throw new RuntimeException('Typesense unavailable'));
    $results = $service->search($user, 'fallback');

    expect($results->getCollection()->modelKeys())->toBe([$match->id])
        ->and(session('warning'))->toBe('Search is temporarily unavailable. Showing basic results.');
});

function paginatorFor(array $items, int $perPage): LengthAwarePaginator
{
    return new LengthAwarePaginator(
        collect($items),
        count($items),
        $perPage,
        1,
        ['path' => '/'],
    );
}
