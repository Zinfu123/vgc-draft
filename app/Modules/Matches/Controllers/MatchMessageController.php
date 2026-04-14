<?php

namespace App\Modules\Matches\Controllers;

use App\Events\MatchMessageSentEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Match\SendMatchMessageRequest;
use App\Modules\Matches\Models\MatchMessage;
use App\Modules\Matches\Models\Set;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MatchMessageController extends Controller
{
    public function index(int $set): JsonResponse
    {
        $setModel = Set::query()->findOrFail($set);

        $messages = MatchMessage::query()
            ->where('set_id', $setModel->id)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (MatchMessage $message) => [
                'id' => $message->id,
                'set_id' => $message->set_id,
                'user_id' => $message->user_id,
                'user_name' => $message->user->name,
                'body' => $message->body,
                'is_read' => $message->is_read,
                'created_at' => $message->created_at?->toISOString(),
            ]);

        return response()->json($messages);
    }

    public function store(SendMatchMessageRequest $request, int $set): RedirectResponse
    {
        $setModel = Set::query()->findOrFail($set);

        $message = MatchMessage::query()->create([
            'set_id' => $setModel->id,
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'is_read' => false,
        ]);

        $message->load('user:id,name');

        MatchMessageSentEvent::dispatch([
            'id' => $message->id,
            'set_id' => $message->set_id,
            'user_id' => $message->user_id,
            'user_name' => $message->user->name,
            'body' => $message->body,
            'is_read' => false,
            'created_at' => $message->created_at?->toISOString(),
        ]);

        return redirect()->back();
    }

    public function markRead(Request $request, int $set): JsonResponse
    {
        $setModel = Set::query()->findOrFail($set);

        MatchMessage::query()
            ->where('set_id', $setModel->id)
            ->where('user_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }
}
