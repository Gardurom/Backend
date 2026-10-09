# SIGA — Base de datos

Última actualización: 2026-10-09

## 1. Plataforma

- PostgreSQL 18.4.
- Base principal: `siga`.
- Base de pruebas: `siga_installation_test`.
- PostGIS habilitado.

## 2. Roles

- `siga_owner`: propietario, NOLOGIN.
- `siga_migrator`: LOGIN, ejecuta migraciones y puede asumir owner.
- `siga_app`: LOGIN, mínimo privilegio.
- `siga_readonly`: lectura.
- `siga_geoserver`: acceso específico de GeoServer.

Las migraciones estructurales usan:

```text
SET ROLE siga_owner
...
RESET ROLE
```

## 3. Esquemas

`institutional`:

- `countries`;
- `territories`;
- `persons`.

`system`:

- `users`;
- `password_reset_tokens`;
- `sessions`;
- `cache`;
- `cache_locks`;
- `jobs`;
- `job_batches`;
- `failed_jobs`;
- `counters`;
- `activities`;
- `roles`;
- `user_roles`;
- `permissions`;
- `role_permissions`;
- `profiles`;
- `user_profiles`.

### Sesiones y reautenticación

La reautenticación para operaciones sensibles no requirió cambios de esquema.

Las marcas:

- `siga_authenticated_at`;
- `siga_reauthenticated_at`;

se conservan dentro de los datos de sesión administrados por Laravel y almacenados de forma cifrada mediante `system.sessions`; no son columnas adicionales de la tabla.

El bloque publicado en `f3eb118` no añadió tablas, columnas, secuencias, funciones ni privilegios PostgreSQL.

## 4. Persona

Clave primaria:

`id_persona uuid default uuidv7()`

Identificadores:

- CURP nullable/unique;
- RFC nullable/unique;
- expediente automático/unique.

Dominios principales:

- sexo: `MASCULINO | FEMENINO | NULL`;
- estado civil: `SOLTERO | CASADO | NULL`;
- estatus: `ACTIVO | INACTIVO | SUSPENDIDO | BAJA`.

`BAJA` requiere `fecha_baja` y la fecha no puede ser anterior a `fecha_alta`.

## 5. Auditoría

`system.activities`:

- PK `id_actividad`;
- FK `id_usuario`;
- entidad/id de entidad;
- acción;
- JSONB de antes/después/campos modificados;
- fecha.

`siga_app` tiene SELECT/INSERT, no UPDATE/DELETE.

## 6. Usuarios y Persona

`system.users` puede referenciar una Persona mediante UUID.

La relación es 1:0..1 desde Persona hacia User.

El correo de acceso tiene integridad de unicidad normalizada.

## 7. Roles

`system.roles` es catálogo de solo lectura para la aplicación.

`system.user_roles` materializa User N:M Role.

## 8. Permisos

`system.permissions` es catálogo.

`system.role_permissions` materializa Role N:M Permission.

Columna:

```text
effect varchar(5) NOT NULL
CHECK effect IN ('ALLOW', 'DENY')
```

No existe default implícito para `effect`.

## 9. Perfiles

`system.profiles` es catálogo de solo lectura.

`system.user_profiles`:

- `user_id`;
- `profile_id`;
- `is_default boolean NOT NULL DEFAULT false`;
- timestamps;
- PK compuesta;
- FKs con cascade on delete.

Índice:

```text
UNIQUE (user_id) WHERE is_default
```

Esto permite como máximo un perfil predeterminado por usuario.

## 10. Secuencias y funciones

La aplicación puede usar las secuencias necesarias, pero no reiniciarlas.

Función principal:

`system.next_expediente_number()`

La función y las secuencias se prueban con la suite Installation.

## 11. Regla de mínimo privilegio

Nunca otorgar permisos de propietario a `siga_app`.

Todo nuevo objeto debe definir explícitamente:

- propietario;
- privilegios PUBLIC;
- privilegios de `siga_app`;
- secuencias asociadas;
- pruebas de permisos.
