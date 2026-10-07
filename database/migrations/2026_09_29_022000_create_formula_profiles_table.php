<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('formula_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('formula_code')->unique();
            $table->string('name');

            $table->foreignUuid('species_id')
                ->references('id')
                ->on('species');

            $table->foreignUuid('breed_id')
                ->nullable()
                ->references('id')
                ->on('breeds');

            /*
             * Example:
             * ["heart_girth_cm", "body_length_cm"]
             *
             * Goat Barili:
             * ["rump_height_cm", "body_length_cm", "heart_girth_cm"]
             */
            $table->json('required_inputs');

            /*
             * Species/breed/age/stage applicability and documented
             * source ranges can be stored here.
             */
            $table->json('applicability')->nullable();

            $table->string('version');

            $table->text('source')->nullable();

            $table->boolean('is_fallback')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['species_id', 'breed_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_profiles');
    }
};