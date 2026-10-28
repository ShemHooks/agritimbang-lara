<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Models\Municipality;
use App\Services\GisAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FarmerDirectoryController extends Controller
{
    public function __construct(
        private readonly GisAccessService $access
    ) {
    }


    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],

            'municipality_id' => [
                'nullable',
                'uuid',
                Rule::exists(Municipality::class, 'id'),
            ],

            'barangay_id' => [
                'nullable',
                'uuid',
                Rule::exists('barangays', 'id'),
            ],

            'verification_status' => [
                'nullable',
                Rule::in([
                    'unverified',
                    'pending',
                    'verified',
                    'rejected',
                ]),
            ],

            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $municipalityId = $this->access->resolveMunicipality(
            $request->user(),
            $filters['municipality_id'] ?? null
        );

        // Validate that a selected barangay belongs to the
        // selected or authorized municipality.
        if (!empty($filters['barangay_id']) && $municipalityId) {
            $belongsToMunicipality = \App\Models\Barangay::query()
                ->whereKey($filters['barangay_id'])
                ->where('municipality_id', $municipalityId)
                ->exists();

            if (!$belongsToMunicipality) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'barangay_id' => 'The selected barangay does not belong to the authorized municipality.',
                ]);
            }
        }

        $query = FarmerProfile::query()
            ->with([
                'user:id,first_name,middle_name,last_name,email,phone_number,status,verification_status',
                'municipality:id,name',
                'barangay:id,name',
            ])
            ->when(
                $municipalityId,
                fn($q) => $q->where('municipality_id', $municipalityId)
            );

        if (!empty($filters['barangay_id'])) {
            $query->where('barangay_id', $filters['barangay_id']);
        }

        if (!empty($filters['verification_status'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where(
                    'verification_status',
                    $filters['verification_status']
                );
            });
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('farmer_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            });
        }

        // Aggregate counts across ALL matching farmers,
        // not just the current page.
        $summary = [
            'total' => (clone $query)->count(),

            'verified' => (clone $query)
                ->whereHas(
                    'user',
                    fn($q) => $q->where('verification_status', 'verified')
                )
                ->count(),

            'pending' => (clone $query)
                ->whereHas(
                    'user',
                    fn($q) => $q->where('verification_status', 'pending')
                )
                ->count(),
        ];

        $farmers = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);

        return response()->json([
            'data' => $farmers->items(),
            'summary' => $summary,
            'meta' => [
                'current_page' => $farmers->currentPage(),
                'last_page' => $farmers->lastPage(),
                'per_page' => $farmers->perPage(),
                'total' => $farmers->total(),
                'from' => $farmers->firstItem(),
                'to' => $farmers->lastItem(),
            ],
        ]);
    }



    public function show(
        Request $request,
        FarmerProfile $farmer
    ): JsonResponse {
        $municipalityId = $this->access->resolveMunicipality(
            $request->user(),
            null
        );

        if (
            $municipalityId !== null &&
            $farmer->municipality_id !== $municipalityId
        ) {
            abort(403, 'You cannot access this farmer profile.');
        }

        $farmer->load([
            'user:id,first_name,middle_name,last_name,email,phone_number,status,verification_status',
            'municipality:id,name',
            'barangay:id,name',
        ]);

        return response()->json([
            'data' => $farmer,
        ]);
    }
}
