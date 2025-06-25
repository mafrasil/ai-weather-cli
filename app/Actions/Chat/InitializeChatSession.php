<?php

namespace App\Actions\Chat;

use App\Actions\User\FindOrCreateUser;
use Illuminate\Console\Command;

class InitializeChatSession
{
    public function execute(?string $userName, bool $useMemories, Command $console): ?array
    {
        $user = app(FindOrCreateUser::class)->execute($userName, $console);

        if (!$user) {
            return null;
        }

        if ($useMemories) {
            $memoryCount = $user->memories()->whereNotIn('type', ['conversation'])->count();
            if ($memoryCount > 0) {
                $console->info("<fg=blue>I remember {$memoryCount} things about you from our previous conversations.</>");
            } else {
                $console->info("<fg=blue>Memory mode enabled - I'll remember important details from our conversation.</>");
            }
        }

        $console->newLine();

        return [
            'user' => $user,
            'use_memories' => $useMemories,
        ];
    }
}
