# SIGA — Documentación técnica

Última actualización: 2026-10-08

## 1. Propósito

Este directorio conserva la documentación técnica viva de SIGA y forma parte de la base de requisitos, diseño y validación del sistema.

La documentación no es decorativa: debe consultarse antes de diseñar o implementar cambios relevantes.

## 2. Tipos de fuente

### Decisiones vivas

`DECISIONES_TECNICAS.md`

Gobierna las decisiones técnicas y funcionales vigentes.

### Documentos vivos por dominio

- `ARQUITECTURA_TECNICA.md`;
- `BASE_DATOS.md`;
- `PERSONA.md`;
- `AUDITORIA.md`;
- `AUTENTICACION_SEGURIDAD.md`;
- `PRUEBAS_Y_CALIDAD.md`.

Definen requisitos, límites, reglas y criterios de aceptación de cada área.

### Estado

`ESTADO_SIGA.md`

Indica qué está realmente publicado en `main`.

### Checkpoints

Congelan decisiones y estado de una fecha concreta.

Son históricos: no se reescriben para incorporar cambios posteriores.

### Historial

`HISTORIAL_IMPLEMENTACION.md`

Resume hitos publicados.

## 3. Jerarquía para evitar conflictos

Antes de programar se debe consultar:

1. decisión explícita más reciente aprobada;
2. `DECISIONES_TECNICAS.md`;
3. documento vivo del dominio afectado;
4. `ESTADO_SIGA.md`;
5. código, migraciones y pruebas de `main`;
6. checkpoints e historial para contexto.

Código, migraciones y pruebas demuestran lo que está implementado.

Los documentos vivos definen lo que debe gobernar el diseño vigente.

Si existe una contradicción entre ambos, no se debe continuar ampliando esa parte hasta reconciliar la diferencia.

## 4. Estado general

Actualmente están publicados:

- núcleo Persona;
- autenticación Sanctum stateful;
- autorización Roles/Permisos;
- efectos ALLOW/DENY;
- protección de API Persona;
- catálogo y relaciones de perfiles;
- asignación de perfiles;
- desasignación de perfiles;
- perfil predeterminado;
- rate limiting específico del login;
- endurecimiento básico de sesión/cookies;
- timeout absoluto de sesión de 8 horas;
- gestión y revocación manual de sesiones propias.

Frontend Angular sigue pendiente.

## 5. Seguridad

La prioridad de SIGA es defensa en profundidad.

Antes de producción son obligatorios los controles descritos en:

`AUTENTICACION_SEGURIDAD.md`

Ya están implementados y probados:

- rate limiting específico del login;
- timeout de inactividad de 30 minutos;
- cifrado de sesión;
- `HttpOnly` y `SameSite=Lax`;
- salvaguarda que exige `Secure` para la cookie de sesión en producción;
- timeout absoluto de sesión de 8 horas;
- listado de sesiones activas propias con identificadores públicos opacos;
- revocación individual y masiva de otras sesiones propias conservando la actual;
- exclusión de sesiones vencidas por inactividad del listado activo.

Permanecen como controles obligatorios antes de producción:

- despliegue HTTPS/HSTS;
- reautenticación para operaciones sensibles;
- auditoría de autenticación y throttling;
- headers defensivos;
- MFA;
- Passkeys/WebAuthn.

## 6. Checkpoints vigentes como evidencia histórica

- `CHECKPOINT_USUARIOS_ROLES_2026-10-06.md`;
- `CHECKPOINT_MATRIZ_ROLES_FASE1_2026-10-06.md`;
- `CHECKPOINT_AUTORIZACION_PERSONA_2026-10-07.md`;
- `CHECKPOINT_SEGURIDAD_PERFILES_2026-10-08.md`.

Un checkpoint puede quedar desactualizado por diseño respecto del estado actual; eso no es un error si existe documentación viva posterior que registre el cambio.

## 7. Trazabilidad

Toda funcionalidad relevante debe seguir:

`necesidad -> requisito -> diseño -> implementación -> prueba -> evidencia -> aceptación`

## 8. Mantenimiento

Actualizar documentación viva cuando:

- cierre un bloque relevante;
- cambie una decisión;
- cambie arquitectura;
- se agregue seguridad;
- cambie estructura DB;
- se publique un hito.

No documentar trabajo local como terminado.

## 9. Gobierno de Git para documentación

- documentos técnicos: `main/docs`;
- no crear ramas ni Pull Requests sin autorización explícita previa;
- cambios documentales aprobados pueden integrarse directamente a `main`;
- verificar el commit remoto después de publicar.

## 10. Orden recomendado de lectura

1. `DECISIONES_TECNICAS.md`
2. `ESTADO_SIGA.md`
3. documento vivo del dominio afectado
4. `ARQUITECTURA_TECNICA.md`
5. `PRUEBAS_Y_CALIDAD.md`
6. checkpoint relacionado
7. `HISTORIAL_IMPLEMENTACION.md`
