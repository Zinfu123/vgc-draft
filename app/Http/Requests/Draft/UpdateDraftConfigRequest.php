<?php

namespace App\Http\Requests\Draft;

use App\Http\Requests\Concerns\ResolvesRouteLeague;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftConfigRequest extends FormRequest
{
    use ResolvesRouteLeague;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('admin', $this->routeLeague());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ban_enabled' => $this->boolean('ban_enabled'),
            'pick_timer_enabled' => $this->boolean('pick_timer_enabled'),
            'quiet_hours_enabled' => $this->boolean('quiet_hours_enabled'),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'draft_date' => ['nullable', 'date'],
            'draft_start_at' => ['nullable', 'date'],
            'draft_points' => ['required', 'integer', 'min:1'],
            'minimum_drafts' => ['required', 'integer', 'min:0'],
            'ban_enabled' => ['boolean'],
            'bans_per_user' => ['required_if:ban_enabled,true', 'nullable', 'integer', 'min:1'],
            'minimum_cost_to_ban' => ['required_if:ban_enabled,true', 'nullable', 'integer', 'min:0'],
            'pick_timer_enabled' => ['boolean'],
            'pick_timer_seconds' => ['required_if:pick_timer_enabled,true', 'nullable', 'integer', 'min:60', 'max:604800'],
            'quiet_hours_enabled' => ['boolean'],
            'quiet_hours_start' => ['required_if:quiet_hours_enabled,true', 'nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['required_if:quiet_hours_enabled,true', 'nullable', 'date_format:H:i'],
            'quiet_hours_timezone' => ['required_if:quiet_hours_enabled,true', 'nullable', 'timezone'],
        ];
    }
}
