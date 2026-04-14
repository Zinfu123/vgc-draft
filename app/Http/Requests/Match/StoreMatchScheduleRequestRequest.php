<?php

namespace App\Http\Requests\Match;

use App\Modules\Matches\Models\Set;
use App\Modules\Teams\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class StoreMatchScheduleRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $set = Set::query()->find((int) $this->route('set'));
        if ($set === null || $set->status === 0) {
            return false;
        }

        return Team::query()
            ->where('user_id', $user->id)
            ->where('league_id', $set->league_id)
            ->whereIn('id', [$set->team1_id, $set->team2_id])
            ->exists();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proposed_at' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proposed_at.required' => 'Please select a date and time.',
            'proposed_at.after' => 'The proposed time must be in the future.',
        ];
    }
}
