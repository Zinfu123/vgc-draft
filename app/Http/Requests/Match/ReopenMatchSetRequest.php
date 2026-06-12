<?php

namespace App\Http\Requests\Match;

use App\Http\Requests\Concerns\ResolvesRouteLeague;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReopenMatchSetRequest extends FormRequest
{
    use ResolvesRouteLeague;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('admin', $this->routeLeague());
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $league = $this->routeLeague();

        return [
            'match_reference' => 'required|string|max:2000',
            'set_id' => [
                'required',
                'integer',
                Rule::exists('sets', 'id')->where(fn ($query) => $query
                    ->where('league_id', $league->id)
                    ->where('status', 0)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'set_id.required' => 'Could not find a set ID. Paste a match link (for example …/match/set/12) or the numeric set ID.',
            'set_id.exists' => 'That completed match was not found in this league.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $reference = $this->input('match_reference');
        if (! is_string($reference)) {
            return;
        }

        $setId = self::parseSetIdFromMatchReference(trim($reference));
        if ($setId !== null) {
            $this->merge(['set_id' => $setId]);
        }
    }

    public static function parseSetIdFromMatchReference(string $reference): ?int
    {
        $trimmed = trim($reference);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $trimmed)) {
            return (int) $trimmed;
        }

        if (preg_match('#/match/set/(\d+)#', $trimmed, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('#/set/(\d+)#', $trimmed, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
