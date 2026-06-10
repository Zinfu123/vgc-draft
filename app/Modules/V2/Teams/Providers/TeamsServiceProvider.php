<?php

namespace App\Modules\V2\Teams\Providers;

use App\Kernel\Audit\ModuleAuditRegistry;
use App\Modules\V2\Teams\Audit\TeamsModuleAuditor;
use Illuminate\Support\ServiceProvider;

class TeamsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(ModuleAuditRegistry::class)
            ->register($this->app->make(TeamsModuleAuditor::class));
    }
}
