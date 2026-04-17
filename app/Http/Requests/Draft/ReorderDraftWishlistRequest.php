<?php

namespace App\Http\Requests\Draft;

use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftWishlistItem;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderDraftWishlistRequest extends FormRequest
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
            'league_pokemon_ids' => ['required', 'array', 'min:1'],
            'league_pokemon_ids.*' => ['integer', 'distinct'],
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
            /** @var array<int, int|string> $rawIds */
            $rawIds = $this->input('league_pokemon_ids', []);
            $ids = array_map(intval(...), $rawIds);

            $team = Team::query()
                ->where('user_id', $user->id)
                ->where('league_id', $leagueId)
                ->whereNull('dropped_at')
                ->first();

            if ($team === null) {
                $validator->errors()->add('league_id', 'Team not found for this user and league.');

                return;
            }

            $validInLeagueCount = LeaguePokemon::query()
                ->where('league_id', $leagueId)
                ->whereIn('id', $ids)
                ->count();

            if ($validInLeagueCount !== count($ids)) {
                $validator->errors()->add('league_pokemon_ids', 'One or more Pokémon are not in this league pool.');

                return;
            }

            $draft = Draft::query()->where('league_id', $leagueId)->first();
            if ($draft !== null && (int) $draft->status === 0) {
                $validator->errors()->add('league_id', 'The draft has ended; wishlist changes are not allowed.');

                return;
            }

            $teamWishlistIds = DraftWishlistItem::query()
                ->where('team_id', $team->id)
                ->pluck('league_pokemon_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $sortedTeamIds = $teamWishlistIds;
            sort($sortedTeamIds);
            $sortedIncoming = $ids;
            sort($sortedIncoming);

            if ($sortedTeamIds !== $sortedIncoming) {
                $validator->errors()->add('league_pokemon_ids', 'The list must match your current wishlist.');

                return;
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

    /**
     * @return array<int, int>
     */
    public function orderedLeaguePokemonIds(): array
    {
        /** @var array<int, int|string> $raw */
        $raw = $this->validated('league_pokemon_ids');

        return array_map(intval(...), $raw);
    }
}
