<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectPriceReferenceRequest;
use App\Models\PriceReference;
use App\Services\PriceValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceValidationController extends BaseController
{
    public function __construct(
        private readonly PriceValidationService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $priceReferences =
            $this->service->getPendingPriceReferences(
                $request->user(),
                $request->only([
                    'species_id',
                    'breed_id',
                    'barangay_id',
                    'sale_purpose',
                ])
            );

        return $this->success($priceReferences);
    }

    public function show(
        Request $request,
        PriceReference $priceReference
    ): JsonResponse {
        $priceReference =
            $this->service->getPriceReferenceForReview(
                $request->user(),
                $priceReference
            );

        return $this->success($priceReference);
    }

    public function approve(
        Request $request,
        PriceReference $priceReference
    ): JsonResponse {
        $priceReference = $this->service->approve(
            $request->user(),
            $priceReference
        );

        return $this->success(
            $priceReference,
            'Price reference approved successfully.'
        );
    }

    public function reject(
        RejectPriceReferenceRequest $request,
        PriceReference $priceReference
    ): JsonResponse {
        $priceReference = $this->service->reject(
            $request->user(),
            $priceReference,
            $request->validated('remarks')
        );

        return $this->success(
            $priceReference,
            'Price reference rejected successfully.'
        );
    }
}