# Checkpoint de implementación: Autorización del módulo Persona

**Proyecto:** Sistema Integral de Gestión Académica (SIGA)
**Fecha del checkpoint:** 2026-10-07
**Estado:** Implementado y probado
**Commit de cierre técnico:** `66c5916` (`test: validar precedencia deny sobre allow en personas`)

---

## 1. Objetivo

Este checkpoint formaliza el cierre de la primera integración real de autorización del backend de SIGA sobre el módulo Persona.

La autorización deja de depender únicamente de que el usuario esté autenticado y pasa a exigir permisos técnicos explícitos resueltos mediante Roles, Permisos y reglas `ALLOW/DENY`.

---

## 2. Arquitectura implementada

La autorización vigente sigue el siguiente flujo:

    PERSONA
       |
       +-- 0..1 USUARIO
                  |
                  +-- N:M ROLES
                           |
                           +-- N:M PERMISOS
                                    |
                                    +-- effect = ALLOW | DENY

La decisión efectiva se centraliza mediante:

    PermissionResolver

Las rutas protegidas utilizan:

    auth:sanctum
           |
           v
    siga.permission:<permiso>
           |
           v
    PermissionResolver

Los Controllers no reimplementan el algoritmo de autorización.

---

## 3. Regla efectiva de autorización

La regla implementada es:

    DENY explícito > ALLOW explícito > ausencia de regla = DENY

El resolvedor distingue internamente tres resultados:

    ALLOW
    DENY
    NO_RULE

La evaluación es:

    DENY
        si cualquiera de los roles asignados al usuario
        contiene el permiso solicitado con effect = DENY

    ALLOW
        si no existe ningún DENY
        y al menos un rol contiene el permiso con effect = ALLOW

    NO_RULE
        si no existe ninguna regla aplicable

`NO_RULE` no autoriza la operación.

Por tanto:

    ALLOW únicamente       -> permitido
    DENY únicamente        -> denegado
    ALLOW + DENY           -> denegado
    sin regla              -> denegado
    sin autenticación      -> 401

---

## 4. Componentes técnicos implementados

La infraestructura de autorización incluye:

- relación Eloquent Rol <-> Permiso;
- `effect` en `system.role_permissions`;
- valores válidos `ALLOW` y `DENY`;
- restricción `CHECK` en PostgreSQL;
- `effect` obligatorio;
- ausencia intencional de `DEFAULT 'ALLOW'`;
- `PermissionDecision`;
- `PermissionResolver`;
- middleware `EnsurePermission`;
- alias `siga.permission`.

La lógica de resolución no se duplica en Controllers ni en las rutas.

---

## 5. Endpoints de Persona protegidos

Las cinco operaciones API actualmente implementadas de Persona están protegidas individualmente.

| Método | Endpoint | Permiso requerido |
|---|---|---|
| `POST` | `/api/personas` | `personas.crear` |
| `GET` | `/api/personas/{id_persona}` | `personas.ver` |
| `PATCH` | `/api/personas/{id_persona}` | `personas.actualizar` |
| `POST` | `/api/personas/{id_persona}/baja` | `personas.baja` |
| `POST` | `/api/personas/{id_persona}/reingreso` | `personas.reingreso` |

Todas las rutas aplican primero:

    auth:sanctum

y después:

    siga.permission:<permiso>

---

## 6. Comportamiento HTTP validado

Las pruebas funcionales verifican los siguientes escenarios.

Usuario no autenticado:

    401 Unauthorized

Usuario autenticado sin permiso efectivo:

    403 Forbidden

Usuario autenticado con `ALLOW` efectivo:

    la solicitud continúa hacia el controlador

Usuario con `ALLOW` y `DENY` simultáneos:

    403 Forbidden

Recurso inexistente solicitado por un usuario autorizado:

    404 Not Found

Esto confirma que la autorización se ejecuta antes de permitir el acceso funcional al recurso.

---

## 7. Validación de precedencia DENY sobre ALLOW

Se implementó una prueba de integración HTTP específica sobre:

    GET /api/personas/{id_persona}

El escenario asigna al mismo usuario dos roles con reglas contrapuestas para `personas.ver`.

    ROL_GESTOR_PERSONAS
        personas.ver -> ALLOW

    ROL_CONSULTA_PERSONAS
        personas.ver -> DENY

Resultado comprobado:

    ALLOW + DENY
         |
         v
        DENY
         |
         v
    403 Forbidden

La modificación utilizada por la prueba ocurre dentro de la transacción funcional de pruebas.

No modifica permanentemente la matriz de permisos de la base de prueba ni la base principal `siga`.

---

