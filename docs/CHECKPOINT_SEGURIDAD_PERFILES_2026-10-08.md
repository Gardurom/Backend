# SIGA — Checkpoint de seguridad, autorización y perfiles

Fecha: 2026-10-08

Base documental de este checkpoint:

- repositorio: `Gardurom/Backend`
- rama publicada revisada: `main`
- commit base: `b4131ee feat: asignar perfiles a usuarios`
- este checkpoint documenta únicamente funcionalidad publicada en GitHub;
- cambios locales no publicados no se consideran terminados.

## 1. Objetivo

Congelar el estado aprobado de autenticación, autorización, perfiles y endurecimiento de seguridad de SIGA antes de continuar con nuevos bloques.

## 2. Autenticación web aprobada

La SPA propia de SIGA utilizará Laravel Sanctum en modo stateful mediante sesión y cookies.

Flujo:

```text
Angular / SPA SIGA
        |
        | HTTPS
        v
/sanctum/csrf-cookie
        |
        v
POST /login
        |
        v
sesión Laravel + cookie
        |
        v
auth:sanctum
        |
        v
API protegida
```

No se utilizarán JWT ni Bearer tokens como mecanismo principal de la SPA.

Los Personal Access Tokens de Sanctum quedan deshabilitados como necesidad funcional actual. Solo deberán incorporarse si aparece un cliente móvil, integración externa o necesidad servidor-a-servidor formalmente aprobada.

## 3. Controles ya implementados

- Sanctum stateful mediante `statefulApi()`.
- guard `web`.
- sesión en PostgreSQL mediante `system.sessions`.
- CSRF mediante Sanctum/Laravel.
- CORS con credenciales y orígenes configurables.
- normalización de correo antes de autenticar.
- regeneración del ID de sesión después de login correcto.
- logout mediante invalidación de sesión y regeneración del token CSRF.
- `auth:sanctum` en API protegida.
- autorización independiente de la autenticación.
- resolución central de permisos.
- semántica `ALLOW | DENY | NO_RULE`.
- precedencia `DENY > ALLOW`.
- default-deny cuando no existe regla.
- middleware `siga.permission` en operaciones de Persona.

## 4. Autorización vigente

Arquitectura:

```text
PERSONA
   |
   +-- 0..1 USUARIO
           |
           +-- N:M ROLES
           |       |
           |       +-- N:M PERMISOS
           |               |
           |               +-- effect = ALLOW | DENY
           |
           +-- N:M PERFILES
                   |
                   +-- experiencia de interfaz / UX
```

Los perfiles no conceden, amplían ni revocan permisos del backend.

## 5. Roles y permisos iniciales

Roles:

- `ROL_ADMIN_SISTEMA`
- `ROL_AUDITOR`
- `ROL_CONSULTA_PERSONAS`
- `ROL_GESTOR_PERSONAS`

Permisos:

- `auditoria.ver`
- `personas.actualizar`
- `personas.baja`
- `personas.crear`
- `personas.reingreso`
- `personas.ver`
- `usuarios.asignar_roles`
- `usuarios.crear`

Todas las relaciones iniciales vigentes fueron cargadas con efecto explícito `ALLOW`. La infraestructura permite introducir `DENY` cuando exista una regla aprobada.

## 6. Perfiles

Catálogo inicial:

- `PERFIL_ADMINISTRACION` — Administración
- `PERFIL_AUDITORIA` — Auditoría
- `PERFIL_PERSONAS` — Personas

Relación:

`system.user_profiles`

Reglas:

- Usuario ↔ Perfil es N:M.
- `is_default` identifica el perfil predeterminado.
- PostgreSQL impide más de un perfil predeterminado por usuario mediante índice único parcial.
- un usuario puede no tener perfiles;
- un usuario puede tener perfiles y no tener ninguno marcado como predeterminado;
- el perfil activo de una sesión no se persiste todavía en `system.user_profiles`.

Acciones publicadas:

