<?php

namespace App\Http\Requests\Match;

use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportSetReplayTeamsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var int|string|null $setId */
        $setId = $this->input('set_id');
        if ($setId === null || $setId === '') {
            return false;
        }

        $set = Set::query()->find($setId);
        if ($set === null) {
            return false;
        }

        $userId = $this->user()?->id;
        if ($userId === null) {
            return false;
        }

        $team = Team::query()
            ->where('user_id', $userId)
            ->where('league_id', $set->league_id)
            ->first();

        if ($team === null) {
            return false;
        }

        return (int) $team->id === (int) $set->team1_id || (int) $team->id === (int) $set->team2_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'set_id' => ['required', 'integer', 'exists:sets,id'],
            'replay_slot' => ['required', 'integer', 'in:1,2,3'],
            'p1_team_id' => ['required', 'integer', 'exists:teams,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var int|string|null $setId */
            $setId = $this->input('set_id');
            if ($setId === null || $setId === '') {
                return;
            }

            $set = Set::query()->find($setId);
            if ($set === null) {
                return;
            }

            $p1TeamId = (int) $this->input('p1_team_id');
            if ($p1TeamId !== (int) $set->team1_id && $p1TeamId !== (int) $set->team2_id) {
                $validator->errors()->add('p1_team_id', 'Player 1 must be one of the teams in this match.');
            }

            $slot = (int) $this->input('replay_slot');
            $url = match ($slot) {
                1 => $set->replay1,
                2 => $set->replay2,
                3 => $set->replay3,
                default => null,
            };

            if ($url === null || trim((string) $url) === '') {
                $validator->errors()->add('replay_slot', 'No replay URL saved for this game. Save a replay link first.');
            }
        });
    }
}
