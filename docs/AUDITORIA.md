# SIGA — Auditoría

Última actualización: 2026-10-08

## 1. Propósito

SIGA conserva evidencia de operaciones relevantes ejecutadas por usuarios autenticados.

## 2. Tabla actual

`system.activities`

Campos:

- `id_actividad`;
- `id_usuario`;
- `entidad`;
- `id_entidad`;
- `accion`;
- `datos_anteriores`;
- `datos_nuevos`;
- `campos_modificados`;
- `fecha_actividad`.

No existe campo `motivo`.

## 3. Dominio actual de acciones

Actualmente:

- `CREACION`;
- `ACTUALIZACION`;
- `BAJA`;
- `REINGRESO`.

Estas acciones cubren el ciclo implementado de Persona.

## 4. Inmutabilidad

`siga_app` puede:

- SELECT;
- INSERT.

No puede:

- UPDATE;
- DELETE.

La auditoría se diseña como evidencia, no como información editable.

## 5. Usuario ejecutor

`id_usuario` proviene del usuario autenticado por el servidor.

El cliente no debe poder suplantar el usuario auditado enviando un identificador arbitrario.

## 6. Transacciones

Cuando una acción de negocio y su auditoría forman una sola operación, deben ejecutarse en la misma transacción.

Si falla la auditoría, la modificación de negocio se revierte.

## 7. Seguridad futura

Se ha aprobado auditar también eventos de autenticación y administración de seguridad, por ejemplo:

- login correcto/fallido;
- logout;
- cambio/restablecimiento de contraseña;
- MFA;
- Passkeys;
- revocación de sesiones;
- cambios de roles;
- cambios de perfiles;
- cambio de perfil predeterminado;
- throttling.

**Estado:** PLANIFICADO.

La tabla actual no admite todavía esos valores en `accion`. No deben insertarse hasta diseñar una ampliación explícita con migración y pruebas.

## 8. Datos prohibidos en auditoría

Nunca registrar:

- contraseñas;
- hashes reutilizables como credenciales;
- cookies;
- session IDs completos;
- secretos MFA/TOTP;
- claves privadas;
- tokens Bearer;
- tokens CSRF.

## 9. Trazabilidad

Cada nueva clase de evento auditable debe definir:

- evento;
- actor;
- entidad;
- datos permitidos;
- política de retención;
- pruebas;
- permisos.
