<?php

namespace App\Modules\League\Services;

use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\Pokedex\Models\Pokedex;
use App\Modules\Pokedex\Services\DraftPoolPokedexResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplyLeaguePokemonTemplateService
{
    public function __construct(
        private LeaguePokemonPoolReplaceEvaluator $replaceEvaluator,
        private DraftPoolPokedexResolver $pokedexResolver,
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function apply(League $league, LeaguePokemonTemplate $template, bool $confirmReplace): void
    {
        $hasRows = LeaguePokemon::query()->where('league_id', $league->id)->exists();

        if ($hasRows && ! $confirmReplace) {
            throw new InvalidArgumentException('This league already has a Pokémon pool. Confirm replacement to continue.');
        }

        if ($hasRows) {
            $verdict = $this->replaceEvaluator->evaluate($league);
            if (! $verdict['allowed']) {
                throw new InvalidArgumentException($verdict['reason'] ?? 'Cannot replace this pool.');
            }
        }

        DB::transaction(function () use ($league, $template, $hasRows): void {
            if ($hasRows) {
                LeaguePokemon::query()->where('league_id', $league->id)->delete();
            }

            $template->loadMissing('rows');

            /** @var array<int, array{pokedex_id: int, name: string, cost: int}> $poolRowsByPokedexId */
            $poolRowsByPokedexId = [];

            foreach ($template->rows as $row) {
                $source = Pokedex::query()->find($row->pokedex_id);
                if ($source === null) {
                    continue;
                }

                $pokemon = $this->pokedexResolver->resolvePokedex($source);
                if ($pokemon === null) {
                    continue;
                }

                $existing = $poolRowsByPokedexId[(int) $pokemon->id] ?? null;
                if ($existing === null || $row->cost > $existing['cost']) {
                    $poolRowsByPokedexId[(int) $pokemon->id] = [
                        'pokedex_id' => (int) $pokemon->id,
                        'name' => (string) $pokemon->name,
                        'cost' => (int) $row->cost,
                    ];
                }
            }

            foreach ($poolRowsByPokedexId as $poolRow) {
                LeaguePokemon::query()->create([
                    'league_id' => $league->id,
                    'pokedex_id' => $poolRow['pokedex_id'],
                    'name' => $poolRow['name'],
                    'cost' => $poolRow['cost'],
                ]);
            }
        });
    }
}
