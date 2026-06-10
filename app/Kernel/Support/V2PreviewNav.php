<?php

namespace App\Kernel\Support;

class V2PreviewNav
{
    /**
     * @return list<array{module: string, href: string}>
     */
    public static function links(): array
    {
        $routes = config('modules.v2.preview_routes', []);

        return collect(config('modules.v2.enabled', []))
            ->filter(fn (string $module): bool => isset($routes[$module]))
            ->map(fn (string $module): array => [
                'module' => $module,
                'href' => $routes[$module],
            ])
            ->values()
            ->all();
    }

    public static function isVisible(): bool
    {
        if (self::links() === []) {
            return false;
        }

        $flag = config('modules.v2.preview_nav');

        if ($flag !== null) {
            return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
        }

        return app()->environment('local', 'staging');
    }
}
