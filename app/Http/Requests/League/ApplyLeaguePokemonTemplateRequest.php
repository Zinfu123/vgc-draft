<?php

namespace App\Http\Requests\League;

use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use Illuminate\Foundation\Http\FormRequest;

class ApplyLeaguePokemonTemplateRequest extends FormRequest
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
            'confirm_replace' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var League $league */
            $league = $this->route('league');
            $hasPool = LeaguePokemon::query()->where('league_id', $league->id)->exists();
            if ($hasPool && ! $this->boolean('confirm_replace')) {
                $validator->errors()->add(
                    'confirm_replace',
                    'This league already has a Pokémon pool. You must confirm replacement to overwrite it.'
                );
            }
        });
    }
}
