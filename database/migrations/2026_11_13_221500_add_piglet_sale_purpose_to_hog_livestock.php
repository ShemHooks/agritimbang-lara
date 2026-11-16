<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // Deprecated: Piglet is not a supported sale purpose. The original
        // four sale purposes remain the only values accepted by application
        // validation. Existing database enum values are retained for legacy
        // records and are not offered for new records.
    }

    public function down(): void
    {
        // No schema change is performed by this deprecated migration.
    }
};
