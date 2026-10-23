# Modelo conceptual Persona–Usuario

**Versión:** 0.1 · **Estado:** PROPUESTA · **Fecha:** 23/10/2026

## Entidades y responsabilidades

| Entidad | Responsabilidad | Referencia conocida |
|---|---|---|
| Persona | Identidad, expediente y ciclo de vida institucional | `institutional.persons` |
| Usuario | Cuenta, autenticación, estado de acceso y sesiones | `system.users` |
| Rol | Agrupar decisiones de autorización | Modelo/tablas por verificar |
| Permiso | Representar acciones protegidas | Catálogo/tablas por verificar |
| Perfil | Contexto UX, menú y forma de trabajo | Modelo/tablas por verificar |

## Cardinalidad propuesta

```text
PERSONA (1) ----- (0..1) USUARIO HUMANO ORDINARIO
                          |-- (0..N) ROLES ---- PERMISOS EFECTIVOS
                          |-- (0..N) PERFILES -> EXPERIENCIA DE USUARIO
```

**Importante:** esta cardinalidad es una preferencia de diseño, no una afirmación de que el esquema existente la imponga. Verificar `system.users.id_persona`, su nulabilidad, referencias e índice único. Las cuentas técnicas y excepciones deberán modelarse por separado.

## Principios

1. Registrar Persona **no** crea Usuario automáticamente.
2. Crear Usuario humano requiere seleccionar una Persona y comprobar vínculos existentes.
3. Los cambios de identidad pertenecen al dominio Persona; credenciales y acceso pertenecen al dominio Usuario.
4. Dar de baja una Persona no elimina su cuenta ni registros históricos; una política determina cuándo suspender acceso.
5. Reactivar una cuenta exige revisión de roles; no se reinstalan automáticamente privilegios históricos.
6. Cambiar perfil no altera permisos efectivos; se impide autorización desde Angular.
7. Todas las transiciones y asignaciones sensibles se autorizan y auditan en Laravel.

## Escenarios a decidir

- Persona sin cuenta: permitido por principio.
- Persona con cuenta activa: una cuenta ordinaria como regla propuesta.
- Persona con cuenta suspendida: conservar vínculo e historial.
- Cuenta antigua sin Persona: plan de regularización, **sin eliminación automática**.
- Cuenta técnica: identidad de servicio y propiedad responsable distintas a la vinculación de persona física.
- Cambio de correo: no cambia el vínculo de Persona.
- Persona de baja y reingreso: revisión de elegibilidad y roles vigentes.

## Validaciones previas a desarrollo

- Revisar migraciones/modelos reales, claves foráneas y unicidad.
- Identificar estado de Persona y estado de Usuario; comprobar que sean independientes.
- Revisar sesiones y política de revocación en Laravel Sanctum.
- Revisar el resolvedor DENY/ALLOW y su respuesta a cambios de roles.
- Confirmar autorización y registro de auditoría para vincular/desvincular cuentas.
