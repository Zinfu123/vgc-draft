<?php

use App\Models\User;
use App\Modules\Draft\Actions\BanPokemonAction;
use App\Modules\Draft\Actions\CreateEditDraftAction;
use App\Modules\Draft\Actions\CreateEditDraftOrderAction;
use App\Modules\Draft\Actions\ReadCurrentDraftAction;
use App\Modules\Draft\Models\BanOrder;
use App\Modules\Draft\Models\Bans;
use App\Modules\Draft\Models\Draft;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\Draft\Models\DraftOrder;
use App\Modules\League\Models\League;
use App\Modules\League\Models\LeaguePokemon;
use App\Modules\Teams\Models\Team;
use App\Notifications\DraftNextTurnNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createLeagueWithBans(int $bansPerUser = 2, int $minimumCostToBan = 3, int $teamCount = 3, ?string $discordWebhookUrl = null): array
{
    $league = League::create([
        'name' => 'Test League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => User::factory()->create()->id,
        'discord_webhook_url' => $discordWebhookUrl,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_points' => 100,
        'minimum_drafts' => 0,
        'enforce_round_count' => false,
        'ban_enabled' => true,
        'bans_per_user' => $bansPerUser,
        'minimum_cost_to_ban' => $minimumCostToBan,
    ]);

    $teams = [];
    for ($i = 1; $i <= $teamCount; $i++) {
        $user = User::factory()->create();
        $teams[] = Team::create([
            'name' => "Team {$i}",
            'league_id' => $league->id,
            'user_id' => $user->id,
            'pick_position' => $i,
            'draft_points' => 100,
            'victory_points' => 0,
            'admin_flag' => $i === 1 ? 1 : 0,
            'set_wins' => 0,
            'set_losses' => 0,
            'game_wins' => 0,
            'game_losses' => 0,
        ]);
    }

    return [$league, $teams];
}

test('draft creation with ban_enabled creates draft with status 2 (banning phase)', function () {
    [$league] = createLeagueWithBans();
    $user = User::factory()->create();
    $this->actingAs($user);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create']);

    $draft = Draft::where('league_id', $league->id)->first();
    expect($draft)->not->toBeNull();
    expect($draft->status)->toBe(2);
});

test('create_ban creates one Bans placeholder per team per ban round', function () {
    [$league, $teams] = createLeagueWithBans(bansPerUser: 2, teamCount: 3);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'create_ban']);

    $bans = Bans::where('league_id', $league->id)->get();
    expect($bans)->toHaveCount(6); // 3 teams × 2 rounds
    expect($bans->where('round_number', 1))->toHaveCount(3);
    expect($bans->where('round_number', 2))->toHaveCount(3);
    expect($bans->every(fn ($ban) => $ban->pokedex_id === null))->toBeTrue();
    expect($bans->every(fn ($ban) => $ban->status === 0))->toBeTrue();
});

test('create_ban_order creates one BanOrder per team per ban round', function () {
    [$league, $teams] = createLeagueWithBans(bansPerUser: 2, teamCount: 3);

    (new CreateEditDraftOrderAction)(['league_id' => $league->id, 'command' => 'create_ban_order']);

    $banOrders = BanOrder::where('league_id', $league->id)->get();
    expect($banOrders)->toHaveCount(6); // 3 teams × 2 rounds
    expect($banOrders->where('round_number', 1))->toHaveCount(3);
    expect($banOrders->where('round_number', 2))->toHaveCount(3);
});

test('create_ban_order marks the last BanOrder as is_last_ban', function () {
    [$league] = createLeagueWithBans(bansPerUser: 2, teamCount: 3);

    (new CreateEditDraftOrderAction)(['league_id' => $league->id, 'command' => 'create_ban_order']);

    $lastBan = BanOrder::where('league_id', $league->id)->where('is_last_ban', 1)->get();
    expect($lastBan)->toHaveCount(1);

    $lastBanRecord = $lastBan->first();
    expect($lastBanRecord->round_number)->toBe(2);
});

