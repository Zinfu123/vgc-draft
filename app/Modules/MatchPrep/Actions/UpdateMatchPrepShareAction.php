<?php

namespace App\Modules\MatchPrep\Actions;

use App\Models\User;
use App\Modules\Matches\Models\Set;
use App\Modules\MatchPrep\Models\MatchPrepNote;
use Illuminate\Support\Str;

class UpdateMatchPrepShareAction
{
    public function __construct(
        private UpsertMatchPrepNoteAction $assertParticipation,
    ) {}

    /**
     * @return array{note: MatchPrepNote, share_url: string|null}
     */
    public function __invoke(User $user, Set $set, bool $shareEnabled, bool $regenerateUuid): array
    {
        $this->assertParticipation->assertUserParticipatesInSet($user, $set);

        $note = MatchPrepNote::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'set_id' => $set->id,
            ],
            [
                'bring_six_slots' => MatchPrepNote::defaultBringSix(),
                'plan_1_slots' => MatchPrepNote::defaultPlanSlots(),
                'plan_2_slots' => MatchPrepNote::defaultPlanSlots(),
                'plan_3_slots' => MatchPrepNote::defaultPlanSlots(),
                'calcs' => [],
                'share_enabled' => false,
            ],
        );

        $note->share_enabled = $shareEnabled;

        if ($shareEnabled) {
            if ($note->share_uuid === null || $regenerateUuid) {
                $note->share_uuid = (string) Str::uuid();
            }
        }

        $note->save();

        $shareUrl = $shareEnabled && $note->share_uuid !== null
            ? url('/match-prep/share/'.$note->share_uuid)
            : null;

        return [
            'note' => $note->fresh(),
            'share_url' => $shareUrl,
        ];
    }
}
