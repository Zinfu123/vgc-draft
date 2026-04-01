<?php

namespace App\Http\Requests\TeamCoverage;

use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TeamCoverageTeamRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $this->route('team');
        if ($user === null || ! $team instanceof Team) {
            return false;
        }

        return (int) $team->user_id === (int) $user->id
            && $team->dropped_at === null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
