<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\MemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemoryService $memoryService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memoryService = new MemoryService();
        $this->user = User::factory()->create(['name' => 'Test User']);
    }

    public function test_can_record_location_memory(): void
    {
        $result = $this->memoryService->recordMemory($this->user, 'home_location', 'Lisbon');

        $this->assertStringContainsString('Recorded memory: home_location = Lisbon', $result);
        $this->assertDatabaseHas('memories', [
            'user_id' => $this->user->id,
            'key' => 'home_location',
        ]);
    }

    public function test_can_record_preference_memory(): void
    {
        $result = $this->memoryService->recordMemory($this->user, 'preferred_units', 'celsius');

        $this->assertStringContainsString('Recorded memory: preferred_units = celsius', $result);
        $this->assertDatabaseHas('memories', [
            'user_id' => $this->user->id,
            'key' => 'preferred_units',
        ]);
    }

    public function test_can_load_memories_when_none_exist(): void
    {
        $result = $this->memoryService->loadMemories($this->user);

        $this->assertStringContainsString('No previous memories found', $result);
        $this->assertStringContainsString('Test User', $result);
    }

    public function test_can_load_existing_memories(): void
    {
        $this->user->memories()->create([
            'key' => 'home_location',
            'value' => ['data' => 'Lisbon'],
            'context' => ['recorded_at' => now()->toISOString()],
        ]);

        $this->user->memories()->create([
            'key' => 'preferred_units',
            'value' => ['data' => 'celsius'],
            'context' => ['recorded_at' => now()->toISOString()],
        ]);

        $result = $this->memoryService->loadMemories($this->user);

        $this->assertStringContainsString('Loaded memories for Test User', $result);
        $this->assertStringContainsString('📍 home_location: Lisbon', $result);
        $this->assertStringContainsString('preferred_units: celsius', $result);
    }

    public function test_updates_existing_memory_instead_of_creating_duplicate(): void
    {
        // Record initial memory
        $this->memoryService->recordMemory($this->user, 'home_location', 'Lisbon');

        // Update the same memory
        $this->memoryService->recordMemory($this->user, 'home_location', 'Porto');

        // Should only have one memory record
        $this->assertEquals(1, $this->user->memories()->where('key', 'home_location')->count());

        // Should have the updated value
        $memory = $this->user->memories()->where('key', 'home_location')->first();
        $this->assertEquals(['data' => 'Porto'], $memory->value);
    }
}
