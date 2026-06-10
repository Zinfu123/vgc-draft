<?php

namespace App\Http\Requests\Battle;

use Illuminate\Foundation\Http\FormRequest;

class SubmitBattleTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'packed_team' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'packed_team.required' => 'A packed team string is required.',
            'packed_team.max' => 'The team string is too long.',
        ];
    }
}
