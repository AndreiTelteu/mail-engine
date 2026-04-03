<?php

test('scout is configured for queued batch indexing', function () {
    expect(config('scout.queue'))->toBeTrue()
        ->and(config('scout.after_commit'))->toBeTrue()
        ->and(config('scout.chunk.searchable'))->toBe(500)
        ->and(config('scout.chunk.unsearchable'))->toBe(500)
        ->and(config('scout.typesense.import_action'))->toBe('upsert');
});

test('queue connections default to processing jobs after database commit', function () {
    expect(config('queue.connections.database.after_commit'))->toBeTrue()
        ->and(config('queue.connections.redis.after_commit'))->toBeTrue()
        ->and(config('queue.connections.sqs.after_commit'))->toBeTrue()
        ->and(config('queue.connections.beanstalkd.after_commit'))->toBeTrue();
});

test('environment example includes queue worker and scout performance settings', function () {
    $environmentExample = file_get_contents(base_path('.env.example'));

    expect($environmentExample)->toContain(
        'QUEUE_CONNECTION=database',
        'QUEUE_AFTER_COMMIT=true',
        'DB_QUEUE_RETRY_AFTER=300',
        'SCOUT_DRIVER=typesense',
        'SCOUT_QUEUE=true',
        'SCOUT_CHUNK_SEARCHABLE=500',
        'SCOUT_CHUNK_UNSEARCHABLE=500',
        'TYPESENSE_IMPORT_ACTION=upsert',
        'php artisan queue:work --tries=3 --backoff=60 --sleep=1',
    );
});
