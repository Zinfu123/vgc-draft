<?php

namespace App\Http\Requests\Pokepaste;

use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use Illuminate\Foundation\Http\FormRequest;

class ParseShowdownPasteRequest extends FormRequest
{
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

        $pokepaste->loadMissing(['team', 'set']);

        return $pokepaste->team !== null
            && (int) $pokepaste->team->user_id === (int) $this->user()->id
            && $pokepaste->set !== null
            && in_array((int) $pokepaste->team_id, [(int) $pokepaste->set->team1_id, (int) $pokepaste->set->team2_id], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paste' => ['required', 'string', 'max:65535'],
        ];
    }
}
