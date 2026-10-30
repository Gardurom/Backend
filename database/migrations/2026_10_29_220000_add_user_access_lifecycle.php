<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::selectOne('SELECT current_database() AS n')->n !== 'siga') {
            throw new RuntimeException('Esta migracion requiere la base siga.');
        }
        $admin = DB::table('system.users')->where('id', 1)->first();
        if ($admin === null || $admin->id_persona !== '01a13ae1-546d-71b8-bbbf-18e7bccc1d45'
            || DB::table('institutional.persons')->where('id_persona', $admin->id_persona)->value('estatus') !== 'ACTIVO') {
            throw new RuntimeException('Se requiere completar primero la vinculacion administrativa autorizada.');
        }
        DB::statement('SET LOCAL ROLE siga_owner');
        DB::unprepared(<<<'SQL'
ALTER TABLE system.users
    ADD COLUMN access_status varchar(20) NOT NULL DEFAULT 'PENDIENTE',
    ADD COLUMN access_version bigint NOT NULL DEFAULT 1,
    ADD CONSTRAINT users_access_status_check CHECK (access_status IN ('PENDIENTE','HABILITADO','DESHABILITADO')),
    ADD CONSTRAINT users_access_version_check CHECK (access_version > 0);

-- Única habilitación inicial expresamente autorizada en esta instalación.
UPDATE system.users SET access_status = CASE WHEN id = 1 THEN 'HABILITADO'
    WHEN NOT EXISTS (SELECT 1 FROM institutional.persons p WHERE p.id_persona = users.id_persona AND p.estatus = 'ACTIVO')
    THEN 'DESHABILITADO' ELSE 'PENDIENTE' END;

INSERT INTO system.activities(id_usuario, entidad, id_entidad, accion, datos_anteriores, datos_nuevos, campos_modificados)
SELECT 1, 'USUARIO', id::text, 'ACTUALIZACION',
    jsonb_build_object('access_status', NULL),
    jsonb_build_object('access_status', access_status, 'access_version', access_version, 'origen', 'MIGRACION_FASE1'),
    '["access_status","access_version"]'::jsonb
FROM system.users;

CREATE FUNCTION system.siga_access_lock() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog AS $$
BEGIN
    PERFORM pg_advisory_xact_lock(73421, 101);
    RETURN NULL;
END;
$$;
REVOKE ALL ON FUNCTION system.siga_access_lock() FROM PUBLIC;

CREATE FUNCTION system.siga_access_audit() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog AS $$
DECLARE actor bigint; source text;
BEGIN
    IF NEW.access_status IS DISTINCT FROM OLD.access_status
       OR NEW.id_persona IS DISTINCT FROM OLD.id_persona THEN
        IF NEW.access_status = 'HABILITADO' AND NOT EXISTS (
            SELECT 1 FROM institutional.persons p WHERE p.id_persona = NEW.id_persona AND p.estatus = 'ACTIVO'
        ) THEN
            RAISE EXCEPTION 'SIGA_PERSON_NOT_ACTIVE' USING ERRCODE = '23514';
        END IF;
        NEW.access_version := OLD.access_version + 1;
        NEW.remember_token := NULL;
        DELETE FROM system.sessions WHERE user_id = NEW.id;
        actor := nullif(current_setting('siga.actor_id', true), '')::bigint;
        source := CASE WHEN actor IS NULL THEN 'CONTROL_AUTOMATICO' ELSE 'OPERADOR' END;
        -- activities exige una FK de usuario. Sin actor explícito se utiliza la cuenta
        -- afectada como referencia técnica, identificando el origen automático.
        actor := coalesce(actor, NEW.id);
        INSERT INTO system.activities(id_usuario, entidad, id_entidad, accion,
            datos_anteriores, datos_nuevos, campos_modificados)
        VALUES (actor, 'USUARIO', NEW.id::text, 'ACTUALIZACION',
            jsonb_build_object('access_status', OLD.access_status, 'access_version', OLD.access_version, 'id_persona', OLD.id_persona),
            jsonb_build_object('access_status', NEW.access_status, 'access_version', NEW.access_version, 'id_persona', NEW.id_persona, 'origen', source),
            '["access_status","access_version","id_persona"]'::jsonb);
    END IF;
    RETURN NEW;
END;
$$;
REVOKE ALL ON FUNCTION system.siga_access_audit() FROM PUBLIC;

CREATE FUNCTION system.siga_person_access_revoke() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog AS $$
BEGIN
    IF NEW.estatus IS DISTINCT FROM OLD.estatus AND NEW.estatus <> 'ACTIVO' THEN
        UPDATE system.users SET access_status = 'DESHABILITADO', updated_at = CURRENT_TIMESTAMP
        WHERE id_persona = NEW.id_persona AND access_status <> 'DESHABILITADO';
    END IF;
    RETURN NEW;
END;
$$;
REVOKE ALL ON FUNCTION system.siga_person_access_revoke() FROM PUBLIC;

CREATE FUNCTION system.siga_require_enabled_admin() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog AS $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM system.users u
        JOIN institutional.persons p ON p.id_persona = u.id_persona
        JOIN system.user_roles ur ON ur.user_id = u.id
        JOIN system.roles r ON r.id = ur.role_id
        WHERE u.access_status = 'HABILITADO' AND p.estatus = 'ACTIVO'
            AND r.code = 'ROL_ADMIN_SISTEMA'
    ) THEN
        RAISE EXCEPTION 'SIGA_LAST_ADMIN' USING ERRCODE = '23514';
    END IF;
    RETURN NULL;
