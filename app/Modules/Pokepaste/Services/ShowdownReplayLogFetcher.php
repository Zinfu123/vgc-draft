<?php

namespace App\Modules\Pokepaste\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ShowdownReplayLogFetcher
{
    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function fetch(string $logDownloadUrl): string
    {
        if (! str_starts_with($logDownloadUrl, 'https://replay.pokemonshowdown.com/')
            || ! str_ends_with($logDownloadUrl, '.log')) {
            throw new InvalidArgumentException('Invalid Showdown log URL.');
        }

        $response = Http::timeout(20)
            ->withOptions(['allow_redirects' => false])
            ->accept('text/plain')
            ->get($logDownloadUrl);

        if ($response->failed()) {
            throw new RuntimeException('Could not download replay log (HTTP '.$response->status().').');
        }

        return $response->body();
    }
}
