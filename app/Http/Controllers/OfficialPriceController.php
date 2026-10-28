<?php

namespace App\Http\Controllers;

use App\Models\PriceReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficialPriceController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'species_id' => ['sometimes', 'uuid'],
            'sale_purpose' => [
                'sometimes',
                'in:slaughter,breeding,fattening,work',
            ],
        ]);

        $prices = PriceReference::query()
            ->where('status', 'official')
            ->when(
                $filters['species_id'] ?? null,
                fn($query, $id) => $query->where('species_id', $id)
            )
            ->when(
                $filters['sale_purpose'] ?? null,
                fn($query, $purpose) => $query->where('sale_purpose', $purpose)
            )
            ->with([
                'municipality',
                'barangay',
                'species',
                'breed',
            ])
            ->orderByDesc('effective_from')
            ->paginate(20);

        return $this->success($prices);
    }
}