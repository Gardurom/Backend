# SIGA — Decisiones técnicas y funcionales

Última actualización: 2026-10-08

## 1. Propósito

Este documento es la fuente viva de decisiones técnicas y funcionales aprobadas para SIGA.

Su función es evitar contradicciones entre requisitos, arquitectura, implementación, pruebas y documentación histórica.

## 2. Estados

| Estado | Significado |
|---|---|
| IMPLEMENTADA | Existe en código, base de datos o configuración y tiene evidencia. |
| PARCIALMENTE IMPLEMENTADA | Existe una parte, pero faltan controles aprobados. |
| PLANIFICADA | Aprobada, aún no implementada. |
| DESCARTADA | No forma parte del diseño vigente. |

## 3. Regla de autoridad y resolución de conflictos

Para diseñar o programar SIGA se aplicará esta jerarquía:

1. decisión explícita más reciente aprobada para el proyecto;
2. este documento, `DECISIONES_TECNICAS.md`;
3. documentos vivos específicos del dominio, por ejemplo:
   - `AUTENTICACION_SEGURIDAD.md`;
   - `PERSONA.md`;
   - `BASE_DATOS.md`;
   - `AUDITORIA.md`;
   - `PRUEBAS_Y_CALIDAD.md`;
   - `ARQUITECTURA_TECNICA.md`;
4. `ESTADO_SIGA.md` para conocer qué está realmente publicado;
5. código, migraciones y pruebas en `main` como evidencia de implementación;
6. checkpoints e historial como evidencia histórica.

Los checkpoints no sustituyen decisiones vivas posteriores.

Si una decisión viva y la implementación publicada entran en conflicto:

- no se debe extender la funcionalidad contradictoria;
- se debe identificar la diferencia;
- se debe determinar cuál refleja la decisión aprobada más reciente;
- se corrige código o documentación según corresponda;
- se agregan o ajustan pruebas antes de continuar.

## 4. DT-001 — PostgreSQL como base principal

**Estado:** IMPLEMENTADA

SIGA utiliza PostgreSQL y aprovecha esquemas, roles, constraints, funciones, UUID, JSONB y PostGIS.

## 5. DT-002 — Separación por esquemas

**Estado:** IMPLEMENTADA

Esquemas principales:

```text
institutional
system
public
```

`institutional` contiene información institucional; `system` contiene infraestructura y seguridad; `public` aloja objetos requeridos por PostGIS.

## 6. DT-003 — Mínimo privilegio en PostgreSQL

**Estado:** IMPLEMENTADA

Roles:

- `siga_owner`: propietario, NOLOGIN;
- `siga_migrator`: ejecuta migraciones y puede asumir `siga_owner`;
- `siga_app`: ejecución normal de Laravel;
- `siga_readonly`: lectura controlada;
- `siga_geoserver`: acceso específico de GeoServer.

La aplicación no debe conectarse como propietario ni migrador.

## 7. DT-004 — Persona como núcleo de identidad registrado

**Estado:** IMPLEMENTADA

Persona se registra únicamente después de validar requisitos. No se utiliza una entidad canónica/borrador separada.

Datos descartados:

- huella dactilar;
- firma autógrafa digitalizada;
- campo género.

No deben reincorporarse sin una nueva decisión formal.

## 8. DT-005 — Auditoría inmutable de aplicación

**Estado:** IMPLEMENTADA

`system.activities` permite a `siga_app`:

- SELECT;
- INSERT.

No permite:

- UPDATE;
- DELETE.

El campo `motivo` está descartado.

## 9. DT-006 — Sanctum stateful para la SPA

**Estado:** IMPLEMENTADA

La SPA propia utiliza sesión/cookie mediante Laravel Sanctum.

No se usa JWT como autenticación principal del navegador.

## 10. DT-007 — Personal Access Tokens no requeridos para la SPA

**Estado:** IMPLEMENTADA COMO RESTRICCIÓN DE ALCANCE

No se habilitarán PAT, OAuth2 o API Keys sin necesidad concreta.

Si aparece un nuevo tipo de cliente, se evaluará el mecanismo apropiado por separado.

## 11. DT-008 — Autorización central por Roles y Permisos

**Estado:** IMPLEMENTADA

Relaciones:

```text
User N:M Role
Role N:M Permission
RolePermission.effect = ALLOW | DENY
```

Regla:

