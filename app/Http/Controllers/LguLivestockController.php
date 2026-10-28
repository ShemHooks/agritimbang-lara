<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLivestockRequest;
use App\Http\Requests\UpdateLivestockRequest;
use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Services\LivestockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LguLivestockController extends BaseController
{
    public function __construct(
        private readonly LivestockService $service
    ) {
    }



    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],

            'status' => [
                'nullable',
                'in:available,in_transaction,sold,archived',
            ],

            'municipality_id' => [
                'nullable',
                'uuid',
                'exists:municipalities,id',
            ],

            'barangay_id' => [
                'nullable',
                'uuid',
                'exists:barangays,id',
            ],

            'species_id' => [
                'nullable',
                'uuid',
                'exists:species,id',
            ],

            'breed_id' => [
                'nullable',
                'uuid',
                'exists:breeds,id',
            ],

            'farmer_id' => [
                'nullable',
                'uuid',
                'exists:farmer_profiles,id',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $livestock = $this->service->getMunicipalityLivestock(
            $request->user(),
            $filters
        );

        return $this->success($livestock);
    }


    public function show(
        Request $request,
        LivestockRecord $livestock
    ): JsonResponse {
        $livestock =
            $this->service->getMunicipalityLivestockRecord(
                $request->user(),
                $livestock
            );

        return $this->success($livestock);
    }

    public function update(
        UpdateLivestockRequest $request,
        LivestockRecord $livestock
    ): JsonResponse {
        $livestock = $this->service->updateForLgu(
            $request->user(),
            $livestock,
            $request->validated()
        );

        return $this->success(
            $livestock,
            'Livestock updated successfully.'
        );
    }
}