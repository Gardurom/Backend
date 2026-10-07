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
            Schema::create('system.role_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');
                $table->timestamps();

                $table->primary(
                    ['role_id', 'permission_id'],
                    'role_permissions_pkey'
                );

                $table->foreign(
                    'role_id',
                    'role_permissions_role_fk'
                )
                    ->references('id')
                    ->on('system.roles')
                    ->restrictOnUpdate()
                    ->cascadeOnDelete();

                $table->foreign(
                    'permission_id',
                    'role_permissions_permission_fk'
                )
                    ->references('id')
                    ->on('system.permissions')
                    ->restrictOnUpdate()
                    ->cascadeOnDelete();
            });

            DB::statement(
                'GRANT SELECT, INSERT, DELETE
                 ON TABLE system.role_permissions
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
            Schema::dropIfExists('system.role_permissions');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
