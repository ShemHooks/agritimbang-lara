<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE transaction_status_logs
            MODIFY COLUMN actor_type
            ENUM(
                'farmer',
                'buyer',
                'lgu_authority',
                'lgu_encoder',
                'system'
            ) NOT NULL
        ");
    }

    public function down(): void
    {

    }
};