- DENY prevalece sobre ALLOW;
- ALLOW aplica solo si no existe DENY;
- ausencia de regla equivale a denegar.

La decisión se resuelve mediante un componente central, no mediante reglas dispersas en controladores.

## 12. DT-009 — Perfiles separados de seguridad

**Estado:** IMPLEMENTADA

```text
User N:M Profile
```

Los perfiles describen experiencia de interfaz y no conceden permisos.

Catálogo inicial:

- `PERFIL_ADMINISTRACION`;
- `PERFIL_AUDITORIA`;
- `PERFIL_PERSONAS`.

`system.user_profiles.is_default` identifica como máximo un perfil predeterminado por usuario.

El perfil activo será una decisión de sesión y no se modela con `is_active` en la tabla pivote.

## 13. DT-010 — Gestión explícita de perfiles de usuario

**Estado:** IMPLEMENTADA

Acciones publicadas:

- `AssignProfile`;
- `UnassignProfile`;
- `SetDefaultProfile`.

Reglas:

- asignar un perfil nuevo no lo vuelve predeterminado automáticamente;
- reasignar un perfil existente es idempotente;
- cambiar el predeterminado solo es válido para un perfil ya asignado;
- desasignar un perfil existente es idempotente;
- desasignar el perfil predeterminado no selecciona otro automáticamente;
- un usuario puede quedar sin perfiles;
- un usuario puede conservar perfiles sin tener uno predeterminado;
- un identificador de perfil inexistente se rechaza;
- perfiles no alteran roles ni permisos.

## 14. DT-011 — Endurecimiento obligatorio de autenticación

**Estado:** PLANIFICADA

Antes de producción se requieren:

- HTTPS;
- cookie Secure;
- HSTS;
- CSP;
- headers defensivos;
- rate limiting de login;
- timeouts de sesión;
- revocación de sesiones;
- auditoría de eventos de autenticación;
- MFA;
- Passkeys/WebAuthn;
- configuración de producción sin debug.

## 15. DT-012 — MFA y Passkeys/WebAuthn

**Estado:** PLANIFICADA

Esta decisión sustituye la definición anterior que posponía Passkeys/WebAuthn como mejora indefinida.

MFA y Passkeys/WebAuthn son requisitos preproducción, con prioridad para cuentas privilegiadas.

No deben documentarse todavía como implementados.

## 16. DT-013 — Defensa en profundidad sin mecanismos innecesarios

**Estado:** IMPLEMENTADA COMO PRINCIPIO

Seguridad prioritaria no significa habilitar todos los mecanismos disponibles.

Cada nuevo mecanismo de autenticación aumenta superficie de ataque y deberá incorporarse únicamente cuando exista un caso de uso real, junto con:

- expiración;
- revocación;
- auditoría;
- pruebas;
- gestión de secretos.

## 17. DT-014 — TDD y validación antes de integrar

**Estado:** IMPLEMENTADA

Flujo de trabajo:

```text
prueba roja
-> implementación mínima
-> prueba verde
-> suite relacionada
-> Pint
-> composer test:all
-> git diff --check
-> commit
-> push
-> verificación remota
```

## 18. DT-015 — Trazabilidad documental

**Estado:** IMPLEMENTADA COMO REGLA

Cada bloque relevante debe mantener:

`necesidad -> requisito -> diseño -> implementación -> prueba -> evidencia -> aceptación`

La documentación nunca debe presentar como terminado un elemento que solo está planificado.

## 19. DT-016 — Checkpoints históricos y documentos vivos

**Estado:** IMPLEMENTADA COMO REGLA

Los checkpoints:

- congelan decisiones y estado de una fecha concreta;
- no se reescriben para aparentar que conocían cambios posteriores;
- sirven como evidencia histórica.

Los documentos vivos:

- deben reflejar la decisión vigente;
- deben actualizarse al cerrar bloques relevantes;
- prevalecen sobre checkpoints anteriores cuando existe una decisión posterior aprobada.

## 20. DT-017 — Gobierno de Git y documentación

**Estado:** IMPLEMENTADA COMO REGLA DE TRABAJO

- la documentación técnica se guarda en `main/docs`;
- no se crearán ramas ni Pull Requests sin autorización explícita previa;
- los cambios documentales aprobados se integrarán directamente a `main` siguiendo validación y trazabilidad;
- no se documentará como publicado un cambio que exista solo localmente.
