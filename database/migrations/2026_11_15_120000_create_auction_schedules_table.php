<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auction_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('municipality_id')->constrained('municipalities')->restrictOnDelete();
            $table->foreignUuid('auction_house_id')->constrained('auction_houses')->restrictOnDelete();
            $table->foreignUuid('barangay_id')->constrained('barangays')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->date('auction_date');
            $table->time('start_time');
            $table->time('expected_end_time')->nullable();
            $table->string('venue', 255);
            $table->dateTime('registration_opens_at');
            $table->dateTime('registration_closes_at');
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->index(['municipality_id', 'status', 'auction_date'], 'auction_schedule_municipality_status_date_idx');
            $table->index(['registration_opens_at', 'registration_closes_at'], 'auction_schedule_registration_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_schedules');
    }
};
