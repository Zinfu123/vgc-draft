<?php

namespace App\Http\Requests\Draft;

use App\Modules\League\Models\League;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $league = $this->route('league');

        return $user !== null && $league instanceof League && $user->can('admin', $league);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ban_enabled' => $this->boolean('ban_enabled'),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'draft_date' => ['nullable', 'date'],
            'draft_points' => ['required', 'integer', 'min:1'],
            'minimum_drafts' => ['required', 'integer', 'min:0'],
            'ban_enabled' => ['boolean'],
            'bans_per_user' => ['required_if:ban_enabled,true', 'nullable', 'integer', 'min:1'],
            'minimum_cost_to_ban' => ['required_if:ban_enabled,true', 'nullable', 'integer', 'min:0'],
        ];
    }
}
