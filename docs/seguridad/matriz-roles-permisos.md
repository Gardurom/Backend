# Matriz propuesta de roles y permisos

**Versión:** 0.1 · **Estado:** PLANTILLA — no representa el catálogo real de SIGA.

## Principios de resolución

- Rol y permiso son fuentes de autorización **solo en el backend**.
- El resolvedor aplica `DENY` por encima de `ALLOW`.
- Si no hay concesión aplicable, el resultado es denegación.
- Un perfil, grupo de perfiles o etiqueta **no** concede permisos.
- Las categorías/grupos de permisos sirven para clasificar el catálogo; no son autorizaciones por sí mismas.

## Ejemplo ilustrativo (no autorizado para producción)

| Permiso de ejemplo | Rol Capturista | Rol Consultor | Observación |
|---|---|---|---|
| `personas.ver` | ALLOW | ALLOW | Clave observada en frontend; verificar catálogo backend |
| `personas.crear` | ALLOW | — | Nombre por verificar |
| `personas.editar` | ALLOW | — | Nombre por verificar |
| `personas.baja` | DENY | — | Nombre por verificar |
| `personas.importar` | DENY | — | Clave observada en frontend; verificar catálogo backend |

`—` significa «sin asignación»: no implica autorización. Todos los roles y permisos de la tabla son **ejemplos**, no asignaciones existentes.

## Matriz real por completar tras inventario

| Código de permiso existente | Operación / recurso | Rol aplicable | Regla ALLOW/DENY | Ámbito | Prueba automatizada |
|---|---|---|---|---|---|
| Pendiente | Pendiente | Pendiente | Pendiente | Pendiente | Pendiente |

## Controles de asignación

1. Comprobar quién tiene permiso para gestionar roles y para administrar **cada rol concreto**.
2. No permitir autoelevación ni asignación fuera del ámbito delegado.
3. Registrar asignación y revocación, usuario afectado y actor.
4. Determinar si las autorizaciones se reflejan inmediatamente en solicitudes posteriores y cómo se invalida cualquier caché/contexto previo.
5. Probar combinaciones de ALLOW y DENY, usuario sin roles, revocación y cambio de perfil.

## Pendientes de decisión

- Catálogo y nomenclatura oficial de permisos.
- Roles del sistema, roles administrables y restricciones de asignación.
- Alcances institucionales y delegación.
- Permisos directos por usuario, si existen: inventariar antes de proponer eliminarlos o crearlos.
