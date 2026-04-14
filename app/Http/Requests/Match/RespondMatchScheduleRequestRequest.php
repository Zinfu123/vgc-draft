<?php

namespace App\Http\Requests\Match;

use App\Modules\Matches\Enums\ScheduleRequestStatus;
use App\Modules\Matches\Models\MatchScheduleRequest;
use App\Modules\Teams\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class RespondMatchScheduleRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        /** @var MatchScheduleRequest|null $scheduleRequest */
        $scheduleRequest = MatchScheduleRequest::query()
            ->with('set')
            ->find((int) $this->route('scheduleRequest'));

        if ($scheduleRequest === null || $scheduleRequest->status !== ScheduleRequestStatus::Pending) {
            return false;
        }

        $set = $scheduleRequest->set;
        if ($set === null) {
            return false;
        }

        $action = $this->input('action');

        // Cancel is only for the proposer
        if ($action === 'cancel') {
            return $scheduleRequest->proposed_by_user_id === $user->id;
        }

        // Accept/decline/reschedule: must be the OTHER participant
        $userTeam = Team::query()
            ->where('user_id', $user->id)
            ->where('league_id', $set->league_id)
            ->whereIn('id', [$set->team1_id, $set->team2_id])
            ->first();

        if ($userTeam === null) {
            return false;
        }

        return $scheduleRequest->proposed_by_user_id !== $user->id;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $baseRules = [
            'action' => ['required', 'string', 'in:accept,decline,reschedule,cancel'],
        ];

        if ($this->input('action') === 'reschedule') {
            $baseRules['proposed_at'] = ['required', 'date', 'after:now'];
        }

        return $baseRules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proposed_at.required' => 'Please select a new date and time for rescheduling.',
            'proposed_at.after' => 'The proposed time must be in the future.',
        ];
    }
}
