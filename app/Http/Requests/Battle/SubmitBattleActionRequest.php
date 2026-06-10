<?php

namespace App\Http\Requests\Battle;

use Illuminate\Foundation\Http\FormRequest;

class SubmitBattleActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accepts PS action strings: "move 1", "move 2 mega", "switch 3", "team 1 2 3 4", "pass"
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'regex:/^(move [1-4]( mega| zmove| ultra| dynamax| terastallize)?|switch [1-6]|team( [1-6]){1,6}|pass)$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'An action is required.',
            'action.regex' => 'Invalid action format.',
        ];
    }
}
