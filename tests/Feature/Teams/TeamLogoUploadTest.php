<?php

use App\Models\User;
use App\Modules\Draft\Models\DraftConfig;
use App\Modules\League\Enums\LeagueStatus;
use App\Modules\League\Models\League;
use App\Modules\Matches\Models\MatchConfig;
use App\Modules\Matches\Models\Pool;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeLeagueWithPool(): League
{
    $league = League::create([
        'name' => 'Logo League',
        'status' => LeagueStatus::Registration->value,
        'draft_points' => 80,
        'league_owner' => 1,
        'maximum_teams' => 10,
    ]);

    DraftConfig::create([
        'league_id' => $league->id,
        'draft_date' => '2026-04-01',
        'draft_points' => 80,
        'ban_enabled' => false,
    ]);

    $matchConfig = MatchConfig::create([
        'league_id' => $league->id,
        'enforce_round_count' => false,
    ]);

    Pool::create([
        'league_id' => $league->id,
        'match_config_id' => $matchConfig->id,
    ]);

    return $league;
}

it('stores the uploaded logo under the league prefix on team creation', function () {
    Storage::fake('s3-team-logos');

    $user = User::factory()->create(['showdown_username' => 'LogoCoach']);
    $league = makeLeagueWithPool();

    $this->actingAs($user)->post('/teams', [
        'name' => 'Logo Squad',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'logo' => UploadedFile::fake()->image('original.png', 64, 64),
    ])->assertRedirect(route('leagues.dashboard', ['league' => $league->id]));

    $team = Team::where('league_id', $league->id)->where('user_id', $user->id)->firstOrFail();

    expect($team->logo)->not->toBeNull();
    expect($team->logo)->toStartWith($league->id.'/');
    Storage::disk('s3-team-logos')->assertExists($team->logo);
});

it('uploads to a new path, deletes the old file, and updates team.logo when overwriting', function () {
    Storage::fake('s3-team-logos');

    $user = User::factory()->create(['showdown_username' => 'OverwriteCoach']);
    $league = makeLeagueWithPool();

    $this->actingAs($user)->post('/teams', [
        'name' => 'Overwrite Squad',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'logo' => UploadedFile::fake()->image('first.png', 64, 64),
    ])->assertRedirect();

    $team = Team::where('league_id', $league->id)->where('user_id', $user->id)->firstOrFail();
    $originalLogoPath = $team->logo;
    expect($originalLogoPath)->not->toBeNull();
    Storage::disk('s3-team-logos')->assertExists($originalLogoPath);

    $this->actingAs($user)->post(route('teams.edit', ['team_id' => $team->id]), [
        'name' => 'Overwrite Squad',
        'showdown_username' => 'OverwriteCoach',
        'logo' => UploadedFile::fake()->image('second.png', 64, 64),
    ])->assertRedirect();

    $team->refresh();

    expect($team->logo)
        ->not->toBeNull()
        ->and($team->logo)->not->toBe($originalLogoPath)
        ->and($team->logo)->toStartWith($league->id.'/');

    Storage::disk('s3-team-logos')->assertExists($team->logo);
    Storage::disk('s3-team-logos')->assertMissing($originalLogoPath);
});

it('produces a unique filename per upload even when the team name is unchanged', function () {
    Storage::fake('s3-team-logos');

    $user = User::factory()->create(['showdown_username' => 'UniqueCoach']);
    $league = makeLeagueWithPool();

    $this->actingAs($user)->post('/teams', [
        'name' => 'Same Name',
        'league_id' => $league->id,
        'user_id' => $user->id,
        'pick_position' => 1,
        'logo' => UploadedFile::fake()->image('a.png', 64, 64),
    ])->assertRedirect();

    $team = Team::where('league_id', $league->id)->where('user_id', $user->id)->firstOrFail();
    $first = $team->logo;

    $this->actingAs($user)->post(route('teams.edit', ['team_id' => $team->id]), [
        'name' => 'Same Name',
        'showdown_username' => 'UniqueCoach',
        'logo' => UploadedFile::fake()->image('b.png', 64, 64),
    ])->assertRedirect();

    $second = $team->fresh()->logo;

    expect($second)->not->toBe($first);
});
