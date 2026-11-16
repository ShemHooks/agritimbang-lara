<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewManualWeightEstimationRequest;
use App\Models\Breed;
use App\Models\LivestockRecord;
use App\Models\Species;
use App\Services\AgeGroupService;
use App\Services\WeightEstimationService;
use Illuminate\Http\JsonResponse;

class ManualWeightEstimationController extends BaseController
{
    public function __construct(
        private readonly WeightEstimationService $weightEstimationService,
        private readonly AgeGroupService $ageGroupService,
    ) {
    }

    public function preview(PreviewManualWeightEstimationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $species = Species::query()->where('is_active', true)->findOrFail($data['species_id']);
        $breed = null;

        if (!empty($data['breed_id'])) {
            $breed = Breed::query()
                ->where('species_id', $species->id)
                ->where('is_active', true)
                ->findOrFail($data['breed_id']);
        }

        $livestock = new LivestockRecord([
            'species_id' => $species->id,
            'breed_id' => $breed?->id,
            'sex' => $data['sex'],
            'age_months' => $data['age_months'] ?? null,
            'age_group' => $data['age_group'] ?? $this->ageGroupService->resolve(
                $species,
                isset($data['age_months']) ? (int) $data['age_months'] : null,
            ),
            'actual_weight_kg' => null,
        ]);

        $livestock->setRelation('species', $species);
        $livestock->setRelation('breed', $breed);

        $measurements = [
            'heart_girth_cm' => $data['heart_girth_cm'] ?? null,
            'body_length_cm' => $data['body_length_cm'] ?? null,
            'rump_height_cm' => $data['rump_height_cm'] ?? null,
        ];

        return $this->success(
            $this->weightEstimationService->preview($livestock, $measurements),
            'Weight estimated successfully. This preview has not been saved to a livestock record.'
        );
    }
}
