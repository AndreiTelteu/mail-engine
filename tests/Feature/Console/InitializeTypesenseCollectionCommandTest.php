<?php

use App\Models\Email;
use Typesense\Client;
use Typesense\Collection;
use Typesense\Collections;

class FakeTypesenseCollection extends Collection
{
    public int $deleteCalls = 0;

    public function __construct() {}

    public function delete(): array
    {
        $this->deleteCalls++;

        return [];
    }
}

class FakeTypesenseCollections extends Collections
{
    public array $createdSchemas = [];

    public function __construct(
        public array $collectionsResponse = [],
        public ?FakeTypesenseCollection $collection = null,
    ) {}

    public function create(array $schema): array
    {
        $this->createdSchemas[] = $schema;

        return $schema;
    }

    public function retrieve(): array
    {
        return $this->collectionsResponse;
    }

    public function offsetGet($offset): Collection
    {
        return $this->collection ??= new FakeTypesenseCollection;
    }
}

class FakeTypesenseClient extends Client
{
    public function __construct(
        private readonly FakeTypesenseCollections $fakeCollections,
    ) {}

    public function getCollections(): Collections
    {
        return $this->fakeCollections;
    }
}

test('scout typesense config defines the email schema and search parameters', function () {
    $schema = config('scout.typesense.model-settings.'.Email::class.'.collection-schema');

    expect($schema['name'])->toBe('emails')
        ->and($schema['default_sorting_field'])->toBe('date')
        ->and(collect($schema['fields'])->pluck('name')->all())
        ->toBe(['id', 'user_id', 'from_address', 'from_name', 'subject', 'body_text', 'folder', 'date'])
        ->and(config('scout.typesense.model-settings.'.Email::class.'.search-parameters.query_by'))
        ->toBe('subject,from_address,from_name,body_text,folder');
});

test('typesense init command creates the configured emails collection when missing', function () {
    $collections = new FakeTypesenseCollections;

    app()->instance(Client::class, new FakeTypesenseClient($collections));

    $this->artisan('mail:typesense:init')
        ->assertSuccessful();

    expect($collections->createdSchemas)->toHaveCount(1)
        ->and($collections->createdSchemas[0]['name'])->toBe('emails');
});

test('typesense init command skips creation when the collection already exists', function () {
    $collections = new FakeTypesenseCollections([
        ['name' => 'emails'],
    ]);

    app()->instance(Client::class, new FakeTypesenseClient($collections));

    $this->artisan('mail:typesense:init')
        ->assertSuccessful();

    expect($collections->createdSchemas)->toBeEmpty();
});

test('typesense init command can recreate an existing collection', function () {
    $collection = new FakeTypesenseCollection;
    $collections = new FakeTypesenseCollections([
        ['name' => 'emails'],
    ], $collection);

    app()->instance(Client::class, new FakeTypesenseClient($collections));

    $this->artisan('mail:typesense:init', ['--fresh' => true])
        ->assertSuccessful();

    expect($collection->deleteCalls)->toBe(1)
        ->and($collections->createdSchemas)->toHaveCount(1)
        ->and($collections->createdSchemas[0]['name'])->toBe('emails');
});
