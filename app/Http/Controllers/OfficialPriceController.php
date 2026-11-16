<?php

namespace App\Http\Controllers;

use App\Models\PriceReference;
use App\Models\FarmerProfile;
use App\Models\LguOfficerProfile;
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
            'municipality_id' => ['sometimes', 'uuid'],
        ]);

        $user = $request->user();
        $roles = $user->roles()->pluck('slug')->all();
        $municipalityId = null;

        if (in_array('farmer', $roles, true)) {
            $municipalityId = FarmerProfile::query()
                ->where('user_id', $user->id)
                ->value('municipality_id');
        } elseif (in_array('lgu_authority', $roles, true) || in_array('lgu_encoder', $roles, true)) {
            $municipalityId = LguOfficerProfile::query()
                ->where('user_id', $user->id)
                ->value('municipality_id');
        } elseif (in_array('admin', $roles, true)) {
            $municipalityId = $filters['municipality_id'] ?? null;
        }

        $today = now()->toDateString();
        $prices = PriceReference::query()
            ->where('status', 'official')
            ->where('sale_purpose', '!=', 'piglet')
            ->where(function ($query) {
                $query->whereNull('reference_category')
                    ->orWhere('reference_category', 'not like', 'Piglet%');
            })
            ->whereNull('barangay_id')
            ->whereNotNull('approved_by')
            ->whereNotNull('approved_at')
            // Include the latest past approved reference as a carry-forward
            // when no newer price has been approved for the current period.
            ->whereDate('effective_from', '<=', $today)
            ->when($municipalityId, fn($query) => $query->where('municipality_id', $municipalityId))
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
                'species',
                'breed',
                'submitter',
                'approver',
            ])
            ->orderByDesc('effective_from')
            ->orderByDesc('approved_at')
            ->paginate(min(max((int) $request->query('per_page', 100), 1), 100));

        return $this->success($prices);
    }
}