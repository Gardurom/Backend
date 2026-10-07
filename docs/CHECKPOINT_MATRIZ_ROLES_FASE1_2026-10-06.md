# Checkpoint de diseño: Matriz inicial de roles de acceso — Fase 1

**Proyecto:** Sistema Integral de Gestión Académica (SIGA)  
**Fecha del checkpoint:** 2026-10-06  
**Estado:** Aprobado  
**Base técnica verificada:** commit `c618dce` (`feat: agregar catalogo inicial de permisos`)  
**Actualización de diseño:** 2026-10-06 — previsión de administrador integral de SIGA

---

## 1. Objetivo

Este checkpoint formaliza la matriz inicial de roles de acceso para la Fase 1 de SIGA.

La decisión se toma después de haber implementado y probado:

- `system.roles`;
- `system.user_roles`;
- `system.permissions`;
- `system.role_permissions`;
- el catálogo inicial de ocho permisos técnicos.

La finalidad es comenzar la autorización real del backend con un conjunto mínimo de roles, sin anticipar todavía los roles académicos, escolares o administrativos de módulos que aún no existen.

---

## 2. Principio de diseño

Los roles de acceso de esta fase describen **qué puede hacer un usuario dentro de SIGA**.

No representan por sí mismos la condición institucional de una Persona.

Por lo tanto:

- ser cadete, instructor, administrativo o directivo no otorga automáticamente permisos técnicos;
- los roles de acceso se asignan explícitamente a `system.users`;
- los permisos se asignan a roles;
- el backend debe validar la autorización independientemente de lo que muestre el frontend;
- se aplica mínimo privilegio.

---

## 3. Roles aprobados para Fase 1

Se aprueban cuatro roles iniciales.

### 3.1 `ROL_GESTOR_PERSONAS`

**Nombre:** Gestor de Personas

**Finalidad:** operar el núcleo de Persona durante la Fase 1.

Permisos:

- `personas.ver`;
- `personas.crear`;
- `personas.actualizar`;
- `personas.baja`;
- `personas.reingreso`.

Este rol puede ejecutar el ciclo operativo actualmente implementado para Persona.

No recibe permisos de administración técnica de usuarios ni de auditoría por el solo hecho de gestionar Personas.

---

### 3.2 `ROL_CONSULTA_PERSONAS`

**Nombre:** Consulta de Personas

**Finalidad:** permitir consulta sin capacidad de modificación.

Permiso:

- `personas.ver`.

No puede:

- registrar Personas;
- actualizar Personas;
- realizar bajas;
- realizar reingresos;
- administrar usuarios o roles;
- consultar auditoría salvo asignación explícita de otro rol que lo permita.

---

### 3.3 `ROL_ADMIN_SISTEMA`

**Nombre:** Administrador del Sistema

**Finalidad:** administrar técnicamente cuentas y asignaciones de acceso.

Permisos:

- `usuarios.crear`;
- `usuarios.asignar_roles`.

Regla de mínimo privilegio:

> `ROL_ADMIN_SISTEMA` no recibe automáticamente permisos sobre Persona ni sobre auditoría.

Administrar cuentas y accesos no equivale a poseer autorización funcional sobre expedientes institucionales.

Si una persona que administra SIGA necesita además operar Personas o revisar auditoría, deberá recibir el rol adicional correspondiente mediante una asignación explícita y auditable.

---

### 3.4 `ROL_AUDITOR`

**Nombre:** Auditor

**Finalidad:** consultar evidencia de trazabilidad y auditoría.

Permiso:

- `auditoria.ver`.

Este rol se diseña como rol de consulta.

No recibe permisos de escritura sobre Persona ni permisos de administración de usuarios por defecto.

---

### 3.5 Previsión futura: `ROL_SUPERADMIN_SIGA`

La arquitectura aprobada permite incorporar en el futuro un rol de administración integral de SIGA si existe una solicitud institucional formal y justificada.

El código previsto para ese caso sería:

```text
ROL_SUPERADMIN_SIGA
```

