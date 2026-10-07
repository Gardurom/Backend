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
            Schema::create('system.user_profiles', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('profile_id');
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->primary(
                    ['user_id', 'profile_id'],
                    'user_profiles_pkey'
                );

                $table->foreign(
                    'user_id',
                    'user_profiles_user_fk'
                )
                    ->references('id')
                    ->on('system.users')
                    ->restrictOnUpdate()
                    ->cascadeOnDelete();

                $table->foreign(
                    'profile_id',
                    'user_profiles_profile_fk'
                )
                    ->references('id')
                    ->on('system.profiles')
                    ->restrictOnUpdate()
                    ->cascadeOnDelete();
            });

            DB::statement(
                'CREATE UNIQUE INDEX user_profiles_one_default_per_user
                 ON system.user_profiles (user_id)
                 WHERE is_default'
            );

            DB::statement(
                'GRANT SELECT, INSERT, UPDATE, DELETE
                 ON TABLE system.user_profiles
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
            Schema::dropIfExists('system.user_profiles');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
