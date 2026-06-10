<?php

namespace App\Http\Requests\Draft;

use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageDraftTimerRequest extends FormRequest
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
}
