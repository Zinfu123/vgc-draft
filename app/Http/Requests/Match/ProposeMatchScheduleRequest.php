<?php

namespace App\Http\Requests\Match;

use App\Modules\Matches\Models\Set;
use Illuminate\Foundation\Http\FormRequest;

class ProposeMatchScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $set = Set::query()->find((int) $this->route('set'));
        if ($set === null) {
            return false;
        }

        return $user->teams()->where('league_id', $set->league_id)->exists();
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
            'proposed_at.required' => 'A proposed time is required.',
            'proposed_at.after' => 'The proposed time must be in the future.',
        ];
    }
}
