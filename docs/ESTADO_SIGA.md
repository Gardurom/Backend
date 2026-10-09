# SIGA — Estado técnico del proyecto

Última actualización: 2026-10-09

Este estado refleja únicamente lo publicado en GitHub `main`.

Commit de referencia:

`f3eb118 feat: exigir reautenticacion en operaciones sensibles`

## 1. Plataforma

- backend Laravel 13;
- PHP 8.5;
- Laravel Sanctum 4;
- PostgreSQL 18.4;
- PostGIS;
- GeoServer 3.0.1;
- frontend Angular todavía pendiente;
- Leaflet previsto para cartografía;
- Windows/PowerShell;
- sin Docker.

Bases:

- `siga`;
- `siga_installation_test`.

## 2. Infraestructura PostgreSQL

Roles principales:

- `siga_owner`;
- `siga_migrator`;
- `siga_app`;
- `siga_readonly`;
- `siga_geoserver`.

Esquemas principales:

- `institutional`;
- `system`;
- `public` para objetos PostGIS.

## 3. Persona

Tabla:

`institutional.persons`

Acciones publicadas:

- `RegisterPerson`;
- `FindPerson`;
- `UpdatePerson`;
- `WithdrawPerson`;
- `ReinstatePerson`.

API publicada:

- `POST /api/personas`;
- `GET /api/personas/{id_persona}`;
- `PATCH /api/personas/{id_persona}`;
- `POST /api/personas/{id_persona}/baja`;
- `POST /api/personas/{id_persona}/reingreso`.

Todas requieren autenticación Sanctum y permiso específico.

## 4. Auditoría

Tabla:

`system.activities`

Acciones actuales:

- `CREACION`;
- `ACTUALIZACION`;
- `BAJA`;
- `REINGRESO`.

`siga_app` puede insertar y consultar, pero no modificar ni eliminar registros.

No existe campo `motivo`.

## 5. Usuarios

`system.users` está relacionado opcionalmente 1:1 con Persona.

El correo de acceso se normaliza y está protegido contra duplicados que difieran solo por mayúsculas/minúsculas.

La creación interna se implementa mediante `CreateUser`.

## 6. Autenticación

Implementado:

- Sanctum stateful;
- sesión/cookie;
- CSRF;
- login;
- logout;
- rate limiting de login: 5 intentos fallidos por correo normalizado + IP en 60 segundos;
- bloqueo posterior con HTTP 429 y `Retry-After`;
- limpieza del contador al autenticar correctamente;
- regeneración de sesión;
- invalidación al logout;
- `auth:sanctum`;
- sesiones en `system.sessions`;
- cifrado de datos de sesión;
- timeout de inactividad de 30 minutos;
- cookie de sesión `HttpOnly`;
- política `SameSite=Lax`;
- producción rechaza el arranque si `SESSION_SECURE_COOKIE` no es `true`;
- timeout absoluto de sesión de 8 horas;
- registro de `siga_authenticated_at` al autenticar;
- expiración absoluta con logout, invalidación de sesión, regeneración CSRF y HTTP 401;
- `GET /api/sessions` para listar únicamente sesiones activas propias;
- `DELETE /api/sessions/{session}` para revocar una sesión propia distinta de la actual;
- `DELETE /api/sessions/others` para revocar todas las demás sesiones propias;
- identificadores públicos HMAC-SHA256 sin exponer el ID real de sesión;
- sesiones expiradas por inactividad no aparecen en el listado activo;
- `POST /reauthenticate` para confirmar nuevamente la contraseña actual;
- el login correcto registra `siga_reauthenticated_at`;
- reautenticación válida durante 900 segundos por defecto;
- límite de 5 fallos de reautenticación en 60 segundos por usuario autenticado + IP;
- HTTP 429 con `Retry-After` cuando se agota el límite de reautenticación;
- HTTP 423 cuando una operación sensible requiere reautenticación reciente;
- revocación individual y masiva de sesiones exige reautenticación reciente;
- BAJA y REINGRESO de Persona exigen reautenticación reciente después de autorización;
- CORS con credenciales.

No se usan PAT como mecanismo de la SPA.

## 7. Autorización

Tablas:

- `system.roles`;
- `system.user_roles`;
- `system.permissions`;
- `system.role_permissions`.

`role_permissions.effect` acepta únicamente:

- `ALLOW`;
- `DENY`.

Regla:

`DENY > ALLOW > NO_RULE/default-deny`

Componentes:

- `PermissionDecision`;
- `PermissionResolver`;
- middleware `EnsurePermission`;
- alias `siga.permission`.

Las cinco operaciones HTTP de Persona están protegidas con permiso específico.

