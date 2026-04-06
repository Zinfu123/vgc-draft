<?php

namespace App\Http\Requests\Match;

use App\Modules\Matches\Models\Set;
use Illuminate\Foundation\Http\FormRequest;

class SendMatchMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'A message is required.',
            'body.max' => 'Messages may not exceed 1000 characters.',
        ];
    }
}
