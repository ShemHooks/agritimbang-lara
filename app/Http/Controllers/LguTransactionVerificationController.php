<?php

namespace App\Http\Controllers;

use App\Services\LguTransactionVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LguTransactionVerificationController extends Controller
{
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
