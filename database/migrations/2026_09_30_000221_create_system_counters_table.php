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
            Schema::create('system.counters', function (Blueprint $table) {
                $table->string('counter_type', 50);
                $table->smallInteger('year');
                $table->bigInteger('last_value');

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->primary(
                    ['counter_type', 'year'],
                    'counters_primary'
                );
            });

            DB::statement(<<<'SQL'
                ALTER TABLE system.counters
                    ADD CONSTRAINT counters_type_check
                        CHECK (
                            counter_type ~ '[^[:space:]]'
                            AND counter_type !~ '^[[:space:]]|[[:space:]]$'
                        ),
                    ADD CONSTRAINT counters_year_check
                        CHECK (year BETWEEN 1 AND 9999),
                    ADD CONSTRAINT counters_last_value_check
                        CHECK (last_value >= 0)
                SQL);

            DB::statement(
                'REVOKE ALL PRIVILEGES ON TABLE system.counters FROM PUBLIC'
            );

            DB::statement(
                'REVOKE ALL PRIVILEGES ON TABLE system.counters FROM siga_app'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            Schema::dropIfExists('system.counters');
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};