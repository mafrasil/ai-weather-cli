<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class MemoryService
{
    public function loadMemories(User $user): string
    {
        $memories = $user->memories()->latest()->get();

        if ($memories->isEmpty()) {
            return "No previous memories found for {$user->name}. This appears to be a new conversation.";
        }

        $formattedMemories = [];

        foreach ($memories as $memory) {
            $formattedMemories[] = $this->formatMemoryForDisplay($memory->key, $memory->value);
        }

        return "Loaded memories for {$user->name}:\n" . implode("\n", $formattedMemories);
    }

    public function recordMemory(User $user, string $memoryKey, string $memoryData): string
    {
        try {
            $memory = $user->memories()->updateOrCreate(
                ['key' => $memoryKey],
                [
                    'value' => ['data' => $memoryData],
                    'context' => [
                        'recorded_at' => now()->toISOString(),
                        'session_type' => 'cli',
                    ],
                ]
            );

            Log::info('Memory recorded', [
                'user' => $user->name,
                'key' => $memoryKey,
                'data' => $memoryData,
            ]);

            return "✅ Recorded memory: {$memoryKey} = {$memoryData}";

        } catch (\Exception $e) {
            Log::error('Failed to record memory', [
                'user' => $user->name,
                'key' => $memoryKey,
                'error' => $e->getMessage(),
            ]);

            return "❌ Failed to record memory. Please try again.";
        }
    }

    private function formatMemoryForDisplay(string $key, array $value): string
    {
        $data = $value['data'] ?? 'Unknown';
        
        // Use semantic formatting based on key naming conventions
        if (str_contains($key, 'location')) {
            return "📍 {$key}: {$data}";
        } elseif (str_contains($key, 'preference') || str_contains($key, 'setting')) {
            return "⚙️ {$key}: {$data}";
        } elseif (str_contains($key, 'context') || str_contains($key, 'note')) {
            return "💭 {$key}: {$data}";
        } else {
            return "📝 {$key}: {$data}";
        }
    }
}
