<?php

namespace App\Tools;

use App\Services\WeatherService;
use Carbon\Carbon;
use Prism\Prism\Tool;

class ForecastTool extends Tool
{
    public function __construct(private WeatherService $weatherService)
    {
        $this
            ->as('get_weather_forecast')
            ->for('Get the weather forecast for a specific location on a future date.')
            ->withStringParameter('location', 'City name or location (e.g., "London", "Paris").')
            ->withStringParameter('date', 'A future date, like "tomorrow", "in 2 days", or "next Monday".')
            ->using($this);
    }

    public function __invoke(string $location, string $date): string
    {
        $daysInFuture = $this->parseDateToDays($date);

        if ($daysInFuture === null) {
            return "I can't get a forecast for '{$date}'. I can only get forecasts for future dates. For current weather, please ask without specifying a date.";
        }

        return $this->weatherService->getWeatherForecast($location, $daysInFuture);
    }

    private function parseDateToDays(string $date): ?int
    {
        $date = strtolower(trim($date));
        $now = Carbon::today();

        try {
            if ($date === 'tomorrow') {
                return 1;
            }

            if (str_starts_with($date, 'in ') && str_ends_with($date, ' days')) {
                $days = (int) filter_var($date, FILTER_SANITIZE_NUMBER_INT);

                return $days > 0 ? $days : null;
            }

            $parsedDate = Carbon::parse($date)->startOfDay();
            if ($parsedDate->isFuture()) {
                return $parsedDate->diffInDays($now);
            }
        } catch (\Exception) {
            return null;
        }

        return null;
    }
}
