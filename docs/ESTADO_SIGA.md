# SIGA — Estado técnico del proyecto

Última actualización: 2026-10-08

Este estado refleja únicamente lo publicado en GitHub `main`.

Commit de referencia:

`b4131ee feat: asignar perfiles a usuarios`

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
- regeneración de sesión;
- invalidación al logout;
- `auth:sanctum`;
- sesiones en `system.sessions`;
- cifrado de sesión definido en `.env.example`;
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
- un perfil asignado no se vuelve predeterminado automáticamente.

Acciones publicadas:

- `AssignProfile`;
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
- asignación de perfiles.

Controles de cierre:

- Laravel Pint;
- `composer test:installation`;
- `composer test:functional`;
- `composer test:all`;
- `git diff --check`.

## 11. Seguridad: nueva prioridad

La arquitectura de autenticación actual se conserva.

Se incorporan como requisitos preproducción:

- rate limiting de login;
- HTTPS/secure cookies;
- HSTS;
- CSP y headers defensivos;
- timeouts de sesión;
- revocación de sesiones;
- auditoría de seguridad;
- MFA;
- Passkeys/WebAuthn.

Passkeys/WebAuthn ya no se considera una mejora indefinidamente diferida.

## 12. Pendientes principales

Inmediatos:

1. completar desasignación de perfiles;
2. rate limiting del login;
3. endurecimiento y pruebas de sesión/cookies;
4. gestión/revocación de sesiones;
5. auditoría de autenticación;
6. headers de seguridad;
7. MFA/Passkeys antes de producción;
8. iniciar frontend Angular e integración SPA.

## 13. Regla documental

Este archivo debe permanecer compacto.

Los detalles de seguridad se encuentran en:

- `AUTENTICACION_SEGURIDAD.md`;
- `CHECKPOINT_SEGURIDAD_PERFILES_2026-10-08.md`;
- `DECISIONES_TECNICAS.md`.
