<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeightEstimationRequest;
use App\Models\LivestockRecord;
use App\Models\WeightEstimation;
use App\Services\LivestockService;
use App\Services\WeightEstimationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmerWeightEstimationController extends BaseController
{
    public function __construct(
        private readonly LivestockService $livestockService,
        private readonly WeightEstimationService $weightEstimationService
    ) {
    }

    public function index(
        Request $request,
        LivestockRecord $livestock
    ): JsonResponse {
        $livestock = $this->livestockService
            ->getFarmerLivestockRecord(
                $request->user(),
                $livestock
            );

        $estimations = WeightEstimation::query()
            ->where('livestock_id', $livestock->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return $this->success($estimations);
    }

    public function store(
        StoreWeightEstimationRequest $request,
        LivestockRecord $livestock
    ): JsonResponse {
        $livestock = $this->livestockService
            ->authorizeFarmerWeightEstimation(
                $request->user(),
                $livestock
            );

        $estimation = $this->weightEstimationService
            ->estimate(
                $livestock,
                $request->validated(),
                $request->user()
            );

        return $this->success(
            $estimation,
            'Weight estimation calculated successfully.',
            201
        );
    }
}