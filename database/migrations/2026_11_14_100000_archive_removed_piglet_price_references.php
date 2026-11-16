<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Keep historical rows for audit purposes, but remove them from active
        // official pricing and approval workflows. New Piglet purposes are
        // rejected by the request validators and excluded from recommendations.
        DB::table('price_references')
            ->where(function ($query) {
                $query->where('sale_purpose', 'piglet')
                    ->orWhere('reference_category', 'like', 'Piglet%');
            })
            ->update([
                'status' => 'archived',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally do not reactivate legacy records automatically.
        // Their prior statuses may have changed since this migration ran.
    }
};
