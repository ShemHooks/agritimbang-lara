<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('breeds', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('species_id')
                ->references('id')
                ->on('species');

            $table->string('name');

            /*
             * Stable machine-readable identifier.
             *
             * Examples:
             * PH_NATIVE
             * LANDRACE
             * MURRAH_CROSS
             */
            $table->string('code');

            /*
             * Broad classification used by services such as
             * the WET formula resolver.
             *
             * This is NOT itself a formula code.
             */
            $table->string('breed_group')->nullable();

            $table->string('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['species_id', 'name'],
                'breeds_species_name_unique'
            );

            $table->unique(
                ['species_id', 'code'],
                'breeds_species_code_unique'
            );

            $table->index(
                ['species_id', 'breed_group', 'is_active'],
                'breeds_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breeds');
    }
};