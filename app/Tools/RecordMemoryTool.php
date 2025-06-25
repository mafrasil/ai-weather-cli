<?php

namespace App\Tools;

use App\Models\User;
use App\Services\MemoryService;
use Prism\Prism\Tool;

class RecordMemoryTool extends Tool
{
    public function __construct(
        private User $user,
        private MemoryService $memoryService
    ) {
        $this
            ->as('record_user_memory')
            ->for('Store important information about the user for future conversations. Use semantic keys like "home_location", "preferred_units", "favorite_cuisine".')
            ->withStringParameter('key', 'A semantic key for this memory using underscores. Examples: "home_location", "work_location", "preferred_units", "favorite_cuisine".')
            ->withStringParameter('value', 'The information to remember.')
            ->using($this);
    }

    public function __invoke(string $key, string $value): string
    {
        if (!str_contains($key, '_') || strlen($key) < 3) {
            return "Invalid memory key format. Please use descriptive keys with underscores like 'home_location', 'preferred_units'.";
        }

        return $this->memoryService->recordMemory($this->user, $key, $value);
    }
}
