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
                $query->orWhere(function ($q) use ($search) {
                    $q->where('municipality_id', $this->municipalityIdFromContext())
                        ->where('farmer_code', 'like', "%{$search}%")
                        ->whereHas(
                            'user',
                            fn($u) =>
                                $u->where('verification_status', 'verified')
                        );
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
