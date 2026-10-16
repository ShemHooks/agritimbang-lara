<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'pending',
                'pending_lgu_verification',
                'verified',
                'rejected',
                'cancelled',
            ])->default('draft')->change();
        });
    }

    public function down(): void
    {
        // New statuses must be migrated before rolling back.
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'pending',
                'verified',
                'rejected',
                'cancelled',
            ])->default('draft')->change();
        });
    }
};
