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

Después de cerrar gestión básica de perfiles, el siguiente bloque recomendado es el rate limiting específico del login.

## 10. Regla de actualización

Agregar aquí solo hitos relevantes ya publicados.

No documentar como terminado un cambio que permanezca únicamente local.
