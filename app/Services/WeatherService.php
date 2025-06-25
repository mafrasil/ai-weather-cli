<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const GEOCODING_URL = 'https://geocoding-api.open-meteo.com/v1/search';
    private const WEATHER_URL = 'https://api.open-meteo.com/v1/forecast';

    public function getCurrentWeather(string $location): string
    {
        try {
            $coordinates = $this->getCoordinates($location);

            if (!$coordinates) {
                return "I couldn't find the location '{$location}'. Please check the spelling or try a different format.";
            }

            $weatherData = $this->fetchWeatherData($coordinates['latitude'], $coordinates['longitude']);

            if (!$weatherData) {
                return "I couldn't retrieve weather data for {$location} right now. Please try again later.";
            }

            return $this->formatWeatherResponse($location, $weatherData);

        } catch (\Exception $e) {
            Log::error('Weather service error', ['error' => $e->getMessage(), 'location' => $location]);
            return "I encountered an error while fetching weather data for {$location}. Please try again.";
        }
    }

    public function getWeatherForecast(string $location, int $daysInFuture): string
    {
        if ($daysInFuture <= 0 || $daysInFuture > 16) {
            return 'I can only provide forecasts for the next 16 days.';
        }

        try {
            $coordinates = $this->getCoordinates($location);

            if (!$coordinates) {
                return "I couldn't find the location '{$location}'. Please check the spelling or try a different format.";
            }

            $weatherData = $this->fetchForecastData($coordinates['latitude'], $coordinates['longitude'], $daysInFuture);

            if (!$weatherData) {
                return "I couldn't retrieve the forecast for {$location} right now. Please try again later.";
            }

            return $this->formatForecastResponse($location, $weatherData, $daysInFuture);

        } catch (\Exception $e) {
            Log::error('Weather forecast service error', ['error' => $e->getMessage(), 'location' => $location]);

            return "I encountered an error while fetching the weather forecast for {$location}. Please try again.";
        }
    }

    private function getCoordinates(string $location): ?array
    {
        $response = Http::get(self::GEOCODING_URL, [
            'name' => $location,
            'count' => 1,
            'language' => 'en',
            'format' => 'json',
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        if (empty($data['results'])) {
            return null;
        }

        $result = $data['results'][0];

        return [
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'name' => $result['name'],
            'country' => $result['country'] ?? '',
        ];
    }

    private function fetchWeatherData(float $latitude, float $longitude): ?array
    {
        $response = Http::get(self::WEATHER_URL, [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m',
            'timezone' => 'auto',
        ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    private function fetchForecastData(float $latitude, float $longitude, int $daysInFuture): ?array
    {
        $response = Http::get(self::WEATHER_URL, [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum',
            'timezone' => 'auto',
            'forecast_days' => $daysInFuture + 1,
        ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    private function formatWeatherResponse(string $location, array $weatherData): string
    {
        $current = $weatherData['current'];
        $units = $weatherData['current_units'];

        $temperature = round($current['temperature_2m']);
        $feelsLike = round($current['apparent_temperature']);
        $humidity = $current['relative_humidity_2m'];
        $windSpeed = round($current['wind_speed_10m']);
        $precipitation = $current['precipitation'];

        $weatherDescription = $this->getWeatherDescription($current['weather_code']);

        $response = "Current weather in {$location}:\n";
        $response .= "🌡️ Temperature: {$temperature}°C (feels like {$feelsLike}°C)\n";
        $response .= "🌤️ Conditions: {$weatherDescription}\n";
        $response .= "💧 Humidity: {$humidity}%\n";
        $response .= "💨 Wind: {$windSpeed} km/h\n";

        if ($precipitation > 0) {
            $response .= "🌧️ Precipitation: {$precipitation} mm\n";
        }

        return trim($response);
    }

    private function formatForecastResponse(string $location, array $weatherData, int $daysInFuture): string
    {
        $targetDate = now()->addDays($daysInFuture)->toDateString();
        $daily = $weatherData['daily'];

        $dateIndex = array_search($targetDate, $daily['time']);

        if ($dateIndex === false) {
            return "I couldn't find forecast data for {$targetDate} for {$location}.";
        }

        $weatherCode = $daily['weather_code'][$dateIndex];
        $weatherDescription = $this->getWeatherDescription($weatherCode);
        $maxTemp = round($daily['temperature_2m_max'][$dateIndex]);
        $minTemp = round($daily['temperature_2m_min'][$dateIndex]);
        $precipitation = $daily['precipitation_sum'][$dateIndex];

        $formattedDate = now()->addDays($daysInFuture)->format('l, F jS');

        $response = "Weather forecast for {$location} on {$formattedDate}:\n";
        $response .= "🌡️ Temperature: {$minTemp}°C to {$maxTemp}°C\n";
        $response .= "🌤️ Conditions: {$weatherDescription}\n";

        if ($precipitation > 0) {
            $response .= "🌧️ Precipitation: {$precipitation} mm expected\n";
        } else {
            $response .= "💧 Precipitation: No precipitation expected\n";
        }

        return trim($response);
    }

    private function getWeatherDescription(int $weatherCode): string
    {
        $weatherCodes = [
            0 => 'Clear sky',
            1 => 'Mainly clear',
            2 => 'Partly cloudy',
            3 => 'Overcast',
            45 => 'Fog',
            48 => 'Depositing rime fog',
            51 => 'Light drizzle',
            53 => 'Moderate drizzle',
            55 => 'Dense drizzle',
            56 => 'Light freezing drizzle',
            57 => 'Dense freezing drizzle',
            61 => 'Slight rain',
            63 => 'Moderate rain',
            65 => 'Heavy rain',
            66 => 'Light freezing rain',
            67 => 'Heavy freezing rain',
            71 => 'Slight snow fall',
            73 => 'Moderate snow fall',
            75 => 'Heavy snow fall',
            77 => 'Snow grains',
            80 => 'Slight rain showers',
            81 => 'Moderate rain showers',
            82 => 'Violent rain showers',
            85 => 'Slight snow showers',
            86 => 'Heavy snow showers',
            95 => 'Thunderstorm',
            96 => 'Thunderstorm with slight hail',
            99 => 'Thunderstorm with heavy hail',
        ];

        return $weatherCodes[$weatherCode] ?? 'Unknown conditions';
    }
}
