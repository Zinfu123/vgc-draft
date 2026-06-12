<?php

namespace App\Http\Requests\Playoff;

use App\Http\Requests\Concerns\ResolvesRouteLeague;
use Illuminate\Foundation\Http\FormRequest;

class GeneratePlayoffBracketRequest extends FormRequest
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
        return [];
    }
}
