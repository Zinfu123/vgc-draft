<?php

namespace App\Http\Requests\Pokepaste;

use App\Enums\PokemonNature;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Services\AuthorizePokepasteEditor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamPokepasteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'details_visible' => $this->boolean('details_visible'),
        ]);

        $slots = $this->input('slots');
        if (! is_array($slots)) {
            return;
        }

        foreach ($slots as $i => $slot) {
            if (! is_array($slot)) {
                continue;
            }
            $id = $slot['league_pokemon_id'] ?? null;
            if ($id === '' || $id === 0 || $id === '0') {
                $slots[$i]['league_pokemon_id'] = null;
            }
        }

        $this->merge(['slots' => $slots]);
    }

    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        /** @var SetTeamPokepaste|null $pokepaste */
        $pokepaste = $this->route('pokepaste');
        if (! $pokepaste instanceof SetTeamPokepaste) {
            return false;
        }

        return app(AuthorizePokepasteEditor::class)->userMayEdit($pokepaste, $this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'details_visible' => ['required', 'boolean'],
            'slots' => ['required', 'array', 'size:6'],
            'slots.*.league_pokemon_id' => ['nullable', 'integer', Rule::exists('league_pokemon', 'id')],
            'slots.*.ability' => ['nullable', 'string', 'max:120'],
            'slots.*.moves' => ['required', 'array', 'size:4'],
            'slots.*.moves.*' => ['nullable', 'string', 'max:120'],
            'slots.*.version_group_held_item_id' => ['nullable', 'integer'],
            'slots.*.nature' => ['nullable', Rule::enum(PokemonNature::class)],
            'slots.*.tera_type' => ['nullable', 'string', 'max:40'],
            'slots.*.evs' => ['nullable', 'array'],
            'slots.*.evs.hp' => ['nullable', 'integer', 'min:0', 'max:252'],
            'slots.*.evs.atk' => ['nullable', 'integer', 'min:0', 'max:252'],
            'slots.*.evs.def' => ['nullable', 'integer', 'min:0', 'max:252'],
            'slots.*.evs.spa' => ['nullable', 'integer', 'min:0', 'max:252'],
            'slots.*.evs.spd' => ['nullable', 'integer', 'min:0', 'max:252'],
            'slots.*.evs.spe' => ['nullable', 'integer', 'min:0', 'max:252'],
        ];
    }
}
