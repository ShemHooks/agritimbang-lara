<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreValuationRequest;
use App\Models\LivestockRecord;
use App\Models\Valuation;
use App\Models\WeightEstimation;
use App\Services\LivestockService;
use App\Services\ValuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmerValuationController extends BaseController
{
    public function __construct(
        private readonly LivestockService $livestockService,
        private readonly ValuationService $valuationService,
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

        $valuations = Valuation::query()
            ->where('livestock_id', $livestock->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return $this->success($valuations);
    }

    public function store(
        StoreValuationRequest $request,
        LivestockRecord $livestock
    ): JsonResponse {
        $livestock = $this->livestockService
            ->authorizeFarmerWeightEstimation(
                $request->user(),
                $livestock
            );

        $data = $request->validated();

        $estimation = null;

        if (!empty($data['weight_estimation_id'])) {
            $estimation = WeightEstimation::query()
                ->where('livestock_id', $livestock->id)
                ->findOrFail($data['weight_estimation_id']);
        }

        $valuation = $this->valuationService->calculate(
            livestock: $livestock,
            user: $request->user(),
            weightEstimation: $estimation
        );

        return $this->success(
            $valuation->load([
                'priceReference',
                'weightEstimation',
            ]),
            'Fair price calculated successfully.',
            201
        );
    }
}
