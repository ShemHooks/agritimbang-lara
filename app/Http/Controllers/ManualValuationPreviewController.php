<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewManualValuationRequest;
use App\Models\Breed;
use App\Models\LivestockRecord;
use App\Models\Species;
use App\Models\WeightEstimation;
use App\Services\AgeGroupService;
use App\Services\PriceReferenceResolver;
use App\Services\PricingAdjustmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ManualValuationPreviewController extends BaseController
{
    public function __construct(
        private readonly PriceReferenceResolver $priceResolver,
        private readonly PricingAdjustmentResolver $adjustmentResolver,
        private readonly AgeGroupService $ageGroupService,
    ) {
    }

    public function preview(PreviewManualValuationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $species = Species::query()->where('is_active', true)->findOrFail($data['species_id']);
        $breed = null;

        if (!empty($data['breed_id'])) {
            $breed = Breed::query()
                ->where('species_id', $species->id)
                ->where('is_active', true)
                ->findOrFail($data['breed_id']);
        }

        $municipalityId = $user->farmerProfile?->municipality_id
            ?? $user->lguOfficerProfile?->municipality_id;

        if (!$municipalityId) {
            throw ValidationException::withMessages([
                'municipality' => 'Your account must have an assigned municipality before a calculator-only valuation can be previewed.',
            ]);
        }

        $ageGroup = $this->ageGroupService->resolve(
            $species,
            isset($data['age_months']) ? (int) $data['age_months'] : null,
        );

        $livestock = new LivestockRecord([
            'species_id' => $species->id,
            'breed_id' => $breed?->id,
            'sex' => $data['sex'],
            'age_months' => $data['age_months'] ?? null,
            'age_group' => $ageGroup,
            'sale_purpose' => $data['sale_purpose'],
            'condition_score' => (int) $data['condition_score'],
            'reproductive_status' => $data['reproductive_status'] ?? 'unknown',
            'parity' => $data['parity'] ?? null,
            'municipality_id' => $municipalityId,
        ]);
        $livestock->setRelation('species', $species);
        $livestock->setRelation('breed', $breed);

        $weight = new WeightEstimation([
            'estimated_weight_kg' => (float) $data['estimated_weight_kg'],
            'frame_category' => $data['frame_category'] ?? null,
        ]);

        $weightSource = $data['weight_source'] ?? 'estimated';
        $date = Carbon::now();
        $price = $this->priceResolver->resolve($livestock, $date);
        $adjustments = $this->adjustmentResolver->resolve($livestock, $date, $weight);

        $basePrice = (float) $price['base_price_per_kg'];
        $finalPrice = round(
            $basePrice
                * (float) $adjustments['breed_factor']
                * (float) $adjustments['age_factor']
                * (float) $adjustments['reproductive_factor']
                * (float) $adjustments['frame_factor']
                * (float) $adjustments['condition_factor'],
            2,
        );
        $fairValue = round((float) $data['estimated_weight_kg'] * $finalPrice, 2);

        $snapshot = [
            'preview_only' => true,
            'calculation_version' => 'FPC-1.1',
            'valuation_date' => $date->toDateString(),
            'livestock' => [
                'species_id' => $species->id,
                'breed_id' => $breed?->id,
                'sex' => $livestock->sex,
                'age_months' => $livestock->age_months,
                'age_group' => $livestock->age_group,
                'sale_purpose' => $livestock->sale_purpose,
                'condition_score' => $livestock->condition_score,
                'reproductive_status' => $livestock->reproductive_status,
                'parity' => $livestock->parity,
                'municipality_id' => $municipalityId,
            ],
            'weight' => [
                'type' => $weightSource,
                'weight_used_kg' => $data['estimated_weight_kg'],
                'estimated_weight_kg' => $data['estimated_weight_kg'],
                'frame_category' => $data['frame_category'] ?? null,
            ],
            'price_reference' => $price,
            'adjustments' => $adjustments['breakdown'],
            'calculation' => [
                'base_price_per_kg' => $basePrice,
                'breed_factor' => $adjustments['breed_factor'],
                'age_factor' => $adjustments['age_factor'],
                'reproductive_factor' => $adjustments['reproductive_factor'],
                'frame_factor' => $adjustments['frame_factor'],
                'condition_factor' => $adjustments['condition_factor'],
                'final_price_per_kg' => $finalPrice,
                'estimated_value' => $fairValue,
                'currency' => 'PHP',
            ],
            'notice' => 'Preview only. No livestock record or valuation history was saved.',
        ];

        return $this->success([
            'estimated_value' => $fairValue,
            'weight_type' => $weightSource,
            'weight_used_kg' => (float) $data['estimated_weight_kg'],
            'base_price_per_kg' => $basePrice,
            'breed_factor' => $adjustments['breed_factor'],
            'age_factor' => $adjustments['age_factor'],
            'reproductive_factor' => $adjustments['reproductive_factor'],
            'frame_factor' => $adjustments['frame_factor'],
            'condition_factor' => $adjustments['condition_factor'],
            'final_price_per_kg' => $finalPrice,
            'calculation_version' => 'FPC-1.1',
            'calculation_snapshot' => $snapshot,
        ], 'Valuation preview calculated. No record was saved.');
    }
}
