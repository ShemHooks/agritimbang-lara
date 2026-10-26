<?php

namespace Tests\Feature;

use App\Models\PriceReference;
use App\Services\ArimaEngine;
use Carbon\CarbonImmutable;
use Mockery;

class ForecastTest extends AnalyticsTestCase
{
    private function seedWeeklyPrices(
        $municipality,
        $species,
        int $weeks = 12
    ): void {
        $start = CarbonImmutable::parse('2026-07-06');

        for ($i = 0; $i < $weeks; $i++) {
            $this->price(
                $municipality,
                $species,
                'official',
                180 + $i,
                $start->addWeeks($i)->toDateString()
            );
        }
    }

    private function mockForecast(int $weeks = 4): void
    {
        $predictions = array_fill(0, $weeks, [
            'predicted_price' => 200.0,
            'lower_95' => 180.0,
            'upper_95' => 220.0,
        ]);

        $engine = Mockery::mock(ArimaEngine::class);

        $engine->shouldReceive('forecast')
            ->once()
            ->withArgs(
                fn($prices, $horizon) =>
                    count($prices) === 12 &&
                    $horizon === $weeks
            )
            ->andReturn([
                'order' => [1, 1, 0],
                'predictions' => $predictions,
            ]);

        $this->app->instance(ArimaEngine::class, $engine);
    }

    private function forecastUrl(
        $municipality,
        $species,
        int $weeks = 4
    ): string {
        return '/api/analytics/forecast?' . http_build_query([
            'municipality_id' => $municipality->id,
            'species_id' => $species->id,
            'sale_purpose' => 'slaughter',
            'weeks' => $weeks,
        ]);
    }

    public function test_forecast_requires_authentication(): void
    {
        $this->getJson('/api/analytics/forecast')
            ->assertUnauthorized();
    }

    public function test_farmer_cannot_access_forecast(): void
    {
        $this->authenticate('farmer');

        $this->getJson('/api/analytics/forecast')
            ->assertForbidden();
    }

    public function test_admin_must_choose_municipality(): void
    {
        $species = $this->species();

        $this->authenticate('admin');

        $this->getJson(
            '/api/analytics/forecast?species_id=' . $species->id
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('municipality_id');
    }

    public function test_forecast_rejects_insufficient_history(): void
    {
        $municipality = $this->municipality();
        $species = $this->species();

        $this->authenticate('admin');

        $this->seedWeeklyPrices($municipality, $species, 5);

        $this->getJson(
            $this->forecastUrl($municipality, $species)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('history')
            ->assertJsonPath(
                'errors.history.0',
                'At least 12 weeks of official price observations are required; found 5.'
            );
    }

    public function test_forecast_rejects_missing_weeks(): void
    {
        $municipality = $this->municipality();
        $species = $this->species();

        $this->authenticate('admin');

        $this->seedWeeklyPrices($municipality, $species);

        PriceReference::query()
            ->where('municipality_id', $municipality->id)
            ->where('species_id', $species->id)
            ->whereDate('effective_from', '2026-08-03')
            ->delete();

        // Keep at least 12 observations so the gap validation
        // is reached instead of the minimum-history validation.
        $this->price(
            $municipality,
            $species,
            'official',
            192,
            '2026-09-28'
        );

        $this->getJson(
            $this->forecastUrl($municipality, $species)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('history')
            ->assertJsonPath(
                'errors.history.0',
                'Official price history contains missing weeks. Supply consecutive weekly observations before forecasting.'
            );
    }

    public function test_admin_gets_weekly_forecast(): void
    {
        $municipality = $this->municipality();
        $species = $this->species();

        $this->seedWeeklyPrices($municipality, $species);

        $this->authenticate('admin');

        $this->mockForecast();

        $this->getJson(
            $this->forecastUrl($municipality, $species)
        )
            ->assertOk()
            ->assertJsonCount(12, 'data.historical')
            ->assertJsonCount(4, 'data.forecast')
            ->assertJsonPath('data.sale_purpose', 'slaughter')
            ->assertJsonPath(
                'data.forecast.0.week',
                '2026-09-28'
            )
            ->assertJsonPath(
                'data.forecast.0.predicted_price',
                200
            );
    }

    public function test_lgu_cannot_forecast_another_municipality(): void
    {
        $own = $this->municipality();
        $other = $this->municipality();
        $species = $this->species();

        $this->authenticate('lgu_encoder', $own);

        $this->getJson(
            $this->forecastUrl($other, $species)
        )
            ->assertForbidden();
    }

    public function test_lgu_can_forecast_assigned_municipality_without_explicit_filter(): void
    {
        $municipality = $this->municipality();
        $species = $this->species();

        $this->seedWeeklyPrices($municipality, $species);

        $this->authenticate('lgu_authority', $municipality);

        $this->mockForecast(2);

        $this->getJson(
            '/api/analytics/forecast?' . http_build_query([
                'species_id' => $species->id,
                'sale_purpose' => 'slaughter',
                'weeks' => 2,
            ])
        )
            ->assertOk()
            ->assertJsonPath(
                'data.municipality_id',
                $municipality->id
            )
            ->assertJsonCount(2, 'data.forecast');
    }

    public function test_invalid_forecast_horizon_is_rejected(): void
    {
        $municipality = $this->municipality();
        $species = $this->species();

        $this->authenticate('admin');

        $this->getJson(
            $this->forecastUrl($municipality, $species, 13)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('weeks');
    }

    public function test_draft_prices_are_not_used(): void
    {
        $municipality = $this->municipality();
        $species = $this->species();

        $this->authenticate('admin');

        $start = CarbonImmutable::parse('2026-07-06');

        for ($i = 0; $i < 12; $i++) {
            $this->price(
                $municipality,
                $species,
                'draft',
                180 + $i,
                $start->addWeeks($i)->toDateString()
            );
        }

        $this->getJson(
            $this->forecastUrl($municipality, $species)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('history');
    }
}
