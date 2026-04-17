<?php

namespace App\Http\Requests\Draft;

use App\Modules\Draft\Models\Draft;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToggleDraftWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'league_id' => ['required', 'integer', Rule::exists('leagues', 'id')],
            'league_pokemon_id' => ['required', 'integer', Rule::exists('league_pokemon', 'id')],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            if ($user === null) {
                return;
            }

            $leagueId = (int) $this->input('league_id');
            $leaguePokemonId = (int) $this->input('league_pokemon_id');

            $team = Team::query()
                ->where('user_id', $user->id)
                ->where('league_id', $leagueId)
                ->whereNull('dropped_at')
                ->first();

            if ($team === null) {
                $validator->errors()->add('league_id', 'Team not found for this user and league.');

                return;
            }

            $leaguePokemon = LeaguePokemon::query()->find($leaguePokemonId);
            if ($leaguePokemon === null || (int) $leaguePokemon->league_id !== $leagueId) {
                $validator->errors()->add('league_pokemon_id', 'This Pokémon is not in this league pool.');

                return;
            }

            $draft = Draft::query()->where('league_id', $leagueId)->first();
            if ($draft !== null && (int) $draft->status === 0) {
                $validator->errors()->add('league_id', 'The draft has ended; wishlist changes are not allowed.');
            }
        });
    }

    public function team(): Team
    {
        /** @var Team $team */
        $team = Team::query()
            ->where('user_id', $this->user()->id)
            ->where('league_id', $this->validated('league_id'))
            ->whereNull('dropped_at')
            ->firstOrFail();

        return $team;
    }
}
