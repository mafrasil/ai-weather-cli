<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Console\Command;

class SummarizeUserContext
{
    public function execute(?string $userName, Command $console): int
    {
        if (!$userName) {
            $console->error('Please specify a user with --user option');
            return 1;
        }

        $user = User::where('name', $userName)->with('memories')->first();
        if (!$user) {
            $console->info("No data found for user: {$userName}");
            return 0;
        }

        $this->displayUserSummary($user, $console);
        return 0;
    }

    private function displayUserSummary(User $user, Command $console): void
    {
        $console->info("Summary for {$user->name}:");
        $console->newLine();

        $memories = $user->memories()->get();

        if ($memories->isEmpty()) {
            $console->info("No memories found for this user.");
            $console->newLine();
            return;
        }

        // Group memories by category
        $locationMemories = $memories->filter(fn($memory) => str_contains($memory->key, 'location'));
        $preferenceMemories = $memories->filter(fn($memory) => str_contains($memory->key, 'preference') || str_contains($memory->key, 'setting'));
        $contextMemories = $memories->filter(fn($memory) => str_contains($memory->key, 'context') || str_contains($memory->key, 'note'));
        $otherMemories = $memories->filter(fn($memory) =>
            !str_contains($memory->key, 'location') &&
            !str_contains($memory->key, 'preference') &&
            !str_contains($memory->key, 'setting') &&
            !str_contains($memory->key, 'context') &&
            !str_contains($memory->key, 'note')
        );

        // Display locations
        if ($locationMemories->isNotEmpty()) {
            $console->info("Locations:");
            foreach ($locationMemories as $memory) {
                $location = is_array($memory->value) ? $memory->value['data'] ?? 'Unknown' : $memory->value;
                $console->info("  • {$memory->key}: {$location}");
            }
            $console->newLine();
        }

        // Display preferences
        if ($preferenceMemories->isNotEmpty()) {
            $console->info("Preferences:");
            foreach ($preferenceMemories as $pref) {
                $value = is_array($pref->value) ? $pref->value['data'] ?? 'Unknown' : $pref->value;
                $console->info("  • {$pref->key}: {$value}");
            }
            $console->newLine();
        }

        // Display context
        if ($contextMemories->isNotEmpty()) {
            $console->info("Context:");
            foreach ($contextMemories as $context) {
                $value = is_array($context->value) ? $context->value['data'] ?? 'Unknown' : $context->value;
                $console->info("  • {$context->key}: {$value}");
            }
            $console->newLine();
        }

        // Display other memories
        if ($otherMemories->isNotEmpty()) {
            $console->info("Other:");
            foreach ($otherMemories as $other) {
                $value = is_array($other->value) ? $other->value['data'] ?? 'Unknown' : $other->value;
                $console->info("  • {$other->key}: {$value}");
            }
            $console->newLine();
        }

        $console->info("Account created: {$user->created_at->diffForHumans()}");
        $console->info("Last updated: {$user->updated_at->diffForHumans()}");
    }
}
