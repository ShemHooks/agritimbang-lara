<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FarmerProfile;
use App\Models\Transaction;
use App\Http\Requests\StoreTransactionRequest;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use App\Services\BuyerConfirmationService;

class FarmerTransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $farmer = FarmerProfile::query()->where('user_id', $request->user()->id)->firstOrFail();
        $transactions = Transaction::query()
            ->where('farmer_id', $farmer->id)
            ->with(['items.livestock.species', 'items.livestock.breed', 'items.valuation', 'municipality', 'barangay', 'farmer.user', 'encoder', 'verifier', 'auctionHouse'])
            ->latest('transaction_date')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $transactions]);
    }


    public function show(Request $request, string $transaction): JsonResponse
    {
        $farmer = FarmerProfile::query()->where('user_id', $request->user()->id)->firstOrFail();
        $record = Transaction::query()
            ->whereKey($transaction)
            ->where('farmer_id', $farmer->id)
            ->with(['items.livestock.species', 'items.livestock.breed', 'items.valuation', 'municipality', 'barangay', 'farmer.user', 'encoder', 'verifier', 'auctionHouse'])
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $record]);
    }

    public function update(StoreTransactionRequest $request, string $transaction): JsonResponse
    {
        $updated = $this->transactionService->updateFarmerDraft(
            $request->user(),
            $transaction,
            $request->validated()
        );

        return response()->json(['success' => true, 'message' => 'Draft transaction updated.', 'data' => $updated]);
    }

    public function store(
        StoreTransactionRequest $request
    ): JsonResponse {
        $transaction = $this->transactionService->createDraft(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Draft transaction created successfully.',
            'data' => $transaction,
        ], 201);
    }


    public function submit(
        \Illuminate\Http\Request $request,
        string $transaction,
        BuyerConfirmationService $confirmationService
    ): JsonResponse {
        $sale = $this->transactionService->submit(
            $request->user(),
            $transaction,
            $confirmationService
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Transaction submitted for buyer confirmation.',
            'data' => $sale,
        ]);
    }

}
