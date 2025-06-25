<?php

namespace Tests\Unit;

use App\Services\WeatherService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherServiceTest extends TestCase
{
    public function test_weather_service_exists(): void
    {
        $weatherService = new WeatherService();
        $this->assertInstanceOf(WeatherService::class, $weatherService);
    }

    public function test_can_get_current_weather(): void
    {
        // Mock the HTTP calls to Open-Meteo API
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    [
                        'latitude' => 38.7167,
                        'longitude' => -9.1333,
                        'name' => 'Lisbon',
                        'country' => 'Portugal',
                    ],
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
                'current_units' => [
                    'temperature_2m' => '°C',
                ],
            ]),
        ]);

        $weatherService = new WeatherService();
        $result = $weatherService->getCurrentWeather('Lisbon');

        $this->assertStringContainsString('Lisbon', $result);
        $this->assertStringContainsString('23°C', $result); // rounded temperature
        $this->assertStringContainsString('65%', $result);
        $this->assertStringContainsString('Mainly clear', $result);
    }

    public function test_handles_invalid_location_gracefully(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response(['results' => []])
        ]);

        $weatherService = new WeatherService();
        $result = $weatherService->getCurrentWeather('InvalidCity123');

        $this->assertStringContainsString("couldn't find the location", $result);
        $this->assertStringContainsString('InvalidCity123', $result);
    }

    public function test_can_get_weather_forecast(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    [
                        'latitude' => 38.7167,
                        'longitude' => -9.1333,
                        'name' => 'Lisbon',
                        'country' => 'Portugal',
                    ],
                ],
            ]),
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'daily' => [
                    'time' => [
                        now()->toDateString(),
                        now()->addDay()->toDateString(),
                        now()->addDays(2)->toDateString(),
                    ],
                    'temperature_2m_max' => [25.2, 27.1, 24.8],
                    'temperature_2m_min' => [18.3, 20.1, 17.9],
                    'weather_code' => [1, 2, 3],
                    'precipitation_sum' => [0, 0.5, 1.2],
                ],
            ]),
        ]);

        $weatherService = new WeatherService();
        $result = $weatherService->getWeatherForecast('Lisbon', 1);

        $this->assertStringContainsString('Lisbon', $result);
        $this->assertStringContainsString('20°C to 27°C', $result);
        $this->assertStringContainsString('forecast', $result);
    }

    public function test_handles_invalid_forecast_days(): void
    {
        $weatherService = new WeatherService();
        $result = $weatherService->getWeatherForecast('Lisbon', 0);

        $this->assertStringContainsString('can only provide forecasts for the next 16 days', $result);

        $result = $weatherService->getWeatherForecast('Lisbon', 20);
        $this->assertStringContainsString('can only provide forecasts for the next 16 days', $result);
    }
}
