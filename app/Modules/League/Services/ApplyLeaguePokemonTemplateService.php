<?php

namespace App\Modules\League\Services;

use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\League\Models\LeaguePokemonTemplate;
use App\Modules\Pokedex\Models\Pokedex;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplyLeaguePokemonTemplateService
{
    public function __construct(
        private LeaguePokemonPoolReplaceEvaluator $replaceEvaluator,
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
            $pokedexIds = $template->rows->pluck('pokedex_id')->unique()->all();
            $names = Pokedex::query()->whereIn('id', $pokedexIds)->pluck('name', 'id');

            foreach ($template->rows as $row) {
                $name = $names[$row->pokedex_id] ?? 'Unknown';
                LeaguePokemon::query()->create([
                    'league_id' => $league->id,
                    'pokedex_id' => $row->pokedex_id,
                    'name' => $name,
                    'cost' => $row->cost,
                ]);
            }
        });
    }
}
