<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // Deprecated: Piglet is not a supported sale purpose. Price references
        // use Slaughter, Breeding, Fattening, or Work only. Existing enum values
        // in databases where this migration already ran are preserved solely
        // to avoid damaging historical records.
    }

    public function down(): void
    {
        // No schema change is performed by this deprecated migration.
    }
};
