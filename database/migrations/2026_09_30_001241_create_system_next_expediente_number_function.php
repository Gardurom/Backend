<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION system.next_expediente_number()
                RETURNS VARCHAR(13)
                LANGUAGE plpgsql
                VOLATILE
                SECURITY DEFINER
                SET search_path = pg_catalog, pg_temp
                AS $function$
                DECLARE
                    v_year SMALLINT;
                    v_number BIGINT;
                BEGIN
                    v_year := EXTRACT(
                        YEAR FROM (
                            transaction_timestamp()
                            AT TIME ZONE 'America/Mexico_City'
                        )
                    )::SMALLINT;

                    INSERT INTO system.counters AS counter (
                        counter_type,
                        year,
                        last_value,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        'EXPEDIENTE',
                        v_year,
                        1,
                        transaction_timestamp(),
                        transaction_timestamp()
                    )
                    ON CONFLICT (counter_type, year)
                    DO UPDATE
                    SET
                        last_value = counter.last_value + 1,
                        updated_at = transaction_timestamp()
                    WHERE counter.last_value < 99999999
                    RETURNING last_value INTO v_number;

                    IF v_number IS NULL THEN
                        RAISE EXCEPTION
                            'Se agotó el consecutivo de expedientes para el año %.',
                            v_year
                            USING ERRCODE = '22003';
                    END IF;

                    RETURN
                        lpad(v_year::TEXT, 4, '0')
                        || '-'
                        || lpad(v_number::TEXT, 8, '0');
                END;
                $function$;
                SQL);

            DB::statement(
                'REVOKE ALL PRIVILEGES
                 ON FUNCTION system.next_expediente_number()
                 FROM PUBLIC'
            );

            DB::statement(
                'GRANT EXECUTE
                 ON FUNCTION system.next_expediente_number()
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
            DB::statement(
                'DROP FUNCTION system.next_expediente_number()'
            );
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};