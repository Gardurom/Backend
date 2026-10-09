# SIGA — Arquitectura técnica

Última actualización: 2026-10-08

## 1. Principios

SIGA se construye con:

- mínimo privilegio;
- separación de responsabilidades;
- defensa en profundidad;
- TDD;
- trazabilidad;
- PostgreSQL como fuente de integridad;
- autorización siempre en backend.

## 2. Componentes

```text
Angular SPA (pendiente)
      |
      | HTTPS + CSRF + cookie de sesión
      v
Laravel 13 + Sanctum
      |
      +--> autenticación
      +--> autorización
      +--> acciones de dominio
      +--> auditoría
      |
      v
PostgreSQL 18
      |
      +--> institutional
      +--> system
      +--> PostGIS
      |
      +--> GeoServer

LibreFS
  |
  +--> archivos controlados, por integrar funcionalmente
```

## 3. Capa web

Frontend previsto:

- Angular;
- Leaflet;
- misma plataforma lógica que el backend;
- autenticación SPA stateful con Sanctum.

El frontend nunca es la autoridad final de permisos.

## 4. Backend

Responsabilidades:

- validar solicitudes;
- autenticar;
- autorizar;
- ejecutar acciones transaccionales;
- auditar;
- acceder a PostgreSQL con `siga_app`.

Los controladores HTTP deben mantenerse delgados y delegar reglas de negocio en acciones/servicios.

## 5. Identidad

```text
Persona
   |
   +-- 0..1 User
           |
           +-- N:M Role
           |       |
           |       +-- N:M Permission
           |
           +-- N:M Profile
```

Roles/Permisos son seguridad.

Perfiles son UX.

## 6. Autorización

`PermissionResolver` implementa:

- DENY prevalece;
- ALLOW en ausencia de DENY;
- ausencia de regla = acceso denegado.

Las rutas protegidas utilizan:

```text
auth:sanctum
siga.permission:<codigo>
```

## 7. Datos

Esquemas:

- `institutional`: entidades institucionales;
- `system`: infraestructura, autenticación, auditoría, autorización y perfiles;
- `public`: objetos requeridos por PostGIS.

El propietario de objetos es `siga_owner`.

La aplicación opera como `siga_app`.

## 8. Geoespacial

PostGIS y GeoServer ya están preparados.

La integración funcional Angular + Leaflet + GeoServer sigue pendiente.

## 9. Archivos

LibreFS está previsto para archivos como fotografía de Persona.

La integración funcional con módulos sigue pendiente y debe mantener controles de autorización y trazabilidad.

## 10. Seguridad

Controles ya implementados:

- Sanctum stateful;
- CSRF;
- sesión regenerada al login;
- invalidación al logout;
- rate limiting del login por correo normalizado + IP;
- timeout de inactividad de sesión de 30 minutos;
- datos de sesión cifrados;
- cookie `HttpOnly` y `SameSite=Lax`;
- producción exige `Secure` para la cookie de sesión;
- timeout absoluto de sesión de 8 horas mediante `siga.session.absolute`;
- `siga_authenticated_at` se registra al autenticar y la expiración absoluta invalida la sesión;
- administración de sesiones activas propias mediante endpoints autenticados;
- IDs públicos opacos derivados con HMAC-SHA256, sin exponer el ID persistido;
- revocación individual y masiva limitada al usuario autenticado, conservando la sesión actual;
- sesiones vencidas por inactividad se excluyen del listado activo;
- mínimo privilegio DB;
- autorización central;
- default-deny.

Controles obligatorios preproducción:

- despliegue HTTPS/HSTS;
- CSP/headers defensivos;
- reautenticación para operaciones sensibles;
- auditoría de eventos de autenticación;
- MFA;
- Passkeys/WebAuthn.

## 11. Evolución

No introducir microservicios, OAuth2, PAT, JWT u otros mecanismos sin un requisito real y aprobación de diseño.

La prioridad es mantener una arquitectura simple, verificable y segura.
