<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE transactions
            MODIFY COLUMN source
            ENUM(
                'farmer',
                'auction_house',
                'lgu_encoder'
            )
            NOT NULL DEFAULT 'farmer'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Prevent rollback from invalidating existing data.
        $hasEncoderTransactions = DB::table('transactions')
            ->where('source', 'lgu_encoder')
            ->exists();

        if ($hasEncoderTransactions) {
            throw new RuntimeException(
                'Cannot rollback: LGU Encoder transactions exist.'
            );
        }

        DB::statement("
            ALTER TABLE transactions
            MODIFY COLUMN source
            ENUM(
                'farmer',
                'auction_house'
            )
            NOT NULL DEFAULT 'farmer'
        ");
    }
};
