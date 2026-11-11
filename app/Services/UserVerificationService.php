<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\User;
use App\Models\UserVerificationDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserVerificationService
{
    /*
    |--------------------------------------------------------------------------
    | Farmer — Own Verification Documents
    |--------------------------------------------------------------------------
    */

    public function getDocuments(User $farmer)
    {
        return UserVerificationDocument::query()
            ->where('user_id', $farmer->id)
            ->with('reviewer')
            ->latest()
            ->get();
    }

    public function getFarmerDocument(
        User $farmer,
        string $documentId
    ): UserVerificationDocument {
        return UserVerificationDocument::query()
            ->whereKey($documentId)
            ->where('user_id', $farmer->id)
            ->with('reviewer')
            ->firstOrFail();
    }

    public function uploadDocument(
        User $farmer,
        array $data,
        UploadedFile $file
    ): UserVerificationDocument {
        // Serialize submissions for the same farmer.
        // Prevents concurrent requests from creating
        // multiple pending verification documents.
        $farmer = User::query()
            ->whereKey($farmer->id)
            ->firstOrFail();

        $path = null;

        try {
            return DB::transaction(function () use ($farmer, $data, $file, &$path) {
                $farmer = User::query()
                    ->whereKey($farmer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $profile = FarmerProfile::query()
                    ->where('user_id', $farmer->id)
                    ->first();

                if (!$profile) {
                    throw ValidationException::withMessages([
                        'profile' => [
                            'Complete your farmer profile before submitting verification documents.',
                        ],
                    ]);
                }

                if ($farmer->verification_status === 'verified') {
                    throw ValidationException::withMessages([
                        'document' => [
                            'Your account is already verified.',
                        ],
                    ]);
                }

                $hasPendingDocument = UserVerificationDocument::query()
                    ->where('user_id', $farmer->id)
                    ->where('status', 'pending')
                    ->exists();

                if ($hasPendingDocument) {
                    throw ValidationException::withMessages([
                        'document' => [
                            'You already have a verification document awaiting review.',
                        ],
                    ]);
                }

                $path = $file->store(
                    "verification-documents/{$farmer->id}",
                    'private'
                );

                if (!$path) {
                    throw ValidationException::withMessages([
                        'document' => [
                            'Unable to store the verification document.',
                        ],
                    ]);
                }

                $document = UserVerificationDocument::create([
                    'user_id' => $farmer->id,
                    'document_type' => $data['document_type'],
                    'document_number' => $data['document_number'] ?? null,
                    'file_path' => $path,
                    'status' => 'pending',
                ]);

                $farmer->update([
                    'verification_status' => 'pending',
                ]);

                return $document;
            });
        } catch (\Throwable $exception) {
            // Database rollback does not remove stored files.
            if ($path !== null) {
                Storage::disk('private')->delete($path);
            }

            throw $exception;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | System Admin — Farmer Verification
    |--------------------------------------------------------------------------
    | Only System Admin may review verification requests,
    | access submitted documents, approve, or reject.
    |
    | No municipality restrictions apply to System Admin.
    */


    public function getPendingFarmers(
        User $reviewer
    ): LengthAwarePaginator {
        $municipalityId = $this->reviewerMunicipalityId($reviewer);

        return User::query()
            ->where('verification_status', 'pending')
            ->whereHas(
                'roles',
                fn($query) => $query->where('slug', 'farmer')
            )
            ->whereHas(
                'verificationDocuments',
                fn($query) => $query->where('status', 'pending')
            )
            ->when(
                $municipalityId !== null,
                fn($query) => $query->whereHas(
                    'farmerProfile',
                    fn($profile) => $profile->where(
                        'municipality_id',
                        $municipalityId
                    )
                )
            )
            ->with([
                'farmerProfile.municipality',
                'farmerProfile.barangay',
                'verificationDocuments' => fn($query) =>
                    $query->where('status', 'pending')->latest(),
            ])
            ->orderBy('last_name')
            ->paginate(15);
    }



    public function getFarmerForReview(
        User $reviewer,
        string $farmerId
    ): User {
        $municipalityId = $this->reviewerMunicipalityId($reviewer);

        return User::query()
            ->whereKey($farmerId)
            ->whereHas(
                'roles',
                fn($query) => $query->where('slug', 'farmer')
            )
            ->when(
                $municipalityId !== null,
                fn($query) => $query->whereHas(
                    'farmerProfile',
                    fn($profile) => $profile->where(
                        'municipality_id',
                        $municipalityId
                    )
                )
            )
            ->with([
                'farmerProfile.municipality',
                'farmerProfile.barangay',
                'verificationDocuments.reviewer',
            ])
            ->firstOrFail();
    }



    public function getDocumentForReview(
        User $reviewer,
        string $documentId
    ): UserVerificationDocument {
        $municipalityId = $this->reviewerMunicipalityId($reviewer);

        return UserVerificationDocument::query()
            ->whereKey($documentId)
            ->whereHas(
                'user.roles',
                fn($query) => $query->where('slug', 'farmer')
            )
            ->when(
                $municipalityId !== null,
                fn($query) => $query->whereHas(
                    'user.farmerProfile',
                    fn($profile) => $profile->where(
                        'municipality_id',
                        $municipalityId
                    )
                )
            )
            ->with([
                'user.farmerProfile.municipality',
                'user.farmerProfile.barangay',
                'reviewer',
            ])
            ->firstOrFail();
    }


    /*
    |--------------------------------------------------------------------------
    | System Admin — Approve Verification
    |--------------------------------------------------------------------------
    */


    public function approve(
        User $reviewer,
        string $documentId
    ): UserVerificationDocument {
        $this->reviewerMunicipalityId($reviewer);

        return DB::transaction(function () use ($reviewer, $documentId) {
            $document = $this->lockDocumentForReview(
                $documentId
            );

            $farmer = $document->user;

            $this->ensureReviewerCanAccessFarmer(
                $reviewer,
                $farmer
            );

            $this->ensurePending($document);

            $document->update([
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $farmer->update([
                'verification_status' => 'verified',
            ]);

            $this->ensureFarmerCode($farmer);

            return $document->fresh([
                'user.farmerProfile.municipality',
                'user.farmerProfile.barangay',
                'reviewer',
            ]);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | System Admin — Reject Verification
    |--------------------------------------------------------------------------
    */


    public function reject(
        User $reviewer,
        string $documentId,
        string $reason
    ): UserVerificationDocument {
        $this->reviewerMunicipalityId($reviewer);

        return DB::transaction(function () use ($reviewer, $documentId, $reason) {
            $document = $this->lockDocumentForReview(
                $documentId
            );

            $this->ensureReviewerCanAccessFarmer(
                $reviewer,
                $document->user
            );

            $this->ensurePending($document);

            $document->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $document->user->update([
                'verification_status' => 'rejected',
            ]);

            return $document->fresh([
                'user.farmerProfile.municipality',
                'user.farmerProfile.barangay',
                'reviewer',
            ]);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */


    private function reviewerMunicipalityId(User $reviewer): ?string
    {
        if ($reviewer->roles()->where('slug', 'admin')->exists()) {
            return null;
        }

        abort_unless(
            $reviewer->roles()
                ->where('slug', 'lgu_authority')
                ->exists(),
            403,
            'You are not authorized to review farmer verifications.'
        );

        abort_unless(
            $reviewer->status === 'active' &&
            $reviewer->verification_status === 'verified',
            403,
            'Your LGU Authority account must be active and verified.'
        );

        $profile = $reviewer->lguOfficerProfile()
            ->with('municipality')
            ->first();

        abort_unless(
            $profile?->municipality &&
            $profile->municipality->is_active,
            403,
            'You must be assigned to an active municipality.'
        );

        return $profile->municipality_id;
    }

    private function ensureReviewerCanAccessFarmer(
        User $reviewer,
        User $farmer
    ): void {
        $municipalityId = $this->reviewerMunicipalityId($reviewer);

        if ($municipalityId === null) {
            return;
        }

        $belongsToMunicipality = FarmerProfile::query()
            ->where('user_id', $farmer->id)
            ->where('municipality_id', $municipalityId)
            ->exists();

        abort_unless(
            $belongsToMunicipality,
            404,
            'Farmer verification not found.'
        );
    }


    private function lockDocumentForReview(
        string $documentId
    ): UserVerificationDocument {
        $document = UserVerificationDocument::query()
            ->whereKey($documentId)
            ->whereHas(
                'user.roles',
                fn($query) => $query->where('slug', 'farmer')
            )
            ->lockForUpdate()
            ->firstOrFail();

        $document->load('user');

        return $document;
    }

    private function ensurePending(
        UserVerificationDocument $document
    ): void {
        if ($document->status !== 'pending') {
            throw ValidationException::withMessages([
                'document' => [
                    'Only pending verification documents can be reviewed.',
                ],
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Farmer Code
    |--------------------------------------------------------------------------
    */

    private function ensureFarmerCode(User $farmer): void
    {
        $profile = $farmer->farmerProfile;

        if (!$profile || $profile->farmer_code) {
            return;
        }

        $profile->update([
            'farmer_code' =>
                'FMR-' .
                strtoupper(
                    substr(
                        str_replace('-', '', $farmer->id),
                        0,
                        10
                    )
                ),
        ]);
    }
}
