<?php

namespace App\Http\Requests\MatchPrep;

use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchPrepShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $set = $this->route('set');

        if ($user === null || ! $set instanceof Set) {
            return false;
        }

        return Team::query()
            ->where('user_id', $user->id)
            ->where('league_id', $set->league_id)
            ->whereIn('id', [(int) $set->team1_id, (int) $set->team2_id])
            ->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'share_enabled' => ['required', 'boolean'],
            'regenerate_uuid' => ['sometimes', 'boolean'],
        ];
    }
}
