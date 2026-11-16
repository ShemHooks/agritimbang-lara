<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_interests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->foreignUuid('livestock_id')->constrained('livestock_records')->restrictOnDelete();
            $table->enum('status', ['active', 'withdrawn'])->default('active');
            $table->timestamps();

            $table->unique(['buyer_id', 'livestock_id'], 'buyer_interests_buyer_livestock_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_interests');
    }
};
