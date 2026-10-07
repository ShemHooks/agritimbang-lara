<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('farmer_id')->references('id')->on('farmer_profiles');
            $table->string('buyer_name');
            $table->string('buyer_phone')->nullable();
            $table->foreignUuid('municipality_id')->references('id')->on('municipalities');
            $table->foreignUuid('barangay_id')->references('id')->on('barangays');
            $table->dateTime('transaction_date');
            $table->enum('status', ['draft', 'pending', 'verified', 'rejected', 'cancelled'])->default('draft');
            $table->decimal('total_reference_value', 14, 2)->default(0);
            $table->decimal('total_selling_price', 14, 2)->default(0);
            $table->foreignUuid('encoded_by')->references('id')->on('users');
            $table->foreignUuid('verified_by')->nullable()->references('id')->on('users');
            $table->dateTime('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
