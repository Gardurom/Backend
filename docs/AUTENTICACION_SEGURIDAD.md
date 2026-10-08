# SIGA — Autenticación y seguridad

Última actualización: 2026-10-08

## 1. Propósito

Este documento define el modelo de autenticación y los controles de seguridad del backend de SIGA, distinguiendo con claridad lo implementado de lo planificado.

## 2. Estrategia principal

La aplicación web propia de SIGA utilizará Laravel Sanctum como SPA stateful mediante sesión y cookies.

No se utilizará JWT ni un Bearer token almacenado en el navegador como mecanismo principal de autenticación de Angular.

```text
SPA SIGA
  |
  +--> GET /sanctum/csrf-cookie
  |
  +--> POST /login
  |       |
  |       +--> RateLimiter (5 fallos / 60 s / correo+IP)
  |       +--> Auth::attempt()
  |       +--> login correcto => limpiar contador
  |       +--> session()->regenerate()
  |
  +--> cookie de sesión
  |
  +--> auth:sanctum
          |
          +--> API protegida
```

## 3. Implementación actual

Componentes confirmados en código:

- Laravel Sanctum.
- `statefulApi()`.
- guard `web`.
- `POST /login`.
- `POST /logout`.
- `GET /api/user`.
- middleware `auth:sanctum`.
- protección CSRF.
- CORS con `supports_credentials=true`.
- orígenes CORS definidos por variable de entorno.
- normalización del correo de acceso.
- rate limiting específico del login por correo normalizado + dirección IP.
- máximo de 5 intentos fallidos dentro de una ventana de 60 segundos.
- bloqueo del siguiente intento con HTTP 429 y encabezado `Retry-After`.
- limpieza del contador después de un login correcto.
- regeneración de sesión después de login.
- invalidación de sesión al logout.
- regeneración del token CSRF al logout.
- sesiones con driver `database`.
- tabla `system.sessions`.

Configuración versionada relevante:

```text
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_LIFETIME=120   # valor de desarrollo actual
```

En `config/session.php`:

- `http_only=true` por defecto;
- `same_site=lax` por defecto;
- `secure` depende del entorno;
- serialización `json`.

## 4. Separación autenticación/autorización

Autenticación responde:

> ¿Quién es el usuario?

Autorización responde:

> ¿Puede ejecutar esta operación?

La autorización no depende de perfiles de interfaz.

```text
Usuario
  |
  +--> Roles
  |      |
  |      +--> Permisos
  |             |
  |             +--> ALLOW / DENY
  |
  +--> Perfiles
         |
         +--> UX / navegación / presentación
```

El backend utiliza `PermissionResolver` y el middleware `siga.permission`.

Regla:

```text
DENY si existe cualquier DENY aplicable
ALLOW si no existe DENY y existe ALLOW
NO_RULE en cualquier otro caso
NO_RULE => acceso denegado
```

## 5. Tokens

La SPA actual no requiere Personal Access Tokens.

No se habilitarán mecanismos de token adicionales sin una necesidad aprobada.

Casos futuros posibles:

- aplicación móvil propia: evaluar Sanctum PAT;
- integración de terceros: evaluar OAuth2/Passport;
- comunicación servidor-servidor: evaluar credenciales específicas con rotación y almacenamiento seguro.

Agregar tokens sin necesidad aumenta la superficie de ataque y no se considera una mejora de seguridad.

## 6. Cookies y HTTPS

Requisitos de producción:

- HTTPS obligatorio;
- `SESSION_SECURE_COOKIE=true`;
- `SESSION_HTTP_ONLY=true`;
- `SESSION_SAME_SITE=lax` como política inicial;
- HSTS;
- dominio de cookie limitado al alcance mínimo necesario;
- nunca enviar sesión mediante HTTP plano.

`SameSite=Strict` podrá evaluarse, pero no se adoptará automáticamente si rompe flujos legítimos de la SPA.

## 7. CSRF

La SPA utilizará el flujo de Sanctum:

