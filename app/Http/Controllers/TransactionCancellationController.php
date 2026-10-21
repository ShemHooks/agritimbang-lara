<?php

namespace App\Http\Controllers;

use App\Services\TransactionCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionCancellationController extends Controller
{
    public function __construct(
        private readonly TransactionCancellationService $service
    ) {
    }

    public function farmerCancel(
        Request $request,
        string $transaction
    ): JsonResponse {
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:2000',
        ]);

        return response()->json([
            'data' => $this->service->cancelByFarmer(
                $request->user(),
                $transaction,
                $data['reason']
            ),
        ]);
    }

    public function lguCancel(
        Request $request,
        string $transaction
    ): JsonResponse {
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:2000',
        ]);

        return response()->json([
            'data' => $this->service->cancelByLgu(
                $request->user(),
                $transaction,
                $data['reason']
            ),
        ]);
    }
}
