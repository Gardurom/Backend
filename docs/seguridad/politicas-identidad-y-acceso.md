# Políticas de identidad, usuarios, roles y autorizaciones

**Versión:** 0.1 · **Fecha:** 23/10/2026 · **Estado:** PROPUESTA
**Responsables por definir:** autoridad institucional de seguridad, administradores funcionales y responsables técnicos.

> POL-03 cuenta con aprobación operativa para el proyecto SIGA, sujeta a ratificación institucional formal. Las demás políticas permanecen como propuestas salvo aprobación expresa. La documentación no sustituye pruebas ni certificación de producción.

## POL-01 — Identidad institucional

**Objetivo:** mantener una identidad institucional coherente y evitar duplicidades.

**Reglas propuestas:** una Persona se registra y administra en `institutional.persons`; la existencia de una cuenta de acceso no obliga a crear otra Persona. La búsqueda de coincidencias y las correcciones de identidad respetarán las autorizaciones y reglas institucionales existentes.

**Verificación prevista:** pruebas de identidad, detección de duplicidades y ausencia de creación automática de Usuario al registrar Persona.

## POL-02 — Vinculación Persona–Usuario

**Objetivo:** relacionar cuentas humanas con identidades institucionales verificadas.

**Reglas propuestas:** una cuenta humana ordinaria se vinculará a una Persona existente mediante una referencia íntegra. Una Persona podrá existir sin Usuario. Se propone una cuenta humana ordinaria por Persona, sujetando excepciones a una decisión formal. Las cuentas técnicas no se tratarán automáticamente como personas físicas.

**Pendiente:** confirmar cardinalidad real, nulabilidad/unicidad de `id_persona`, cuentas preexistentes y excepciones antes de definir restricciones de base de datos.

## POL-03 — Ciclo de vida del Usuario

**Estado:** APROBADA PARA OPERACIÓN DEL PROYECTO SIGA, con reservas de certificación.
**Versión:** 1.0 · **Fecha de aprobación operativa:** 01/11/2026.
**Alcance:** control de acceso de Personas y Usuarios actualmente implementado.
**Aprobación institucional formal:** pendiente de identificación y ratificación por la autoridad competente.

**Objetivo:** garantizar que únicamente las cuentas institucionalmente elegibles puedan autenticarse y operar.

### Reglas aprobadas para SIGA

1. Toda cuenta humana interactiva requiere una Persona vinculada con estado ACTIVO.
2. El acceso exige que el Usuario esté HABILITADO; PENDIENTE o DESHABILITADO impiden el acceso.
3. La baja o inactivación de Persona impide la autenticación y el uso de sesiones anteriores.
4. La deshabilitación de Usuario revoca sus sesiones y conserva su identidad, historial y roles para trazabilidad.
5. El reingreso de Persona no habilita automáticamente la cuenta. La habilitación debe ser expresa y requiere revisión de roles.
6. Los bloqueos de seguridad, suspensiones temporales y periodos de vigencia administrativa se aplican mediante los controles existentes, con evaluación en backend.
7. Las operaciones administrativas sensibles requieren permisos efectivos y reautenticación reciente.
8. Los perfiles no conceden permisos. La autorización efectiva se resuelve en Laravel, respetando DENY sobre ALLOW.
9. Los cambios de acceso deben conservar su registro de auditoría.
10. No se permite dejar SIGA sin un administrador habilitado.

### Límites y reservas

- No se aprueban plazos arbitrarios de suspensión ni nuevas excepciones de cuentas técnicas.
- La autoridad institucional deberá ratificar responsables, causas de bloqueo, periodos de conservación y procedimientos de recuperación.
- Las pruebas unitarias y de navegador no sustituyen las certificaciones transaccionales pendientes de H1, H2 y H5.
- La relación académica o laboral con vigencia pertenece a H4 y no se considera implementada por esta política.
- La presente aprobación operativa no equivale a certificación de producción ni aprobación normativa institucional formal.

## POL-04 — Asignación y revocación de roles

**Objetivo:** impedir asignaciones no autorizadas y elevación de privilegios.

**Reglas propuestas:** solo operadores autorizados podrán asignar/revocar roles, y solo dentro del ámbito que administran. No se permitirán autoasignaciones privilegiadas ni cambios de rol mediante manipulación de Angular. Cada cambio requerirá validación del lado del servidor y auditoría.