1. solicitar `/sanctum/csrf-cookie`;
2. recibir la cookie XSRF;
3. enviar las credenciales;
4. mantener las cookies de sesión;
5. consumir la API con credenciales.

No deben deshabilitarse las defensas CSRF para simplificar el frontend.

## 8. Rate limiting de autenticación

**Estado: IMPLEMENTADO**

La ruta `POST /login` cuenta con un limitador específico versionado.

Reglas implementadas:

- la clave del bucket combina correo normalizado y dirección IP;
- se permiten hasta 5 intentos de autenticación fallidos;
- la ventana de expiración es de 60 segundos;
- el siguiente intento cuando el bucket está agotado responde HTTP 429;
- la respuesta 429 incluye `Retry-After`;
- un login correcto ejecuta la limpieza del bucket;
- errores de validación previos a `Auth::attempt()` no incrementan el contador;
- no existe bloqueo permanente de la cuenta.

Evidencia:

- implementación: `AuthenticatedSessionController`;
- prueba funcional: `LoginRateLimitTest`;
- commit: `215eab0 feat: limitar intentos de inicio de sesion`.

La auditoría de eventos de throttling y otros eventos sospechosos sigue planificada en la sección de auditoría de autenticación.

## 9. Sesiones

**Estado actual**

- persistencia en PostgreSQL;
- regeneración al autenticar;
- invalidación al cerrar sesión.

**Endurecimiento planificado**

- timeout por inactividad;
- timeout absoluto;
- revocación de otras sesiones ante cambios críticos;
- reautenticación para operaciones sensibles;
- posibilidad de administrar sesiones activas.

Valores iniciales de diseño:

- 30 minutos de inactividad;
- 8 horas de duración absoluta.

Estos valores no se consideran implementados hasta existir código/configuración y pruebas.

## 10. MFA y Passkeys/WebAuthn

**Estado: PLANIFICADO / REQUISITO PREPRODUCCIÓN**

La decisión anterior de posponer Passkeys/WebAuthn indefinidamente queda reemplazada.

SIGA deberá incorporar autenticación reforzada antes de producción:

- MFA para elevar la garantía de identidad;
- Passkeys/WebAuthn como mecanismo resistente a phishing;
- prioridad especial para cuentas administrativas, auditoras y privilegiadas;
- TOTP puede utilizarse como mecanismo complementario;
- SMS no será la opción preferida.

La implementación se realizará después de cerrar los bloques inmediatos del núcleo funcional, sin dejar de ser requisito de salida a producción.

## 11. Headers de seguridad

**Estado: PLANIFICADO**

Producción deberá incorporar como mínimo:

- `Strict-Transport-Security`;
- `Content-Security-Policy`;
- `X-Content-Type-Options: nosniff`;
- `Referrer-Policy`;
- `Permissions-Policy`;
- protección contra framing mediante `frame-ancestors`.

## 12. Auditoría de autenticación

**Estado: PLANIFICADO**

Deben registrarse de forma segura eventos relevantes, sin almacenar secretos:

- login correcto/fallido;
- logout;
- cambios de contraseña;
- restablecimiento de contraseña;
- MFA;
- Passkeys;
- revocación de sesiones;
- cambios de roles y perfiles;
- cambios de perfil predeterminado;
- throttling.

La tabla `system.activities` actual tiene un dominio de acciones limitado a Persona y no debe ampliarse informalmente.

## 13. Secretos y configuración de producción

Requisitos:

```text
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
```

Nunca versionar:

- contraseñas;
- `APP_KEY` real;
- secretos MFA;
- tokens;
- claves privadas;
- credenciales de base;
- cookies o identificadores de sesión.

## 14. Criterio de aceptación de seguridad

Un control de seguridad solo se considerará implementado cuando exista:

`decisión -> implementación -> prueba -> evidencia -> aceptación`

Véase también:

- `CHECKPOINT_SEGURIDAD_PERFILES_2026-10-08.md`
- `DECISIONES_TECNICAS.md`
- `PRUEBAS_Y_CALIDAD.md`
