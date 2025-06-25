<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeatherChatCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_shows_help_information(): void
    {
        $this->artisan('weather:chat --help')
            ->expectsOutputToContain('Start an AI weather chatbot session')
            ->assertExitCode(0);
    }

    public function test_summarize_option_works_for_existing_user(): void
    {
        $user = User::factory()->create(['name' => 'testuser']);

        // Add some memories
        $user->memories()->create([
            'key' => 'home_location',
            'value' => ['data' => 'Lisbon'],
            'context' => ['recorded_at' => now()->toISOString()],
        ]);

        $this->artisan('weather:chat --user=testuser --summarize')
            ->expectsOutputToContain('Summary for testuser')
            ->expectsOutputToContain('Locations')
            ->expectsOutputToContain('Lisbon')
            ->assertExitCode(0);
    }

    public function test_summarize_option_handles_new_user(): void
    {
        $this->artisan('weather:chat --user=newuser --summarize')
            ->expectsOutputToContain('No data found for user: newuser')
            ->assertExitCode(0);
    }

    public function test_summarize_requires_user_parameter(): void
    {
        $this->artisan('weather:chat --summarize')
            ->expectsOutputToContain('Please specify a user with --user option')
            ->assertExitCode(1);
    }
}
