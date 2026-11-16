<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auction_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('auction_schedule_id')->constrained('auction_schedules')->restrictOnDelete();
            $table->foreignUuid('livestock_id')->constrained('livestock_records')->restrictOnDelete();
            $table->foreignUuid('farmer_id')->constrained('farmer_profiles')->restrictOnDelete();
            $table->foreignUuid('registered_by')->constrained('users')->restrictOnDelete();

            // A non-null value reserves this animal across ALL auction schedules.
            // Releasing/withdrawing/completing a registration sets it to NULL.
            $table->foreignUuid('active_livestock_id')->nullable()
                ->constrained('livestock_records')->restrictOnDelete();

            $table->string('status', 24)->default('registered');
            $table->dateTime('registered_at');
            $table->dateTime('withdrawn_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            // MySQL/MariaDB allow multiple NULL values in a unique index.
            // This enforces one active registration per livestock record.
            $table->unique('active_livestock_id', 'auction_registration_one_active_per_livestock');
            $table->index(['auction_schedule_id', 'status'], 'auction_registration_schedule_status_idx');
            $table->index(['farmer_id', 'created_at'], 'auction_registration_farmer_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_registrations');
    }
};
