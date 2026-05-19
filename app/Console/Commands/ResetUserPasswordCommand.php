<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetUserPasswordCommand extends Command
{
    protected $signature = 'user:reset-password
                            {id : The ID of the user}
                            {password : The new plain-text password to hash and store}';

    protected $description = 'Set a new hashed password for a user by ID';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $password = (string) $this->argument('password');

        $user = User::query()->find($id);

        if ($user === null) {
            $this->error("No user found with ID: {$id}");

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info("Password updated for {$user->name} ({$user->email}).");

        return self::SUCCESS;
    }
}
