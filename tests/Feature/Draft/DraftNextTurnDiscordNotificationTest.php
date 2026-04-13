<?php

use App\Models\User;
use App\Modules\Draft\Actions\NotifyDraftNextTurnAction;
use App\Modules\Draft\Models\Draft;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Notifications\DraftNextTurnNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('DraftNextTurnNotification toDiscord mentions discord id and includes draft url', function () {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Test League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => $owner->id,
    ]);

    $user = User::factory()->create([
        'name' => 'Coach',
        'discord_id' => '987654321098765432',
    ]);

    $notification = new DraftNextTurnNotification($league, $user, 'pick');
    $payload = $notification->toDiscord($league);

    expect($payload['content'])->toBe('<@987654321098765432>')
        ->and($payload['embeds'][0]['description'])->toContain('It\'s your turn to pick')
        ->and($payload['embeds'][0]['description'])->toContain('/draft/'.$league->id);
});

it('DraftNextTurnNotification toDiscord uses ban copy for ban phase', function () {
    $owner = User::factory()->create();
    $league = League::create([
        'name' => 'Ban League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => $owner->id,
    ]);

    $user = User::factory()->create(['name' => 'Banner', 'discord_id' => null]);

    $notification = new DraftNextTurnNotification($league, $user, 'ban');
    $payload = $notification->toDiscord($league);

    expect($payload['content'])->toBe('Banner')
        ->and($payload['embeds'][0]['description'])->toContain('It\'s your turn to ban')
        ->and($payload['embeds'][0]['description'])->toContain('/draft/'.$league->id);
});

it('NotifyDraftNextTurnAction does nothing when league has no discord webhook', function () {
    Notification::fake();

    $league = League::create([
        'name' => 'No Webhook',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => User::factory()->create()->id,
        'discord_webhook_url' => null,
    ]);

    Draft::create([
        'league_id' => $league->id,
        'round_number' => 1,
        'status' => 1,
        'pick_number' => 1,
    ]);

    (new NotifyDraftNextTurnAction)(['league_id' => $league->id]);

    Notification::assertNothingSent();
});

it('NotifyDraftNextTurnAction does nothing when draft is finished', function () {
    Notification::fake();

    $league = League::create([
        'name' => 'Ended',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => false,
        'league_owner' => User::factory()->create()->id,
        'discord_webhook_url' => 'https://discord.com/api/webhooks/test/token',
    ]);

    Draft::create([
        'league_id' => $league->id,
        'round_number' => 1,
        'status' => 0,
        'pick_number' => 1,
    ]);

    (new NotifyDraftNextTurnAction)(['league_id' => $league->id]);

    Notification::assertNothingSent();
});

it('sends DraftNextTurnNotification after a pick when webhook is set', function () {
    Notification::fake();
    Event::fake();

    [$owner, $league, $team1, $team2, $user1, $user2] = createLeagueForDiscordTests();

    $user2->update(['discord_id' => '111222333444555666']);

    $pokedexId = DB::table('pokedex')->insertGetId([
        'nationaldex_id' => 25,
        'name' => 'Pikachu',
        'type1' => 'Electric',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pokemon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pokedexId,
        'name' => 'Pikachu',
        'cost' => 10,
    ]);

    $this->actingAs($owner)->post('/draft/create', ['league_id' => $league->id]);

    $this->actingAs($user1)->post(route('draft.pick'), [
        'league_id' => $league->id,
        'pokemon_id' => $pokemon->id,
        'pokemon_cost' => $pokemon->cost,
        'pokemon_name' => $pokemon->name,
    ]);

    Notification::assertSentTo($league, DraftNextTurnNotification::class, function (DraftNextTurnNotification $notification) use ($user2): bool {
        return $notification->phase === 'pick'
            && $notification->nextUser->is($user2);
    });
});
