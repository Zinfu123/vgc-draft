<?php

namespace App\Modules\V2\Matches\Providers;

use App\Kernel\Audit\ModuleAuditRegistry;
use App\Modules\V2\Matches\Audit\MatchesModuleAuditor;
use Illuminate\Support\ServiceProvider;

class MatchesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(ModuleAuditRegistry::class)
            ->register($this->app->make(MatchesModuleAuditor::class));
    }
}