## 8. Catálogo inicial de autorización

Roles:

- `ROL_ADMIN_SISTEMA`;
- `ROL_AUDITOR`;
- `ROL_CONSULTA_PERSONAS`;
- `ROL_GESTOR_PERSONAS`.

Permisos:

- `auditoria.ver`;
- `personas.actualizar`;
- `personas.baja`;
- `personas.crear`;
- `personas.reingreso`;
- `personas.ver`;
- `usuarios.asignar_roles`;
- `usuarios.crear`.

## 9. Perfiles

Tablas:

- `system.profiles`;
- `system.user_profiles`.

Catálogo:

- `PERFIL_ADMINISTRACION`;
- `PERFIL_AUDITORIA`;
- `PERFIL_PERSONAS`.

Reglas:

- perfiles son UX, no seguridad;
- User↔Profile es N:M;
- `is_default` permite como máximo un perfil predeterminado por usuario;
- un perfil asignado no se vuelve predeterminado automáticamente;
- eliminar un perfil asignado no selecciona otro predeterminado;
- un usuario puede quedar sin perfiles;
- un usuario puede tener perfiles sin predeterminado.

Acciones publicadas:

- `AssignProfile`;
- `UnassignProfile`;
- `SetDefaultProfile`.

## 10. Pruebas y calidad

Existen suites:

- Unit;
- Feature;
- Installation;
- Functional;
- HTTP funcional.

Cobertura funcional relevante:

- Persona;
- auditoría;
- autenticación;
- relación Persona↔Usuario;
- Roles;
- Permisos;
- precedencia DENY;
- Perfiles;
- perfil predeterminado;
- asignación y desasignación de perfiles;
- rate limiting de login;
- configuración segura de sesión/cookies;
- timeout absoluto de sesión de 8 horas;
- gestión y revocación de sesiones;
- protección de la sesión actual y aislamiento entre usuarios;
- filtrado del límite exacto de inactividad;
- reautenticación con contraseña actual;
- rate limiting de reautenticación;
- límite exacto de 15 minutos para reautenticación;
- protección por reautenticación de revocación de sesiones;
- protección por reautenticación de BAJA y REINGRESO de Persona.

Controles de cierre:

- Laravel Pint;
- `composer test:installation`;
- `composer test:functional`;
- `composer test:all`;
- `git diff --check`.

## 11. Seguridad: prioridad vigente

La arquitectura de autenticación actual se conserva.

Controles de hardening ya implementados:

- rate limiting de login;
- timeout de inactividad de 30 minutos;
- cifrado de sesión;
- `HttpOnly` y `SameSite=Lax`;
- salvaguarda de `Secure` obligatoria en producción;
- timeout absoluto de sesión de 8 horas;
- listado y revocación manual de sesiones propias con identificadores opacos;
- conservación obligatoria de la sesión actual en los endpoints de administración;
- filtrado de sesiones vencidas por inactividad;
- reautenticación para operaciones sensibles mediante contraseña actual;
- ventana de reautenticación de 15 minutos por defecto;
- rate limiting de reautenticación;
- protección de revocación de sesiones, BAJA y REINGRESO mediante `siga.reauthenticated`.

Permanecen como requisitos preproducción:

- despliegue HTTPS y HSTS;
- CSP y headers defensivos;
- auditoría de seguridad;
- MFA;
- Passkeys/WebAuthn.

Passkeys/WebAuthn no se considera una mejora opcional indefinida.

## 12. Próximo bloque recomendado

Con la gestión básica de perfiles, el rate limiting, el endurecimiento de sesión/cookies, el timeout absoluto de 8 horas, la gestión/revocación manual de sesiones y la reautenticación para operaciones sensibles cerrados, el siguiente bloque de trabajo recomendado es:

`iniciar frontend Angular e integración SPA`

La integración inicial deberá respetar desde el comienzo:

- Sanctum stateful;
- `/sanctum/csrf-cookie`;
- `POST /login`;
- `POST /logout`;
- `POST /reauthenticate`;
- cookies con credenciales;
- flujo HTTP 423 para solicitar reautenticación antes de repetir una operación sensible.

Antes de producción siguen pendientes:

1. auditoría de autenticación;
2. headers de seguridad;
3. MFA;
4. Passkeys/WebAuthn.

## 13. Regla documental

Este archivo es un resumen operativo.

Las decisiones vigentes se obtienen de:

- `DECISIONES_TECNICAS.md`;
- documentos vivos específicos del dominio.

Los checkpoints se conservan como evidencia histórica y no gobiernan cambios posteriores cuando existe una decisión viva más reciente.
