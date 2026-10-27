<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('municipality_id')->references('id')->on('municipalities');
            $table->string("name");
            $table->string("psgc_code")->nullable();
            $table->boolean("is_active")->default(true);
            $table->unique(['municipality_id', 'name']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangays');
    }
};
