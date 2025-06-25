<?php

namespace Tests\Unit;

use App\Actions\Chat\ProcessChatMessage;
use App\Enums\AnthropicModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Testing\TextStepFake;
use Prism\Prism\Text\ResponseBuilder;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

class ChatProcessingTest extends TestCase
{
    use RefreshDatabase;

    private ProcessChatMessage $processor;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new ProcessChatMessage();
        $this->user = User::factory()->create(['name' => 'TestUser']);
    }

    private function mockConsole()
    {
        $console = $this->mock(\Illuminate\Console\Command::class);
        $output = $this->mock(\Symfony\Component\Console\Output\OutputInterface::class);

        $console->shouldReceive('getOutput')->andReturn($output);
        $output->shouldReceive('write')->andReturn();
        $console->shouldReceive('error')->andReturn();

        return $console;
    }

    public function test_simple_weather_query_without_tools(): void
    {
        // Mock weather API
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    ['latitude' => 38.7167, 'longitude' => -9.1333, 'name' => 'Lisbon', 'country' => 'Portugal'],
                ],
            ]),
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'current' => [
                    'temperature_2m' => 22.5,
                    'apparent_temperature' => 23.0,
                    'relative_humidity_2m' => 65,
                    'wind_speed_10m' => 8.2,
                    'weather_code' => 1,
                    'precipitation' => 0,
                ],
                'current_units' => ['temperature_2m' => '°C'],
            ]),
        ]);

        // Mock Prism response
        $fakeResponse = TextResponseFake::make()
            ->withText('The weather in Lisbon is currently 23°C with partly cloudy conditions.')
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(50, 30))
            ->withMeta(new Meta('test-1', AnthropicModel::CLAUDE_3_5_HAIKU->value));

        Prism::fake([$fakeResponse]);

        $console = $this->mockConsole();

        $chatHistory = [
            ['role' => 'user', 'content' => 'What is the weather in Lisbon?'],
        ];

        $response = $this->processor->execute($chatHistory, $this->user, false, $console, false);

        $this->assertEquals('The weather in Lisbon is currently 23°C with partly cloudy conditions.', $response);
    }

    public function test_weather_query_with_tool_usage(): void
    {
        // Mock weather API
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    ['latitude' => 38.7167, 'longitude' => -9.1333, 'name' => 'Lisbon', 'country' => 'Portugal'],
                ],
            ]),
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'current' => [
                    'temperature_2m' => 22.5,
                    'apparent_temperature' => 23.0,
                    'relative_humidity_2m' => 65,
                    'wind_speed_10m' => 8.2,
                    'weather_code' => 1,
                    'precipitation' => 0,
                ],
                'current_units' => ['temperature_2m' => '°C'],
            ]),
        ]);

        // Mock Prism response with tool usage
        $responses = [
            (new ResponseBuilder)
                ->addStep(
                    TextStepFake::make()
                        ->withToolCalls([
                            new ToolCall(
                                id: 'call_weather_123',
                                name: 'get_current_weather',
                                arguments: ['location' => 'Lisbon']
                            ),
                        ])
                        ->withFinishReason(FinishReason::ToolCalls)
                        ->withUsage(new Usage(40, 20))
                        ->withMeta(new Meta('test-step-1', AnthropicModel::CLAUDE_3_5_HAIKU->value))
                )
                ->addStep(
                    TextStepFake::make()
                        ->withText('Based on the current weather data, Lisbon is experiencing pleasant conditions with 23°C temperature and partly cloudy skies.')
                        ->withToolResults([
                            new ToolResult(
                                toolCallId: 'call_weather_123',
                                toolName: 'get_current_weather',
                                args: ['location' => 'Lisbon'],
                                result: 'Current weather in Lisbon: 🌡️ Temperature: 23°C (feels like 23°C) 🌤️ Conditions: Mainly clear 💧 Humidity: 65% 💨 Wind: 8 km/h'
                            ),
                        ])
                        ->withFinishReason(FinishReason::Stop)
                        ->withUsage(new Usage(60, 40))
                        ->withMeta(new Meta('test-step-2', AnthropicModel::CLAUDE_3_5_HAIKU->value))
                )
                ->toResponse(),
        ];

        Prism::fake($responses);

        $console = $this->mockConsole();

        $chatHistory = [
            ['role' => 'user', 'content' => 'What is the weather in Lisbon?'],
        ];

        $response = $this->processor->execute($chatHistory, $this->user, false, $console, false);

        $this->assertStringContainsString('Lisbon', $response);
        $this->assertStringContainsString('23°C', $response);
        $this->assertStringContainsString('pleasant conditions', $response);
    }

    public function test_memory_loading_and_recording(): void
    {
        // Add existing memory
        $this->user->memories()->create([
            'type' => 'location',
            'key' => 'home',
            'value' => ['location' => 'Lisbon'],
            'context' => ['recorded_at' => now()->toISOString()],
        ]);

        // Mock Prism response with memory tool usage
        $responses = [
            (new ResponseBuilder)
                ->addStep(
                    TextStepFake::make()
                        ->withToolCalls([
                            new ToolCall(
                                id: 'call_memory_load',
                                name: 'load_user_memories',
                                arguments: ['reason' => 'Loading user context for weather request']
                            ),
                        ])
                        ->withFinishReason(FinishReason::ToolCalls)
                        ->withUsage(new Usage(30, 15))
                        ->withMeta(new Meta('memory-step-1', AnthropicModel::CLAUDE_3_5_HAIKU->value))
                )
                ->addStep(
                    TextStepFake::make()
                        ->withText('I see you\'re from Lisbon! Would you like me to check the current weather there?')
                        ->withToolResults([
                            new ToolResult(
                                toolCallId: 'call_memory_load',
                                toolName: 'load_user_memories',
                                args: ['reason' => 'Loading user context for weather request'],
                                result: 'Loaded memories for TestUser: 📍 home: Lisbon'
                            ),
                        ])
                        ->withFinishReason(FinishReason::Stop)
                        ->withUsage(new Usage(50, 25))
                        ->withMeta(new Meta('memory-step-2', AnthropicModel::CLAUDE_3_5_HAIKU->value))
                )
                ->toResponse(),
        ];

        Prism::fake($responses);

        $console = $this->mockConsole();

        $chatHistory = [
            ['role' => 'user', 'content' => 'Hi there!'],
        ];

        $response = $this->processor->execute($chatHistory, $this->user, true, $console, false);

        $this->assertStringContainsString('Lisbon', $response);
        $this->assertStringContainsString('weather', $response);
    }

    public function test_forecast_tool_usage(): void
    {
        // Mock weather API for forecast
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    ['latitude' => 38.7167, 'longitude' => -9.1333, 'name' => 'Lisbon', 'country' => 'Portugal'],
                ],
            ]),
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'daily' => [
                    'time' => [now()->addDay()->toDateString()],
                    'temperature_2m_max' => [25.2],
                    'temperature_2m_min' => [18.3],
                    'weather_code' => [1],
                    'precipitation_sum' => [0],
                ],
            ]),
        ]);

        // Mock Prism response with forecast tool usage
        $responses = [
            (new ResponseBuilder)
                ->addStep(
                    TextStepFake::make()
                        ->withToolCalls([
                            new ToolCall(
                                id: 'call_forecast_123',
                                name: 'get_weather_forecast',
                                arguments: ['location' => 'Lisbon', 'date' => 'tomorrow']
                            ),
                        ])
                        ->withFinishReason(FinishReason::ToolCalls)
                        ->withUsage(new Usage(45, 25))
                        ->withMeta(new Meta('forecast-step-1', AnthropicModel::CLAUDE_3_5_HAIKU->value))
                )
                ->addStep(
                    TextStepFake::make()
                        ->withText('Tomorrow in Lisbon looks great! Expect temperatures between 18°C and 25°C with mainly clear skies.')
                        ->withToolResults([
                            new ToolResult(
                                toolCallId: 'call_forecast_123',
                                toolName: 'get_weather_forecast',
                                args: ['location' => 'Lisbon', 'date' => 'tomorrow'],
                                result: 'Weather forecast for Lisbon: 🌡️ Temperature: 18°C to 25°C 🌤️ Conditions: Mainly clear 💧 Precipitation: No precipitation expected'
                            ),
                        ])
                        ->withFinishReason(FinishReason::Stop)
                        ->withUsage(new Usage(65, 35))
                        ->withMeta(new Meta('forecast-step-2', AnthropicModel::CLAUDE_3_5_HAIKU->value))
                )
                ->toResponse(),
        ];

        Prism::fake($responses);

        $console = $this->mockConsole();

        $chatHistory = [
            ['role' => 'user', 'content' => 'What will the weather be like tomorrow in Lisbon?'],
        ];

        $response = $this->processor->execute($chatHistory, $this->user, false, $console, false);

        $this->assertStringContainsString('Tomorrow', $response);
        $this->assertStringContainsString('18°C', $response);
        $this->assertStringContainsString('25°C', $response);
        $this->assertStringContainsString('clear', $response);
    }

    public function test_streaming_response_processing(): void
    {
        // Mock simple streaming response
        $fakeResponse = TextResponseFake::make()
            ->withText('The weather looks great today! Perfect for a walk outside.')
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(40, 25))
            ->withMeta(new Meta('stream-test', AnthropicModel::CLAUDE_3_5_HAIKU->value));

        Prism::fake([$fakeResponse])->withFakeChunkSize(10); // 10 character chunks

        $console = $this->mockConsole();

        $chatHistory = [
            ['role' => 'user', 'content' => 'How is the weather?'],
        ];

        $response = $this->processor->execute($chatHistory, $this->user, false, $console, true);

        $this->assertEquals('The weather looks great today! Perfect for a walk outside.', $response);
    }

    public function test_handles_vague_weather_queries(): void
    {
        // Mock a response when no specific location is provided
        $fakeResponse = TextResponseFake::make()
            ->withText('I need to know the specific location you\'re interested in to provide weather information.')
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(40, 25))
            ->withMeta(new Meta('vague-test', AnthropicModel::CLAUDE_3_5_HAIKU->value));

        Prism::fake([$fakeResponse]);

        $console = $this->mockConsole();

        $chatHistory = [
            ['role' => 'user', 'content' => 'What is the weather?'],
        ];

        $response = $this->processor->execute($chatHistory, $this->user, false, $console, false);

        // The AI should ask for a location when the query is vague
        $this->assertStringContainsString('location', $response);
    }
}