test('create_ban_order uses snake draft order across rounds', function () {
    [$league, $teams] = createLeagueWithBans(bansPerUser: 2, teamCount: 3);

    (new CreateEditDraftOrderAction)(['league_id' => $league->id, 'command' => 'create_ban_order']);

    $round1 = BanOrder::where('league_id', $league->id)->where('round_number', 1)->orderBy('ban_number')->get();
    $round2 = BanOrder::where('league_id', $league->id)->where('round_number', 2)->orderBy('ban_number')->get();

    // Round 1: ascending pick_position (1, 2, 3)
    expect($round1[0]->team_id)->toBe($teams[0]->id); // pick_position 1
    expect($round1[1]->team_id)->toBe($teams[1]->id); // pick_position 2
    expect($round1[2]->team_id)->toBe($teams[2]->id); // pick_position 3

    // Round 2: descending pick_position (3, 2, 1)
    expect($round2[0]->team_id)->toBe($teams[2]->id); // pick_position 3
    expect($round2[1]->team_id)->toBe($teams[1]->id); // pick_position 2
    expect($round2[2]->team_id)->toBe($teams[0]->id); // pick_position 1
});

test('create_ban_order sets status 1 (pending) on all records', function () {
    [$league] = createLeagueWithBans(bansPerUser: 1, teamCount: 2);

    (new CreateEditDraftOrderAction)(['league_id' => $league->id, 'command' => 'create_ban_order']);

    $banOrders = BanOrder::where('league_id', $league->id)->get();
    expect($banOrders->every(fn ($order) => $order->status === 1))->toBeTrue();
});

test('draft creation with ban_enabled creates both Bans and BanOrder records', function () {
    [$league, $teams] = createLeagueWithBans(bansPerUser: 1, teamCount: 2);
    $admin = User::find($teams[0]->user_id);
    $this->actingAs($admin);

    $this->post(route('draft.create'), ['league_id' => $league->id]);

    expect(Bans::where('league_id', $league->id)->count())->toBe(2);   // 2 teams × 1 round
    expect(BanOrder::where('league_id', $league->id)->count())->toBe(2);
    expect(Draft::where('league_id', $league->id)->first()->status)->toBe(2);
});

test('draft creation without ban_enabled creates no Bans or BanOrder records', function () {
    $league = League::create([
        'name' => 'No Ban League',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => User::factory()->create()->id,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_points' => 100,
        'minimum_drafts' => 0,
        'enforce_round_count' => false,
        'ban_enabled' => false,
        'bans_per_user' => 1,
        'minimum_cost_to_ban' => 0,
    ]);

    $user = User::factory()->create();
    Team::create([
        'name' => 'Team 1',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'draft_points' => 100,
        'victory_points' => 0,
        'admin_flag' => 1,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $this->actingAs($user)->post(route('draft.create'), ['league_id' => $league->id]);

    expect(Bans::where('league_id', $league->id)->count())->toBe(0);
    expect(BanOrder::where('league_id', $league->id)->count())->toBe(0);
    expect(Draft::where('league_id', $league->id)->first()->status)->toBe(1);
});

test('abort_draft cleans up Bans and BanOrder records', function () {
    [$league, $teams] = createLeagueWithBans(bansPerUser: 1, teamCount: 2);
    $admin = User::find($teams[0]->user_id);
    $this->actingAs($admin);

    $this->post(route('draft.create'), ['league_id' => $league->id]);
    expect(Bans::where('league_id', $league->id)->count())->toBeGreaterThan(0);
    expect(BanOrder::where('league_id', $league->id)->count())->toBeGreaterThan(0);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'abort_draft']);

    expect(Bans::where('league_id', $league->id)->count())->toBe(0);
    expect(BanOrder::where('league_id', $league->id)->count())->toBe(0);
});

test('abort_draft resets banned flag on league pokemon', function () {
    [$league, $teams] = createLeagueWithBans(bansPerUser: 1, teamCount: 2);

    $pokedexId = DB::table('pokedex')->insertGetId(['nationaldex_id' => 1, 'name' => 'Bulbasaur', 'type1' => 'Grass', 'created_at' => now(), 'updated_at' => now()]);

    $pokemon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pokedexId,
        'name' => 'Bulbasaur',
        'cost' => 5,
        'banned' => true,
    ]);

    $admin = User::find($teams[0]->user_id);
    $this->actingAs($admin)->post(route('draft.create'), ['league_id' => $league->id]);

    (new CreateEditDraftAction)(['league_id' => $league->id, 'command' => 'abort_draft']);

    expect($pokemon->fresh()->banned)->toBeFalse();
});

