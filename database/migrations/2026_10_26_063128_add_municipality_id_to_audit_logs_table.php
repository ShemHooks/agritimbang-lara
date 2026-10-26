<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignUuid('municipality_id')->nullable()
                ->after('user_id')->constrained('municipalities')->nullOnDelete();
            $table->index(['municipality_id', 'created_at'], 'audit_logs_municipality_created_index');
            $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_entity_index');
            $table->dropIndex('audit_logs_municipality_created_index');
            $table->dropConstrainedForeignId('municipality_id');
        });
    }
};
