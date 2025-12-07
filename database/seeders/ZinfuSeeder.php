<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ZinfuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::where('email', 'Ninfu1@gmail.com')->doesntExist()) {
            $user = User::create([
                'name' => 'Zinfu',
                'email' => 'Ninfu1@gmail.com',
                'password' => Hash::make('Nimiety1'),
            ]);
            if (User::where('email', 'theslurper@vietnam.com')->doesntExist()) {
                $user = User::create([
                    'name' => 'The Slurper',
                    'email' => 'theslurper@vietnam.com',
                    'password' => Hash::make('uw6kAPqmPnuN5Gv'),
                ]);
            }
        }
    }
}
