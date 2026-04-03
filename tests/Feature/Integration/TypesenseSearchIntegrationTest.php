<?php

use App\Models\Email;
use App\Models\User;
use App\Services\ScoutEmailSearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder as ScoutBuilder;

test('typesense integration executes search queries with user and folder filters', function () {
    config(['scout.driver' => 'typesense']);

    $user = User::factory()->create();
    $match = Email::withoutSyncingToSearch(fn () => Email::create([
        'user_id' => $user->id,
        'message_id' => '<integration-search@example.com>',
        'folder' => 'Archive',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Integration search result',
        'date' => now(),
        'body_text' => 'Typesense integration body',
        'attachments' => [],
    ]));

    $service = new ScoutEmailSearchService(function (ScoutBuilder $builder, int $perPage) use ($match, $user) {
        expect($builder->query)->toBe('invoice')
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

        return integrationSearchPaginator([$match], $perPage);
    });

    $results = $service->search($user, 'invoice', 'Archive', 25);

    expect($results->getCollection()->pluck('id')->all())->toBe([$match->id])
        ->and($results->perPage())->toBe(25);
});

test('typesense integration falls back gracefully when the search engine is unavailable', function () {
    config(['scout.driver' => 'typesense']);
    session()->start();

    $user = User::factory()->create();
    $email = Email::withoutSyncingToSearch(fn () => Email::create([
        'user_id' => $user->id,
        'message_id' => '<integration-fallback@example.com>',
        'folder' => 'INBOX',
        'from_address' => 'sender@example.com',
        'to_addresses' => [],
        'cc_addresses' => [],
        'subject' => 'Fallback search result',
        'date' => now(),
        'body_text' => 'invoice fallback content',
        'attachments' => [],
    ]));

    $service = new ScoutEmailSearchService(fn () => throw new RuntimeException('Typesense unavailable'));
    $results = $service->search($user, 'invoice');

    expect($results->getCollection()->modelKeys())->toBe([$email->id])
        ->and(session('warning'))->toBe('Search is temporarily unavailable. Showing basic results.');
});

function integrationSearchPaginator(array $items, int $perPage): LengthAwarePaginator
{
    return new LengthAwarePaginator(
        collect($items),
        count($items),
        $perPage,
        1,
        ['path' => '/'],
    );
}
