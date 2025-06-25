<?php

namespace App\Tools;

use App\Models\User;
use App\Services\MemoryService;
use Prism\Prism\Tool;

class LoadMemoryTool extends Tool
{
    public function __construct(
        private User $user,
        private MemoryService $memoryService
    ) {
        $this
            ->as('load_user_memories')
            ->for('Load stored information about the user from previous conversations. Call this at the start of the conversation.')
            ->withStringParameter('reason', 'A short justification for why you need to access user memories.')
            ->using($this);
    }

    public function __invoke(string $reason): string
    {
        return $this->memoryService->loadMemories($this->user);
    }
}