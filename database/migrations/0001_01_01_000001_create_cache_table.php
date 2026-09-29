<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::create('system.cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->bigInteger('expiration')->index();
            });

            Schema::create('system.cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->bigInteger('expiration')->index();
            });

            DB::statement('GRANT USAGE ON SCHEMA system TO siga_app');
            DB::statement(
                'GRANT SELECT, INSERT, UPDATE, DELETE
                 ON TABLE system.cache, system.cache_locks TO siga_app'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::dropIfExists('system.cache_locks');
            Schema::dropIfExists('system.cache');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};