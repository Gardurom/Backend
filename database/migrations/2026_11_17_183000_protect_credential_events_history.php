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
CREATE FUNCTION institutional.reject_credential_event_changes()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    RAISE EXCEPTION
        'El historial de credenciales es inmutable.'
        USING ERRCODE = '55000';
END;
$function$;
SQL);

            DB::statement(<<<'SQL'
CREATE TRIGGER credential_events_immutable
BEFORE UPDATE OR DELETE
ON institutional.credential_events
FOR EACH ROW
EXECUTE FUNCTION institutional.reject_credential_event_changes()
SQL);
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function down(): void
    {
        DB::statement('SET ROLE siga_owner');

        try {
            DB::statement(<<<'SQL'
DROP TRIGGER IF EXISTS credential_events_immutable
ON institutional.credential_events
SQL);

            DB::statement(<<<'SQL'
DROP FUNCTION IF EXISTS
institutional.reject_credential_event_changes()
SQL);
        } finally {
            DB::statement('RESET ROLE');
        }
    }
};