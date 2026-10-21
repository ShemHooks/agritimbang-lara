<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('transaction_id')
                ->constrained('transactions')
                ->restrictOnDelete();

            $table->foreignUuid('buyer_confirmation_id')
                ->nullable()
                ->constrained('buyer_confirmations')
                ->restrictOnDelete();

            // Buyers may report through a token without an account.
            $table->foreignUuid('reported_by')
                ->nullable()
                ->constrained('users');

            $table->enum('reporter_type', [
                'buyer',
                'farmer',
                'lgu',
            ]);

            $table->string('reason');
            $table->text('description');

            $table->enum('status', [
                'open',
                'under_review',
                'resolved',
                'dismissed',
            ])->default('open');

            $table->foreignUuid('reviewed_by')
                ->nullable()
                ->constrained('users');

            $table->timestamp('reviewed_at')->nullable();

            $table->foreignUuid('resolved_by')
                ->nullable()
                ->constrained('users');

            $table->timestamp('resolved_at')->nullable();

            $table->enum('resolution', [
                'upheld',
                'dismissed',
            ])->nullable();

            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            $table->index(['transaction_id', 'status']);
            $table->index(['reporter_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_disputes');
    }
};
