<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auction_houses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');

            $table->foreignUuid('municipality_id')
                ->constrained('municipalities');

            $table->foreignUuid('barangay_id')
                ->nullable()
                ->constrained('barangays');

            $table->string('address')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['municipality_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_houses');
    }
};
