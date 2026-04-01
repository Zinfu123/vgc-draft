<?php

namespace App\Console\Commands;

use App\Modules\Pokepaste\Services\ShowdownFormatHelper;
use App\Modules\Stats\Models\VgcLadderSpeciesUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportShowdownVgcUsageCommand extends Command
{
    protected $signature = 'stats:import-showdown-vgc
                            {url : URL of a JSON chaos / usage file}
                            {--format= : Showdown ladder format key (e.g. gen9vgc2026regi)}
                            {--period= :YYYY-MM month label for this snapshot}';

    protected $description = 'Import Pokémon Showdown ladder usage (VGC format) into vgc_ladder_species_usage';

    public function handle(): int
    {
        $url = (string) $this->argument('url');
        $format = (string) ($this->option('format') ?? '');
        $period = (string) ($this->option('period') ?? now()->format('Y-m'));

        if ($format === '') {
            $this->error('The --format option is required (Showdown format id, must match VGC allowlist).');

            return self::FAILURE;
        }

        if (! $this->isAllowedFormat($format)) {
            $this->error('Format key is not on the VGC allowlist (config/showdown_vgc.php).');

            return self::FAILURE;
        }

        $this->info("Fetching {$url}");
        $response = Http::timeout(300)->acceptJson()->get($url);
        if (! $response->successful()) {
            $this->error('HTTP '.$response->status());

            return self::FAILURE;
        }

        /** @var mixed $data */
        $data = $response->json();
        if (! is_array($data)) {
            $this->error('Response was not a JSON object.');

            return self::FAILURE;
        }

        /** @var array<string, mixed> $blocks */
        $blocks = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        $imported = 0;
        foreach ($blocks as $speciesLabel => $block) {
            if (! is_string($speciesLabel) || ! is_array($block)) {
                continue;
            }

            $key = ShowdownFormatHelper::speciesToMatchKey($speciesLabel);
            if ($key === '') {
                continue;
            }

            $usageRaw = $block['usage'] ?? $block['Usage'] ?? null;
            $usagePercent = $this->parseUsagePercent($usageRaw);
            VgcLadderSpeciesUsage::query()->updateOrCreate(
                [
                    'format_key' => $format,
                    'period' => $period,
                    'species_key' => $key,
                ],
                [
                    'usage_percent' => $usagePercent,
                    'detail' => $block,
                    'imported_at' => now(),
                ]
            );
            $imported++;
        }

        $this->info("Imported {$imported} species rows for {$format} / {$period}.");

        return self::SUCCESS;
    }

    private function isAllowedFormat(string $formatKey): bool
    {
        $key = strtolower($formatKey);
        foreach (config('showdown_vgc.allowed_format_substrings', ['vgc']) as $sub) {
            if (str_contains($key, strtolower((string) $sub))) {
                return true;
            }
        }

        $exact = config('showdown_vgc.allowed_format_keys', []);

        return is_array($exact) && in_array($formatKey, $exact, true);
    }

    private function parseUsagePercent(mixed $raw): float
    {
        if ($raw === null) {
            return 0.0;
        }

        if (is_numeric($raw)) {
            $v = (float) $raw;
            if ($v > 0 && $v <= 1.0) {
                return round($v * 100, 4);
            }

            return round($v, 4);
        }

        if (is_string($raw)) {
            $s = trim(str_replace('%', '', $raw));
            if (is_numeric($s)) {
                return round((float) $s, 4);
            }
        }

        return 0.0;
    }
}
