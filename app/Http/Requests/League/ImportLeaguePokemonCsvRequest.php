<?php

namespace App\Http\Requests\League;

use App\Modules\League\Models\League;
use Illuminate\Foundation\Http\FormRequest;

class ImportLeaguePokemonCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $league = $this->route('league');
        if ($league instanceof League) {
            return $user->can('admin', $league);
        }

        $leagueId = (int) $this->input('league_id');
        $league = League::query()->find($leagueId);

        return $league !== null && $user->can('admin', $league);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];

        if (! $this->route('league') instanceof League) {
            $rules['league_id'] = ['required', 'integer', 'exists:leagues,id'];
        }

        return $rules;
    }
}
