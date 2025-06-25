<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Console\Command;

class FindOrCreateUser
{
    public function execute(?string $userName, Command $console): ?User
    {
        if (!$userName) {
            $userName = $console->ask('What should I call you?');

            if (!$userName) {
                $console->error('A name is required to start a session.');
                return null;
            }
        }

        $userName = trim($userName);
        if (strlen($userName) < 2) {
            $console->error('Username must be at least 2 characters long.');
            return null;
        }

        $user = User::firstOrCreate(['name' => $userName]);

        if ($user->wasRecentlyCreated) {
            $console->info("Nice to meet you, {$userName}! I'm your AI weather assistant.");
            $console->info("I can remember things about you across sessions if you use the --memories flag.");
        } else {
            $console->info("Welcome back, {$userName}!");
        }

        return $user;
    }
}
