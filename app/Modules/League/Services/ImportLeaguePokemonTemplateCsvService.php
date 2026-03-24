<?php

namespace App\Modules\League\Services;

use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\League\Models\LeaguePokemonTemplateRow;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportLeaguePokemonTemplateCsvService
{
    public function __construct(
        private NationaldexCostCsvReader $csvReader,
    ) {}

    /**
     * @return array{template: LeaguePokemonTemplate, rows_imported: int, rows_skipped_unknown_dex: int}
     */
    public function import(string $absolutePath, string $displayName, ?string $slug = null, bool $replace = false, ?int $versionGroupId = null, bool $isPublished = true): array
    {
        $resolved = realpath($absolutePath);
        if ($resolved === false || ! is_readable($resolved)) {
            throw new InvalidArgumentException("CSV not found or unreadable: {$absolutePath}");
        }

        $slug = $slug !== null && $slug !== ''
            ? Str::slug($slug)
            : Str::slug($displayName);

        if ($slug === '') {
            throw new InvalidArgumentException('Slug cannot be empty; provide a --slug or a display name that slugifies to a non-empty value.');
        }

        $parsedRows = $this->csvReader->readFromPath($resolved);

        $resolvedVersionGroupId = $versionGroupId;
        if ($resolvedVersionGroupId === null) {
            $found = VersionGroup::query()->orderByDesc('sort_order')->value('id');
            if ($found === null) {
                throw new InvalidArgumentException('No version group is available. Create version_groups or pass an explicit version group id.');
            }
            $resolvedVersionGroupId = (int) $found;
        }

        return DB::transaction(function () use ($displayName, $slug, $replace, $parsedRows, $resolvedVersionGroupId, $isPublished): array {
            $existing = LeaguePokemonTemplate::query()->where('slug', $slug)->first();

            if ($existing !== null && ! $replace) {
                throw new InvalidArgumentException("A template with slug \"{$slug}\" already exists. Use --replace to overwrite its rows.");
            }

            if ($existing === null) {
                $template = LeaguePokemonTemplate::query()->create([
                    'name' => $displayName,
                    'slug' => $slug,
                    'description' => null,
                    'version_group_id' => $resolvedVersionGroupId,
                    'is_published' => $isPublished,
                ]);
            } else {
                $template = $existing;
                $template->update([
                    'name' => $displayName,
                    'version_group_id' => $resolvedVersionGroupId,
                    'is_published' => $isPublished,
                ]);
                LeaguePokemonTemplateRow::query()->where('league_pokemon_template_id', $template->id)->delete();
            }

            $imported = 0;
            $skipped = 0;

            foreach ($parsedRows as [$nationaldexId, $cost]) {
                $pokemon = Pokedex::query()->where('nationaldex_id', $nationaldexId)->first();
                if ($pokemon === null) {
                    $skipped++;

                    continue;
                }

                LeaguePokemonTemplateRow::query()->updateOrCreate(
                    [
                        'league_pokemon_template_id' => $template->id,
                        'pokedex_id' => $pokemon->id,
                    ],
                    [
                        'cost' => $cost,
                    ]
                );
                $imported++;
            }

            return [
                'template' => $template->fresh(),
                'rows_imported' => $imported,
                'rows_skipped_unknown_dex' => $skipped,
            ];
        });
    }
}
