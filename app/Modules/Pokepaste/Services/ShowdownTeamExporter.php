<?php

namespace App\Modules\Pokepaste\Services;

use App\Enums\PokemonNature;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Models\VersionGroupHeldItem;

class ShowdownTeamExporter
{
    private const EV_ORDER = ['hp' => 'HP', 'atk' => 'Atk', 'def' => 'Def', 'spa' => 'SpA', 'spd' => 'SpD', 'spe' => 'Spe'];

    /**
     * @param  array<int, array<string, mixed>>  $slots
     */
    public function export(array $slots, ?VersionGroup $versionGroup = null): string
    {
        $blocks = [];

        foreach ($slots as $slot) {
            $leaguePokemon = LeaguePokemon::query()
                ->with('pokemon')
                ->find((int) ($slot['league_pokemon_id'] ?? 0));

            if ($leaguePokemon === null || ! $leaguePokemon->pokemon instanceof Pokedex) {
                continue;
            }

            /** @var Pokedex $pokedex */
            $pokedex = $leaguePokemon->pokemon;
            $species = ShowdownFormatHelper::pokemonDisplayLabel((string) $pokedex->name);
            $item = $this->resolveItemLabel($slot, $versionGroup);

            $lines = [];
            $lines[] = $item !== null && $item !== '' ? "{$species} @ {$item}" : $species;
            $lines[] = 'Ability: '.trim((string) ($slot['ability'] ?? ''));
            $lines[] = 'Level: 50';

            $evLine = $this->formatEvsLine($slot['evs'] ?? null);
            if ($evLine !== null) {
                $lines[] = $evLine;
            }

            $natureLabel = $this->resolveNatureLabel($slot);
            if ($natureLabel !== null && $natureLabel !== '') {
                $lines[] = $natureLabel.' Nature';
            }

            if (! empty($slot['tera_type'])) {
                $lines[] = 'Tera Type: '.trim((string) $slot['tera_type']);
            }

            $moves = $slot['moves'] ?? [];
            if (is_array($moves)) {
                foreach (array_slice($moves, 0, 4) as $move) {
                    $lines[] = '- '.ShowdownFormatHelper::moveSlugToDisplay((string) $move);
                }
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private function resolveItemLabel(array $slot, ?VersionGroup $versionGroup): ?string
    {
        $heldId = $slot['version_group_held_item_id'] ?? null;
        if ($heldId !== null && (int) $heldId > 0 && $versionGroup !== null) {
            $row = VersionGroupHeldItem::query()
                ->where('version_group_id', $versionGroup->id)
                ->find((int) $heldId);

            if ($row !== null) {
                return trim((string) ($row->display_name_en ?: $row->name));
            }
        }

        if (isset($slot['item']) && is_string($slot['item']) && trim($slot['item']) !== '') {
            return trim($slot['item']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private function resolveNatureLabel(array $slot): ?string
    {
        $raw = $slot['nature'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            $enum = PokemonNature::tryFrom((int) $raw);

            return $enum?->label();
        }

        if (is_string($raw)) {
            $enum = PokemonNature::tryFromShowdownName($raw);

            return $enum?->label();
        }

        return null;
    }

    /**
     * @param  array<string, int>|null  $evs
     */
    private function formatEvsLine(mixed $evs): ?string
    {
        if (! is_array($evs) || $evs === []) {
            return null;
        }

        $parts = [];
        foreach (self::EV_ORDER as $key => $label) {
            if (! empty($evs[$key])) {
                $parts[] = ((int) $evs[$key]).' '.$label;
            }
        }

        if ($parts === []) {
            return null;
        }

        return 'EVs: '.implode(' / ', $parts);
    }
}