## 8. Roles de Persona utilizados

`ROL_GESTOR_PERSONAS` mantiene los siguientes permisos iniciales:

    personas.ver          -> ALLOW
    personas.crear        -> ALLOW
    personas.actualizar   -> ALLOW
    personas.baja         -> ALLOW
    personas.reingreso    -> ALLOW

`ROL_CONSULTA_PERSONAS` mantiene:

    personas.ver          -> ALLOW

El `DENY` utilizado para validar precedencia se genera exclusivamente dentro de la prueba transaccional.

---

## 9. Principios de seguridad confirmados

La implementación mantiene los siguientes principios:

- autenticación y autorización son controles distintos;
- estar autenticado no implica estar autorizado;
- los permisos se conceden mediante Roles;
- un usuario puede tener múltiples Roles;
- `DENY` explícito prevalece sobre `ALLOW`;
- ausencia de regla implica denegación;
- no existen permisos directos Usuario -> Permiso en esta etapa;
- no existe bypass de autorización por ser administrador;
- `ROL_ADMIN_SISTEMA` no recibe automáticamente permisos de Persona;
- el frontend no constituye una fuente de autorización;
- se aplica mínimo privilegio.

---

## 10. Commits principales de la etapa

La evolución relevante de autorización quedó registrada en:

    eab8918  docs: definir allow deny y perfiles de interfaz
    a1c9b56  feat: relacionar roles con permisos en Eloquent
    bef152f  feat: agregar efectos allow deny a permisos por rol
    5b35df3  feat: agregar resolvedor central de permisos
    0631c97  feat: proteger consulta de personas por permiso
    1ff93b6  feat: proteger registro de personas por permiso
    a63c459  feat: proteger actualizacion de personas por permiso
    14d6814  feat: proteger baja de personas por permiso
    7888fb8  feat: proteger reingreso de personas por permiso
    66c5916  test: validar precedencia deny sobre allow en personas

---

## 11. Estado de autorización del módulo Persona

A partir de este checkpoint, la autorización de las operaciones API actualmente implementadas de Persona se considera:

    IMPLEMENTADA
    PROBADA
    PROTEGIDA POR PERMISOS
    CON DENEGACIÓN POR DEFECTO
    CON PRECEDENCIA DENY > ALLOW

Este checkpoint cierra específicamente la integración del módulo Persona con la infraestructura inicial de autorización.

No implica que todos los requerimientos funcionales futuros del dominio Persona estén terminados.

---

## 12. Elementos no implementados todavía

Quedan fuera de este checkpoint:

- `system.profiles`;
- `system.user_profiles`;
- Perfil por defecto;
- Perfil activo por sesión;
- configuración de módulos y menús del frontend;
- autorización contextual por alcance de datos;
- `system.user_permissions`;
- roles académicos de módulos futuros;
- `ROL_SUPERADMIN_SIGA`;
- MFA;
- Passkeys/WebAuthn;
- API administrativa completa de Roles y Permisos;
- protección de las futuras operaciones administrativas para creación de usuarios y asignación de roles.

---

## 13. Siguiente bloque aprobado

La siguiente etapa será el diseño e implementación controlada de:

    system.profiles
    system.user_profiles

con relación:

    USUARIO <-> N:M <-> PERFIL

Los Perfiles tendrán responsabilidad exclusiva sobre la experiencia de interfaz.

Regla obligatoria:

    PERFIL != PERMISO

Separación conceptual:

    Perfil
        -> módulos visibles
        -> menús
        -> navegación
        -> página inicial
        -> contexto de interfaz

    Roles + Permisos
        -> autorización real del backend

Un Perfil no podrá conceder, ampliar, reducir ni sustituir la autorización resuelta por `PermissionResolver`.

---

## 14. Referencias internas

Este checkpoint complementa:

    docs/CHECKPOINT_USUARIOS_ROLES_2026-10-06.md
    docs/CHECKPOINT_MATRIZ_ROLES_FASE1_2026-10-06.md

y documenta el cierre técnico de la integración de la matriz de autorización con las rutas actuales de Persona.

---

## 15. Decisión del checkpoint

**CERRADO Y APROBADO.**

La infraestructura inicial basada en:

    Usuario
       |
       v
    Roles
       |
       v
    Permisos
       |
       v
    ALLOW / DENY
       |
       v
    PermissionResolver
       |
       v
    Middleware
       |
       v
    Endpoint protegido

queda adoptada como patrón de autorización del backend de SIGA.

Las nuevas funcionalidades deberán reutilizar este mecanismo y no implementar algoritmos alternativos de autorización sin un cambio de diseño previamente aprobado.