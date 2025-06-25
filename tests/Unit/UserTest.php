<?php

namespace Tests\Unit;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_memories(): void
    {
        $user = User::factory()->create();

        $memory = $user->memories()->create([
            'key' => 'home_location',
            'value' => ['data' => 'Lisbon'],
            'context' => ['recorded_at' => now()->toISOString()],
        ]);

        $this->assertInstanceOf(Memory::class, $memory);
        $this->assertEquals($user->id, $memory->user_id);
        $this->assertEquals('home_location', $memory->key);
    }

    public function test_memory_key_pattern_scope_works(): void
    {
        $user = User::factory()->create();

        $user->memories()->create([
            'key' => 'home_location',
            'value' => ['data' => 'Lisbon'],
            'context' => [],
        ]);

        $user->memories()->create([
            'key' => 'work_location',
            'value' => ['data' => 'Porto'],
            'context' => [],
        ]);

        $user->memories()->create([
            'key' => 'preferred_units',
            'value' => ['data' => 'celsius'],
            'context' => [],
        ]);

        $locationMemories = $user->memories()->forKeyPattern('%location')->get();
        $preferenceMemories = $user->memories()->forKeyPattern('preferred%')->get();

        $this->assertCount(2, $locationMemories);
        $this->assertCount(1, $preferenceMemories);
        $this->assertTrue($locationMemories->contains('key', 'home_location'));
        $this->assertTrue($locationMemories->contains('key', 'work_location'));
        $this->assertEquals('preferred_units', $preferenceMemories->first()->key);
    }
}
