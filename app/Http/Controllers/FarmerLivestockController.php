<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLivestockRequest;
use App\Http\Requests\UpdateLivestockRequest;
use App\Http\Requests\UpdateLivestockStatusRequest;
use App\Models\LivestockRecord;
use App\Services\LivestockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmerLivestockController extends BaseController
{
    public function __construct(
        private readonly LivestockService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $livestock = $this->service->getFarmerLivestock(
            $request->user(),
            $request->only([
                'status',
                'species_id',
                'breed_id',
                'per_page',
            ])
        );

        return $this->success($livestock);
    }

    public function store(
        StoreLivestockRequest $request
    ): JsonResponse {
        $livestock = $this->service->createForFarmer(
            $request->user(),
            $request->validated()
        );

        return $this->success(
            $livestock,
            'Livestock registered successfully.',
            201
        );
    }

    public function show(
        Request $request,
        LivestockRecord $livestock
    ): JsonResponse {
        $livestock =
            $this->service->getFarmerLivestockRecord(
                $request->user(),
                $livestock
            );

        return $this->success($livestock);
    }

    public function update(
        UpdateLivestockRequest $request,
        LivestockRecord $livestock
    ): JsonResponse {
        $livestock = $this->service->updateForFarmer(
            $request->user(),
            $livestock,
            $request->validated()
        );

        return $this->success(
            $livestock,
            'Livestock updated successfully.'
        );
    }

    public function destroy(Request $request, LivestockRecord $livestock): JsonResponse
    {
        $this->service->deleteForFarmer($request->user(), $livestock);
        return $this->success(null, 'Livestock record deleted successfully.');
    }

    public function updateStatus(
        UpdateLivestockStatusRequest $request,
        LivestockRecord $livestock
    ): JsonResponse {
        $livestock =
            $this->service->updateFarmerStatus(
                $request->user(),
                $livestock,
                $request->validated('status')
            );

        return $this->success(
            $livestock,
            'Livestock status updated successfully.'
        );
    }
}