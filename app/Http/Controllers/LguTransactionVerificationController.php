<?php

namespace App\Http\Controllers;

use App\Services\LguTransactionVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LguTransactionVerificationController extends Controller
{


    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->roles()
                ->where('slug', 'lgu_authority')
                ->exists(),
            403
        );

        $municipalityId = $user->lguOfficerProfile?->municipality_id;

        abort_unless(
            $municipalityId,
            403,
            'No municipality assigned to this LGU Authority.'
        );

        $validated = $request->validate([
            'status' => [
                'nullable',
                'in:pending_lgu_verification,verified,rejected,disputed',
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $transactions = \App\Models\Transaction::query()
            ->with([
                'farmer.user',
                'municipality',
                'barangay',
                'encoder',
            ])
            ->where('municipality_id', $municipalityId)
            ->when(
                !empty($validated['status']),
                fn($query) => $query->where(
                    'status',
                    $validated['status']
                )
            )
            ->when(
                !empty($validated['search']),
                function ($query) use ($validated) {
                    $search = $validated['search'];

                    $query->where(function ($q) use ($search) {
                        $q->where('id', 'like', "%{$search}%")
                            ->orWhere('buyer_name', 'like', "%{$search}%")
                            ->orWhereHas('farmer.user', function ($userQuery) use ($search) {
                                $userQuery
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    });
                }
            )
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }


    public function show(
        Request $request,
        string $transaction
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user->roles()
                ->where('slug', 'lgu_authority')
                ->exists(),
            403
        );

        $municipalityId = $user->lguOfficerProfile?->municipality_id;

        abort_unless($municipalityId, 403);

        $record = \App\Models\Transaction::query()
            ->with([
                'farmer.user',
                'municipality',
                'barangay',
                'encoder',
                'items.livestock.species',
                'items.livestock.breed',
                'items.valuation',
                'statusLogs',
            ])
            ->where('municipality_id', $municipalityId)
            ->findOrFail($transaction);

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function approve(
        Request $request,
        string $transaction,
        LguTransactionVerificationService $service
    ): JsonResponse {
        $result = $service->approve($request->user(), $transaction);

        return response()->json([
            'message' => 'Transaction verified successfully.',
            'data' => $result,
        ]);
    }

    public function reject(
        Request $request,
        string $transaction,
        LguTransactionVerificationService $service
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $result = $service->reject(
            $request->user(),
            $transaction,
            $validated['reason']
        );

        return response()->json([
            'message' => 'Transaction rejected successfully.',
            'data' => $result,
        ]);
    }
}
