<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceReferenceRequest;
use App\Http\Requests\UpdatePriceReferenceRequest;
use App\Models\PriceReference;
use App\Services\PriceReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceReferenceController extends BaseController
{
    public function __construct(
        private readonly PriceReferenceService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->getPriceReferences(
            $request->user(),
            $request->only([
                'status',
                'species_id',
                'breed_id',
                'barangay_id',
                'sale_purpose',
            ])
        );

        return $this->success($data);
    }

    public function store(
        StorePriceReferenceRequest $request
    ): JsonResponse {
        $priceReference = $this->service->create(
            $request->user(),
            $request->validated()
        );

        return $this->success(
            $priceReference,
            'Price reference created successfully.',
            201
        );
    }

    public function show(
        Request $request,
        PriceReference $priceReference
    ): JsonResponse {
        return $this->success(
            $this->service->getPriceReference(
                $request->user(),
                $priceReference
            )
        );
    }

    public function update(
        UpdatePriceReferenceRequest $request,
        PriceReference $priceReference
    ): JsonResponse {
        $priceReference = $this->service->update(
            $request->user(),
            $priceReference,
            $request->validated()
        );

        return $this->success(
            $priceReference,
            'Price reference updated successfully.'
        );
    }

    public function submit(
        Request $request,
        PriceReference $priceReference
    ): JsonResponse {
        $priceReference = $this->service->submit(
            $request->user(),
            $priceReference
        );

        return $this->success(
            $priceReference,
            'Price reference submitted for review.'
        );
    }
}