<?php

namespace App\Http\Requests\TeamCoverage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TeamCoveragePokedexSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'type1' => ['sometimes', 'nullable', 'string', 'max:30'],
            'type2' => ['sometimes', 'nullable', 'string', 'max:30'],
            'generation' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ];
    }
}
