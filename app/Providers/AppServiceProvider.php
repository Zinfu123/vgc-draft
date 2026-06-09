<?php

namespace App\Providers;

use App\Kernel\Audit\ModuleAuditRegistry;
use App\Kernel\Contracts\PokedexPages;
use App\Kernel\Contracts\ShowdownFormatter;
use App\Kernel\Contracts\TeamCoveragePlanner;
use App\Kernel\Support\ShowdownFormatterService;
use App\Modules\League\Models\League;
use App\Modules\Pokedex\Services\PokedexPagesService;
use App\Modules\TeamCoverage\Services\TeamCoveragePlannerService;
use App\Policies\LeaguePolicy;
use App\Support\CleanupInvalidViteHotFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleAuditRegistry::class);
        $this->app->singleton(ShowdownFormatter::class, ShowdownFormatterService::class);
        $this->app->singleton(PokedexPages::class, PokedexPagesService::class);
        $this->app->singleton(TeamCoveragePlanner::class, TeamCoveragePlannerService::class);

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CleanupInvalidViteHotFile::deleteIfInvalid(
            $this->app->isLocal(),
            public_path('hot'),
        );

        Model::automaticallyEagerLoadRelationships();

        Gate::policy(League::class, LeaguePolicy::class);

        Event::listen(SocialiteWasCalled::class, \SocialiteProviders\Discord\DiscordExtendSocialite::class);
    }
}
