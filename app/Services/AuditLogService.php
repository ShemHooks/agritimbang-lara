<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AuditLogService
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'token',
        'access_token', 'refresh_token', 'api_token', 'remember_token',
        'secret', 'authorization', 'cookie', 'otp', 'otp_code', 'pin',
        'valid_id', 'id_number', 'document_number', 'document_path',
        'file_path', 'face_photo', 'face_image', 'photo', 'image',
    ];

    /**
     * Call from the same DB::transaction() as the successful business mutation.
     * Do not accept actor or municipality from untrusted request parameters.
     * For municipality-owned actions, a municipality UUID is required.
     */
    public function record(
        User $actor,
        string $action,
        Model $entity,
        ?string $municipalityId,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        if (trim($action) === '' || ! $entity->exists || ! $entity->getKey()) {
            throw new InvalidArgumentException('A persisted entity and non-empty action are required.');
        }

        return AuditLog::create([
            'user_id' => $actor->getKey(),
            'municipality_id' => $municipalityId,
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => (string) $entity->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 2000),
        ]);
    }

    public function sanitize(array $values): array
    {
        $clean = [];
        foreach ($values as $key => $value) {
            $normalized = strtolower((string) $key);
            if ($this->isSensitive($normalized)) {
                // Drop sensitive keys entirely rather than storing their contents.
                continue;
            }
            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }
        return $clean;
    }

    private function isSensitive(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($key === $sensitive || str_contains($key, $sensitive)) {
                return true;
            }
        }
        return false;
    }
}
