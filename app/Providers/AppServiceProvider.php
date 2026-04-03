<?php

namespace App\Providers;

use App\Services\DatabaseEmailIndexingService;
use App\Services\EmailIndexingService;
use App\Services\EmailSearchService;
use App\Services\ImapConnectionService;
use App\Services\ScoutEmailSearchService;
use App\Services\WebklexImapConnectionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Webklex\PHPIMAP\ClientManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClientManager::class, fn (): ClientManager => new ClientManager([]));
        $this->app->singleton(ImapConnectionService::class, WebklexImapConnectionService::class);
        $this->app->singleton(EmailIndexingService::class, DatabaseEmailIndexingService::class);
        $this->app->singleton(EmailSearchService::class, ScoutEmailSearchService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
