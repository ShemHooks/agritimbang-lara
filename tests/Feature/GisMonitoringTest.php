<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayGeometry;
use App\Models\FarmerProfile;
use App\Models\LguOfficerProfile;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GisMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function makeMunicipality(bool $active = true): Municipality
    {
        return Municipality::create([
            'name' => 'Municipality ' . uniqid(),
            'province' => 'Cebu',
            'is_active' => $active,
        ]);
    }

    private function makeBarangay(Municipality $municipality, bool $active = true): Barangay
    {
        return Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Barangay ' . uniqid(),
            'is_active' => $active,
        ]);
    }

    private function addGeometry(Barangay $barangay): void
    {
        BarangayGeometry::create([
            'barangay_id' => $barangay->id,
            'geometry_type' => 'Polygon',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [123.50, 10.00],
                        [123.51, 10.00],
                        [123.51, 10.01],
                        [123.50, 10.00],
                    ]
                ],
            ],
            'source' => 'test',
        ]);
    }

    private function makeUser(string $roleSlug, ?Municipality $municipality = null): User
    {
        $user = User::create([
            'first_name' => 'GIS',
            'last_name' => 'Tester',
            'email' => uniqid('gis_') . '@example.test',
            'password' => 'password123',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucwords(str_replace('_', ' ', $roleSlug))]
        );
        $user->roles()->attach($role->id);

        if ($municipality !== null) {
            LguOfficerProfile::create([
                'user_id' => $user->id,
                'municipality_id' => $municipality->id,
                'station' => 'Municipal Agriculture Office',
                'designation' => 'Livestock Officer',
            ]);
        }

        return $user;
    }

    private function makeSpecies(): Species
    {
        return Species::create([
            'name' => 'Species ' . uniqid(),
            'code' => 'SP' . strtoupper(substr(uniqid(), -6)),
        ]);
    }

    private function makeLivestock(Barangay $barangay, Species $species, string $status): LivestockRecord
    {
        $user = $this->makeUser('farmer');
        $farmer = FarmerProfile::create([
            'user_id' => $user->id,
            'municipality_id' => $barangay->municipality_id,
            'barangay_id' => $barangay->id,
        ]);

        return LivestockRecord::create([
            'farmer_id' => $farmer->id,
            'species_id' => $species->id,
            'sex' => 'male',
            'municipality_id' => $barangay->municipality_id,
            'barangay_id' => $barangay->id,
            'status' => $status,
            'created_by' => $user->id,
        ]);
    }

    public function test_public_municipalities_only_include_active_municipalities(): void
    {
        $active = $this->makeMunicipality(true);
        $inactive = $this->makeMunicipality(false);

        $this->getJson('/api/gis/municipalities')
            ->assertOk()
            ->assertJsonFragment(['id' => $active->id])
            ->assertJsonMissing(['id' => $inactive->id]);
    }

    public function test_public_barangays_exclude_inactive_municipalities_and_barangays(): void
    {
        $activeMunicipality = $this->makeMunicipality();
        $inactiveMunicipality = $this->makeMunicipality(false);
        $visible = $this->makeBarangay($activeMunicipality);
        $hiddenBarangay = $this->makeBarangay($activeMunicipality, false);
        $hiddenMunicipalityBarangay = $this->makeBarangay($inactiveMunicipality);

        $this->getJson('/api/gis/barangays')
            ->assertOk()
            ->assertJsonFragment(['id' => $visible->id])
            ->assertJsonMissing(['id' => $hiddenBarangay->id])
            ->assertJsonMissing(['id' => $hiddenMunicipalityBarangay->id]);
    }

    public function test_public_geojson_returns_only_active_boundaries_without_livestock_statistics(): void
    {
        $active = $this->makeMunicipality();
        $inactive = $this->makeMunicipality(false);
        $visible = $this->makeBarangay($active);
        $hidden = $this->makeBarangay($inactive);
        $this->addGeometry($visible);
        $this->addGeometry($hidden);
        $this->makeLivestock($visible, $this->makeSpecies(), 'available');

        $response = $this->getJson('/api/gis/geojson')
            ->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.id', $visible->id)
            ->assertJsonPath('features.0.geometry.type', 'Polygon');

        $properties = $response->json('features.0.properties');
        $this->assertArrayNotHasKey('total_livestock', $properties);
        $this->assertArrayNotHasKey('available', $properties);
        $this->assertArrayNotHasKey('sold', $properties);
        $this->assertArrayNotHasKey('archived', $properties);
    }

    public function test_monitoring_endpoints_require_authentication(): void
    {
        $this->getJson('/api/gis/monitoring-geojson')->assertUnauthorized();
        $this->getJson('/api/gis/livestock-summary')->assertUnauthorized();
    }

    public function test_farmer_cannot_access_monitoring_endpoints(): void
    {
        Sanctum::actingAs($this->makeUser('farmer'));

        $this->getJson('/api/gis/monitoring-geojson')->assertForbidden();
        $this->getJson('/api/gis/livestock-summary')->assertForbidden();
    }

    public function test_admin_can_monitor_multiple_municipalities(): void
    {
        $first = $this->makeMunicipality();
        $second = $this->makeMunicipality(false);
        $firstBarangay = $this->makeBarangay($first);
        $secondBarangay = $this->makeBarangay($second);
        $this->addGeometry($firstBarangay);
        $this->addGeometry($secondBarangay);
        $species = $this->makeSpecies();
        $this->makeLivestock($firstBarangay, $species, 'available');
        $this->makeLivestock($secondBarangay, $species, 'sold');
        Sanctum::actingAs($this->makeUser('admin'));

        $this->getJson('/api/gis/monitoring-geojson')
            ->assertOk()
            ->assertJsonCount(2, 'features');

        $this->getJson('/api/gis/livestock-summary')
            ->assertOk()
            ->assertJsonPath('data.total_livestock', 2)
            ->assertJsonPath('data.by_status.available', 1)
            ->assertJsonPath('data.by_status.sold', 1);
    }

    public function test_admin_can_filter_monitoring_by_municipality(): void
    {
        $first = $this->makeMunicipality();
        $second = $this->makeMunicipality();
        $species = $this->makeSpecies();
        $this->makeLivestock($this->makeBarangay($first), $species, 'available');
        $this->makeLivestock($this->makeBarangay($second), $species, 'sold');
        Sanctum::actingAs($this->makeUser('admin'));

        $this->getJson('/api/gis/livestock-summary?municipality_id=' . $first->id)
            ->assertOk()
            ->assertJsonPath('data.total_livestock', 1)
            ->assertJsonPath('data.by_status.available', 1)
            ->assertJsonPath('data.by_status.sold', 0);
    }

    public function test_lgu_authority_is_automatically_scoped_to_assigned_municipality(): void
    {
        $assigned = $this->makeMunicipality();
        $other = $this->makeMunicipality();
        $species = $this->makeSpecies();
        $this->makeLivestock($this->makeBarangay($assigned), $species, 'available');
        $this->makeLivestock($this->makeBarangay($other), $species, 'sold');
        Sanctum::actingAs($this->makeUser('lgu_authority', $assigned));

        $this->getJson('/api/gis/livestock-summary')
            ->assertOk()
            ->assertJsonPath('data.total_livestock', 1)
            ->assertJsonPath('data.by_status.available', 1)
            ->assertJsonPath('data.by_status.sold', 0);
    }

    public function test_lgu_encoder_can_monitor_assigned_municipality(): void
    {
        $municipality = $this->makeMunicipality();
        $barangay = $this->makeBarangay($municipality);
        $this->addGeometry($barangay);
        $this->makeLivestock($barangay, $this->makeSpecies(), 'available');
        Sanctum::actingAs($this->makeUser('lgu_encoder', $municipality));

        $this->getJson('/api/gis/monitoring-geojson')
            ->assertOk()
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.total_livestock', 1)
            ->assertJsonPath('features.0.properties.available', 1);
    }

    public function test_lgu_cannot_request_another_municipality(): void
    {
        $assigned = $this->makeMunicipality();
        $other = $this->makeMunicipality();
        Sanctum::actingAs($this->makeUser('lgu_authority', $assigned));

        $this->getJson('/api/gis/livestock-summary?municipality_id=' . $other->id)
            ->assertForbidden();
        $this->getJson('/api/gis/monitoring-geojson?municipality_id=' . $other->id)
            ->assertForbidden();
    }

    public function test_lgu_cannot_monitor_inactive_assigned_municipality(): void
    {
        $inactive = $this->makeMunicipality(false);
        Sanctum::actingAs($this->makeUser('lgu_authority', $inactive));

        $this->getJson('/api/gis/livestock-summary')->assertForbidden();
        $this->getJson('/api/gis/monitoring-geojson')->assertForbidden();
    }

    public function test_lgu_without_officer_profile_cannot_monitor(): void
    {
        Sanctum::actingAs($this->makeUser('lgu_encoder'));

        $this->getJson('/api/gis/livestock-summary')->assertForbidden();
    }

    public function test_lgu_cannot_filter_summary_by_barangay_outside_its_municipality(): void
    {
        $assigned = $this->makeMunicipality();
        $other = $this->makeMunicipality();
        $otherBarangay = $this->makeBarangay($other);
        Sanctum::actingAs($this->makeUser('lgu_authority', $assigned));

        $this->getJson('/api/gis/livestock-summary?barangay_id=' . $otherBarangay->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['barangay_id']);
    }

    public function test_livestock_summary_aggregates_status_and_species_filters(): void
    {
        $municipality = $this->makeMunicipality();
        $barangay = $this->makeBarangay($municipality);
        $cattle = $this->makeSpecies();
        $goats = $this->makeSpecies();
        foreach (['available', 'in_transaction', 'sold', 'archived'] as $status) {
            $this->makeLivestock($barangay, $cattle, $status);
        }
        $this->makeLivestock($barangay, $goats, 'available');
        Sanctum::actingAs($this->makeUser('admin'));

        $this->getJson('/api/gis/livestock-summary?municipality_id=' . $municipality->id)
            ->assertOk()
            ->assertJsonPath('data.total_livestock', 5)
            ->assertJsonPath('data.by_status.available', 2)
            ->assertJsonPath('data.by_status.in_transaction', 1)
            ->assertJsonPath('data.by_status.sold', 1)
            ->assertJsonPath('data.by_status.archived', 1)
            ->assertJsonCount(2, 'data.by_species');

        $this->getJson('/api/gis/livestock-summary?species_id=' . $cattle->id)
            ->assertOk()
            ->assertJsonPath('data.total_livestock', 4)
            ->assertJsonCount(1, 'data.by_species');
    }

    public function test_monitoring_geojson_contains_correct_status_counts(): void
    {
        $municipality = $this->makeMunicipality();
        $barangay = $this->makeBarangay($municipality);
        $this->addGeometry($barangay);
        $species = $this->makeSpecies();
        foreach (['available', 'in_transaction', 'sold', 'archived'] as $status) {
            $this->makeLivestock($barangay, $species, $status);
        }
        Sanctum::actingAs($this->makeUser('admin'));

        $this->getJson('/api/gis/monitoring-geojson?municipality_id=' . $municipality->id)
            ->assertOk()
            ->assertJsonPath('features.0.properties.total_livestock', 4)
            ->assertJsonPath('features.0.properties.available', 1)
            ->assertJsonPath('features.0.properties.in_transaction', 1)
            ->assertJsonPath('features.0.properties.sold', 1)
            ->assertJsonPath('features.0.properties.archived', 1);
    }

    public function test_summary_rejects_barangay_not_belonging_to_selected_municipality(): void
    {
        $first = $this->makeMunicipality();
        $second = $this->makeMunicipality();
        $barangay = $this->makeBarangay($second);
        Sanctum::actingAs($this->makeUser('admin'));

        $this->getJson('/api/gis/livestock-summary?municipality_id=' . $first->id . '&barangay_id=' . $barangay->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['barangay_id']);
    }
}