END;
$$;
REVOKE ALL ON FUNCTION system.siga_require_enabled_admin() FROM PUBLIC;

CREATE TRIGGER siga_users_access_lock BEFORE INSERT OR UPDATE OR DELETE ON system.users
FOR EACH STATEMENT EXECUTE FUNCTION system.siga_access_lock();
CREATE TRIGGER siga_persons_access_lock BEFORE UPDATE OR DELETE ON institutional.persons
FOR EACH STATEMENT EXECUTE FUNCTION system.siga_access_lock();
CREATE TRIGGER siga_user_roles_access_lock BEFORE INSERT OR UPDATE OR DELETE ON system.user_roles
FOR EACH STATEMENT EXECUTE FUNCTION system.siga_access_lock();
CREATE TRIGGER siga_user_access_audit BEFORE UPDATE ON system.users
FOR EACH ROW EXECUTE FUNCTION system.siga_access_audit();
CREATE TRIGGER siga_person_access_revoke AFTER UPDATE OF estatus ON institutional.persons
FOR EACH ROW EXECUTE FUNCTION system.siga_person_access_revoke();
CREATE TRIGGER siga_users_admin_guard AFTER INSERT OR UPDATE OR DELETE ON system.users
FOR EACH STATEMENT EXECUTE FUNCTION system.siga_require_enabled_admin();
CREATE TRIGGER siga_persons_admin_guard AFTER UPDATE OR DELETE ON institutional.persons
FOR EACH STATEMENT EXECUTE FUNCTION system.siga_require_enabled_admin();
CREATE TRIGGER siga_roles_admin_guard AFTER INSERT OR UPDATE OR DELETE ON system.user_roles
FOR EACH STATEMENT EXECUTE FUNCTION system.siga_require_enabled_admin();

INSERT INTO system.permissions(name, code, created_at, updated_at) VALUES
('Habilitar cuentas de usuario','usuarios.habilitar', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('Deshabilitar cuentas de usuario','usuarios.deshabilitar', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
INSERT INTO system.role_permissions(role_id, permission_id, effect, created_at, updated_at)
SELECT r.id, p.id, 'ALLOW', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM system.roles r CROSS JOIN system.permissions p
WHERE r.code = 'ROL_ADMIN_SISTEMA' AND p.code IN ('usuarios.habilitar','usuarios.deshabilitar');

-- Elimina sesiones anteriores que aún no contienen la versión de acceso.
DELETE FROM system.sessions WHERE user_id IS NOT NULL;
UPDATE system.users SET remember_token = NULL;
SQL);
        DB::statement('RESET ROLE');
    }

    public function down(): void
    {
        throw new RuntimeException('La reversión del ciclo de acceso requiere un procedimiento explícito; no retire controles mediante rollback genérico.');
    }
};
