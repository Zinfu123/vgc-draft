<?php

namespace App\Http\Requests\Match;

use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Matches\Models\Set;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondToMatchScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $scheduleRequest = MatchScheduleRequest::query()->find((int) $this->route('scheduleRequest'));
        if ($scheduleRequest === null) {
            return false;
        }

        if ($scheduleRequest->proposed_by_user_id === $user->id) {
            return false;
        }

        $set = Set::query()->find($scheduleRequest->set_id);
        if ($set === null) {
            return false;
        }

        return $user->teams()->where('league_id', $set->league_id)->exists();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['accepted', 'declined'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'A response is required.',
            'status.in' => 'Response must be accepted or declined.',
        ];
    }
}
