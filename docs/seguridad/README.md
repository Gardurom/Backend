# Seguridad, identidad y autorizaciones de SIGA

**Versión documental:** 0.1
**Fecha:** 23 de octubre de 2026
**Estado:** PROPUESTA — pendiente de revisión y aprobación institucional
**Alcance:** Backend Laravel, PostgreSQL, frontend Angular y procesos administrativos asociados.

## Objetivo

Centralizar las políticas y decisiones propuestas sobre identidad de Personas, cuentas de Usuario, roles, permisos, perfiles y su auditoría. Este conjunto **no acredita por sí mismo que los controles estén implementados**.

## Documentos

1. [Políticas de identidad y acceso](politicas-identidad-y-acceso.md) — catálogo POL-01 a POL-10.
2. [Modelo Persona–Usuario](modelo-persona-usuario.md) — identidad, vinculación y estados.
3. [Matriz de roles y permisos](matriz-roles-permisos.md) — plantilla y reglas de resolución.
4. [Procedimientos administrativos](procedimientos-administrativos.md) — flujos operativos y controles.

## Principios acordados

- `institutional.persons` representa la identidad institucional; `system.users`, la cuenta de acceso.
- Una Persona no requiere necesariamente una cuenta de Usuario.
- Los **roles y permisos** son la única fuente de autorización; el **perfil** solo organiza la experiencia de usuario.
- Toda operación protegida se autoriza en Laravel; los guards y menús de Angular no son barreras de seguridad.
- `DENY` tiene precedencia sobre `ALLOW`; en ausencia de concesión se deniega.
- No realizar migraciones, modificaciones de datos institucionales o creación automática de usuarios a partir de esta documentación.

## Estados documentales

- **Propuesta:** regla descrita, no aprobada.
- **Aprobada:** autorizada por responsables institucionales; requiere evidencia de aprobación.
- **Implementada:** código, esquema y controles identificados.
- **Verificada:** existen pruebas/evidencias vigentes de comportamiento.

La clasificación es independiente por política. No se deduce «implementada» de que una funcionalidad similar exista.

## Antes de implementación

- Inventariar el esquema real, claves, restricciones y modelos de `institutional.persons`, `system.users` y relaciones de roles/permisos/perfiles.
- Revisar `PermissionResolver`, endpoints, middleware, pruebas y mecanismos de auditoría existentes.
- Definir autoridad de aprobación, excepciones y tratamiento de cuentas técnicas.
- Convertir cada política aprobada en criterios de aceptación y pruebas.
