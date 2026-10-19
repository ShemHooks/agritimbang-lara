<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('source', [
                'farmer',
                'auction_house',
            ])->default('farmer');

            $table->foreignUuid('auction_house_id')
                ->nullable()
                ->constrained('auction_houses')
                ->restrictOnDelete();

            $table->enum('buyer_confirmation_method', [
                'sms',
                'in_person',
            ])->nullable();

            $table->string('acknowledgement_reference')
                ->nullable();

            $table->text('acknowledgement_notes')
                ->nullable();

            $table->index(['source', 'status']);
        });

        // Preserve existing farmer transactions.
        DB::table('transactions')
            ->where('source', 'farmer')
            ->whereNull('buyer_confirmation_method')
            ->update([
                'buyer_confirmation_method' => 'sms',
            ]);

        // Auction-house sales may have no previous FPC valuation.
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropForeign(['valuation_id']);
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->uuid('valuation_id')
                ->nullable()
                ->change();

            $table->foreign('valuation_id')
                ->references('id')
                ->on('valuations');
        });
    }

    public function down(): void
    {
        // Existing auction-house rows must be removed or migrated
        // before restoring the NOT NULL valuation constraint.
        if (
            DB::table('transaction_items')
                ->whereNull('valuation_id')
                ->exists()
        ) {
            throw new RuntimeException(
                'Cannot roll back: transaction items have null valuations.'
            );
        }

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropForeign(['valuation_id']);
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->uuid('valuation_id')
                ->nullable(false)
                ->change();

            $table->foreign('valuation_id')
                ->references('id')
                ->on('valuations');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['source', 'status']);
            $table->dropForeign(['auction_house_id']);

            $table->dropColumn([
                'source',
                'auction_house_id',
                'buyer_confirmation_method',
                'acknowledgement_reference',
                'acknowledgement_notes',
            ]);
        });
    }
};
