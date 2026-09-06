<?php

namespace App\Console\Commands;

use App\Jobs\SyncUserEmailsJob;
use App\Models\Email;
use App\Models\ImapSetting;
use App\Models\MailFolder;
use App\Models\SyncSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

class ResetAndImportMailCommand extends Command
{
    protected $signature = 'mail:reset-and-import {--force : Delete the local mail cache without prompting}';

    protected $description = 'Delete the local mail cache, rebuild the Typesense collection, and queue a full IMAP import.';

    public function handle(Client $client): int
    {
        if (! $this->option('force') && ! $this->confirm('This deletes every locally indexed email and search document. Continue?')) {
            return self::SUCCESS;
        }

        try {
            $this->resetLocalMail();
            $this->resetTypesenseCollection($client);
        } catch (TypesenseClientError $exception) {
            $this->components->error("Unable to reset Typesense: {$exception->getMessage()}");

            return self::FAILURE;
        }

        ImapSetting::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->lazyById()
            ->each(fn (ImapSetting $setting): mixed => SyncUserEmailsJob::dispatch($setting->user_id));

        $this->components->info('Local mail cache cleared and full IMAP import queued.');

        return self::SUCCESS;
    }

    private function resetLocalMail(): void
    {
        DB::transaction(function (): void {
            Email::query()->delete();
            MailFolder::query()->delete();
            SyncSession::query()->delete();
        });
    }

    private function resetTypesenseCollection(Client $client): void
    {
        $schema = config('scout.[REDACTED].model-settings.'.Email::class.'.collection-schema', []);
        $collectionName = $schema['name'] ?? (new Email)->searchableAs();
        $collections = $client->getCollections();

        try {
            $collections[$collectionName]->delete();
        } catch (ObjectNotFound) {
            // The collection is created below when this is the first import.
        }

        $collections->create($schema);
    }
}
