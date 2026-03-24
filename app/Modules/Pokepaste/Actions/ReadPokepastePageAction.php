<?php

namespace App\Modules\Pokepaste\Actions;

use App\Enums\PokemonNature;
use App\Enums\PokemonTeraType;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Services\EnsureSetTeamPokepasteSlotRows;
use App\Modules\Pokepaste\Support\PokepasteSlotDefaults;

class ReadPokepastePageAction
{
    public function __construct(
        private BuildPokepasteRosterPayloadAction $buildRoster,
        private EnsureSetTeamPokepasteSlotRows $ensureSlotRows,
        private BuildPokepasteViewCardsAction $buildViewCards,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(SetTeamPokepaste $pokepaste): array
    {
        $pokepaste->loadMissing(['team', 'matchable']);
        $team = $pokepaste->team;
        $league = $pokepaste->resolveLeague();
        $set = $pokepaste->setModel();
        $playoffMatch = $pokepaste->playoffMatch();

        $viewCards = ($this->buildViewCards)($pokepaste);

        if ($league === null || $team === null) {
            return [
                'set' => null,
                'playoff_match' => null,
                'league' => null,
                'team' => null,
                'roster' => [],
                'slots' => PokepasteSlotDefaults::sixEmptySlots(),
                'held_items' => [],
                'all_tera_types' => [],
                'natures' => PokemonNature::optionsForFrontend(),
                'view_cards' => $viewCards,
            ];
        }

        $versionGroup = $league->versionGroup();

        $rosterPokemon = $team->pokemon()->with('pokemon')->orderBy('cost', 'desc')->get();
        $roster = ($this->buildRoster)($rosterPokemon, $versionGroup);

        ($this->ensureSlotRows)($pokepaste);
        $pokepaste->load('pasteSlots');
        $byIndex = $pokepaste->pasteSlots->keyBy('slot_index');
        $slots = [];
        for ($i = 0; $i < 6; $i++) {
            $row = $byIndex->get($i);
            $slots[] = $row !== null ? $row->toFrontendSlotArray() : PokepasteSlotDefaults::emptyOne();
        }

        $heldItems = [];
        if ($versionGroup !== null) {
            $heldItems = $versionGroup->heldItems()
                ->orderByRaw('COALESCE(display_name_en, name) asc')
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'label' => $row->display_name_en ?: $row->name,
                ])
                ->values()
                ->all();
        }

        $generation = $versionGroup?->generation ?? $league->pokemon_game->generation();
        $allTeraTypes = PokemonTeraType::allValuesForGeneration($generation);

        $setPayload = null;
        if ($set !== null) {
            $setPayload = [
                'id' => $set->id,
                'league_id' => $set->league_id,
                'round' => $set->round,
            ];
        }

        $playoffMatchPayload = null;
        if ($playoffMatch !== null) {
            $playoffMatchPayload = [
                'id' => $playoffMatch->id,
                'slot' => $playoffMatch->slot,
                'round_index' => $playoffMatch->round_index,
                'league_id' => $league->id,
            ];
        }

        return [
            'set' => $setPayload,
            'playoff_match' => $playoffMatchPayload,
            'league' => [
                'id' => $league->id,
                'name' => $league->name,
            ],
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'roster' => $roster,
            'slots' => $slots,
            'held_items' => $heldItems,
            'all_tera_types' => $allTeraTypes,
            'natures' => PokemonNature::optionsForFrontend(),
            'view_cards' => $viewCards,
        ];
    }
}
