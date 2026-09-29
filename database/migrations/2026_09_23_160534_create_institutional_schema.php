<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create the institutional PostgreSQL schema.
     */
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement('CREATE SCHEMA institutional');
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    /**
     * Drop the institutional PostgreSQL schema.
     */
    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement('DROP SCHEMA institutional');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};