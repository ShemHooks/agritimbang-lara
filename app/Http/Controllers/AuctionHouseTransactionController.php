<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuctionHouseTransactionRequest;
use App\Services\AuctionHouseTransactionService;
use Illuminate\Http\JsonResponse;

class AuctionHouseTransactionController extends Controller
{
    public function store(
        StoreAuctionHouseTransactionRequest $request,
        AuctionHouseTransactionService $service
    ): JsonResponse {
        $transaction = $service->recordCompletedSale(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' =>
                'Completed sale recorded and submitted for LGU verification.',
            'data' => $transaction,
        ], 201);
    }
}
