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
            Schema::create('system.permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('code', 100)->unique();
                $table->timestamps();
            });

            DB::statement(
                'GRANT SELECT
                 ON TABLE system.permissions
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
            Schema::dropIfExists('system.permissions');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};
