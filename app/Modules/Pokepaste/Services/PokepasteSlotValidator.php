<?php

namespace App\Modules\Pokepaste\Services;

use App\Enums\PokemonNature;
use App\Enums\PokemonTeraType;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Pokedex\Models\AbilityGenerationData;
use App\Modules\Pokedex\Models\PokemonGenerationData;
use App\Modules\Pokedex\Models\VersionGroup;
use App\Modules\Pokedex\Models\VersionGroupHeldItem;
use App\Modules\Pokepaste\Support\PokepasteSlotDefaults;
use App\Modules\Teams\Models\Team;
use Illuminate\Validation\ValidationException;

class PokepasteSlotValidator
{
    public function __construct(
        private VersionGroupHeldItemLookup $heldItemLookup,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $slots
     * @return array<int, array<string, mixed>>
     */
    public function validateAndNormalize(Team $team, League $league, array $slots, bool $allowPartialSave = false): array
    {
        if (count($slots) !== 6) {
            throw ValidationException::withMessages(['slots' => 'A team must have exactly 6 slots.']);
        }

        $versionGroup = $league->versionGroup();
        if ($versionGroup === null) {
            throw ValidationException::withMessages(['slots' => 'League version group is not configured.']);
        }

        $seenLeaguePokemonIds = [];
        $seenHeldItemIds = [];

        foreach ($slots as $index => $slot) {
            $prefix = "slots.{$index}";
            $rawId = $slot['league_pokemon_id'] ?? null;
            $leaguePokemonId = is_numeric($rawId) ? (int) $rawId : 0;

            if ($leaguePokemonId <= 0) {
                if (! $allowPartialSave) {
                    throw ValidationException::withMessages([$prefix.'.league_pokemon_id' => 'Each slot must have a Pokémon from your roster.']);
                }
                $slots[$index] = PokepasteSlotDefaults::emptyOne();

                continue;
            }

            if (isset($seenLeaguePokemonIds[$leaguePokemonId])) {
                throw ValidationException::withMessages([$prefix.'.league_pokemon_id' => 'The same Pokémon cannot be used in more than one slot.']);
            }
            $seenLeaguePokemonIds[$leaguePokemonId] = true;

            $leaguePokemon = LeaguePokemon::query()
                ->where('id', $leaguePokemonId)
                ->where('drafted_by', $team->id)
                ->where('league_id', $league->id)
                ->first();

            if ($leaguePokemon === null) {
                throw ValidationException::withMessages([$prefix.'.league_pokemon_id' => 'Invalid roster Pokémon for this team.']);
            }

            $gameData = PokemonGenerationData::query()
                ->where('pokedex_id', $leaguePokemon->pokedex_id)
                ->where('version_group_id', $versionGroup->id)
                ->first();

            if ($gameData === null) {
                throw ValidationException::withMessages([$prefix.'.league_pokemon_id' => 'Game data is missing for this Pokémon. Import version group data first.']);
            }

            $abilityRows = AbilityGenerationData::query()
                ->where('pokedex_id', $leaguePokemon->pokedex_id)
                ->where('version_group_id', $versionGroup->id)
                ->get();

            $ability = trim((string) ($slot['ability'] ?? ''));

            if ($ability === '') {
                if (! $allowPartialSave) {
                    throw ValidationException::withMessages([$prefix.'.ability' => 'Each Pokémon must have an ability.']);
                }
            } else {
                $matchedDisplay = null;
                $abilityKey = ShowdownFormatHelper::abilityToMatchKey($ability);
                foreach ($abilityRows as $row) {
                    $rowKey = ShowdownFormatHelper::abilityToMatchKey($row->ability_name);
                    if ($rowKey === $abilityKey) {
                        $matchedDisplay = ShowdownFormatHelper::moveSlugToDisplay($row->ability_name);

                        break;
                    }
                }
                if ($matchedDisplay === null) {
                    throw ValidationException::withMessages([$prefix.'.ability' => 'Invalid ability for this Pokémon.']);
                }
                $ability = $matchedDisplay;
            }

            $moves = $slot['moves'] ?? [];
            if (! is_array($moves)) {
                throw ValidationException::withMessages([$prefix.'.moves' => 'Moves must be an array.']);
            }
            $moves = array_values(array_pad(array_slice($moves, 0, 4), 4, ''));

            $learnset = $gameData->learnset ?? [];
            $allowedSlugs = [];
            foreach ($learnset as $row) {
                if (is_array($row) && isset($row['move_name']) && is_string($row['move_name'])) {
                    $allowedSlugs[ShowdownFormatHelper::moveToSlug($row['move_name'])] = true;
                }
            }

            $normalizedMoves = [];
            $seenMoveSlugsInSlot = [];
            foreach ($moves as $mi => $move) {
                $slug = ShowdownFormatHelper::moveToSlug((string) $move);
                if ($slug === '') {
                    if (! $allowPartialSave) {
                        throw ValidationException::withMessages([$prefix.".moves.{$mi}" => 'Exactly four moves are required.']);
                    }
                    $normalizedMoves[] = '';

                    continue;
                }
                if (! isset($allowedSlugs[$slug])) {
                    throw ValidationException::withMessages([$prefix.".moves.{$mi}" => 'Invalid move for this Pokémon.']);
                }
                if (isset($seenMoveSlugsInSlot[$slug])) {
                    throw ValidationException::withMessages([$prefix.".moves.{$mi}" => 'The same move cannot be selected more than once on this Pokémon.']);
                }
                $seenMoveSlugsInSlot[$slug] = true;
                $normalizedMoves[] = $slug;
            }

            $teraType = isset($slot['tera_type']) && $slot['tera_type'] !== null && $slot['tera_type'] !== ''
                ? trim((string) $slot['tera_type'])
                : null;

            if ($teraType !== null && $teraType !== '') {
                $mechanics = $gameData->mechanics ?? [];
                $teraCapable = ! empty($mechanics['tera_capable']);
                if (! $teraCapable) {
                    throw ValidationException::withMessages([$prefix.'.tera_type' => 'This Pokémon cannot use Tera Type in this format.']);
                }
                if (! PokemonTeraType::isAllowedValue($teraType, $versionGroup->generation)) {
                    throw ValidationException::withMessages([$prefix.'.tera_type' => 'Invalid Tera Type.']);
                }
            }

            $heldItemId = $this->resolveHeldItemId($slot, $versionGroup, $prefix);

            if ($heldItemId !== null) {
                if (isset($seenHeldItemIds[$heldItemId])) {
                    throw ValidationException::withMessages([$prefix.'.version_group_held_item_id' => 'The same held item cannot be used in more than one slot.']);
                }
                $seenHeldItemIds[$heldItemId] = true;
            }

            $nature = $this->resolveNature($slot['nature'] ?? null, $prefix);

            $evs = $this->normalizeEvs($slot['evs'] ?? null);

            $slots[$index] = [
                'league_pokemon_id' => $leaguePokemonId,
                'ability' => $ability,
                'moves' => $normalizedMoves,
                'version_group_held_item_id' => $heldItemId,
                'nature' => $nature,
                'tera_type' => $teraType,
                'evs' => $evs,
            ];
        }

        return $slots;
    }

    /**
     * @return array<string, int>|null
     */
    private function normalizeEvs(mixed $evs): ?array
    {
        if ($evs === null || ! is_array($evs)) {
            return null;
        }

        $keys = ['hp', 'atk', 'def', 'spa', 'spd', 'spe'];
        $out = [];
        foreach ($keys as $k) {
            if (! array_key_exists($k, $evs)) {
                continue;
            }
            $v = (int) $evs[$k];
            if ($v < 0 || $v > 252) {
                throw ValidationException::withMessages(['slots' => "Invalid EV value for {$k}."]);
            }
            if ($v !== 0) {
                $out[$k] = $v;
            }
        }

        if ($out === []) {
            return null;
        }

        $sum = array_sum($out);
        if ($sum > 510) {
            throw ValidationException::withMessages(['slots' => 'EV total cannot exceed 510.']);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private function resolveHeldItemId(array $slot, VersionGroup $versionGroup, string $prefix): ?int
    {
        $heldItemId = isset($slot['version_group_held_item_id']) ? (int) $slot['version_group_held_item_id'] : null;
        if ($heldItemId !== null && $heldItemId <= 0) {
            $heldItemId = null;
        }

        if ($heldItemId === null && isset($slot['item']) && is_string($slot['item']) && trim($slot['item']) !== '') {
            $resolved = $this->heldItemLookup->findIdByShowdownLabel($versionGroup, $slot['item']);
            if ($resolved === null) {
                throw ValidationException::withMessages([$prefix.'.version_group_held_item_id' => 'Unknown held item for this game version.']);
            }
            $heldItemId = $resolved;
        }

        if ($heldItemId === null) {
            return null;
        }

        $exists = VersionGroupHeldItem::query()
            ->where('id', $heldItemId)
            ->where('version_group_id', $versionGroup->id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([$prefix.'.version_group_held_item_id' => 'Invalid held item for this league.']);
        }

        return $heldItemId;
    }

    private function resolveNature(mixed $raw, string $prefix): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            $enum = PokemonNature::tryFrom((int) $raw);
            if ($enum === null) {
                throw ValidationException::withMessages([$prefix.'.nature' => 'Invalid nature.']);
            }

            return $enum->value;
        }

        if (is_string($raw)) {
            $enum = PokemonNature::tryFromShowdownName($raw);
            if ($enum === null) {
                throw ValidationException::withMessages([$prefix.'.nature' => 'Invalid nature.']);
            }

            return $enum->value;
        }

        throw ValidationException::withMessages([$prefix.'.nature' => 'Invalid nature.']);
    }
}
