<?php

namespace App\Http\Requests\Match;

use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewSetReplayPlayersRequest extends FormRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $set = Set::query()->find((int) $this->input('set_id'));
            if ($set === null) {
                return;
            }

            $slot = (int) $this->input('replay_slot');
            $url = match ($slot) {
                1 => $set->replay1,
                2 => $set->replay2,
                3 => $set->replay3,
                default => null,
            };

            if ($url === null || trim((string) $url) === '') {
                $validator->errors()->add('replay_slot', 'No replay URL saved for this game.');
            }
        });
    }
}
