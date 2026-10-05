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
            Schema::table('system.users', function (Blueprint $table) {
                $table->uuid('id_persona')
                    ->nullable()
                    ->unique('users_person_unique');

                $table->foreign(
                    'id_persona',
                    'users_person_fk'
                )
                    ->references('id_persona')
                    ->on('institutional.persons')
                    ->restrictOnUpdate()
                    ->restrictOnDelete();
            });
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::table('system.users', function (Blueprint $table) {
                $table->dropForeign('users_person_fk');
                $table->dropUnique('users_person_unique');
                $table->dropColumn('id_persona');
            });
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
