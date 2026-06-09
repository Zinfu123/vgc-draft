<?php

namespace App\Modules\V2\Pokedex\Providers;

use App\Kernel\Audit\ModuleAuditRegistry;
use App\Modules\V2\Pokedex\Audit\PokedexModuleAuditor;
use Illuminate\Support\ServiceProvider;

class PokedexServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(ModuleAuditRegistry::class)
            ->register($this->app->make(PokedexModuleAuditor::class));
    }
}
