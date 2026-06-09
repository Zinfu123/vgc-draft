<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v2')
    ->middleware(['web'])
    ->name('v2.')
    ->group(function (): void {
        Route::get('/', function () {
            return response()->json([
                'version' => 2,
                'modules' => config('modules.v2.enabled', []),
            ]);
        })->name('health');

        foreach (config('modules.v2.enabled', []) as $module) {
            $path = app_path('Modules/V2/'.$module.'/routes.php');

            if (is_file($path)) {
                require $path;
            }
        }
    });
