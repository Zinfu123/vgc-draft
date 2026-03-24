<?php

namespace App\Http\Requests\League;

use App\Modules\League\Models\League;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $league = $this->route('league');

        return $user !== null && $league instanceof League && $user->can('own', $league);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var League $league */
        $league = $this->route('league');

        return [
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where('league_id', $league->id),
            ],
            'admin_flag' => ['required', 'boolean'],
        ];
    }
}
