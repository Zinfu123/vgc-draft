<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createLeagueForDiscordTests(): array
{
    $owner = \App\Models\User::factory()->create();

    $league = \App\Modules\League\Models\League::create([
        'name' => 'Discord League',
        'status' => \App\Modules\League\Enums\LeagueStatus::RegularSeason->value,
        'league_owner' => $owner->id,
        'discord_webhook_url' => 'https://discord.com/api/webhooks/test/token',
    ]);

    \App\Modules\Draft\Models\DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => now()->addDay(),
        'draft_points' => 80,
        'ban_enabled' => false,
    ]);

    \App\Modules\Matches\Models\MatchConfig::create([
        'league_id' => $league->id,
        'enforce_round_count' => false,
    ]);

    $user1 = \App\Models\User::factory()->create();
    $user2 = \App\Models\User::factory()->create();

    $team1 = \App\Modules\Teams\Models\Team::create([
        'name' => 'Team Rocket',
        'league_id' => $league->id,
        'user_id' => $user1->id,
        'admin_flag' => 1,
        'pick_position' => 1,
        'seed' => 1,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    $team2 = \App\Modules\Teams\Models\Team::create([
        'name' => 'Team Aqua',
        'league_id' => $league->id,
        'user_id' => $user2->id,
        'admin_flag' => 0,
        'pick_position' => 2,
        'seed' => 2,
        'draft_points' => 80,
        'victory_points' => 0,
        'set_wins' => 0,
        'set_losses' => 0,
        'game_wins' => 0,
        'game_losses' => 0,
    ]);

    return [$owner, $league, $team1, $team2, $user1, $user2];
}
