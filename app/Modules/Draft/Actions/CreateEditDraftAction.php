<?php

namespace App\Modules\Draft\Actions;

use App\Modules\Draft\Models\Draft;

class CreateEditDraftAction
{
    public function __invoke($data)
    {
        // Create Draft
        if ($data['command'] == 'create') {
            $draft = Draft::create([
                'league_id' => $data['league_id'],
                'round_number' => $data['round_number'],
                'status' => $data['status'],
            ]);

            return $draft;
        }
        // Next Round
        elseif ($data['command'] == 'next_round') {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->round_number++;
            $draft->save();
        }
        // End Draft
        elseif ($data['command'] == 'end_draft') {
            $draft = Draft::where('league_id', $data['league_id'])->first();
            $draft->status = 0;
            $draft->save();
        }

        return $draft;
    }
}
