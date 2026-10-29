<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {

        DB::statement('ALTER TABLE jobs DROP PRIMARY KEY');

        Schema::table('jobs', function (Blueprint $table) {
            $table->bigIncrements('id')->first();
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {

    }
};