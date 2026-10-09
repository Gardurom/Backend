# SIGA — Historial de implementación

Última actualización: 2026-10-08

## 1. Propósito

Este archivo resume hitos técnicos publicados.

Git sigue siendo la fuente primaria del detalle de commits.

## 2. Infraestructura inicial

Se configuraron:

- PostgreSQL/PostGIS;
- roles de mínimo privilegio;
- esquemas `institutional` y `system`;
- GeoServer;
- respaldo integral de base;
- Laravel backend.

## 3. Núcleo Persona

Se incorporaron:

- países y territorios;
- contador/función de expediente;
- `institutional.persons`;
- reglas de dominio e integridad;
- acciones de registro, consulta, actualización, baja y reingreso;
- API autenticada;
- auditoría transaccional.

## 4. Usuarios y autenticación

Se incorporaron:

- relación Persona↔User;
- normalización de correo;
- creación interna de usuarios;
- Sanctum stateful;
- CSRF;
- login/logout;
- sesiones en PostgreSQL;
- rate limiting específico del login por correo normalizado + IP;
- limpieza del contador tras autenticación correcta;
- timeout de inactividad de sesión de 30 minutos;
- cifrado de datos de sesión;
- política de cookie `HttpOnly` + `SameSite=Lax`;
- salvaguarda que exige `SESSION_SECURE_COOKIE=true` en producción;
- timeout absoluto de sesión de 8 horas con marca de inicio autenticado y expiración HTTP 401;
- listado de sesiones activas propias sin exponer identificadores persistidos;
- revocación individual de sesiones propias distintas de la actual;
- revocación de todas las demás sesiones propias conservando la actual;
- filtrado de sesiones expiradas por inactividad, incluido el límite exacto configurado.

Commits relevantes:

`215eab0 feat: limitar intentos de inicio de sesion`

`d3acd0e feat: endurecer configuracion de sesiones`

`bee9ca1 feat: agregar timeout absoluto de sesion`

`bc9d17c feat: gestionar y revocar sesiones`

## 5. Roles y permisos

Hitos:

- catálogo `system.roles`;
- relación `system.user_roles`;
- catálogo `system.permissions`;
- relación `system.role_permissions`;
- matriz inicial;
- columna `effect`;
- efectos explícitos `ALLOW | DENY`;
- `PermissionResolver`;
- middleware de permiso;
- protección de todas las rutas Persona;
- prueba end-to-end de precedencia DENY.

Checkpoint asociado:

`CHECKPOINT_AUTORIZACION_PERSONA_2026-10-07.md`

## 6. Perfiles

Hitos publicados:

- `system.profiles`;
- `system.user_profiles`;
- índice de un solo perfil predeterminado por usuario;
- modelos Eloquent;
- catálogo inicial;
- `SetDefaultProfile`;
- `AssignProfile`;
- `UnassignProfile`.

Commits relevantes:

```text
b4131ee
feat: asignar perfiles a usuarios

d58f461
feat: desasignar perfiles de usuarios
```

Reglas consolidadas:

- perfiles no conceden permisos;
- asignación y desasignación son explícitas;
- no existe selección automática de perfil predeterminado;
- desasignar el predeterminado puede dejar al usuario sin predeterminado.

## 7. Seguridad — decisión del 8 de octubre

Se conserva Sanctum SPA con sesión/cookies como arquitectura principal.

Dentro de este endurecimiento ya se publicó:

- rate limiting del login con máximo de 5 fallos por correo normalizado + IP en 60 segundos;
- respuesta HTTP 429 con `Retry-After`;
- limpieza del bucket tras login correcto;
- timeout de inactividad de 30 minutos;
- cifrado de sesión;
- `HttpOnly` y `SameSite=Lax`;
- validación de arranque que exige cookie `Secure` en producción;
- timeout absoluto de sesión de 8 horas;
- invalidación de la sesión y respuesta HTTP 401 al alcanzar el límite absoluto;
- administración y revocación manual de sesiones propias mediante IDs públicos opacos;
- protección de la sesión actual y aislamiento entre usuarios;
- exclusión del listado de sesiones vencidas por inactividad.

Permanecen como requisitos preproducción:

- despliegue HTTPS/HSTS;
- CSP/headers;
- reautenticación sensible;
- auditoría de seguridad;
- MFA;
- Passkeys/WebAuthn.

La decisión anterior de diferir Passkeys/WebAuthn como mejora posterior queda reemplazada.

Checkpoint histórico:

`CHECKPOINT_SEGURIDAD_PERFILES_2026-10-08.md`

## 8. Gobierno documental

A partir del 8 de octubre:

- `DECISIONES_TECNICAS.md` gobierna decisiones vivas;
- `ESTADO_SIGA.md` refleja lo publicado;
- documentos de dominio definen reglas específicas;
- checkpoints son históricos;
- no se crean ramas ni Pull Requests sin autorización explícita.

## 9. Próximo bloque

Después de cerrar gestión básica de perfiles, rate limiting, endurecimiento de sesión/cookies, timeout absoluto de 8 horas y gestión/revocación manual de sesiones, el siguiente bloque recomendado es la reautenticación para operaciones sensibles.

## 10. Regla de actualización

Agregar aquí solo hitos relevantes ya publicados.

No documentar como terminado un cambio que permanezca únicamente local.
