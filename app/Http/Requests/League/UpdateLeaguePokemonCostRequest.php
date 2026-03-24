<?php

namespace App\Http\Requests\League;

use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaguePokemonCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $league = $this->route('league');
        $leaguePokemon = $this->route('leaguePokemon');

        if ($user === null || ! $league instanceof League || ! $leaguePokemon instanceof LeaguePokemon) {
            return false;
        }

        if ((int) $leaguePokemon->league_id !== (int) $league->id) {
            return false;
        }

        return $user->can('admin', $league);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cost' => ['required', 'integer', 'min:0', 'max:999999'],
        ];
    }
}
