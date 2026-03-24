<?php

namespace App\Modules\Pokepaste\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pokepaste\ParseShowdownPasteRequest;
use App\Http\Requests\Pokepaste\UpdateTeamPokepasteRequest;
use App\Modules\League\Models\League;
use App\Modules\Pokepaste\Actions\ParseShowdownPasteAction;
use App\Modules\Pokepaste\Actions\ReadPokepastePageAction;
use App\Modules\Pokepaste\Actions\UpdateSetTeamPokepasteAction;
use App\Modules\Pokepaste\Models\SetTeamPokepaste;
use App\Modules\Pokepaste\Services\EnsureSetTeamPokepasteSlotRows;
use App\Modules\Pokepaste\Support\PokepasteSlotDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PokepasteController extends Controller
{
    public function __construct(
        private EnsureSetTeamPokepasteSlotRows $ensureSlotRows,
    ) {}

    public function show(
        Request $request,
        SetTeamPokepaste $pokepaste,
        ReadPokepastePageAction $readPokepastePageAction,
    ): Response {
        $pokepaste->loadMissing(['team', 'set.league']);
        abort_if(
            $pokepaste->team === null
            || $pokepaste->set === null
            || $pokepaste->set->league === null,
            404
        );
        abort_unless(
            in_array((int) $pokepaste->team_id, [(int) $pokepaste->set->team1_id, (int) $pokepaste->set->team2_id], true),
            404
        );

        $user = $request->user();
        $isOwner = $user !== null
            && (int) $pokepaste->team->user_id === (int) $user->id;

        $hasData = $this->pokepasteHasSlotData($pokepaste);

        if ($isOwner) {
            if ($request->boolean('view')) {
                $editMode = false;
            } elseif ($request->boolean('edit')) {
                $editMode = true;
            } else {
                $editMode = ! $hasData;
            }
        } else {
            $editMode = false;
        }

        $pageData = $readPokepastePageAction($pokepaste);

        if (! $isOwner) {
            $pageData = [
                'set' => $pageData['set'],
                'league' => $pageData['league'],
                'team' => $pageData['team'],
                'view_cards' => $pageData['view_cards'],
                'roster' => [],
                'slots' => PokepasteSlotDefaults::sixEmptySlots(),
                'held_items' => [],
                'all_tera_types' => [],
                'natures' => [],
            ];
        }

        return Inertia::render('pokepaste/PokepasteShow', array_merge($pageData, [
            'pokepaste_public_id' => (string) $pokepaste->public_id,
            'is_owner' => $isOwner,
            'edit_mode' => $editMode,
            'paste_has_data' => $hasData,
        ]));
    }

    public function update(
        UpdateTeamPokepasteRequest $request,
        SetTeamPokepaste $pokepaste,
        UpdateSetTeamPokepasteAction $updateSetTeamPokepasteAction,
    ) {
        $pokepaste->loadMissing('set');
        $league = League::query()->findOrFail($pokepaste->set->league_id);

        return $updateSetTeamPokepasteAction($pokepaste, $league, $request->validated('slots'));
    }

    public function parse(
        ParseShowdownPasteRequest $request,
        SetTeamPokepaste $pokepaste,
        ParseShowdownPasteAction $parseShowdownPasteAction,
    ): JsonResponse {
        return $parseShowdownPasteAction($pokepaste, $request->validated('paste'));
    }

    private function pokepasteHasSlotData(SetTeamPokepaste $pokepaste): bool
    {
        ($this->ensureSlotRows)($pokepaste);

        return $pokepaste->pasteSlots()->whereNotNull('league_pokemon_id')->exists();
    }
}
