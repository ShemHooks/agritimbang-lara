<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('transaction_id')
                ->references('id')
                ->on('transactions');

            $table->foreignUuid('livestock_id')
                ->references('id')
                ->on('livestock_records');

            $table->foreignUuid('valuation_id')
                ->references('id')
                ->on('valuations');

            $table->enum('weight_type', [
                'actual',
                'estimated',
            ]);

            $table->decimal('weight_used_kg', 12, 4);

            $table->decimal('base_price_per_kg', 10, 2);

            $table->decimal('final_price_per_kg', 10, 2);

            $table->decimal('reference_value', 14, 2);

            /*
             * Actual negotiated TOTAL price for this animal/item.
             */
            $table->decimal('actual_selling_price', 14, 2);

            /*
             * Useful later for analytics/ARIMA.
             */
            $table->decimal(
                'actual_selling_price_per_kg',
                10,
                2
            )->nullable();

            $table->decimal('price_difference', 14, 2)
                ->nullable();

            $table->decimal('percentage_deviation', 10, 4)
                ->nullable();

            /*
             * Immutable copy of the valuation used when
             * the transaction was created.
             */
            $table->json('valuation_snapshot');

            $table->string('calculation_version');

            $table->timestamps();

            $table->index(['transaction_id', 'livestock_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};