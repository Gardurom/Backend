# SIGA — Historial de implementación

Última actualización: 2026-10-08

## 1. Propósito

Este archivo resume hitos técnicos. Git sigue siendo la fuente primaria de detalle.

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
- acciones de registro, actualización, baja y reingreso;
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
- sesiones en PostgreSQL.

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
- `AssignProfile`.

Commit de referencia del estado publicado:

```text
b4131ee
feat: asignar perfiles a usuarios
```

## 7. Seguridad — decisión del 8 de octubre

Se conserva Sanctum SPA con sesión/cookies como arquitectura principal.

Se elevan a requisitos preproducción:

- HTTPS/HSTS;
- Secure cookies;
- CSP/headers;
- rate limiting;
- timeouts/revocación de sesión;
- auditoría de seguridad;
- MFA;
- Passkeys/WebAuthn.

La decisión anterior de diferir Passkeys/WebAuthn como mejora posterior queda reemplazada.

Checkpoint:

`CHECKPOINT_SEGURIDAD_PERFILES_2026-10-08.md`

## 8. Regla de actualización

Agregar aquí solo hitos relevantes ya publicados.

No documentar como terminado un cambio que permanezca únicamente local.
