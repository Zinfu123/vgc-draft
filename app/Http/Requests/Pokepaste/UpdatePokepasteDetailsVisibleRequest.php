<?php

namespace App\Http\Requests\Pokepaste;

use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Services\AuthorizePokepasteEditor;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePokepasteDetailsVisibleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'details_visible' => $this->boolean('details_visible'),
        ]);
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
        ];
    }
}
