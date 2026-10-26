<?php

namespace Tests\Feature;

use App\Models\PriceReference;
use App\Services\ForecastService;
use App\Services\ReportExportService;
use Illuminate\Http\Response;
use Mockery;

class ReportTest extends AnalyticsTestCase
{
    private function url(array $params): string
    {
        return '/api/analytics/reports/export?'.http_build_query($params);
    }

    public function test_authentication_is_required(): void
    {
        $this->getJson($this->url(['type' => 'prices', 'format' => 'pdf']))->assertUnauthorized();
    }

    public function test_farmer_is_forbidden(): void
    {
        $this->authenticate('farmer');
        $this->getJson($this->url(['type' => 'prices', 'format' => 'pdf']))->assertForbidden();
    }

    public function test_admin_must_select_municipality(): void
    {
        $this->authenticate('admin');
        $this->getJson($this->url(['type' => 'prices', 'format' => 'pdf']))
            ->assertUnprocessable()->assertJsonValidationErrors('municipality_id');
    }

    public function test_lgu_cannot_export_other_municipality(): void
    {
        $own = $this->municipality();
        $other = $this->municipality();
        $this->authenticate('lgu_encoder', $own);
        $this->getJson($this->url(['type' => 'prices', 'format' => 'pdf', 'municipality_id' => $other->id]))
            ->assertForbidden();
    }

    public function test_rejects_invalid_format_and_type(): void
    {
        $this->authenticate('admin');
        $this->getJson($this->url(['type' => 'unknown', 'format' => 'csv']))
            ->assertUnprocessable()->assertJsonValidationErrors(['type', 'format']);
    }

    public function test_rejects_inverted_dates(): void
    {
        $m = $this->municipality();
        $this->authenticate('admin');
        $this->getJson($this->url(['type' => 'prices', 'format' => 'pdf', 'municipality_id' => $m->id,
            'date_from' => '2026-10-20', 'date_to' => '2026-10-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }

    public function test_official_prices_only_and_municipality_scoping(): void
    {
        $m = $this->municipality();
        $other = $this->municipality();
        $s = $this->species();
        $this->price($m, $s, 'official', 200, '2026-10-05');
        $this->price($m, $s, 'draft', 100, '2026-10-05');
        $this->price($other, $s, 'official', 300, '2026-10-05');
        $this->authenticate('lgu_authority', $m);
        $exporter = Mockery::mock(ReportExportService::class);
        $exporter->shouldReceive('download')->once()->withArgs(function ($report, $format) use ($m) {
            return $format === 'pdf' && $report['municipality_id'] === $m->id
                && $report['count'] === 1 && (float) $report['rows'][0]['price_per_kg'] === 200.0;
        })->andReturn(response('mock report', 200));
        $this->app->instance(ReportExportService::class, $exporter);
        $this->get($this->url(['type' => 'prices', 'format' => 'pdf']))->assertOk();
    }

    public function test_pdf_download_has_correct_signature(): void
    {
        $m = $this->municipality();
        $this->authenticate('admin');
        $response = $this->get($this->url(['type' => 'prices', 'format' => 'pdf', 'municipality_id' => $m->id]));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_xlsx_download_has_correct_signature(): void
    {
        $m = $this->municipality();
        $this->authenticate('admin');
        $response = $this->get($this->url(['type' => 'prices', 'format' => 'xlsx', 'municipality_id' => $m->id]));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_forecast_report_uses_forecast_service(): void
    {
        $m = $this->municipality();
        $s = $this->species();
        $this->authenticate('admin');
        $forecaster = Mockery::mock(ForecastService::class);
        $forecaster->shouldReceive('forecast')->once()
            ->with($m->id, $s->id, 2, 'slaughter')
            ->andReturn(['forecast' => [
                ['week' => '2026-11-02', 'predicted_price' => 200, 'lower_95' => 180, 'upper_95' => 220],
                ['week' => '2026-11-09', 'predicted_price' => 201, 'lower_95' => 181, 'upper_95' => 221],
            ]]);
        $this->app->instance(ForecastService::class, $forecaster);
        $exporter = Mockery::mock(ReportExportService::class);
        $exporter->shouldReceive('download')->once()->withArgs(fn ($r, $f) => $r['count'] === 2 && $f === 'pdf')
            ->andReturn(response('mock report', 200));
        $this->app->instance(ReportExportService::class, $exporter);
        $this->get($this->url(['type' => 'forecast', 'format' => 'pdf', 'municipality_id' => $m->id,
            'species_id' => $s->id, 'weeks' => 2, 'sale_purpose' => 'slaughter']))->assertOk();
    }
}
