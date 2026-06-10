<?php

namespace App\Http\Requests\TeamCoverage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TeamCoverageLearnsetRequest extends FormRequest
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
            'game' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
