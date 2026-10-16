<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_status_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('transaction_id')
                ->constrained('transactions');

            $table->string('from_status')->nullable();
            $table->string('to_status');

            $table->enum('actor_type', [
                'farmer',
                'buyer',
                'lgu',
                'system',
            ]);

            $table->foreignUuid('actor_user_id')
                ->nullable()
                ->constrained('users');

            $table->text('remarks')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['transaction_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_status_logs');
    }
};
