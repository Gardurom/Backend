# SIGA — Pruebas y calidad

Última actualización: 2026-10-09

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
- asignación y desasignación de perfiles;
- rate limiting de login;
- configuración de seguridad de sesión/cookies;
- timeout absoluto de sesión;
- gestión y revocación de sesiones;
- aislamiento de sesiones entre usuarios;
- límite exacto del timeout de inactividad;
- reautenticación con contraseña actual;
- rate limiting de reautenticación;
- limpieza del bucket después de reautenticación correcta;
- límite exacto de reautenticación: 14:59 válido y 15:00 vencido;
- revocación de sesiones protegida por reautenticación;
- BAJA y REINGRESO de Persona protegidos por reautenticación;
- ausencia de mutación y auditoría cuando BAJA o REINGRESO reciben HTTP 423.

### Último cierre global publicado

Para el bloque `f3eb118 feat: exigir reautenticacion en operaciones sensibles`:

- Unit/Feature: 2 pruebas / 2 assertions;
- Installation: 55 pruebas / 280 assertions;
- Functional: 163 pruebas / 2091 assertions;
- total: **220 pruebas / 2373 assertions**.

Todas las suites finalizaron en verde antes del commit y push.

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

Controles de seguridad con prueba funcional ya implementados:

- rate limiting de login:
  - cinco fallos permitidos por correo normalizado + IP;
  - siguiente intento bloqueado con HTTP 429;
  - login correcto limpia intentos anteriores;
- seguridad de sesión/cookies:
  - timeout de inactividad de 30 minutos;
  - datos de sesión cifrados;
  - `HttpOnly=true`;
  - `SameSite=Lax`;
  - producción rechaza una configuración con `session.secure !== true`;
  - producción acepta `session.secure=true`;
- timeout absoluto de sesión:
  - expira al alcanzar 8 horas;
  - el login registra `siga_authenticated_at`;
  - permanece válida justo antes de 8 horas;
  - sesiones existentes sin marca absoluta inicializan la marca de forma compatible;
- gestión y revocación de sesiones:
  - lista únicamente sesiones propias;
  - nunca expone el ID real persistido;
  - utiliza un ID público HMAC-SHA256;
  - permite revocar una sesión propia distinta de la actual;
  - no permite revocar sesiones de otro usuario;
  - no permite revocar la sesión actual mediante el endpoint individual;
  - permite revocar todas las demás sesiones propias conservando la actual;
  - la revocación masiva es idempotente cuando no existen otras sesiones;
  - no lista sesiones expiradas por inactividad;
  - verifica el límite exacto: 30:00 expira y 29:59 permanece activa;
- reautenticación de operaciones sensibles:
  - el login correcto registra `siga_reauthenticated_at`;
  - `POST /reauthenticate` acepta la contraseña actual correcta;
  - una contraseña incorrecta responde 422 y no crea marca de reautenticación;
  - cinco fallos son permitidos y el intento posterior responde HTTP 429 con `Retry-After`;
  - un éxito limpia los intentos previos;
  - 14:59 de antigüedad permite la operación;
  - exactamente 15:00 exige nueva confirmación;
  - revocación individual y masiva sin marca reciente responde HTTP 423;
  - BAJA y REINGRESO sin marca reciente responden HTTP 423;
  - BAJA y REINGRESO bloqueados por HTTP 423 no modifican datos ni generan su actividad de auditoría.

Evidencia publicada:

- `SessionAbsoluteTimeoutTest` — `bee9ca1 feat: agregar timeout absoluto de sesion`;
- `SessionManagementTest` — `bc9d17c feat: gestionar y revocar sesiones`;
- `SensitiveOperationReauthenticationTest`, `PersonApiWithdrawalTest` y `PersonApiReinstatementTest` — `f3eb118 feat: exigir reautenticacion en operaciones sensibles`.

Deben añadirse pruebas específicas para los controles preproducción pendientes:

- headers defensivos;
- auditoría de login y throttling;
- MFA;
- Passkeys/WebAuthn.

Estos controles restantes no se consideran implementados todavía.

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
