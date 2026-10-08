# SIGA — Estado técnico del proyecto

Última actualización: 2026-10-08

Este estado refleja únicamente lo publicado en GitHub `main`.

Commit de referencia:

`d3acd0e feat: endurecer configuracion de sesiones`

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
- configuración segura de sesión/cookies.

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
- salvaguarda de `Secure` obligatoria en producción.

Permanecen como requisitos preproducción:

- despliegue HTTPS y HSTS;
- CSP y headers defensivos;
- timeout absoluto de sesión;
- revocación y gestión de sesiones;
- reautenticación para operaciones sensibles;
- auditoría de seguridad;
- MFA;
- Passkeys/WebAuthn.

Passkeys/WebAuthn no se considera una mejora opcional indefinida.

## 12. Próximo bloque recomendado

Con la gestión básica de perfiles, el rate limiting y el endurecimiento básico de sesión/cookies cerrados, el siguiente bloque técnico recomendado es:

`timeout absoluto y gestión/revocación de sesiones`

Después:

1. reautenticación para operaciones sensibles;
2. auditoría de autenticación;
3. headers de seguridad;
4. MFA/Passkeys antes de producción;
5. iniciar frontend Angular e integración SPA.

## 13. Regla documental

Este archivo es un resumen operativo.

Las decisiones vigentes se obtienen de:

- `DECISIONES_TECNICAS.md`;
- documentos vivos específicos del dominio.

Los checkpoints se conservan como evidencia histórica y no gobiernan cambios posteriores cuando existe una decisión viva más reciente.
