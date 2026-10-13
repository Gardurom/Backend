<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement(<<<'SQL'
                ALTER TABLE institutional.persons
                    ADD CONSTRAINT persons_apellidos_check
                    CHECK (
                        apellido_paterno IS NOT NULL
                        OR apellido_materno IS NOT NULL
                    )
                SQL);
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement(
                'ALTER TABLE institutional.persons
                 DROP CONSTRAINT IF EXISTS persons_apellidos_check'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
