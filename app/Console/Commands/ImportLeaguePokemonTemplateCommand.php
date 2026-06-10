<?php

namespace App\Console\Commands;

use App\Modules\League\Services\ImportLeaguePokemonTemplateCsvService;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class ImportLeaguePokemonTemplateCommand extends Command
{
    protected $signature = 'league:pokemon-template-import
                            {path : Absolute or relative path to CSV (nationaldex_id,cost per row; optional header row)}
                            {name : Display name for the template}
                            {--slug= : URL-friendly unique key (defaults from display name)}
                            {--replace : Replace rows on an existing template with the same slug}
                            {--version-group= : Version group slug (e.g. scarlet-violet); defaults to highest sort_order}
                            {--no-publish : Create or update template as unpublished (hidden from the public catalog)}';

    protected $description = 'Import or update a league Pokémon pool template from CSV (developer / CLI).';

    public function handle(ImportLeaguePokemonTemplateCsvService $service): int
    {
        $rawPath = (string) $this->argument('path');
        $path = $rawPath;
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[a-zA-Z]:\\\\/', $path)) {
            $path = base_path($path);
        }

        $name = (string) $this->argument('name');
        $slug = $this->option('slug') !== null ? (string) $this->option('slug') : null;
        $replace = (bool) $this->option('replace');

        $versionGroupSlug = $this->option('version-group');
        $versionGroupId = null;
        if ($versionGroupSlug !== null && $versionGroupSlug !== '') {
            $vg = VersionGroup::query()->where('slug', (string) $versionGroupSlug)->first();
            if ($vg === null) {
                $this->error("Unknown version group slug: {$versionGroupSlug}");

                return self::FAILURE;
            }
            $versionGroupId = (int) $vg->id;
        }

        try {
            $isPublished = ! (bool) $this->option('no-publish');
            $result = $service->import($path, $name, $slug, $replace, $versionGroupId, $isPublished);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $template = $result['template'];
        $template->loadMissing('versionGroup');
        $this->info("Template #{$template->id} \"{$template->name}\" (slug: {$template->slug})");
        if ($template->versionGroup !== null) {
            $this->info("Version group: {$template->versionGroup->name} (generation {$template->versionGroup->generation})");
        }
        $this->info("Rows imported: {$result['rows_imported']}");
        if ($result['rows_skipped_unknown_dex'] > 0) {
            $this->warn("Rows skipped (unknown nationaldex_id): {$result['rows_skipped_unknown_dex']}");
        }

        return self::SUCCESS;
    }
}
