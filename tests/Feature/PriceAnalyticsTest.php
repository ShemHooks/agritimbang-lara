<?php

namespace Tests\Feature;

class PriceAnalyticsTest extends AnalyticsTestCase
{
    public function test_prices_require_authentication(): void
    {
        $this->getJson('/api/analytics/prices')->assertUnauthorized();
    }

    public function test_farmer_cannot_view_price_analytics(): void
    {
        $this->authenticate('farmer');
        $this->getJson('/api/analytics/prices')->assertForbidden();
    }

    public function test_only_official_prices_appear_in_history(): void
    {
        $municipality = $this->municipality();
        $species = $this->species();
        $official = $this->price($municipality, $species, 'official', 185);
        $this->price($municipality, $species, 'draft', 190);
        $this->authenticate('admin');
        $this->getJson('/api/analytics/prices')->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.points.0.id', $official->id)
            ->assertJsonPath('data.points.0.price_per_kg', 185);
    }

    public function test_prices_can_be_filtered_by_species(): void
    {
        $municipality = $this->municipality();
        $cattle = $this->species();
        $goat = $this->species();
        $this->price($municipality, $cattle, 'official', 180);
        $this->price($municipality, $goat, 'official', 220);
        $this->authenticate('admin');
        $this->getJson('/api/analytics/prices?species_id='.$cattle->id)->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.points.0.species_id', $cattle->id);
    }

    public function test_lgu_price_history_excludes_other_municipalities(): void
    {
        $first = $this->municipality();
        $second = $this->municipality();
        $species = $this->species();
        $this->price($first, $species, 'official', 180);
        $this->price($second, $species, 'official', 240);
        $this->authenticate('lgu_encoder', $first);
        $this->getJson('/api/analytics/prices')->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.points.0.municipality_id', $first->id);
        $this->getJson('/api/analytics/prices?municipality_id='.$second->id)->assertForbidden();
    }

    public function test_admin_can_filter_price_history_by_municipality(): void
    {
        $first = $this->municipality();
        $second = $this->municipality();
        $species = $this->species();
        $this->price($first, $species, 'official', 180);
        $this->price($second, $species, 'official', 240);
        $this->authenticate('admin');
        $this->getJson('/api/analytics/prices?municipality_id='.$second->id)->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.points.0.municipality_id', $second->id);
    }

    public function test_price_history_is_ordered_by_effective_date(): void
    {
        $municipality = $this->municipality();
        $species = $this->species();
        $this->price($municipality, $species, 'official', 200, '2026-10-14');
        $this->price($municipality, $species, 'official', 180, '2026-10-01');
        $this->authenticate('admin');
        $this->getJson('/api/analytics/prices')->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.points.0.effective_from', '2026-10-01')
            ->assertJsonPath('data.points.1.effective_from', '2026-10-14');
    }

    public function test_invalid_species_id_is_rejected(): void
    {
        $this->authenticate('admin');
        $this->getJson('/api/analytics/prices?species_id=invalid')->assertUnprocessable();
    }
}
