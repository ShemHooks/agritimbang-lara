<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('weight_estimations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('livestock_id')
                ->references('id')
                ->on('livestock_records');

            $table->foreignUuid('formula_profile_id')
                ->references('id')
                ->on('formula_profiles');

            $table->decimal('heart_girth_cm', 10, 2)->nullable();
            $table->decimal('body_length_cm', 10, 2)->nullable();
            $table->decimal('rump_height_cm', 10, 2)->nullable();

            $table->decimal('estimated_weight_kg', 12, 4);

            /*
             * Snapshot these even though formula_profile_id exists.
             * Formula configuration may change in the future.
             */
            $table->string('formula_code');
            $table->string('formula_version');

            $table->enum('estimate_confidence', [
                'normal',
                'low',
            ])->default('normal');

            $table->json('warnings')->nullable();

            $table->enum('frame_category', [
                'small',
                'medium',
                'large',
                'unknown',
            ])->default('unknown');

            $table->decimal('frame_z', 10, 4)->nullable();

            $table->decimal(
                'reference_mean_body_length_cm',
                10,
                4
            )->nullable();

            $table->decimal(
                'reference_sd_body_length_cm',
                10,
                4
            )->nullable();

            $table->unsignedInteger('reference_n')->nullable();

            $table->string('reference_group')->nullable();
            $table->string('frame_stats_version')->nullable();

            $table->foreignUuid('calculated_by')
                ->references('id')
                ->on('users');

            $table->timestamps();

            $table->index(['livestock_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_estimations');
    }
};