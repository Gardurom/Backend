<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create the system PostgreSQL schema.
     */
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement('CREATE SCHEMA system');
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    /**
     * Drop the system PostgreSQL schema.
     */
    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement('DROP SCHEMA system');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};