**Pendiente:** definir catálogo, ámbitos, delegación y prohibiciones de autoaprobación.

## POL-05 — Autorización efectiva

**Objetivo:** garantizar decisiones uniformes y denegación por defecto.

**Reglas propuestas:** Laravel resuelve los permisos en cada operación protegida. `DENY` prevalece sobre `ALLOW`; sin concesión aplicable se deniega. La interfaz solo refleja decisiones para mejorar la UX, nunca concede acceso.

**Pendiente:** contrastar fuentes y alcance efectivo del `PermissionResolver`, caché y revocación.

## POL-06 — Administración de perfiles

**Objetivo:** separar personalización de autorización.

**Reglas propuestas:** los perfiles, grupos de perfiles y etiquetas organizan menús, contexto y widgets. Cambiar de perfil no concede ni revoca permisos. Los grupos/categorías de permisos clasifican el catálogo, no sustituyen las reglas efectivas de autorización.

**Verificación prevista:** pruebas de cambio de perfil sin modificación de los permisos que devuelve Laravel.

## POL-07 — Autenticación y sesiones

**Objetivo:** proteger el acceso y las operaciones sensibles.

**Reglas propuestas:** utilizar la autenticación vigente mediante Laravel Sanctum y sesión; aplicar caducidad absoluta y controles de sesión existentes. Distinguir 401 (sesión inválida), 403 (denegación) y errores de comunicación. Exigir reautenticación en las operaciones identificadas como sensibles; evitar reintentos automáticos de dichas operaciones.

**Pendiente:** catálogo de operaciones sensibles, revocación inmediata, límites de sesión y verificación de XSRF desde navegador. Passkeys/WebAuthn queda fuera de la fase actual.

## POL-08 — Baja y reingreso de Personas

**Objetivo:** separar situación institucional y acceso al sistema.

**Reglas propuestas:** una baja de Persona no elimina su Usuario ni su trazabilidad. Si la baja termina la relación que justifica el acceso, se propone suspender la cuenta e invalidar sesiones. Ante reingreso se revisarán expresamente cuenta, roles y vigencia; no se restaurarán automáticamente autorizaciones anteriores.

**Pendiente de decisión institucional:** tipos de baja con impacto en acceso y responsables de la autorización de reingreso.

## POL-09 — Auditoría y trazabilidad

**Objetivo:** reconstruir cambios de cuentas, roles y permisos.

**Reglas propuestas:** registrar actor, usuario afectado, operación, cambio realizado, instante y resultado conforme al mecanismo de auditoría existente. No registrar contraseñas, tokens ni secretos. La estructura de `system.activities` no incorporará un campo `motivo` por esta propuesta.

**Pendiente:** confirmar qué eventos se guardan actualmente, retención, acceso y pruebas de trazabilidad.

## POL-10 — Cuentas privilegiadas y técnicas

**Objetivo:** limitar privilegios elevados y accesos no humanos.

**Reglas propuestas:** distinguir cuentas humanas privilegiadas de cuentas de servicio; conceder permisos mínimos, propietarios responsables, mecanismos de credenciales adecuados y revisión periódica. Las cuentas técnicas no deberán simular una Persona institucional.

**Pendiente:** definir catálogo de cuentas, caducidad, responsables, excepciones y alcance de autenticación no interactiva.

## Matriz de control y aprobación

| Código | Estado documental | Implementación | Pruebas / evidencia |
|---|---|---|---|
| POL-01 | Propuesta | Por verificar | Por identificar |
| POL-02 | Propuesta | Por verificar | Por identificar |
| POL-03 | Aprobada para operación SIGA; ratificación institucional pendiente | Controles de acceso implementados | 130 pruebas unitarias; T19/T20; F5.1–F5.10 con reservas |
| POL-04 | Propuesta | Por verificar | Por identificar |
| POL-05 | Propuesta | Por verificar | Por identificar |
| POL-06 | Propuesta | Por verificar | Por identificar |
| POL-07 | Propuesta | Parcialmente conocida, por contrastar | Por vincular a pruebas existentes |
| POL-08 | Propuesta | Por verificar | Por identificar |
| POL-09 | Propuesta | Por verificar | Por identificar |
| POL-10 | Propuesta | Por verificar | Por identificar |

**Criterio de aprobación:** cada política requiere responsable institucional, resolución expresa, fecha y versión; una aprobación documental no equivale a puesta en producción.
