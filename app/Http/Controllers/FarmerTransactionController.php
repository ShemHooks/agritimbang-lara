<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class FarmerTransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService
    ) {
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
}