test('LeaguePokemon banned field defaults to false', function () {
    $league = League::create([
        'name' => 'Ban Field Test',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => User::factory()->create()->id,
    ]);

    $pokedexId = DB::table('pokedex')->insertGetId(['nationaldex_id' => 1, 'name' => 'Bulbasaur', 'type1' => 'Grass', 'created_at' => now(), 'updated_at' => now()]);

    $pokemon = LeaguePokemon::create([
        'league_id' => $league->id,
        'pokedex_id' => $pokedexId,
        'name' => 'Bulbasaur',
        'cost' => 5,
    ]);

    expect($pokemon->fresh()->banned)->toBeFalse();
});

test('BanPokemonAction sends DraftNextTurnNotification when discord webhook is set', function () {
    Notification::fake();

    [$league, $teams] = createLeagueWithBans(bansPerUser: 1, minimumCostToBan: 3, teamCount: 2, discordWebhookUrl: 'https://discord.com/api/webhooks/test/token');

    $secondTeamCoach = User::find($teams[1]->user_id);
    $secondTeamCoach?->update(['discord_id' => '555666777888999000']);

    $admin = User::find($teams[0]->user_id);
    $this->actingAs($admin)->post(route('draft.create'), ['league_id' => $league->id]);

    $pokedexId = DB::table('pokedex')->insertGetId(['nationaldex_id' => 1, 'name' => 'Bulbasaur', 'type1' => 'Grass', 'created_at' => now(), 'updated_at' => now()]);
    $pokemon = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pokedexId, 'name' => 'Bulbasaur', 'cost' => 5]);

    $firstBanOrder = BanOrder::where('league_id', $league->id)->where('status', 1)->orderBy('round_number')->orderBy('ban_number')->first();

    (new BanPokemonAction)(['league_id' => $league->id, 'team_id' => $firstBanOrder->team_id, 'pokemon_id' => $pokemon->id]);

    Notification::assertSentTo($league, DraftNextTurnNotification::class, function (DraftNextTurnNotification $notification) use ($secondTeamCoach): bool {
        return $notification->phase === 'ban'
            && $secondTeamCoach !== null
            && $notification->nextUser->is($secondTeamCoach);
    });
});

test('BanPokemonAction bans pokemon, updates Bans record, and marks BanOrder done', function () {
    [$league, $teams] = createLeagueWithBans(bansPerUser: 1, minimumCostToBan: 3, teamCount: 2);

    $admin = User::find($teams[0]->user_id);
    $this->actingAs($admin)->post(route('draft.create'), ['league_id' => $league->id]);

    $pokedexId = DB::table('pokedex')->insertGetId(['nationaldex_id' => 1, 'name' => 'Bulbasaur', 'type1' => 'Grass', 'created_at' => now(), 'updated_at' => now()]);
    $pokemon = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pokedexId, 'name' => 'Bulbasaur', 'cost' => 5]);

    $firstBanOrder = BanOrder::where('league_id', $league->id)->where('status', 1)->orderBy('ban_number')->first();

    (new BanPokemonAction)(['league_id' => $league->id, 'team_id' => $firstBanOrder->team_id, 'pokemon_id' => $pokemon->id]);

    expect($pokemon->fresh()->banned)->toBeTrue();
    expect($firstBanOrder->fresh()->status)->toBe(0);
    expect(Bans::where('league_id', $league->id)->where('team_id', $firstBanOrder->team_id)->whereNotNull('pokedex_id')->count())->toBe(1);
});

test('BanPokemonAction transitions to draft phase when all bans complete', function () {
    [$league, $teams] = createLeagueWithBans(bansPerUser: 1, minimumCostToBan: 0, teamCount: 2);

    $admin = User::find($teams[0]->user_id);
    $this->actingAs($admin)->post(route('draft.create'), ['league_id' => $league->id]);

    $banOrders = BanOrder::where('league_id', $league->id)->where('status', 1)->orderBy('ban_number')->get();

    foreach ($banOrders as $index => $banOrder) {
        $pokedexId = DB::table('pokedex')->insertGetId(['nationaldex_id' => $index + 1, 'name' => 'Pokemon'.$index, 'type1' => 'Fire', 'created_at' => now(), 'updated_at' => now()]);
        $pokemon = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pokedexId, 'name' => 'Pokemon'.$index, 'cost' => 5]);

        (new BanPokemonAction)(['league_id' => $league->id, 'team_id' => $banOrder->team_id, 'pokemon_id' => $pokemon->id]);
    }

    expect(Draft::where('league_id', $league->id)->first()->status)->toBe(1);
    expect(DraftOrder::where('league_id', $league->id)->count())->toBeGreaterThan(0);
});

