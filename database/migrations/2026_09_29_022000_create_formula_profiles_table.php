<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('formula_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('formula_code');
            $table->string('name');

            $table->foreignUuid('species_id')
                ->references('id')
                ->on('species');

            $table->foreignUuid('breed_id')
                ->nullable()
                ->references('id')
                ->on('breeds');


            $table->json('required_inputs');


            $table->json('applicability')->nullable();

            $table->string('version');

            $table->text('source')->nullable();

            $table->boolean('is_fallback')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['formula_code', 'version'],
                'formula_profiles_code_version_unique'
            );

            $table->index(
                ['species_id', 'breed_id', 'is_active'],
                'formula_profiles_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_profiles');
    }
};