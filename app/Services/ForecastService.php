<?php

namespace App\Services;

use App\Models\PriceReference;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ForecastService
{
    public function __construct(private readonly ArimaEngine $engine) {}

    public function forecast(string $municipalityId, string $speciesId, int $weeks, ?string $salePurpose = null): array
    {
        $minimum = (int) config('forecast.minimum_observations', 12);
        $maximum = (int) config('forecast.maximum_observations', 104);

        // Restrict to a single municipality, species and sale-purpose category.
        // NULL sale purpose is its own category; it is not combined with named purposes.
        $query = PriceReference::query()
            ->where('status', 'official')
            ->where('municipality_id', $municipalityId)
            ->where('species_id', $speciesId)
            ->whereNotNull('effective_from');

        $salePurpose === null
            ? $query->whereNull('sale_purpose')
            : $query->where('sale_purpose', $salePurpose);

        $rows = $query->orderBy('effective_from')->get(['effective_from', 'price_per_kg']);
        $weekly = [];

        foreach ($rows as $row) {
            $price = (float) $row->price_per_kg;
            if (!is_finite($price) || $price <= 0) {
                continue;
            }
            $week = CarbonImmutable::parse($row->effective_from)->startOfWeek()->toDateString();
            $weekly[$week][] = $price;
        }

        ksort($weekly);
        $history = [];
        foreach ($weekly as $week => $prices) {
            $history[] = ['week' => $week, 'price' => round(array_sum($prices) / count($prices), 2)];
        }
        $history = array_slice($history, -$maximum);

        if (count($history) < $minimum) {
            throw ValidationException::withMessages([
                'history' => "At least {$minimum} weeks of official price observations are required; found ".count($history).'.',
            ]);
        }

        // Do not invent prices for missing weeks; reject gaps in the observed time series.
        for ($i = 1; $i < count($history); $i++) {
            $expected = CarbonImmutable::parse($history[$i - 1]['week'])->addWeek()->toDateString();
            if ($history[$i]['week'] !== $expected) {
                throw ValidationException::withMessages([
                    'history' => 'Official price history contains missing weeks. Supply consecutive weekly observations before forecasting.',
                ]);
            }
        }

        $result = $this->engine->forecast(array_column($history, 'price'), $weeks);
        $lastWeek = CarbonImmutable::parse(end($history)['week']);
        $forecast = [];
        foreach ($result['predictions'] as $index => $point) {
            $forecast[] = [
                'week' => $lastWeek->addWeeks($index + 1)->toDateString(),
                'predicted_price' => round((float) $point['predicted_price'], 2),
                'lower_95' => round((float) $point['lower_95'], 2),
                'upper_95' => round((float) $point['upper_95'], 2),
            ];
        }

        return [
            'municipality_id' => $municipalityId,
            'species_id' => $speciesId,
            'sale_purpose' => $salePurpose,
            'unit' => 'PHP/kg',
            'model' => 'ARIMA',
            'order' => $result['order'],
            'historical' => $history,
            'forecast' => $forecast,
            'disclaimer' => 'Statistical estimates only; not guaranteed market prices.',
        ];
    }
}
