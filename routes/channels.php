<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('set_updated.{set_id}', function () {
    return true;
});

Broadcast::channel('draft.detail.{league_id}', function () {
    //
    return true;
});

Broadcast::channel('end.draft.{draft_id}', function () {
    return true;
});

Broadcast::channel('match.submission.{match_id}', function () {
    return true;
});
