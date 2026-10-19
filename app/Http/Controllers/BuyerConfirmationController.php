<?php

namespace App\Http\Controllers;

use App\Services\BuyerConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerConfirmationController extends Controller
{
    public function __construct(
        private readonly BuyerConfirmationService $service
    ) {
    }

    public function show(string $token): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->preview($token),
        ]);
    }

    public function confirm(string $token): JsonResponse
    {
        $this->service->confirm($token);

        return response()->json([
            'success' => true,
            'message' =>
                'Transaction confirmed. Awaiting LGU verification.',
        ]);
    }

    public function dispute(
        Request $request,
        string $token
    ): JsonResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->service->dispute($token, $data['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Transaction dispute recorded.',
        ]);
    }
}
