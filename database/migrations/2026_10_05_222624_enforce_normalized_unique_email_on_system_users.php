<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement(
                'CREATE UNIQUE INDEX users_email_normalized_unique
                 ON system.users (LOWER(BTRIM(email)))'
            );

            DB::statement(
                'UPDATE system.users
                 SET email = LOWER(BTRIM(email))
                 WHERE email <> LOWER(BTRIM(email))'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement(
                'DROP INDEX IF EXISTS system.users_email_normalized_unique'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
