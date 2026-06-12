<?php

namespace App\Http\Requests\Playoff;

use App\Enums\Playoffs\PlayoffFormat;
use App\Http\Requests\Concerns\ResolvesRouteLeague;
use App\Modules\Playoffs\Services\PlayoffBracketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlayoffConfigRequest extends FormRequest
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
            'format' => ['required', Rule::enum(PlayoffFormat::class)],
            'bracket_size' => ['required', 'integer', Rule::in(PlayoffBracketService::allowedBracketSizes())],
            'seed_order' => ['required', 'array', 'min:2'],
            'seed_order.*' => [
                'integer',
                Rule::exists('teams', 'id')->where('league_id', $league->id),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var list<int> $order */
            $order = $this->input('seed_order', []);
            if (count($order) !== count(array_unique($order))) {
                $validator->errors()->add('seed_order', 'Each team may only appear once in the seed order.');
            }
        });
    }
}
