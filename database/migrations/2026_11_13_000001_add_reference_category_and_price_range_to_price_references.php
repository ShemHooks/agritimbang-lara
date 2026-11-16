<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('price_references', function (Blueprint $table) {
            if (!Schema::hasColumn('price_references', 'reference_category')) {
                $table->string('reference_category', 40)->default('Slaughter')->after('sale_purpose');
            }
            if (!Schema::hasColumn('price_references', 'price_min_per_unit')) {
                $table->decimal('price_min_per_unit', 10, 2)->nullable()->after('price_per_kg');
            }
            if (!Schema::hasColumn('price_references', 'price_max_per_unit')) {
                $table->decimal('price_max_per_unit', 10, 2)->nullable()->after('price_min_per_unit');
            }
            if (!Schema::hasColumn('price_references', 'price_unit')) {
                $table->string('price_unit', 20)->default('PHP/kg')->after('price_max_per_unit');
            }
        });

        Schema::table('livestock_records', function (Blueprint $table) {
            if (!Schema::hasColumn('livestock_records', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('actual_weight_kg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('livestock_records', function (Blueprint $table) {
            if (Schema::hasColumn('livestock_records', 'photo_path')) $table->dropColumn('photo_path');
        });
        Schema::table('price_references', function (Blueprint $table) {
            foreach (['reference_category', 'price_min_per_unit', 'price_max_per_unit', 'price_unit'] as $column) {
                if (Schema::hasColumn('price_references', $column)) $table->dropColumn($column);
            }
        });
    }
};
