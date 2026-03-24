<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->input('command') === 'updatePokepaste') {
            return [
                'command' => 'required|string|in:updatePokepaste',
                'set_id' => 'required|integer|exists:sets,id',
                'team1_pokepaste' => 'nullable|string|max:2000',
                'team2_pokepaste' => 'nullable|string|max:2000',
            ];
        }

        return [
            'command' => 'required|string|in:update',
            'set_id' => 'required|integer|exists:sets,id',
            'team1_id' => 'required|integer|exists:teams,id',
            'team2_id' => 'required|integer|exists:teams,id',
            'team1_score' => 'required|integer|min:0|max:2',
            'team2_score' => 'required|integer|min:0|max:2',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('command') !== 'update') {
                return;
            }

            $team1Score = (int) $this->input('team1_score');
            $team2Score = (int) $this->input('team2_score');

            if (($team1Score === 2 && $team2Score <= 1) || ($team2Score === 2 && $team1Score <= 1)) {
                return;
            }

            $validator->errors()->add(
                'set_result',
                'One team must reach 2 game wins before the set can be submitted (2-0 or 2-1).'
            );
        });
    }
}
