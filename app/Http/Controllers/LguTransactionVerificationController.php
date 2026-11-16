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
                'in:pending_lgu_verification,verified,rejected,disputed,all,review_queue',
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $reviewStatus = $validated['status'] ?? 'pending_lgu_verification';

        $transactions = \App\Models\Transaction::query()
            // Municipality can be recorded on the transaction, inherited from
            // the farmer profile, or (for older/imported records) only present
            // on the livestock item. Include all three without crossing the
            // Authority's assigned-municipality boundary.
            ->where(function ($scope) use ($municipalityId) {
                $scope->where('municipality_id', $municipalityId)
                    ->orWhereHas('farmer', fn ($farmer) =>
                        $farmer->where('municipality_id', $municipalityId)
                    )
                    ->orWhereHas('items.livestock', fn ($livestock) =>
                        $livestock->where('municipality_id', $municipalityId)
                    );
            })
            ->with([
                'farmer.user',
                'municipality',
                'barangay',
                'encoder',
                'buyerConfirmations',
                'statusLogs',
            ])
            ->when(
                empty($validated['status']) || $reviewStatus === 'review_queue',
                fn ($query) => $query->where(function ($queue) {
                    $queue->where('status', 'pending_lgu_verification')
                        // Legacy farmer sales become reviewable after buyer
                        // confirmation. Older encoder/auction-house records
                        // may still carry `pending` despite direct submission.
                        ->orWhere(function ($legacy) {
                            $legacy->where('status', 'pending')
                                ->where(function ($eligible) {
                                    $eligible->whereIn('source', ['lgu_encoder', 'auction_house'])
                                        ->orWhereHas('buyerConfirmations', fn ($confirmation) =>
                                            $confirmation->where('status', 'confirmed')
                                        )
                                        ->orWhereHas('statusLogs', fn ($log) =>
                                            $log->where('to_status', 'pending_lgu_verification')
                                        );
                                });
                        });
                }),
                fn ($query) => $reviewStatus !== 'all'
                    ? $query->where('status', $reviewStatus)
                    : $query
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


    /**
     * Read-only transaction history for the Authority's assigned municipality.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->roles()->where('slug', 'lgu_authority')->exists(),
            403
        );

        $municipalityId = $user->lguOfficerProfile?->municipality_id;
        abort_unless($municipalityId, 403, 'No municipality assigned to this LGU Authority.');

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:100'],
            'species' => ['nullable', 'uuid'],
            'purpose' => ['nullable', 'string', 'max:40'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $transactions = \App\Models\Transaction::query()
            ->where(function ($scope) use ($municipalityId) {
                $scope->where('municipality_id', $municipalityId)
                    ->orWhereHas('farmer', fn ($farmer) =>
                        $farmer->where('municipality_id', $municipalityId)
                    );
            })
            ->with([
                'items.livestock.species',
                'items.livestock.breed',
                'items.valuation',
                'municipality',
                'barangay',
                'farmer.user',
                'encoder',
                'verifier',
                'statusLogs.actor',
            ])
            ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['date_from']), fn ($q) => $q->whereDate('transaction_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn ($q) => $q->whereDate('transaction_date', '<=', $filters['date_to']))
            ->when(!empty($filters['species']) || !empty($filters['purpose']), function ($q) use ($filters) {
                $q->whereHas('items.livestock', function ($livestock) use ($filters) {
                    if (!empty($filters['species'])) $livestock->where('species_id', $filters['species']);
                    if (!empty($filters['purpose'])) $livestock->where('sale_purpose', strtolower($filters['purpose']));
                });
            })
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $term = trim($filters['search']);
                $q->where(function ($match) use ($term) {
                    $match->where('id', 'like', "%{$term}%")
                        ->orWhere('buyer_name', 'like', "%{$term}%")
                        ->orWhere('buyer_phone', 'like', "%{$term}%")
                        ->orWhereHas('farmer.user', function ($farmer) use ($term) {
                            $farmer->where('first_name', 'like', "%{$term}%")
                                ->orWhere('middle_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                });
            })
            ->latest('transaction_date')
            ->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'species' => \App\Models\Species::query()->orderBy('name')->get(['id', 'name']),
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
                'statusLogs.actor',
                'buyerConfirmations',
            ])
            ->where(function ($scope) use ($municipalityId) {
                $scope->where('municipality_id', $municipalityId)
                    ->orWhereHas('farmer', fn ($farmer) =>
                        $farmer->where('municipality_id', $municipalityId)
                    )
                    ->orWhereHas('items.livestock', fn ($livestock) =>
                        $livestock->where('municipality_id', $municipalityId)
                    );
            })
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
