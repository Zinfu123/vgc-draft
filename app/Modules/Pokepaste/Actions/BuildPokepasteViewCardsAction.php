<?php

namespace App\Modules\Pokepaste\Actions;

use App\Enums\PokemonNature;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Models\SetTeamPokepasteSlot;
use App\Modules\Pokepaste\Services\EnsureSetTeamPokepasteSlotRows;
use App\Modules\Pokepaste\Services\ShowdownFormatHelper;

class BuildPokepasteViewCardsAction
{
    public function __construct(
        private EnsureSetTeamPokepasteSlotRows $ensureSlotRows,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(SetTeamPokepaste $pokepaste): array
    {
        ($this->ensureSlotRows)($pokepaste);
        $pokepaste->load(['pasteSlots.leaguePokemon.pokemon', 'pasteSlots.heldItem']);

        $byIndex = $pokepaste->pasteSlots->keyBy('slot_index');
        $cards = [];
        for ($i = 0; $i < 6; $i++) {
            $row = $byIndex->get($i);
            $cards[] = $row !== null ? $this->cardFromSlot($row) : $this->emptyCard();
        }

        return $cards;
    }

    /**
     * @return array<string, mixed>
     */
    private function cardFromSlot(SetTeamPokepasteSlot $row): array
    {
        $lp = $row->leaguePokemon;
        if ($row->league_pokemon_id === null || $lp === null) {
            return $this->emptyCard();
        }

        $dex = $lp->pokemon;
        $species = $dex !== null
            ? ShowdownFormatHelper::pokemonDisplayLabel((string) $dex->name)
            : ShowdownFormatHelper::pokemonDisplayLabel((string) $lp->name);
        $nickname = trim((string) $lp->name);
        $showNickname = $nickname !== '' && strcasecmp($nickname, $species) !== 0;

        $held = $row->heldItem;
        $itemLabel = null;
        if ($held !== null) {
            $display = trim((string) ($held->display_name_en ?? ''));
            $itemLabel = $display !== '' ? $display : trim((string) $held->name);
            if ($itemLabel === '') {
                $itemLabel = null;
            }
        }

        $itemSprite = $held?->resolvedSpriteUrl();

        $spriteUrl = $dex?->sprite_url;
        if (is_string($spriteUrl) && trim($spriteUrl) === '') {
            $spriteUrl = null;
        }

        $moves = $row->moves ?? [];
        if (! is_array($moves)) {
            $moves = [];
        }
        $moves = array_values(array_pad(array_slice($moves, 0, 4), 4, ''));
        $movesDisplay = [];
        foreach ($moves as $slug) {
            $s = is_string($slug) ? trim($slug) : '';
            $movesDisplay[] = $s !== '' ? ShowdownFormatHelper::moveSlugToDisplay($s) : '';
        }

        $natureLabel = null;
        if ($row->nature !== null) {
            $n = PokemonNature::tryFrom((int) $row->nature);
            $natureLabel = $n?->label();
        }

        $ability = trim((string) ($row->ability ?? ''));

        return [
            'filled' => true,
            'species_label' => $species,
            'nickname_label' => $showNickname ? $nickname : null,
            'sprite_url' => $spriteUrl,
            'item_label' => $itemLabel,
            'item_sprite_url' => $itemSprite,
            'ability' => $ability !== '' ? $ability : null,
            'tera_type' => $this->nonEmptyString($row->tera_type),
            'nature_label' => $natureLabel,
            'evs_line' => $this->formatEvsLine($row),
            'moves' => $movesDisplay,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCard(): array
    {
        return [
            'filled' => false,
            'species_label' => null,
            'nickname_label' => null,
            'sprite_url' => null,
            'item_label' => null,
            'item_sprite_url' => null,
            'ability' => null,
            'tera_type' => null,
            'nature_label' => null,
            'evs_line' => null,
            'moves' => ['', '', '', ''],
        ];
    }

    private function formatEvsLine(SetTeamPokepasteSlot $row): ?string
    {
        $order = [
            'hp' => 'HP',
            'atk' => 'Atk',
            'def' => 'Def',
            'spa' => 'SpA',
            'spd' => 'SpD',
            'spe' => 'Spe',
        ];
        $parts = [];
        foreach ($order as $key => $label) {
            $v = (int) $row->getAttribute('ev_'.$key);
            if ($v > 0) {
                $parts[] = "{$v} {$label}";
            }
        }

        return $parts === [] ? null : 'EVs: '.implode(' / ', $parts);
    }

    private function nonEmptyString(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $t = trim($v);

        return $t !== '' ? $t : null;
    }
}
