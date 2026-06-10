<?php

namespace App\Modules\V2\Draft\Providers;

use App\Kernel\Audit\ModuleAuditRegistry;
use App\Modules\V2\Draft\Audit\DraftModuleAuditor;
use Illuminate\Support\ServiceProvider;

class DraftServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->make(ModuleAuditRegistry::class)
            ->register($this->app->make(DraftModuleAuditor::class));
    }
}
