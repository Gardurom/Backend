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
            Schema::create('institutional.academic_participants', function (Blueprint $table) {
                $table->uuid('id_participante')
                    ->default(DB::raw('uuidv7()'))
                    ->primary();

                $table->uuid('id_persona')->unique();

                $table->foreign('id_persona')
                    ->references('id_persona')
                    ->on('institutional.persons')
                    ->restrictOnUpdate()
                    ->restrictOnDelete();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });

            Schema::create('institutional.academic_trainers', function (Blueprint $table) {
                $table->uuid('id_formador')
                    ->default(DB::raw('uuidv7()'))
                    ->primary();

                $table->uuid('id_persona')->unique();

                $table->foreign('id_persona')
                    ->references('id_persona')
                    ->on('institutional.persons')
                    ->restrictOnUpdate()
                    ->restrictOnDelete();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });

            foreach ([
                'institutional.academic_participants',
                'institutional.academic_trainers',
            ] as $table) {
                DB::statement("REVOKE ALL PRIVILEGES ON TABLE {$table} FROM PUBLIC");
                DB::statement("REVOKE ALL PRIVILEGES ON TABLE {$table} FROM siga_app");
                DB::statement("GRANT SELECT, INSERT ON TABLE {$table} TO siga_app");
            }
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::dropIfExists('institutional.academic_trainers');
            Schema::dropIfExists('institutional.academic_participants');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
