<?php

namespace App\Http\Controllers;

use App\Services\TransactionDisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionDisputeController extends Controller
{
    public function __construct(
        private readonly TransactionDisputeService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->index($request->user())
        );
    }

    public function show(
        Request $request,
        string $dispute
    ): JsonResponse {
        return response()->json([
            'data' => $this->service->show(
                $request->user(),
                $dispute
            ),
        ]);
    }

    public function review(
        Request $request,
        string $dispute
    ): JsonResponse {
        return response()->json([
            'data' => $this->service->startReview(
                $request->user(),
                $dispute
            ),
        ]);
    }

    public function resolve(
        Request $request,
        string $dispute
    ): JsonResponse {
        $data = $request->validate([
            'resolution' => [
                'required',
                Rule::in(['upheld', 'dismissed']),
            ],
            'resolution_notes' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
        ]);

        return response()->json([
            'data' => $this->service->resolve(
                $request->user(),
                $dispute,
                $data['resolution'],
                $data['resolution_notes']
            ),
        ]);
    }
}
