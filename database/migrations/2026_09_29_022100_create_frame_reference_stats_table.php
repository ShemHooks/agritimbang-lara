<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('frame_reference_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('species_id')
                ->references('id')
                ->on('species');

            $table->foreignUuid('breed_id')
                ->nullable()
                ->references('id')
                ->on('breeds');

            $table->string('age_group');

            $table->enum('sex', [
                'male',
                'female',
                'unknown',
            ]);

            $table->unsignedInteger('sample_size');

            $table->decimal('mean_body_length_cm', 10, 4);
            $table->decimal('sd_body_length_cm', 10, 4);

            $table->string('version');
            $table->timestamp('computed_at');

            $table->timestamps();

            $table->index([
                'species_id',
                'breed_id',
                'age_group',
                'sex',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frame_reference_stats');
    }
};