Este rol **no forma parte de la Fase 1**, no se insertará todavía en `system.roles` y no modifica la matriz aprobada en este checkpoint.

Su finalidad sería distinta de `ROL_ADMIN_SISTEMA`:

- `ROL_ADMIN_SISTEMA` conserva una responsabilidad técnica limitada a administración de cuentas, roles y accesos;
- `ROL_SUPERADMIN_SIGA`, si se aprueba posteriormente, representaría una autorización integral y excepcional sobre los módulos funcionales que expresamente se le asignen.

No se implementará como una puerta trasera ni como una excepción que omita el modelo de autorización. Deberá continuar respetando la arquitectura:

```text
Usuario → Rol → Permisos
```

Por lo tanto, un eventual `ROL_SUPERADMIN_SIGA` deberá contar con permisos explícitos y verificables. La existencia del rol no debe implicar por sí sola un bypass global en el código.

Antes de incorporarlo deberán definirse y aprobarse, como mínimo:

1. la solicitud institucional que justifique su existencia;
2. el catálogo exacto de permisos que tendrá;
3. los módulos y alcances de datos sobre los que podrá actuar;
4. las incompatibilidades o restricciones aplicables;
5. la auditoría de su asignación, revocación y operaciones sensibles;
6. controles reforzados de autenticación cuando estén disponibles, incluyendo la evaluación de MFA o Passkeys/WebAuthn.

La asignación de este rol deberá considerarse excepcional, de mínimo número de usuarios y plenamente auditable.

Mientras `ROL_SUPERADMIN_SIGA` no sea aprobado e implementado, un usuario que necesite cubrir varias responsabilidades deberá recibir explícitamente los roles vigentes correspondientes. La combinación de roles seguirá sujeta a separación de funciones, incompatibilidades y alcance de datos.

---

## 4. Matriz aprobada

| Rol | personas.ver | personas.crear | personas.actualizar | personas.baja | personas.reingreso | usuarios.crear | usuarios.asignar_roles | auditoria.ver |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| `ROL_GESTOR_PERSONAS` | Sí | Sí | Sí | Sí | Sí | No | No | No |
| `ROL_CONSULTA_PERSONAS` | Sí | No | No | No | No | No | No | No |
| `ROL_ADMIN_SISTEMA` | No | No | No | No | No | Sí | Sí | No |
| `ROL_AUDITOR` | No | No | No | No | No | No | No | Sí |

---

## 5. Representación de la matriz

```text
ROL_GESTOR_PERSONAS
 ├─ personas.ver
 ├─ personas.crear
 ├─ personas.actualizar
 ├─ personas.baja
 └─ personas.reingreso

ROL_CONSULTA_PERSONAS
 └─ personas.ver

ROL_ADMIN_SISTEMA
 ├─ usuarios.crear
 └─ usuarios.asignar_roles

ROL_AUDITOR
 └─ auditoria.ver
```

---

## 6. Roles que no se incorporan todavía

Los siguientes roles del catálogo funcional amplio no se incorporarán todavía como parte de esta Fase 1:

- `ROL_CADETE`;
- `ROL_INSTRUCTOR`;
- `ROL_TUTOR`;
- `ROL_CAPTURISTA`;
- `ROL_SECRETARIA_ACAD`;
- `ROL_SECRETARIA_GEN`;
- `ROL_JEFE_ACADEMIA`;
- `ROL_CONTROL_ESCOLAR`;
- `ROL_SERVICIOS_BENEFICIOS`;
- `ROL_BIBLIOTECARIO`;
- `ROL_RECURSOS_HUMANOS`;
- `ROL_RECTOR`;
- `ROL_VICERRECTOR`;
- `ROL_DIRECTOR_ACADEMIA`;
- `ROL_ASPIRANTE`.

La razón es que esos roles dependen de módulos, relaciones institucionales o reglas de negocio que aún no han sido implementados.

Se incorporarán de forma incremental cuando exista:

1. un caso de uso real;
2. un conjunto de permisos definido;
3. un alcance de datos definido;
4. reglas de separación de funciones;
5. pruebas de autorización.

