<?php

namespace App\Support;

final class CleanupInvalidViteHotFile
{
    /**
     * Remove `public/hot` when it would break Vite / Expose 3: an empty file still satisfies
     * `file_exists()` so Expose tries to share Vite but skips (empty HMR URL), never rewriting
     * `hot`, and Laravel emits relative `/@vite/client` URLs that 404 on the app host.
     */
    public static function deleteIfInvalid(bool $isLocalApp, string $hotPath): void
    {
        if (! $isLocalApp || ! is_file($hotPath)) {
            return;
        }

        $contents = trim((string) file_get_contents($hotPath));

        if ($contents === '') {
            @unlink($hotPath);

            return;
        }

        if (! str_starts_with($contents, 'http://') && ! str_starts_with($contents, 'https://')) {
            @unlink($hotPath);
        }
    }
}
