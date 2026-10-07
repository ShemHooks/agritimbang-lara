<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('livestock_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('farmer_id')
                ->references('id')
                ->on('farmer_profiles');

            $table->foreignUuid('species_id')
                ->references('id')
                ->on('species');

            $table->foreignUuid('breed_id')
                ->nullable()
                ->references('id')
                ->on('breeds');

            $table->enum('sex', [
                'male',
                'female',
                'unknown',
            ])->default('unknown');

            /*
             * Store objective age when known.
             * Species-specific age/production stage can be derived
             * by the application.
             */
            $table->unsignedSmallInteger('age_months')->nullable();

            /*
             * Snapshot/derived classification such as:
             * calf, yearling, young_adult, mature_adult,
             * piglet_pre_starter, starter, grower, finisher,
             * adult_breeder, kid, young, adult.
             */
            $table->string('age_group')->nullable();

            $table->enum('reproductive_status', [
                'has_given_birth',
                'never_given_birth',
                'not_applicable',
                'unknown',
            ])->default('unknown');

            $table->unsignedSmallInteger('parity')->nullable();

            /*
             * Required for resolving purpose-specific price references.
             */
            $table->enum('sale_purpose', [
                'slaughter',
                'breeding',
                'fattening',
                'work',
            ])->default('slaughter');

            /*
             * Livestock Condition Score:
             * 1 = poorest
             * 2 = below average
             * 3 = neutral
             * 4 = good
             * 5 = best
             */
            $table->unsignedTinyInteger('condition_score')->nullable();

            /*
             * Actual scale weight, when available.
             * Actual weight always takes priority over WET.
             */
            $table->decimal('actual_weight_kg', 10, 2)->nullable();

            $table->foreignUuid('municipality_id')
                ->references('id')
                ->on('municipalities');

            $table->foreignUuid('barangay_id')
                ->references('id')
                ->on('barangays');

            $table->enum('status', [
                'available',
                'in_transaction',
                'sold',
                'archived',
            ])->default('available');

            $table->foreignUuid('created_by')
                ->references('id')
                ->on('users');

            $table->timestamps();

            $table->index(['species_id', 'breed_id']);
            $table->index(['municipality_id', 'barangay_id']);
            $table->index(['farmer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livestock_records');
    }
};