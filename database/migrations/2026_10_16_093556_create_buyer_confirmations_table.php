<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('buyer_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('transaction_id')
                ->constrained('transactions');

            $table->string('buyer_phone');

            // SHA-256 hash; never store the raw token.
            $table->string('token_hash', 64)->unique();

            // Hash of transaction details presented to buyer.
            $table->string('transaction_snapshot_hash', 64);

            $table->enum('status', [
                'pending',
                'confirmed',
                'disputed',
                'expired',
                'revoked',
            ])->default('pending');

            $table->dateTime('expires_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('disputed_at')->nullable();
            $table->dateTime('consumed_at')->nullable();

            $table->text('dispute_reason')->nullable();

            $table->timestamps();

            $table->index(['transaction_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_confirmations');
    }
};
