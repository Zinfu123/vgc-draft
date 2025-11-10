<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ZinfuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!User::where('email', 'Ninfu1@gmail.com')->doesntExist()) {
        $user = User::create([
            'name' => 'Zinfu',
            'email' => 'Ninfu1@gmail.com',
            'password' => Hash::make('Nimiety1'),
        ]);
    }}
}