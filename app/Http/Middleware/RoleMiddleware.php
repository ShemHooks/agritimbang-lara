<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Compare role identifiers in PHP instead of relying on SQL string
        // normalization. This handles legacy values such as "LGU Encoder",
        // "LGU-Encoder", "lgu_encoder", and mixed-case variants consistently.
        $normalize = static function (?string $value): string {
            $value = strtolower(trim((string) $value));
            $value = preg_replace('/[^a-z0-9]+/', '_', $value);
            return trim($value, '_');
        };

        $allowed = array_values(array_unique(array_map($normalize, $roles)));
        $hasRole = $user->roles()
            ->get(['roles.name', 'roles.slug'])
            ->contains(function ($role) use ($normalize, $allowed) {
                return in_array($normalize($role->slug), $allowed, true)
                    || in_array($normalize($role->name), $allowed, true);
            });

        if (!$hasRole) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}