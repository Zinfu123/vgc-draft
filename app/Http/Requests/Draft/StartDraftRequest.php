<?php

namespace App\Http\Requests\Draft;

use App\Modules\Draft\Models\Draft;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StartDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $leagueId = (int) $this->input('league_id');
        $league = League::query()->find($leagueId);
        if ($league === null) {
            return false;
        }

        if ((int) $league->league_owner === (int) $user->id) {
            return true;
        }

        return Team::query()
            ->where('league_id', $leagueId)
            ->where('user_id', $user->id)
            ->where('admin_flag', 1)
            ->whereNull('dropped_at')
            ->exists();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'league_id' => ['required', 'integer', Rule::exists('leagues', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $leagueId = (int) $this->input('league_id');

            if (Draft::query()->where('league_id', $leagueId)->exists()) {
                $validator->errors()->add('league_id', 'A draft has already been started for this league.');

                return;
            }

            $hasTeams = Team::query()
                ->where('league_id', $leagueId)
                ->whereNull('dropped_at')
                ->exists();

            if (! $hasTeams) {
                $validator->errors()->add('league_id', 'Add at least one team before starting the draft.');
            }
        });
    }
}
