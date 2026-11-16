<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('mobile_number', 20);
            $table->enum('buyer_type', ['individual', 'business']);
            $table->string('account_code', 32)->unique();
            $table->foreignUuid('auction_id')->constrained('auction_schedules')->cascadeOnDelete();
            $table->foreignUuid('municipality_id')->constrained('municipalities')->restrictOnDelete();
            $table->enum('attendance_intention', ['yes', 'no', 'tentative']);
            $table->boolean('contact_consent')->default(false);
            $table->enum('status', ['temporary', 'expired'])->default('temporary');
            $table->boolean('is_temporary')->default(true);
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['account_code', 'auction_id'], 'buyers_account_code_auction_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyers');
    }
};
