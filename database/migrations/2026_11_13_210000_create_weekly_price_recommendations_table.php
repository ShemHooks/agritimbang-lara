<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the weekly recommendation table when it does not exist.
     *
     * This migration is intentionally safe for installations where the table
     * was created manually or by an earlier partial migration attempt. In that
     * case, it preserves the existing rows and only adds the required composite
     * index if it is missing.
     */
    public function up(): void
    {
        if (! Schema::hasTable('weekly_price_recommendations')) {
            Schema::create('weekly_price_recommendations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('municipality_id')
                    ->constrained('municipalities')
                    ->cascadeOnDelete();
                $table->foreignUuid('species_id')
                    ->constrained('species');
                $table->string('sale_purpose', 30);
                $table->date('week_start');
                $table->date('week_end');
                $table->decimal('recommended_price', 12, 2)->nullable();
                $table->unsignedInteger('transaction_count')->default(0);
                $table->unsignedInteger('observation_count')->default(0);
                $table->unsignedInteger('included_count')->default(0);
                $table->unsignedInteger('excluded_count')->default(0);
                $table->decimal('q1', 14, 4)->nullable();
                $table->decimal('q3', 14, 4)->nullable();
                $table->decimal('iqr', 14, 4)->nullable();
                $table->decimal('lower_bound', 14, 4)->nullable();
                $table->decimal('upper_bound', 14, 4)->nullable();
                $table->json('observations')->nullable();
                $table->string('status', 24)->default('insufficient_data');
                $table->foreignUuid('generated_by')->constrained('users');
                $table->foreignUuid('used_by')->nullable()->constrained('users');
                $table->timestamp('used_at')->nullable();
                $table->foreignUuid('price_reference_id')
                    ->nullable()
                    ->constrained('price_references')
                    ->nullOnDelete();
                $table->timestamps();

                $table->unique(
                    ['municipality_id', 'species_id', 'sale_purpose', 'week_start'],
                    'weekly_recommendation_scope_unique'
                );
                $table->index(
                    ['municipality_id', 'week_start', 'status'],
                    'weekly_price_recommendations_municipality_week_status_index'
                );
            });

            return;
        }

        // Existing installations may already have this table but not this index.
        $indexes = collect(DB::select('SHOW INDEX FROM weekly_price_recommendations'))
            ->pluck('Key_name')
            ->unique();

        $requiredIndex = 'weekly_price_recommendations_municipality_week_status_index';

        if (! $indexes->contains($requiredIndex)) {
            Schema::table('weekly_price_recommendations', function (Blueprint $table) use ($requiredIndex) {
                $table->index(
                    ['municipality_id', 'week_start', 'status'],
                    $requiredIndex
                );
            });
        }
    }

    /**
     * Keep this migration's rollback non-destructive.
     *
     * The table may have existed before this migration was registered, so
     * dropping it here could delete pre-existing recommendation records.
     * Remove the table manually only if you intentionally want to erase it.
     */
    public function down(): void
    {
        // Intentionally no-op to protect existing recommendation data.
    }
};
