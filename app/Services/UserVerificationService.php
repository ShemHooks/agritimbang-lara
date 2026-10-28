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
        User $admin
    ): LengthAwarePaginator {
        $this->ensureSystemAdmin($admin);

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
            ->with([
                'farmerProfile.municipality',
                'farmerProfile.barangay',
                'verificationDocuments' => fn($query) =>
                    $query
                        ->where('status', 'pending')
                        ->latest(),
            ])
            ->orderBy('last_name')
            ->paginate(15);
    }

    public function getFarmerForReview(
        User $admin,
        string $farmerId
    ): User {
        $this->ensureSystemAdmin($admin);

        return User::query()
            ->whereKey($farmerId)
            ->whereHas(
                'roles',
                fn($query) => $query->where('slug', 'farmer')
            )
            ->with([
                'farmerProfile.municipality',
                'farmerProfile.barangay',
                'verificationDocuments.reviewer',
            ])
            ->firstOrFail();
    }

    public function getDocumentForReview(
        User $admin,
        string $documentId
    ): UserVerificationDocument {
        $this->ensureSystemAdmin($admin);

        return UserVerificationDocument::query()
            ->whereKey($documentId)
            ->whereHas(
                'user.roles',
                fn($query) => $query->where('slug', 'farmer')
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
        User $admin,
        string $documentId
    ): UserVerificationDocument {
        $this->ensureSystemAdmin($admin);

        return DB::transaction(function () use ($admin, $documentId) {
            $document = $this->lockDocumentForReview($documentId);

            $this->ensurePending($document);

            $farmer = $document->user;

            $document->update([
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => $admin->id,
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
        User $admin,
        string $documentId,
        string $reason
    ): UserVerificationDocument {
        $this->ensureSystemAdmin($admin);

        return DB::transaction(function () use ($admin, $documentId, $reason) {
            $document = $this->lockDocumentForReview($documentId);

            $this->ensurePending($document);

            $document->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
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

    private function ensureSystemAdmin(User $user): void
    {
        abort_unless(
            $user->roles()
                ->where('slug', 'admin')
                ->exists(),
            403,
            'Only the System Admin can review farmer verifications.'
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
