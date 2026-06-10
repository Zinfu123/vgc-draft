<?php

namespace App\Console\Commands;

use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class SyncShowdownVgcUsageCommand extends Command
{
    protected $signature = 'stats:sync-showdown-vgc-usage
                            {--period= : Force YYYY-MM (overrides auto-detection)}';

    protected $description = 'Download latest Smogon chaos JSON per VersionGroup showdown_format_key into vgc_ladder_species_usage';

    public function handle(): int
    {
        $groups = VersionGroup::query()
            ->whereNotNull('showdown_format_key')
            ->where('showdown_format_key', '!=', '')
            ->get();

        if ($groups->isEmpty()) {
            $this->warn('No version groups have showdown_format_key set; nothing to sync.');

            return self::SUCCESS;
        }

        $baseUrl = rtrim((string) config('showdown_vgc.chaos_base_url'), '/');
        $forcedPeriod = $this->option('period');
        $forcedPeriod = is_string($forcedPeriod) && preg_match('/^\d{4}-\d{2}$/', $forcedPeriod)
            ? $forcedPeriod
            : null;

        $synced = 0;
        foreach ($groups as $group) {
            $formatKey = (string) $group->showdown_format_key;
            $rating = (int) ($group->showdown_ladder_rating ?? config('showdown_vgc.default_ladder_rating', 1760));

            $period = $forcedPeriod
                ?? (string) config('showdown_vgc.import_period')
                ?: $this->resolvePeriodForFormat($baseUrl, $formatKey, $rating);

            if ($period === null) {
                $this->error("Could not resolve a stats period for {$formatKey} ({$group->slug}).");

                continue;
            }

            $url = "{$baseUrl}/{$period}/chaos/{$formatKey}-{$rating}.json";
            $this->info("Syncing {$group->slug}: {$url}");

            $code = Artisan::call('stats:import-showdown-vgc', [
                'url' => $url,
                '--format' => $formatKey,
                '--period' => $period,
            ]);

            if ($code !== self::SUCCESS) {
                $this->error(Artisan::output());

                continue;
            }

            $this->line(trim(Artisan::output()));
            $synced++;
        }

        $this->info("Finished: {$synced} / {$groups->count()} version group(s) synced.");

        return $synced > 0 || $groups->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Pick the newest month that returns HTTP 200 for the chaos file (tries current month, then previous).
     */
    private function resolvePeriodForFormat(string $baseUrl, string $formatKey, int $rating): ?string
    {
        $candidates = [
            now()->format('Y-m'),
            now()->subMonth()->format('Y-m'),
        ];

        foreach ($candidates as $period) {
            $url = "{$baseUrl}/{$period}/chaos/{$formatKey}-{$rating}.json";
            $response = Http::timeout(45)->head($url);
            if ($response->successful()) {
                return $period;
            }
        }

        return null;
    }
}
