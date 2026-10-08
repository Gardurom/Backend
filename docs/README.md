# SIGA — Documentación técnica

Última actualización: 2026-10-08

## 1. Propósito

Este directorio conserva la documentación técnica viva de SIGA.

La fuente de verdad se obtiene conjuntamente de:

- código;
- migraciones;
- pruebas;
- configuración;
- commits;
- documentación aprobada.

## 2. Documentos principales

### ESTADO_SIGA.md

Resumen operativo del estado publicado.

### ARQUITECTURA_TECNICA.md

Arquitectura general, capas, responsabilidades e integraciones.

### BASE_DATOS.md

PostgreSQL, esquemas, roles, privilegios y objetos principales.

### PERSONA.md

Núcleo de identidad institucional.

### AUDITORIA.md

Auditoría transaccional, inmutabilidad y evolución prevista.

### AUTENTICACION_SEGURIDAD.md

Sanctum, sesiones, CSRF, cookies y plan obligatorio de endurecimiento.

### PRUEBAS_Y_CALIDAD.md

Estrategia TDD, suites, aislamiento y criterios de cierre.

### DECISIONES_TECNICAS.md

Registro de decisiones vigentes y su estado.

### HISTORIAL_IMPLEMENTACION.md

Hitos técnicos ya publicados.

## 3. Checkpoints

Checkpoints relevantes:

- `CHECKPOINT_USUARIOS_ROLES_2026-10-06.md`;
- `CHECKPOINT_MATRIZ_ROLES_FASE1_2026-10-06.md`;
- `CHECKPOINT_AUTORIZACION_PERSONA_2026-10-07.md`;
- `CHECKPOINT_SEGURIDAD_PERFILES_2026-10-08.md`.

Los checkpoints congelan decisiones de una fecha concreta y no sustituyen al estado vivo.

## 4. Estado general

Actualmente están publicados:

- núcleo Persona;
- autenticación Sanctum stateful;
- autorización Roles/Permisos;
- efectos ALLOW/DENY;
- protección de API Persona;
- catálogo y relaciones de perfiles;
- asignación y perfil predeterminado.

Frontend Angular sigue pendiente.

## 5. Seguridad

La prioridad de SIGA es defensa en profundidad.

Antes de producción son obligatorios los controles descritos en:

`AUTENTICACION_SEGURIDAD.md`

incluyendo:

- HTTPS/HSTS;
- rate limiting;
- sesiones endurecidas;
- auditoría de autenticación;
- headers defensivos;
- MFA;
- Passkeys/WebAuthn.

## 6. Trazabilidad

Toda funcionalidad relevante debe seguir:

`necesidad -> requisito -> diseño -> implementación -> prueba -> evidencia -> aceptación`

## 7. Mantenimiento

Actualizar documentación cuando:

- cierre un bloque relevante;
- cambie una decisión;
- cambie arquitectura;
- se agregue seguridad;
- cambie estructura DB;
- se publique un hito.

No documentar trabajo local como terminado.

## 8. Orden recomendado de lectura

1. `ESTADO_SIGA.md`
2. `ARQUITECTURA_TECNICA.md`
3. `BASE_DATOS.md`
4. `PERSONA.md`
5. `AUTENTICACION_SEGURIDAD.md`
6. `AUDITORIA.md`
7. `PRUEBAS_Y_CALIDAD.md`
8. `DECISIONES_TECNICAS.md`
9. `HISTORIAL_IMPLEMENTACION.md`
10. checkpoint más reciente
