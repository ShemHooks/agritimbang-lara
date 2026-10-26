<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\BarangayGeometry;
use App\Models\Municipality;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BarangayService
{
    public function getBarangays(
        ?string $search = null,
        ?string $municipalityId = null,
        ?bool $isActive = null
    ): LengthAwarePaginator {
        return Barangay::query()
            ->when(
                $municipalityId,
                fn($query, $municipalityId) =>
                    $query->where(
                        'municipality_id',
                        $municipalityId
                    )
            )
            ->when(
                $isActive !== null,
                fn($query) =>
                    $query->where(
                        'is_active',
                        $isActive
                    )
            )
            ->when(
                $search,
                function ($query, $search) {
                    $query->where(
                        function ($query) use ($search) {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'psgc_code',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->with([
                'municipality',
            ])
            ->withCount('geometry')
            ->orderBy('name')
            ->paginate(20);
    }


    public function getMunicipalityBarangays(
        Municipality $municipality
    ) {
        return Barangay::query()
            ->where(
                'municipality_id',
                $municipality->id
            )
            ->with('municipality')
            ->orderBy('name')
            ->get();
    }


    public function getBarangay(
        string $id
    ): Barangay {
        return Barangay::query()
            ->with([
                'municipality',
                'geometry',
            ])
            ->findOrFail($id);
    }


    public function createBarangay(
        array $data
    ): Barangay {
        return DB::transaction(function () use ($data) {

            $municipality = Municipality::findOrFail(
                $data['municipality_id']
            );

            if (!$municipality->is_active) {
                throw new RuntimeException(
                    'Cannot create a Barangay under an inactive municipality.'
                );
            }

            $barangay = Barangay::create([
                'municipality_id' =>
                    $municipality->id,

                'name' =>
                    $data['name'],

                'psgc_code' =>
                    $data['psgc_code'] ?? null,

                'is_active' =>
                    $data['is_active'] ?? true,
            ]);

            return $this->getBarangay(
                $barangay->id
            );
        });
    }


    public function updateBarangay(
        Barangay $barangay,
        array $data
    ): Barangay {
        return DB::transaction(
            function () use ($barangay, $data) {

                if (
                    array_key_exists(
                        'municipality_id',
                        $data
                    )
                ) {
                    $municipality =
                        Municipality::findOrFail(
                            $data['municipality_id']
                        );

                    if (!$municipality->is_active) {
                        throw new RuntimeException(
                            'Cannot assign Barangay to an inactive municipality.'
                        );
                    }

                    $barangay->municipality_id =
                        $municipality->id;
                }

                foreach (
                    [
                        'name',
                        'psgc_code',
                    ] as $field
                ) {
                    if (
                        array_key_exists(
                            $field,
                            $data
                        )
                    ) {
                        $barangay->{$field} =
                            $data[$field];
                    }
                }

                $barangay->save();

                return $this->getBarangay(
                    $barangay->id
                );
            }
        );
    }


    public function updateStatus(
        Barangay $barangay,
        bool $isActive
    ): Barangay {
        /*
         * Prevent activating a Barangay when
         * its parent Municipality is inactive.
         */
        if (
            $isActive &&
            !$barangay->municipality->is_active
        ) {
            throw new RuntimeException(
                'Cannot activate a Barangay under an inactive municipality.'
            );
        }

        $barangay->update([
            'is_active' => $isActive,
        ]);

        return $this->getBarangay(
            $barangay->id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GIS Geometry
    |--------------------------------------------------------------------------
    */

    public function getGeometry(
        Barangay $barangay
    ): ?BarangayGeometry {
        return $barangay
            ->geometry()
            ->first();
    }


    public function upsertGeometry(
        Barangay $barangay,
        array $data
    ): BarangayGeometry {

        if (
            !in_array($data['geometry_type'] ?? null, [
                'Polygon',
                'MultiPolygon',
            ], true)
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'geometry_type' => 'Only Polygon and MultiPolygon geometries are supported.',
            ]);
        }

        if (
            !isset($data['geometry']) ||
            !is_array($data['geometry']) ||
            empty($data['geometry']['coordinates']) ||
            ($data['geometry']['type'] ?? null) !== $data['geometry_type']
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'geometry' => 'A valid GeoJSON geometry object is required.',
            ]);
        }
        return DB::transaction(
            function () use ($barangay, $data) {

                $geometry = BarangayGeometry::updateOrCreate(
                    [
                        'barangay_id' =>
                            $barangay->id,
                    ],
                    [
                        'geometry' =>
                            $data['geometry'],

                        'geometry_type' =>
                            $data['geometry_type'],

                        'source' =>
                            $data['source'] ?? null,

                        'source_reference' =>
                            $data['source_reference'] ?? null,
                    ]
                );

                return $geometry->load(
                    'barangay.municipality'
                );
            }
        );
    }
}