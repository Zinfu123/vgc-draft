<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\League\Models\League;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Seeder;

class LeagueTeamsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a user for the teams
        $user = User::first();
        if (! $user) {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        // Verify league exists
        $league = League::find(1);
        if (! $league) {
            $this->command->error('League with id 1 does not exist. Please create it first.');

            return;
        }

        // Get league draft_points for teams
        $draftPoints = $league->draft_points ?? 0;

        // Create 20 teams
        for ($i = 1; $i <= 20; $i++) {
            Team::create([
                'name' => 'Team '.$i,
                'league_id' => 1,
                'user_id' => $user->id,
                'pick_position' => $i,
                'seed' => $i,
                'draft_points' => $draftPoints,
                'trades' => 4,
                'victory_points' => 0,
                'set_wins' => 0,
                'set_losses' => 0,
                'game_wins' => 0,
                'game_losses' => 0,
                'admin_flag' => $i === 1, // First team is admin
            ]);
        }

        $this->command->info('Created 20 teams for League id 1');
    }
}
