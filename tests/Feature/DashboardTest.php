<?php

namespace Tests\Feature;

class DashboardTest extends AnalyticsTestCase
{
    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/analytics/dashboard')->assertUnauthorized();
    }

    public function test_farmer_cannot_access_dashboard(): void
    {
        $this->authenticate('farmer');
        $this->getJson('/api/analytics/dashboard')->assertForbidden();
    }

    public function test_admin_can_access_all_five_analytics_endpoints(): void
    {
        $this->authenticate('admin');
        foreach (['dashboard', 'livestock', 'prices', 'transactions', 'valuations'] as $endpoint) {
            $this->getJson('/api/analytics/'.$endpoint)->assertOk()->assertJsonStructure(['data']);
        }
    }

    public function test_dashboard_counts_livestock_farmers_and_verified_sales(): void
    {
        $municipality = $this->municipality();
        $barangay = $this->barangay($municipality);
        $farmer = $this->farmer($municipality, $barangay);
        $species = $this->species();
        $this->livestock($farmer, $species, $municipality, $barangay, 'available');
        $this->livestock($farmer, $species, $municipality, $barangay, 'sold');
        $this->transaction($farmer, $municipality, $barangay, 'verified', 1200);
        $this->transaction($farmer, $municipality, $barangay, 'pending_lgu_verification', 500);
        $this->authenticate('admin');

        $this->getJson('/api/analytics/dashboard')->assertOk()
            ->assertJsonPath('data.registered_farmers', 1)
            ->assertJsonPath('data.total_livestock', 2)
            ->assertJsonPath('data.livestock_by_status.available', 1)
            ->assertJsonPath('data.livestock_by_status.sold', 1)
            ->assertJsonPath('data.verified_transactions', 1)
            ->assertJsonPath('data.verified_sales_total', 1200);
    }

    public function test_lgu_dashboard_is_scoped_to_assigned_municipality(): void
    {
        $first = $this->municipality();
        $second = $this->municipality();
        $species = $this->species();
        foreach ([$first, $second] as $municipality) {
            $barangay = $this->barangay($municipality);
            $farmer = $this->farmer($municipality, $barangay);
            $this->livestock($farmer, $species, $municipality, $barangay);
        }
        $this->authenticate('lgu_authority', $first);
        $this->getJson('/api/analytics/dashboard')->assertOk()
            ->assertJsonPath('data.total_livestock', 1)
            ->assertJsonPath('data.registered_farmers', 1);
        $this->getJson('/api/analytics/dashboard?municipality_id='.$second->id)->assertForbidden();
    }

    public function test_lgu_encoder_can_read_assigned_municipality(): void
    {
        $municipality = $this->municipality();
        $this->authenticate('lgu_encoder', $municipality);
        $this->getJson('/api/analytics/dashboard')->assertOk();
    }

    public function test_inactive_municipality_blocks_lgu_analytics(): void
    {
        $municipality = $this->municipality(false);
        $this->authenticate('lgu_authority', $municipality);
        $this->getJson('/api/analytics/dashboard')->assertForbidden();
    }

    public function test_livestock_species_distribution_and_municipality_filter(): void
    {
        $municipality = $this->municipality();
        $barangay = $this->barangay($municipality);
        $farmer = $this->farmer($municipality, $barangay);
        $species = $this->species();
        $this->livestock($farmer, $species, $municipality, $barangay);
        $this->livestock($farmer, $species, $municipality, $barangay);
        $this->authenticate('admin');
        $this->getJson('/api/analytics/livestock?municipality_id='.$municipality->id)
            ->assertOk()->assertJsonCount(1, 'data.by_species')
            ->assertJsonPath('data.by_species.0.total', 2)
            ->assertJsonCount(1, 'data.by_barangay');
    }

    public function test_transaction_analytics_separate_verified_and_pending(): void
    {
        $municipality = $this->municipality();
        $barangay = $this->barangay($municipality);
        $farmer = $this->farmer($municipality, $barangay);
        $this->transaction($farmer, $municipality, $barangay, 'verified', 1000);
        $this->transaction($farmer, $municipality, $barangay, 'pending_lgu_verification', 9000);
        $this->authenticate('admin');
        $this->getJson('/api/analytics/transactions')->assertOk()
            ->assertJsonCount(2, 'data.by_status')
            ->assertJsonCount(1, 'data.verified_monthly')
            ->assertJsonPath('data.verified_monthly.0.transaction_count', 1);
    }

    public function test_invalid_municipality_id_is_rejected(): void
    {
        $this->authenticate('admin');
        $this->getJson('/api/analytics/dashboard?municipality_id=not-a-uuid')->assertUnprocessable();
    }
}
