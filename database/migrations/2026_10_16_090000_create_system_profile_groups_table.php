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
            Schema::create('system.profile_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->string('description', 255)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            DB::statement(
                'GRANT SELECT
                 ON TABLE system.profile_groups
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
            Schema::dropIfExists('system.profile_groups');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
