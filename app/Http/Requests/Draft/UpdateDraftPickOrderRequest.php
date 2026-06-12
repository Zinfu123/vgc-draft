<?php

namespace App\Http\Requests\Draft;

use App\Http\Requests\Concerns\ResolvesRouteLeague;
use App\Modules\Draft\Models\Draft;
use App\Modules\Teams\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDraftPickOrderRequest extends FormRequest
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
            'team_ids' => ['required', 'array'],
            'team_ids.*' => [
                'integer',
                Rule::exists('teams', 'id')
                    ->where('league_id', $league->id)
                    ->whereNull('dropped_at'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $league = $this->routeLeague();

            if (Draft::where('league_id', $league->id)->exists()) {
                $validator->errors()->add('team_ids', 'Pick order cannot be changed while a draft exists for this league.');

                return;
            }

            /** @var list<int|string> $rawIds */
            $rawIds = $this->input('team_ids', []);
            $ids = array_map(fn ($id) => (int) $id, $rawIds);

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('team_ids', 'Each team may only appear once in the pick order.');

                return;
            }

            $expectedIds = Team::query()
                ->where('league_id', $league->id)
                ->whereNull('dropped_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $sortedInput = $ids;
            sort($sortedInput);
            $sortedExpected = $expectedIds;
            sort($sortedExpected);

            if ($sortedInput !== $sortedExpected) {
                $validator->errors()->add('team_ids', 'Team list must include each league team exactly once.');
            }
        });
    }
}
