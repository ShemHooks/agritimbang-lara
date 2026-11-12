<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $column = DB::selectOne("
            SHOW COLUMNS
            FROM transaction_status_logs
            LIKE 'actor_type'
        ");

        $type = $column->Type;

        if (!str_starts_with(strtolower($type), 'enum(')) {
            throw new RuntimeException(
                'actor_type must be an ENUM column.'
            );
        }

        // Preserve all existing ENUM values.
        if (preg_match("/'lgu'(?:,|\))/", $type)) {
            return;
        }

        $newType = substr($type, 0, -1) . ",'lgu')";

        // Preserve the existing nullability and default.
        $nullable = $column->Null === 'YES'
            ? 'NULL'
            : 'NOT NULL';

        $default = $column->Default === null
            ? ''
            : ' DEFAULT ' . DB::getPdo()->quote($column->Default);

        DB::statement("
            ALTER TABLE transaction_status_logs
            MODIFY COLUMN actor_type
            {$newType} {$nullable}{$default}
        ");
    }

    public function down(): void
    {
        // Preserve the expanded ENUM to avoid losing
        // values used by existing transaction logs.
    }
};
