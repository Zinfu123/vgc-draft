<?php

namespace App\Http\Requests\DamageCalculator;

use Illuminate\Foundation\Http\FormRequest;

class DamageCalculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ev = ['nullable', 'integer', 'min:0', 'max:252'];
        $iv = ['nullable', 'integer', 'min:0', 'max:31'];

        return [
            'version_group_slug' => ['required', 'string', 'max:64'],
            'move_id' => ['required', 'integer', 'min:1'],
            'attacker' => ['required', 'array'],
            'attacker.pokedex_id' => ['required', 'integer', 'exists:pokedex,id'],
            'attacker.level' => ['required', 'integer', 'min:1', 'max:100'],
            'attacker.nature' => ['required', 'integer', 'min:0', 'max:24'],
            'attacker.tera_type' => ['nullable', 'string', 'max:32'],
            'attacker.terastallized' => ['sometimes', 'boolean'],
            'attacker.burned' => ['sometimes', 'boolean'],
            'attacker.item' => ['nullable', 'string', 'max:32'],
            'attacker.ev.hp' => $ev,
            'attacker.ev.atk' => $ev,
            'attacker.ev.def' => $ev,
            'attacker.ev.spa' => $ev,
            'attacker.ev.spd' => $ev,
            'attacker.ev.spe' => $ev,
            'attacker.iv.hp' => $iv,
            'attacker.iv.atk' => $iv,
            'attacker.iv.def' => $iv,
            'attacker.iv.spa' => $iv,
            'attacker.iv.spd' => $iv,
            'attacker.iv.spe' => $iv,
            'defender' => ['required', 'array'],
            'defender.pokedex_id' => ['required', 'integer', 'exists:pokedex,id'],
            'defender.level' => ['required', 'integer', 'min:1', 'max:100'],
            'defender.nature' => ['required', 'integer', 'min:0', 'max:24'],
            'defender.tera_type' => ['nullable', 'string', 'max:32'],
            'defender.terastallized' => ['sometimes', 'boolean'],
            'defender.burned' => ['sometimes', 'boolean'],
            'defender.item' => ['nullable', 'string', 'max:32'],
            'defender.ev.hp' => $ev,
            'defender.ev.atk' => $ev,
            'defender.ev.def' => $ev,
            'defender.ev.spa' => $ev,
            'defender.ev.spd' => $ev,
            'defender.ev.spe' => $ev,
            'defender.iv.hp' => $iv,
            'defender.iv.atk' => $iv,
            'defender.iv.def' => $iv,
            'defender.iv.spa' => $iv,
            'defender.iv.spd' => $iv,
            'defender.iv.spe' => $iv,
        ];
    }
}
