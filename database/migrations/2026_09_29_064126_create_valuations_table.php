<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('valuations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('livestock_id')
                ->references('id')
                ->on('livestock_records');

            $table->foreignUuid('price_reference_id')
                ->nullable()
                ->references('id')
                ->on('price_references');

            $table->foreignUuid('weight_estimation_id')
                ->nullable()
                ->references('id')
                ->on('weight_estimations');

            $table->enum('weight_type', [
                'actual',
                'estimated',
            ]);

            $table->decimal('weight_used_kg', 12, 4);

            /*
             * Original resolved price before adjustments.
             */
            $table->decimal('base_price_per_kg', 10, 2);

            $table->decimal('breed_factor', 8, 4)
                ->default(1.0000);

            $table->decimal('age_factor', 8, 4)
                ->default(1.0000);

            $table->decimal('reproductive_factor', 8, 4)
                ->default(1.0000);

            $table->decimal('frame_factor', 8, 4)
                ->default(1.0000);

            $table->decimal('condition_factor', 8, 4)
                ->default(1.0000);

            $table->decimal('final_price_per_kg', 10, 2);

            $table->decimal('estimated_value', 14, 2);

            /*
             * Human-readable/structured explanation of factors
             * that were applied.
             */
            $table->json('adjustment_breakdown')->nullable();

            /*
             * Full immutable input/output snapshot.
             */
            $table->json('calculation_snapshot');

            $table->string('calculation_version');

            $table->foreignUuid('calculated_by')
                ->references('id')
                ->on('users');

            $table->timestamps();

            $table->index(['livestock_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valuations');
    }
};