- `AssignProfile`
- `SetDefaultProfile`

`UnassignProfile` no forma parte de este checkpoint porque todavía no estaba publicado en `main` al crear este documento.

## 7. Seguridad: controles obligatorios antes de producción

Los siguientes controles pasan a ser requisitos de endurecimiento y no mejoras opcionales:

1. HTTPS obligatorio.
2. cookie de sesión con `Secure` en producción.
3. `HttpOnly=true`.
4. `SameSite=Lax` como valor inicial aprobado, salvo necesidad técnica documentada.
5. HSTS en producción.
6. Content Security Policy.
7. `X-Content-Type-Options: nosniff`.
8. `Referrer-Policy`.
9. `Permissions-Policy`.
10. protección contra framing mediante CSP `frame-ancestors`.
11. rate limiting específico de login.
12. tiempo máximo de inactividad de sesión.
13. tiempo máximo absoluto de sesión.
14. revocación de sesiones ante cambios críticos de credenciales o privilegios.
15. auditoría de eventos de autenticación y seguridad.
16. MFA.
17. Passkeys/WebAuthn para elevar la resistencia a phishing, especialmente en cuentas privilegiadas.
18. `APP_ENV=production` y `APP_DEBUG=false`.
19. secretos fuera del repositorio.
20. pruebas de seguridad antes de aceptación productiva.

## 8. Cambio de decisión sobre Passkeys/MFA

La decisión anterior que difería Passkeys/WebAuthn indefinidamente queda sustituida.

Nueva decisión:

- MFA y Passkeys/WebAuthn se mantienen fuera del bloque inmediato de desarrollo funcional;
- sin embargo, pasan a ser requisitos de seguridad preproducción;
- las cuentas privilegiadas deberán recibir el nivel de autenticación más fuerte disponible;
- SMS no será el mecanismo preferido de MFA.

## 9. Sesiones

Configuración base actual:

- driver `database`;
- tabla `system.sessions`;
- `SESSION_ENCRYPT=true` en `.env.example`;
- `HttpOnly=true` por defecto de configuración;
- `SameSite=lax` por defecto de configuración;
- serialización de sesión en JSON.

Objetivo de endurecimiento:

- inactividad: valor inicial de diseño de 30 minutos;
- duración absoluta: valor inicial de diseño de 8 horas;
- reautenticación para acciones especialmente sensibles.

Los valores finales deben validarse en pruebas y quedar explícitos en el entorno de producción.

## 10. Auditoría de seguridad pendiente

Debe diseñarse una extensión de auditoría para eventos como:

- login exitoso;
- login fallido;
- logout;
- MFA correcto/fallido;
- registro/eliminación de Passkey;
- cambio/restablecimiento de contraseña;
- asignación/desasignación de Rol;
- asignación/desasignación de Perfil;
- cambio de perfil predeterminado;
- revocación de sesión;
- throttling o bloqueo temporal.

Nunca deben registrarse:

- contraseñas;
- cookies;
- identificadores completos de sesión;
- secretos TOTP;
- claves privadas;
- tokens Bearer;
- tokens CSRF.

La tabla actual `system.activities` no soporta todavía estos nuevos tipos de acción; cualquier ampliación deberá diseñarse y probarse explícitamente.

## 11. Próximos bloques de seguridad recomendados

Orden recomendado:

1. cerrar el CRUD de asignación de perfiles;
2. rate limiting de login;
3. pruebas de configuración segura de sesión/cookies;
4. gestión y revocación de sesiones;
5. auditoría de autenticación y seguridad;
6. headers defensivos y configuración HTTPS/HSTS;
7. MFA;
8. Passkeys/WebAuthn;
9. integración Angular con Sanctum SPA.

## 12. Regla de cierre

Ningún control planificado debe documentarse como implementado hasta existir evidencia en código, configuración y pruebas.

Este checkpoint sustituye cualquier texto anterior que presentara Passkeys/WebAuthn como una mejora indefinidamente pospuesta.
