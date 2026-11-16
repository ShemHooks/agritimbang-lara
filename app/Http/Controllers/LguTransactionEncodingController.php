<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEncoderTransactionRequest;
use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\Transaction;
use App\Services\BuyerConfirmationService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LguTransactionEncodingController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly BuyerConfirmationService $confirmationService
    ) {
    }

    private function municipalityId(Request $request): string
    {
        $user = $request->user();

        abort_unless(
            $user->roles()
                ->where('slug', 'lgu_encoder')
                ->exists(),
            403
        );

        $municipalityId =
            $user->lguOfficerProfile?->municipality_id;

        abort_unless(
            $municipalityId,
            403,
            'No municipality assigned to this encoder.'
        );

        return (string) $municipalityId;
    }

    public function farmers(Request $request): JsonResponse
    {
        $municipalityId = $this->municipalityId($request);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim($data['search'] ?? '');

        $farmers = FarmerProfile::query()
            ->with([
                'user:id,first_name,last_name,verification_status',
                'barangay:id,name',
            ])
            ->where('municipality_id', $municipalityId)
            ->whereHas('user', function ($query) use ($search) {
                $query->where('verification_status', 'verified');

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('verification_status', 'verified')
                            ->where(function ($nameQuery) use ($search) {
                                $nameQuery->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    })->orWhere('farmer_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('farmer_code')
            ->paginate($data['per_page'] ?? 15);

        return response()->json([
            'success' => true,
            'data' => $farmers,
        ]);
    }

    public function livestock(
        Request $request,
        FarmerProfile $farmer
    ): JsonResponse {
        $municipalityId = $this->municipalityId($request);

        abort_unless(
            (string) $farmer->municipality_id === $municipalityId,
            403
        );

        abort_unless(
            $farmer->user?->verification_status === 'verified',
            422,
            'The selected farmer is not verified.'
        );

        $livestock = LivestockRecord::query()
            ->with([
                'species',
                'breed',
                'valuations' => fn($query) =>
                    $query->latest('created_at'),
            ])
            ->where('farmer_id', $farmer->id)
            ->where('municipality_id', $municipalityId)
            ->where('status', 'available')
            ->whereDoesntHave('transactionItems.transaction', function ($query) {
                $query->whereIn('status', [
                    'pending',
                    'pending_lgu_verification',
                    'verified',
                ]);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $livestock,
        ]);
    }

    /**
     * Transaction history for the authenticated encoder. Records are limited
     * to transactions encoded by this user and to the user's assigned
     * municipality; this includes drafts and submitted/verified records.
     */
    public function history(Request $request): JsonResponse
    {
        $municipalityId = $this->municipalityId($request);

        $validated = $request->validate([
            'status' => ['nullable', 'in:draft,pending,pending_lgu_verification,verified,rejected,cancelled,disputed'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'species' => ['nullable', 'uuid'],
            'purpose' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim($validated['search'] ?? '');
        $dateFrom = $validated['date_from'] ?? $validated['from'] ?? null;
        $dateTo = $validated['date_to'] ?? $validated['to'] ?? null;

        $transactions = Transaction::query()
            ->with([
                'farmer.user:id,first_name,middle_name,last_name,phone_number',
                'municipality:id,name',
                'barangay:id,name',
                'items.livestock.species:id,name',
                'items.livestock.breed:id,name',
                'items.valuation',
                'encoder:id,first_name,middle_name,last_name',
                'verifier:id,first_name,middle_name,last_name',
                'statusLogs.actor:id,first_name,middle_name,last_name',
            ])
            ->withCount('items')
            // Encoder history is the transaction history for the assigned
            // municipality, including farmer-submitted and encoder-assisted rows.
            // Do not filter by encoded_by: that would hide farmer submissions.
            ->where('municipality_id', $municipalityId)
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when($dateFrom, fn ($query) => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('transaction_date', '<=', $dateTo))
            ->when(!empty($validated['species']) || !empty($validated['purpose']), function ($query) use ($validated) {
                $query->whereHas('items.livestock', function ($livestock) use ($validated) {
                    if (!empty($validated['species'])) $livestock->where('species_id', $validated['species']);
                    if (!empty($validated['purpose'])) $livestock->where('sale_purpose', strtolower($validated['purpose']));
                });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('id', 'like', "%{$search}%")
                        ->orWhere('buyer_name', 'like', "%{$search}%")
                        ->orWhere('buyer_phone', 'like', "%{$search}%")
                        ->orWhereHas('farmer.user', function ($userQuery) use ($search) {
                            $userQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('phone_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'species' => \App\Models\Species::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(
        StoreEncoderTransactionRequest $request
    ): JsonResponse {
        $this->municipalityId($request);

        $transaction = $this->transactionService
            ->createEncoderDraft(
                $request->user(),
                $request->validated()
            );

        return response()->json([
            'success' => true,
            'message' => 'Transaction draft encoded successfully.',
            'data' => $transaction,
        ], 201);
    }

    public function submit(
        Request $request,
        Transaction $transaction
    ): JsonResponse {
        $this->municipalityId($request);

        $sale = $this->transactionService
            ->submitEncoderDraft(
                $request->user(),
                $transaction->id,
                $this->confirmationService
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Transaction submitted for LGU verification.',
            'data' => $sale,
        ]);
    }
}
