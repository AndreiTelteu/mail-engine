<?php

namespace App\Console\Commands;

use App\Models\Email;
use Illuminate\Console\Command;
use Typesense\Client;
use Typesense\Exceptions\TypesenseClientError;

class InitializeTypesenseCollectionCommand extends Command
{
    protected $signature = 'mail:typesense:init {--fresh : Delete the existing emails collection before recreating it}';

    protected $description = 'Initialize the Typesense collection used for indexed emails.';

    public function handle(Client $client): int
    {
        $schema = config('scout.typesense.model-settings.'.Email::class.'.collection-schema', []);
        $collectionName = $schema['name'] ?? (new Email)->searchableAs();
        $schema['name'] = $collectionName;

        try {
            $collections = $client->getCollections();
            $existingCollections = collect($collections->retrieve())
                ->pluck('name')
                ->filter()
                ->all();

            $exists = in_array($collectionName, $existingCollections, true);

            if ($exists && $this->option('fresh')) {
                $collections[$collectionName]->delete();

                $this->components->info("Deleted existing Typesense collection [{$collectionName}].");

                $exists = false;
            }

            if ($exists) {
                $this->components->info("Typesense collection [{$collectionName}] already exists.");

                return self::SUCCESS;
            }

            $collections->create($schema);

            $this->components->info("Created Typesense collection [{$collectionName}].");

            return self::SUCCESS;
        } catch (TypesenseClientError $exception) {
            $this->components->error("Unable to initialize Typesense collection [{$collectionName}]: {$exception->getMessage()}");

            return self::FAILURE;
        }
    }
}