---

## 7. Relación con condiciones institucionales

Este checkpoint reafirma que:

```text
CONDICIÓN INSTITUCIONAL ≠ ROL DE ACCESO
```

Ejemplos:

- una Persona puede ser instructor y no tener todavía cuenta de SIGA;
- un instructor con cuenta no recibe automáticamente `ROL_GESTOR_PERSONAS`;
- un administrativo no recibe automáticamente `ROL_ADMIN_SISTEMA`;
- un directivo no recibe automáticamente acceso técnico global.

Las relaciones académicas o institucionales deberán modelarse en sus dominios correspondientes.

---

## 8. Múltiples roles por usuario

La arquitectura vigente permite N roles por usuario mediante `system.user_roles`.

Ejemplo válido:

```text
Usuario
 ├─ ROL_ADMIN_SISTEMA
 └─ ROL_AUDITOR
```

Sin embargo, la combinación de roles no debe interpretarse como una autorización ilimitada.

La acumulación estará sujeta a:

- mínimo privilegio;
- incompatibilidades futuras;
- separación de funciones;
- alcance de datos;
- políticas explícitas.

---

## 9. Implementación técnica posterior a este checkpoint

La aprobación de esta matriz autoriza iniciar los siguientes bloques técnicos, cada uno mediante TDD:

1. insertar los cuatro roles aprobados en `system.roles`;
2. probar el catálogo de roles;
3. insertar las asociaciones aprobadas en `system.role_permissions`;
4. probar la matriz rol-permiso;
5. incorporar relaciones Eloquent con `Permission`, cuando corresponda;
6. crear el mecanismo de autorización del backend;
7. proteger los endpoints de Persona;
8. proteger creación y asignación de usuarios/roles;
9. incorporar auditoría para asignación y revocación de accesos.

Cada bloque deberá cerrarse con pruebas específicas, suite global, Pint, `git diff --check`, commit y push.

---

## 10. Estado de los permisos aprobados

Al momento de este checkpoint ya existe el siguiente catálogo técnico:

- `auditoria.ver` — Consultar auditoría;
- `personas.actualizar` — Actualizar personas;
- `personas.baja` — Dar de baja personas;
- `personas.crear` — Registrar personas;
- `personas.reingreso` — Reingresar personas;
- `personas.ver` — Consultar personas;
- `usuarios.asignar_roles` — Asignar roles a usuarios;
- `usuarios.crear` — Crear usuarios.

Estos permisos constituyen la base de la matriz aprobada.

---

## 11. Decisiones que no se toman todavía

Este checkpoint no autoriza aún:

- roles académicos adicionales;
- permisos de calificaciones, grupos, asistencia, biblioteca, RH, servicios o beneficios;
- asignación automática de roles según condición institucional;
- superusuario funcional implícito;
- creación o asignación de `ROL_SUPERADMIN_SIGA` sin una aprobación institucional y técnica posterior;
- permisos directos a Personas;
- permisos directos a usuarios fuera del modelo rol-permiso;
- delegación temporal de roles;
- MFA o Passkeys/WebAuthn;
- perfiles como fuente independiente de autorización.

---

## 12. Decisión del checkpoint

**APROBADO.**

La Fase 1 de SIGA adoptará inicialmente los siguientes roles de acceso:

```text
ROL_GESTOR_PERSONAS
ROL_CONSULTA_PERSONAS
ROL_ADMIN_SISTEMA
ROL_AUDITOR
```

y la matriz rol-permiso definida en este documento será la referencia para los siguientes bloques de implementación.

La posible incorporación futura de `ROL_SUPERADMIN_SIGA` queda únicamente como previsión arquitectónica. No forma parte de esta matriz ni autoriza su creación en la Fase 1.

Este checkpoint complementa el documento:

`docs/CHECKPOINT_USUARIOS_ROLES_2026-10-06.md`

y constituye la autorización funcional para comenzar la carga controlada del catálogo inicial de roles y sus relaciones con permisos.
