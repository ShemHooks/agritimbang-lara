<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class ImportCebuBarangays extends Command
{
    protected $signature = 'gis:import-cebu {file=storage/app/gis/cebu_barangays.geojson} {--dry-run : Validate and count without database writes}';
    protected $description = 'Import Cebu municipalities, barangays and GeoJSON boundaries by PSGC code';

    public function handle(): int
    {
        $path = $this->argument('file');
        $path = is_file($path) ? $path : base_path($path);
        if (!is_file($path)) {
            $this->error("GeoJSON file not found: {$path}");
            return self::FAILURE;
        }

        try {
            $json = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (($json['type'] ?? null) !== 'FeatureCollection' || !is_array($json['features'] ?? null)) {
                throw new RuntimeException('Expected a GeoJSON FeatureCollection.');
            }

            // A barangay may have several separate feature polygons. Merge parts by PSGC.
            $municipalities = [];
            $barangays = [];
            $conflicts = [];
            foreach ($json['features'] as $index => $feature) {
                $p = $feature['properties'] ?? [];
                if (strcasecmp(trim($p['ADM2_EN'] ?? ''), 'Cebu') !== 0) {
                    throw new RuntimeException("Feature {$index} is not in Cebu.");
                }
                $munCode = preg_replace('/^PH/i', '', trim((string) ($p['ADM3_PCODE'] ?? '')));
                $barCode = trim((string) ($p['psgc_code'] ?? ''));
                $munName = trim((string) ($p['ADM3_EN'] ?? ''));
                $barName = trim((string) ($p['ADM4_EN'] ?? ''));
                $g = $feature['geometry'] ?? null;
                if (
                    !preg_match('/^\d{7,10}$/', $munCode) || !preg_match('/^\d{10}$/', $barCode)
                    || $munName === '' || $barName === '' || !is_array($g)
                    || !in_array($g['type'] ?? null, ['Polygon', 'MultiPolygon'], true)
                    || empty($g['coordinates']) || !is_array($g['coordinates'])
                ) {
                    throw new RuntimeException("Invalid codes, names or geometry at feature {$index}.");
                }
                if (isset($municipalities[$munCode]) && $municipalities[$munCode] !== $munName) {
                    throw new RuntimeException("Conflicting municipality names for {$munCode}.");
                }
                $municipalities[$munCode] = $munName;
                // Once a PSGC is conflicting, never accept another feature for it.
                if (isset($conflicts[$barCode])) {
                    $conflicts[$barCode]['names'][$barName] = true;
                    continue;
                }
                if (!isset($barangays[$barCode])) {
                    $barangays[$barCode] = ['municipality_code' => $munCode, 'name' => $barName, 'parts' => []];
                }
                if ($barangays[$barCode]['municipality_code'] !== $munCode || $barangays[$barCode]['name'] !== $barName) {
                    $conflicts[$barCode] = [
                        'municipality_codes' => [
                            $barangays[$barCode]['municipality_code'] => true,
                            $munCode => true,
                        ],
                        'names' => [
                            $barangays[$barCode]['name'] => true,
                            $barName => true,
                        ],
                    ];
                    unset($barangays[$barCode]);
                    continue;
                }
                $parts = $g['type'] === 'Polygon' ? [$g['coordinates']] : $g['coordinates'];
                array_push($barangays[$barCode]['parts'], ...$parts);
            }

            $this->info(sprintf('Validated %d features, %d municipalities/cities and %d importable barangays.', count($json['features']), count($municipalities), count($barangays)));
            $this->warn(sprintf('Skipped %d conflicting PSGC groups.', count($conflicts)));
            foreach ($conflicts as $code => $conflict) {
                $this->warn(sprintf(
                    '  %s: %s (municipality codes: %s)',
                    $code,
                    implode(' / ', array_keys($conflict['names'])),
                    implode(', ', array_keys($conflict['municipality_codes']))
                ));
            }
            if ($this->option('dry-run'))
                return self::SUCCESS;

            foreach (['municipalities', 'barangays', 'barangay_geometries'] as $table) {
                if (!Schema::hasTable($table))
                    throw new RuntimeException("Missing table: {$table}");
            }
            if (!Schema::hasColumn('municipalities', 'psgc_code')) {
                throw new RuntimeException('Add municipalities.psgc_code (nullable, unique string) before import; see README.');
            }
            if (!Schema::hasColumn('barangays', 'psgc_code')) {
                throw new RuntimeException('Missing barangays.psgc_code column.');
            }

            $counts = ['municipalities_created' => 0, 'barangays_created' => 0, 'geometries_created' => 0, 'geometries_updated' => 0];
            DB::transaction(function () use ($municipalities, $barangays, &$counts) {
                $munIds = [];
                foreach ($municipalities as $code => $name) {
                    $existing = DB::table('municipalities')->where('psgc_code', $code)->first();
                    if (!$existing) {
                        // Never silently link to a same-named municipality without a PSGC code.
                        $sameName = DB::table('municipalities')->where('name', $name)->where('province', 'Cebu')->first();
                        if ($sameName)
                            throw new RuntimeException("Existing Cebu municipality '{$name}' lacks matching PSGC {$code}. Resolve manually first.");
                        $id = (string) Str::uuid();
                        DB::table('municipalities')->insert([
                            'id' => $id,
                            'name' => $name,
                            'province' => 'Cebu',
                            'psgc_code' => $code,
                            'is_active' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $counts['municipalities_created']++;
                    } else {
                        if ($existing->name !== $name || $existing->province !== 'Cebu') {
                            throw new RuntimeException("Existing municipality {$code} conflicts with dataset: {$name}, Cebu.");
                        }
                        $id = $existing->id;
                    }
                    $munIds[$code] = $id;
                }
                foreach ($barangays as $code => $data) {
                    $munId = $munIds[$data['municipality_code']];
                    $existing = DB::table('barangays')->where('psgc_code', $code)->first();
                    if (!$existing) {
                        $sameName = DB::table('barangays')->where('municipality_id', $munId)->where('name', $data['name'])->first();
                        if ($sameName)
                            throw new RuntimeException("Existing barangay '{$data['name']}' lacks matching PSGC {$code}. Resolve manually first.");
                        $id = (string) Str::uuid();
                        DB::table('barangays')->insert([
                            'id' => $id,
                            'municipality_id' => $munId,
                            'name' => $data['name'],
                            'psgc_code' => $code,
                            'is_active' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $counts['barangays_created']++;
                    } else {
                        if ($existing->municipality_id !== $munId || $existing->name !== $data['name']) {
                            throw new RuntimeException("Existing barangay {$code} conflicts with dataset.");
                        }
                        $id = $existing->id;
                    }
                    $parts = $data['parts'];
                    $geometry = count($parts) === 1
                        ? ['type' => 'Polygon', 'coordinates' => $parts[0]]
                        : ['type' => 'MultiPolygon', 'coordinates' => $parts];
                    $current = DB::table('barangay_geometries')->where('barangay_id', $id)->first();
                    $payload = [
                        'geometry' => json_encode($geometry, JSON_THROW_ON_ERROR),
                        'geometry_type' => $geometry['type'],
                        'source' => 'Cebu barangay GeoJSON import',
                        'source_reference' => $code,
                        'updated_at' => now(),
                    ];
                    if ($current) {
                        DB::table('barangay_geometries')->where('barangay_id', $id)->update($payload);
                        $counts['geometries_updated']++;
                    } else {
                        DB::table('barangay_geometries')->insert(array_merge($payload, [
                            'id' => (string) Str::uuid(),
                            'barangay_id' => $id,
                            'created_at' => now(),
                        ]));
                        $counts['geometries_created']++;
                    }
                }
            });
            foreach ($counts as $key => $value)
                $this->line("{$key}: {$value}");
            $this->line('conflicting_barangay_groups_skipped: ' . count($conflicts));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