test('BanPokemonAction throws when pokemon is below minimum cost to ban', function () {
    [$league, $teams] = createLeagueWithBans(bansPerUser: 1, minimumCostToBan: 5, teamCount: 2);

    $admin = User::find($teams[0]->user_id);
    $this->actingAs($admin)->post(route('draft.create'), ['league_id' => $league->id]);

    $pokedexId = DB::table('pokedex')->insertGetId(['nationaldex_id' => 1, 'name' => 'Pikachu', 'type1' => 'Electric', 'created_at' => now(), 'updated_at' => now()]);
    $cheapPokemon = LeaguePokemon::create(['league_id' => $league->id, 'pokedex_id' => $pokedexId, 'name' => 'Pikachu', 'cost' => 2]);

    $firstBanOrder = BanOrder::where('league_id', $league->id)->where('status', 1)->orderBy('ban_number')->first();

    expect(fn () => (new BanPokemonAction)(['league_id' => $league->id, 'team_id' => $firstBanOrder->team_id, 'pokemon_id' => $cheapPokemon->id]))
        ->toThrow(\Exception::class, 'minimum cost');
});

test('ReadCurrentDraftAction lastban returns the most recently performed ban, not the highest-id placeholder', function () {
    // Two rounds of snake-ordered bans across 3 teams. Pre-created Bans rows
    // follow team-creation order within each round, so team[0]'s round-2 row
    // has a LOWER id than team[2]'s round-2 row. In snake order, team[0] bans
    // LAST in round 2 — so the "last ban" is team[0]'s row, even though its
    // pre-created id is not the highest.
    [$league, $teams] = createLeagueWithBans(bansPerUser: 2, minimumCostToBan: 0, teamCount: 3);

    $admin = User::find($teams[0]->user_id);
    $this->actingAs($admin)->post(route('draft.create'), ['league_id' => $league->id]);

    $pokemonByTeam = [];
    foreach ($teams as $index => $team) {
        $pokedexIdR1 = DB::table('pokedex')->insertGetId([
            'nationaldex_id' => 100 + $index,
            'name' => "PokemonR1_{$index}",
            'type1' => 'Fire',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pokedexIdR2 = DB::table('pokedex')->insertGetId([
            'nationaldex_id' => 200 + $index,
            'name' => "PokemonR2_{$index}",
            'type1' => 'Fire',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pokemonByTeam[$team->id] = [
            1 => LeaguePokemon::create([
                'league_id' => $league->id,
                'pokedex_id' => $pokedexIdR1,
                'name' => "PokemonR1_{$index}",
                'cost' => 5,
            ]),
            2 => LeaguePokemon::create([
                'league_id' => $league->id,
                'pokedex_id' => $pokedexIdR2,
                'name' => "PokemonR2_{$index}",
                'cost' => 5,
            ]),
        ];
    }

    $banSequence = [
        [$teams[0], 1],
        [$teams[1], 1],
        [$teams[2], 1],
        [$teams[2], 2],
        [$teams[1], 2],
        [$teams[0], 2],
    ];

    foreach ($banSequence as $step => [$team, $round]) {
        Carbon\Carbon::setTestNow(Carbon\Carbon::create(2026, 1, 1, 12, 0, $step));
        (new BanPokemonAction)([
            'league_id' => $league->id,
            'team_id' => $team->id,
            'pokemon_id' => $pokemonByTeam[$team->id][$round]->id,
        ]);
    }
    Carbon\Carbon::setTestNow();

    $lastBan = (new ReadCurrentDraftAction)(['league_id' => $league->id, 'command' => 'lastban']);

    expect($lastBan)->not->toBeNull();
    expect($lastBan->team_id)->toBe($teams[0]->id);
    expect($lastBan->round_number)->toBe(2);
});

test('DraftConfig bans_per_user and minimum_cost_to_ban are stored correctly', function () {
    $league = League::create([
        'name' => 'Config Test',
        'status' => \App\Modules\League\Enums\LeagueStatus::Staging->value,
        'open' => true,
        'league_owner' => User::factory()->create()->id,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_points' => 100,
        'minimum_drafts' => 0,
        'enforce_round_count' => false,
        'ban_enabled' => true,
        'bans_per_user' => 3,
        'minimum_cost_to_ban' => 5,
    ]);

    $config = DraftConfig::where('league_id', $league->id)->first();
    expect($config->bans_per_user)->toBe(3);
    expect($config->minimum_cost_to_ban)->toBe(5);
});
