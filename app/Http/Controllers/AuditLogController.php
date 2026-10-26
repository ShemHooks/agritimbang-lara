<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'municipality_id' => ['nullable', 'uuid', Rule::exists('municipalities', 'id')],
            'user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
            'action' => ['nullable', 'string', 'max:255'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $isAdmin = $user->roles()->where('slug', 'admin')->exists();
        $isAuthority = $user->roles()->where('slug', 'lgu_authority')->exists();

        // Defence in depth: do not rely on route middleware alone.
        abort_unless($isAdmin || $isAuthority, 403);

        $municipalityId = $validated['municipality_id'] ?? null;
        if (! $isAdmin) {
            $assignedId = $user->lguOfficerProfile?->municipality_id;
            $isActive = $assignedId && DB::table('municipalities')
                ->where('id', $assignedId)->where('is_active', true)->exists();
            abort_unless($isActive, 403, 'No active assigned municipality.');
            abort_if($municipalityId !== null && $municipalityId !== $assignedId, 403);
            $municipalityId = $assignedId;
        }

        $query = AuditLog::query()->with('user:id,first_name,last_name');
        if ($municipalityId !== null) {
            $query->where('municipality_id', $municipalityId);
        }
        foreach (['user_id', 'action', 'entity_type', 'entity_id'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }
        if (isset($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }
        if (isset($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        // LGU users must never see global or unscoped audit entries.
        return response()->json([
            'data' => $query->orderByDesc('created_at')->orderByDesc('id')
                ->paginate($validated['per_page'] ?? 20),
        ]);
    }
}
