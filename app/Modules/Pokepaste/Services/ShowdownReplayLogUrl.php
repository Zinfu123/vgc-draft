<?php

namespace App\Modules\Pokepaste\Services;

use InvalidArgumentException;

class ShowdownReplayLogUrl
{
    /**
     * @throws InvalidArgumentException
     */
    public static function resolveLogDownloadUrl(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            throw new InvalidArgumentException('Replay URL is empty.');
        }

        $parts = parse_url($input);
        if ($parts === false || ! isset($parts['host'])) {
            throw new InvalidArgumentException('Invalid replay URL.');
        }

        if (strtolower($parts['host']) !== 'replay.pokemonshowdown.com') {
            throw new InvalidArgumentException('URL must be on replay.pokemonshowdown.com.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        if ($scheme !== 'https') {
            throw new InvalidArgumentException('Only HTTPS replay URLs are allowed.');
        }

        $path = trim((string) ($parts['path']), '/');
        if ($path === '') {
            throw new InvalidArgumentException('Replay path is missing.');
        }

        if (str_ends_with(strtolower($path), '.log')) {
            $path = substr($path, 0, -4);
        }

        if (! preg_match('/^[a-z0-9][a-z0-9\-]*$/i', $path)) {
            throw new InvalidArgumentException('Invalid replay id.');
        }

        return 'https://replay.pokemonshowdown.com/'.$path.'.log';
    }

    /**
     * Lowercase battle id for duplicate detection, or null if the URL is not a valid Showdown replay.
     */
    public static function battleKeyFromReplayUrl(string $input): ?string
    {
        try {
            $logUrl = self::resolveLogDownloadUrl($input);
        } catch (\Throwable) {
            return null;
        }

        if (preg_match('#/([a-z0-9][a-z0-9\-]*)\.log$#i', $logUrl, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }
}
