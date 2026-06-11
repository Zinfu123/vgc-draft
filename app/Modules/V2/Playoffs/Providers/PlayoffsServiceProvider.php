<?php

namespace App\Modules\V2\Playoffs\Providers;

use App\Kernel\Audit\ModuleAuditRegistry;
use App\Modules\V2\Playoffs\Audit\PlayoffsModuleAuditor;
use Illuminate\Support\ServiceProvider;

class PlayoffsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(ModuleAuditRegistry::class)
            ->register($this->app->make(PlayoffsModuleAuditor::class));
    }
}
