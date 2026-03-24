<?php

namespace App\Http\Requests\League;

use App\Modules\League\Models\League;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaguePokemonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $league = $this->route('league');

        return $user !== null && $league instanceof League && $user->can('admin', $league);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pokedex_id' => ['required', 'integer', Rule::exists('pokedex', 'id')],
            'cost' => ['required', 'integer', 'min:0', 'max:999999'],
        ];
    }
}
