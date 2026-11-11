<?php

namespace App\Services;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class GisAccessService
{
    /**
     * Existing GIS access.
     *
     * Admin: all municipalities or selected municipality.
     * LGU Authority: assigned municipality only.
     * LGU Encoder: assigned municipality only.
     *
     * Preserve existing access behavior.
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
            (string) $requestedMunicipalityId !==
            (string) $municipality->id
        ) {
            throw new AuthorizationException(
                'You cannot access another municipality.'
            );
        }

        return $municipality->id;
    }

    /**
     * Cross-municipality monitoring access.
     *
     * Only System Admin and LGU Authority.
     *
     * This method must be used exclusively for
     * read-only aggregated monitoring endpoints.
     */
    public function resolveMonitoringMunicipality(
        User $user,
        ?string $requestedMunicipalityId = null
    ): ?string {
        $roles = $user->roles()->pluck('slug')->all();

        if (in_array('admin', $roles, true)) {
            return $this->validateMonitoringMunicipality(
                $requestedMunicipalityId
            );
        }

        if (!in_array('lgu_authority', $roles, true)) {
            throw new AuthorizationException(
                'Only LGU Authorities may access cross-municipality monitoring.'
            );
        }

        if (
            $user->status !== 'active' ||
            $user->verification_status !== 'verified'
        ) {
            throw new AuthorizationException(
                'Your LGU Authority account must be active and verified.'
            );
        }

        $profile = $user->lguOfficerProfile()
            ->with('municipality')
            ->first();

        if (
            !$profile?->municipality ||
            !$profile->municipality->is_active
        ) {
            throw new AuthorizationException(
                'You must belong to an active municipality.'
            );
        }

        return $this->validateMonitoringMunicipality(
            $requestedMunicipalityId
        );
    }

    /**
     * Allow viewing all active municipalities
     * or selecting one active municipality.
     */
    private function validateMonitoringMunicipality(
        ?string $municipalityId
    ): ?string {
        if ($municipalityId === null) {
            return null;
        }

        $exists = Municipality::query()
            ->whereKey($municipalityId)
            ->where('is_active', true)
            ->exists();

        if (!$exists) {
            throw new AuthorizationException(
                'The selected municipality is unavailable for monitoring.'
            );
        }

        return $municipalityId;
    }
}
