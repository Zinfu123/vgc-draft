<?php

namespace App\Http\Requests\Match;

use App\Modules\Matches\Models\Set;
use App\Modules\Pokepaste\Services\SetReplayUrlDuplicateChecker;
use App\Modules\Teams\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSetReplaysRequest extends FormRequest
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
            'replay1' => ['nullable', 'url', 'max:500'],
            'replay2' => ['nullable', 'url', 'max:500'],
            'replay3' => ['nullable', 'url', 'max:500'],
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

            $messages = app(SetReplayUrlDuplicateChecker::class)->validateSubmission(
                $set,
                [
                    $this->input('replay1'),
                    $this->input('replay2'),
                    $this->input('replay3'),
                ]
            );

            foreach ($messages as $message) {
                $validator->errors()->add('replay1', $message);
            }
        });
    }
}
