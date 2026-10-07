<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pricing_adjustment_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->enum('dimension', [
                'breed',
                'age_stage',
                'reproductive_status',
                'frame',
            ]);

            $table->foreignUuid('species_id')
                ->references('id')
                ->on('species');

            $table->foreignUuid('breed_id')
                ->nullable()
                ->references('id')
                ->on('breeds');

            /*
             * Examples:
             * finisher
             * has_given_birth
             * large
             * etc.
             */
            $table->string('category_key');

            $table->enum('sale_purpose', [
                'slaughter',
                'breeding',
                'fattening',
                'work',
            ])->nullable();

            $table->decimal('factor', 8, 4)->default(1.0000);

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->text('source_note')->nullable();

            $table->enum('status', [
                'draft',
                'approved',
                'archived',
            ])->default('draft');

            $table->foreignUuid('approved_by')
                ->nullable()
                ->references('id')
                ->on('users');

            $table->timestamps();

            $table->index(
                [
                    'species_id',
                    'dimension',
                    'category_key',
                    'status',
                ],
                'pricing_rules_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_adjustment_rules');
    }
};