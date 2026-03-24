<?php

namespace App\Console\Commands;

use App\Modules\League\Services\CreateLeaguePokemonTemplateFromLeaguePoolService;
use App\Modules\Pokedex\Models\VersionGroup;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class LeaguePokemonTemplateFromLeagueCommand extends Command
{
    protected $signature = 'league:pokemon-template-from-league
                            {league : League ID to copy the Pokémon pool from}
                            {name : Display name for the new template}
                            {--slug= : URL-friendly unique key (defaults from display name)}
                            {--replace : Overwrite an existing template that uses the same slug}
                            {--version-group= : Version group slug; overrides the league\'s default game version group}
                            {--no-publish : Create or update template as unpublished (hidden from the public catalog)}';

    protected $description = 'Create a league Pokémon pool template from an existing league\'s pool (pokedex_id + cost per row).';

    public function handle(CreateLeaguePokemonTemplateFromLeaguePoolService $service): int
    {
        $leagueId = (int) $this->argument('league');
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
            $result = $service->createFromLeague($leagueId, $name, $slug, $replace, $versionGroupId, $isPublished);
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
        $this->info("Rows written: {$result['rows_written']}");

        return self::SUCCESS;
    }
}
