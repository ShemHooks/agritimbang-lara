<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(
            'auction_house_encoder_assignments',
            function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->foreignUuid('auction_house_id')
                    ->constrained('auction_houses')
                    ->cascadeOnDelete();

                $table->foreignUuid('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->boolean('is_active')->default(true);

                $table->foreignUuid('assigned_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique([
                    'auction_house_id',
                    'user_id',
                ], 'auction_encoder_unique');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'auction_house_encoder_assignments'
        );
    }
};
