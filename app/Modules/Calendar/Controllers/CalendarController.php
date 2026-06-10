<?php

namespace App\Modules\Calendar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Calendar\Actions\ReadCalendarEventsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request, ReadCalendarEventsAction $readCalendarEventsAction): Response
    {
        $events = $readCalendarEventsAction($request->user());

        return Inertia::render('calendar/CalendarIndex', [
            'draftDays' => $events['draft_days'],
            'matchWeekStarts' => $events['match_week_starts'],
            'scheduledMatches' => $events['scheduled_matches'],
        ]);
    }
}
