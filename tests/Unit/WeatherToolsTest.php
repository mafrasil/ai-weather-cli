<?php

namespace Tests\Unit;

use App\Services\WeatherService;
use App\Tools\ForecastTool;
use App\Tools\WeatherTool;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherToolsTest extends TestCase
{
    public function test_weather_tool_integration(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    ['latitude' => 40.7128, 'longitude' => -74.0060, 'name' => 'New York', 'country' => 'United States'],
                ],
            ]),
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'current' => [
                    'temperature_2m' => 20.0,
                    'apparent_temperature' => 21.0,
                    'relative_humidity_2m' => 70,
                    'wind_speed_10m' => 10.5,
                    'weather_code' => 2,
                    'precipitation' => 0.1,
                ],
                'current_units' => ['temperature_2m' => '°C'],
            ]),
        ]);

        $weatherService = new WeatherService();
        $weatherTool = new WeatherTool($weatherService);
        $result = $weatherTool->__invoke('New York');

        $this->assertStringContainsString('New York', $result);
        $this->assertStringContainsString('20°C', $result);
        $this->assertStringContainsString('70%', $result);
        $this->assertStringContainsString('Partly cloudy', $result);
    }

    public function test_forecast_tool_integration(): void
    {
        // Mock the weather API
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    ['latitude' => 51.5074, 'longitude' => -0.1278, 'name' => 'London', 'country' => 'United Kingdom'],
                ],
            ]),
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'daily' => [
                    'time' => [
                        now()->toDateString(),
                        now()->addDays(3)->toDateString(),
                    ],
                    'temperature_2m_max' => [18.0, 22.0],
                    'temperature_2m_min' => [12.0, 15.0],
                    'weather_code' => [3, 1],
                    'precipitation_sum' => [2.5, 0.0],
                ],
            ]),
        ]);

        $weatherService = new WeatherService();
        $forecastTool = new ForecastTool($weatherService);
        $result = $forecastTool->__invoke('London', 'in 3 days');

        $this->assertStringContainsString('London', $result);
        $this->assertStringContainsString('15°C to 22°C', $result);
        $this->assertStringContainsString('forecast', $result);
    }

    public function test_weather_tool_handles_invalid_location(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response(['results' => []])
        ]);

        $weatherService = new WeatherService();
        $weatherTool = new WeatherTool($weatherService);
        $result = $weatherTool->__invoke('InvalidLocation123');

        $this->assertStringContainsString('couldn\'t find', $result);
        $this->assertStringContainsString('InvalidLocation123', $result);
    }

    public function test_forecast_tool_validates_date_parameter(): void
    {
        $weatherService = new WeatherService();
        $forecastTool = new ForecastTool($weatherService);

        // Test invalid date string
        $result = $forecastTool->__invoke('London', '20');
        $this->assertStringContainsString('can\'t get a forecast for \'20\'', $result);

        // Test another invalid date
        $result = $forecastTool->__invoke('London', 'yesterday');
        $this->assertStringContainsString('can\'t get a forecast for \'yesterday\'', $result);
    }

    public function test_forecast_tool_accepts_valid_date_formats(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    ['latitude' => 51.5074, 'longitude' => -0.1278, 'name' => 'London', 'country' => 'United Kingdom'],
                ],
            ]),
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'daily' => [
                    'time' => [
                        now()->toDateString(),
                        now()->addDay()->toDateString(),
                    ],
                    'temperature_2m_max' => [18.0, 22.0],
                    'temperature_2m_min' => [12.0, 15.0],
                    'weather_code' => [3, 1],
                    'precipitation_sum' => [2.5, 0.0],
                ],
            ]),
        ]);

        $weatherService = new WeatherService();
        $forecastTool = new ForecastTool($weatherService);

        $result = $forecastTool->__invoke('London', 'tomorrow');
        $this->assertStringContainsString('London', $result);
        $this->assertStringContainsString('forecast', $result);
    }
}
