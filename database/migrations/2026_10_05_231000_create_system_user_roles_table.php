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
            Schema::create('system.user_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('role_id');
                $table->timestamps();

                $table->primary(
                    ['user_id', 'role_id'],
                    'user_roles_pkey'
                );

                $table->foreign(
                    'user_id',
                    'user_roles_user_fk'
                )
                    ->references('id')
                    ->on('system.users')
                    ->restrictOnUpdate()
                    ->cascadeOnDelete();

                $table->foreign(
                    'role_id',
                    'user_roles_role_fk'
                )
                    ->references('id')
                    ->on('system.roles')
                    ->restrictOnUpdate()
                    ->cascadeOnDelete();
            });

            DB::statement(
                'GRANT SELECT, INSERT, DELETE
                 ON TABLE system.user_roles
                 TO siga_app'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::dropIfExists('system.user_roles');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
