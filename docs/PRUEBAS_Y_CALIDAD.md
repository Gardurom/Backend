# SIGA — Pruebas y calidad

Última actualización: 2026-10-08

## 1. Principio

SIGA aplica TDD y validación por capas.

```text
rojo
-> implementación
-> verde
-> suite relacionada
-> Pint
-> suite global
-> diff check
-> commit/push
```

## 2. Suites

### Unit / Feature

Pruebas rápidas del framework y unidades aisladas.

### Installation

Valida PostgreSQL e infraestructura real:

- rol de aplicación;
- transacciones;
- esquemas;
- tablas;
- columnas;
- constraints;
- privilegios;
- secuencias;
- funciones;
- roles;
- permisos;
- perfiles;
- relaciones pivote;
- catálogos iniciales.

### Functional

Valida reglas con PostgreSQL real mediante transacciones reversibles.

Áreas cubiertas:

- Persona;
- auditoría;
- autenticación;
- usuarios;
- autorización;
- DENY sobre ALLOW;
- perfiles;
- perfil predeterminado;
- asignación de perfiles.

## 3. Entorno funcional

Las pruebas funcionales utilizan:

- `.env.installation`;
- base `siga_installation_test`;
- login DB `siga_app`;
- transacciones read-write dentro de la prueba;
- rollback al terminar.

No deben ejecutarse accidentalmente contra la base principal.

## 4. Migraciones de prueba

Las migraciones de la base de pruebas utilizan:

`.env.installation-migration`

Antes de migrar debe comprobarse:

`DB_DATABASE=siga_installation_test`

La base principal utiliza:

`.env.migration`

y debe verificarse explícitamente antes de ejecutar migraciones.

## 5. Controles de calidad

- Laravel Pint;
- `php -l`;
- `git diff --check`;
- `git diff --cached --check`;
- Composer;
- pruebas automatizadas;
- revisión de permisos PostgreSQL;
- verificación directa con psql cuando aplica.

## 6. Seguridad

Deben añadirse pruebas específicas para los controles preproducción aprobados:

- rate limiting;
- flags de cookies;
- timeouts;
- revocación de sesiones;
- headers defensivos;
- auditoría de login;
- MFA;
- Passkeys/WebAuthn.

Estos elementos no se consideran implementados todavía.

## 7. Evidencia

Un bloque solo se considera cerrado cuando existe evidencia suficiente:

- prueba roja;
- prueba verde;
- suite relacionada;
- suite global cuando corresponda;
- estilo;
- diff limpio;
- commit;
- push;
- verificación remota.

## 8. Regla de no regresión

La incorporación de un nuevo control de seguridad no debe debilitar:

- autorización existente;
- mínimo privilegio;
- CSRF;
- auditoría;
- integridad de base;
- aislamiento de pruebas.
