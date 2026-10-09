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
  |       +--> registra siga_authenticated_at
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
- `GET /api/sessions`.
- `DELETE /api/sessions/{session}`.
- `DELETE /api/sessions/others`.
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
- timeout absoluto de sesión de 8 horas.
- middleware `siga.session.absolute` en las rutas API autenticadas actuales.
- listado de sesiones activas del usuario autenticado.
- identificadores públicos opacos de sesión mediante HMAC-SHA256; el ID real de `system.sessions` no se expone.
- revocación individual limitada a sesiones propias distintas de la sesión actual.
- revocación masiva de las demás sesiones propias conservando la sesión actual.
- las sesiones vencidas por inactividad no se presentan como activas.

Configuración versionada relevante:

```text
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_LIFETIME=30
```

En `config/session.php` y la configuración de arranque:

- `http_only=true`;
- `same_site=lax`;
- `SESSION_LIFETIME=30`;
- datos de sesión cifrados;
- `secure` depende del entorno fuera de producción;
- en `production`, la aplicación rechaza el arranque si `session.secure !== true`;
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

**Controles implementados en aplicación/configuración:**

- `SESSION_HTTP_ONLY=true` como política efectiva;
- `SESSION_SAME_SITE=lax` como política inicial;
- la aplicación exige `SESSION_SECURE_COOKIE=true` al arrancar en `production`;
- pruebas funcionales verifican `HttpOnly`, `SameSite=Lax`, cifrado y la salvaguarda de `Secure`.

**Requisitos operativos de producción todavía pendientes de despliegue/verificación:**

- HTTPS obligatorio;
- HSTS;
- dominio de cookie limitado al alcance mínimo necesario;
- confirmar que las cookies emitidas se transportan únicamente sobre HTTPS;
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
- datos de sesión cifrados;
- regeneración al autenticar;
- invalidación al cerrar sesión;
- timeout por inactividad de 30 minutos;
- cookie `HttpOnly`;
- política `SameSite=Lax`;
- salvaguarda de `Secure` obligatoria al arrancar en producción;
- timeout absoluto de 8 horas desde el inicio autenticado.
- administración de sesiones activas del propio usuario;
- revocación de una sesión propia distinta de la actual;
- revocación de todas las demás sesiones propias conservando la actual;
- IDs públicos de sesión derivados con HMAC-SHA256, sin exponer el ID persistido;
- sesiones con inactividad igual o superior al límite configurado no se listan como activas.

Evidencia:

- prueba funcional: `SessionSecurityConfigurationTest`;
- commit: `d3acd0e feat: endurecer configuracion de sesiones`;
- prueba funcional: `SessionAbsoluteTimeoutTest`;
- commit: `bee9ca1 feat: agregar timeout absoluto de sesion`;
- prueba funcional: `SessionManagementTest`;
- commit: `bc9d17c feat: gestionar y revocar sesiones`.

Reglas del timeout absoluto implementado:

- el login correcto registra `siga_authenticated_at` después de regenerar la sesión;
- la duración máxima es de 8 horas;
- justo antes de 8 horas la sesión continúa válida;
- al alcanzar o superar 8 horas se ejecuta logout, se invalida la sesión, se regenera el token CSRF y se responde HTTP 401;
- una sesión existente sin `siga_authenticated_at` inicializa la marca en su primera petición con sesión;
- si la petición autenticada no tiene store de sesión, el middleware no intenta aplicar un timeout de sesión y deja continuar el mecanismo de autenticación correspondiente.

**Endurecimiento planificado**

- reautenticación para operaciones sensibles;
- revocación automática de otras sesiones cuando un cambio crítico de credenciales o identidad así lo requiera;
- auditoría de los eventos de revocación de sesión.

La administración y revocación manual de sesiones ya están implementadas; los disparadores automáticos por cambios críticos y su auditoría siguen pendientes.

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
