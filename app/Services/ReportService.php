<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public const TYPES = ['livestock', 'prices', 'transactions', 'valuations', 'forecast'];
    private const MAX_ROWS = 5000;

    public function __construct(
        private readonly GisAccessService $access,
        private readonly ForecastService $forecastService,
    ) {}

    public function build(User $user, array $filters): array
    {
        // Resolve scope before querying or exporting anything.
        $municipalityId = $this->access->resolveMunicipality($user, $filters['municipality_id'] ?? null);
        if (!$municipalityId) {
            throw ValidationException::withMessages(['municipality_id' => 'Select a municipality to generate a report.']);
        }
        $type = $filters['type'];
        if ($type === 'forecast') {
            $result = $this->forecastService->forecast(
                $municipalityId,
                $filters['species_id'],
                (int) ($filters['weeks'] ?? 4),
                $filters['sale_purpose'] ?? null,
            );
            $rows = array_map(static fn (array $r): array => [
                'week' => $r['week'], 'predicted_price' => $r['predicted_price'],
                'lower_95' => $r['lower_95'], 'upper_95' => $r['upper_95'],
            ], $result['forecast']);
            return $this->package($type, $municipalityId, $filters, $rows, [
                'week' => 'Forecast Week', 'predicted_price' => 'Predicted PHP/kg',
                'lower_95' => '95% Lower', 'upper_95' => '95% Upper',
            ], 'ARIMA statistical estimates, not guaranteed market prices.');
        }

        $q = match ($type) {
            'livestock' => DB::table('livestock_records as l')->where('l.municipality_id', $municipalityId),
            'prices' => DB::table('price_references as p')->where('p.municipality_id', $municipalityId)->where('p.status', 'official'),
            'transactions' => DB::table('transactions as t')->where('t.municipality_id', $municipalityId)->where('t.status', 'verified'),
            'valuations' => DB::table('valuations as v')->join('livestock_records as l', 'l.id', '=', 'v.livestock_id')->where('l.municipality_id', $municipalityId),
        };

        if (!empty($filters['species_id'])) {
            if ($type === 'livestock' || $type === 'valuations') $q->where('l.species_id', $filters['species_id']);
            if ($type === 'prices') $q->where('p.species_id', $filters['species_id']);
            // Transaction records are municipality-scoped; species filtering requires transaction_items join.
            if ($type === 'transactions') {
                $q->whereExists(function ($sub) use ($filters) {
                    $sub->selectRaw('1')->from('transaction_items as ti')
                        ->join('livestock_records as li', 'li.id', '=', 'ti.livestock_id')
                        ->whereColumn('ti.transaction_id', 't.id')
                        ->where('li.species_id', $filters['species_id']);
                });
            }
        }
        if ($type === 'prices') {
            if (array_key_exists('sale_purpose', $filters)) {
                $q->where('p.sale_purpose', $filters['sale_purpose']);
            }
        }
        $dateColumn = match ($type) {
            'livestock' => 'l.created_at', 'prices' => 'p.effective_from',
            'transactions' => 't.transaction_date', 'valuations' => 'v.created_at',
        };
        if (!empty($filters['date_from'])) $q->whereDate($dateColumn, '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $q->whereDate($dateColumn, '<=', $filters['date_to']);

        [$columns, $select] = match ($type) {
            'livestock' => [
                ['id' => 'Record ID', 'species' => 'Species', 'barangay' => 'Barangay', 'sex' => 'Sex', 'status' => 'Status', 'created_at' => 'Recorded At'],
                ['l.id', 's.name as species', 'b.name as barangay', 'l.sex', 'l.status', 'l.created_at'],
            ],
            'prices' => [
                ['id' => 'Reference ID', 'species' => 'Species', 'effective_from' => 'Effective Date', 'sale_purpose' => 'Sale Purpose', 'price_per_kg' => 'PHP/kg'],
                ['p.id', 's.name as species', 'p.effective_from', 'p.sale_purpose', 'p.price_per_kg'],
            ],
            'transactions' => [
                ['id' => 'Transaction ID', 'transaction_date' => 'Transaction Date', 'buyer_name' => 'Buyer', 'total_selling_price' => 'Selling Price (PHP)', 'status' => 'Status'],
                ['t.id', 't.transaction_date', 't.buyer_name', 't.total_selling_price', 't.status'],
            ],
            'valuations' => [
                ['id' => 'Valuation ID', 'livestock_id' => 'Livestock ID', 'species' => 'Species', 'weight_type' => 'Weight Type', 'created_at' => 'Valued At'],
                ['v.id', 'v.livestock_id', 's.name as species', 'v.weight_type', 'v.created_at'],
            ],
        };
        if ($type === 'livestock' || $type === 'prices' || $type === 'valuations') {
            $speciesJoin = $type === 'prices' ? 'p.species_id' : 'l.species_id';
            $q->leftJoin('species as s', 's.id', '=', $speciesJoin);
        }
        if ($type === 'livestock') $q->leftJoin('barangays as b', 'b.id', '=', 'l.barangay_id');
        $q->select($select)->orderBy($dateColumn)->orderBy($type === 'valuations' ? 'v.id' : ($type === 'livestock' ? 'l.id' : ($type === 'prices' ? 'p.id' : 't.id')));
        // Limit + 1 ensures we never silently truncate official reports.
        $rows = $q->limit(self::MAX_ROWS + 1)->get()->map(static fn ($r) => (array) $r)->all();
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['report' => 'Too many records. Narrow the date range (maximum 5,000 rows).']);
        }
        return $this->package($type, $municipalityId, $filters, $rows, $columns);
    }

    private function package(string $type, string $municipalityId, array $filters, array $rows, array $columns, ?string $note = null): array
    {
        return [
            'title' => ucwords(str_replace('_', ' ', $type)).' Report',
            'type' => $type,
            'municipality_id' => $municipalityId,
            'generated_at' => now()->toIso8601String(),
            'filters' => $filters,
            'columns' => $columns,
            'rows' => $rows,
            'count' => count($rows),
            'note' => $note,
        ];
    }
}
