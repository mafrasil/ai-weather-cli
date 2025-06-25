<?php

namespace App\Tools;

use App\Services\WeatherService;
use Prism\Prism\Tool;

class WeatherTool extends Tool
{
    public function __construct(private WeatherService $weatherService)
    {
        $this
            ->as('get_current_weather')
            ->for('Get current weather conditions for a specific location')
            ->withStringParameter('location', 'City name or location (e.g., "London", "Paris", "New York")')
            ->using($this);
    }

    public function __invoke(string $location): string
    {
        return $this->weatherService->getCurrentWeather($location);
    }
}
