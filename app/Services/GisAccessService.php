<?php

namespace App\Services;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class GisAccessService
{
    /**
     * Resolve the municipality allowed for livestock monitoring.
     *
     * Admin: all municipalities or a selected municipality.
     * LGU: assigned active municipality only.
     */
    public function resolveMunicipality(
        User $user,
        ?string $requestedMunicipalityId
    ): ?string {
        $roles = $user->roles()->pluck('slug')->all();

        if (in_array('admin', $roles, true)) {
            return $requestedMunicipalityId;
        }

        if (
            !array_intersect(
                ['lgu_authority', 'lgu_encoder'],
                $roles
            )
        ) {
            throw new AuthorizationException(
                'You are not authorized to access GIS livestock monitoring.'
            );
        }

        $profile = $user->lguOfficerProfile()
            ->with('municipality')
            ->first();

        $municipality = $profile?->municipality;

        if (!$municipality || !$municipality->is_active) {
            throw new AuthorizationException(
                'You are not assigned to an active municipality.'
            );
        }

        if (
            $requestedMunicipalityId !== null &&
            $requestedMunicipalityId !== $municipality->id
        ) {
            throw new AuthorizationException(
                'You cannot access another municipality.'
            );
        }

        return $municipality->id;
    }
}