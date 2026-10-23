# Procedimientos administrativos de identidad y acceso

**Versión:** 0.1 · **Estado:** PROPUESTA · **Fecha:** 23/10/2026

Cada procedimiento requiere autorización de Laravel, manejo de errores, controles de concurrencia cuando correspondan y trazabilidad. Esta guía **no ejecuta** ninguno de los procedimientos.

## PR-01 — Crear o vincular Usuario humano

1. Operador autorizado busca Persona por criterios permitidos.
2. Sistema verifica que exista y que no tenga una cuenta ordinaria incompatible con la regla vigente.
3. Operador registra identificador de cuenta conforme a los mecanismos vigentes de autenticación.
4. Laravel valida autoridad y vínculo; se crea/vincula sin duplicar identidad.
5. Los roles se asignan mediante procedimiento independiente; no se conceden implícitamente.
6. Registrar evento y resultado; no almacenar credenciales en auditoría.

**Alternativas:** si existe una cuenta, mostrarla según permisos; si falta Persona, requerir su alta institucional; cuentas técnicas siguen un flujo separado.

## PR-02 — Asignar o revocar rol

1. Seleccionar Usuario y consultar asignaciones vigentes.
2. Mostrar solo roles administrables, sin tratar la lista de Angular como seguridad.
3. Mostrar vista previa de permisos resultantes, incluyendo `DENY`.
4. Laravel valida autoridad, ámbito y restricciones anti-escalamiento.
5. Aplicar cambio, auditar e invalidar contexto/caché de permisos cuando corresponda.
6. Probar acceso real a operaciones protegidas tras la asignación/revocación.

## PR-03 — Suspender o desactivar Usuario

1. Verificar solicitud y autoridad para ejecutarla.
2. Conservar la Persona y la cuenta para historial.
3. Impedir futuras autenticaciones y revocar sesiones según política aprobada.
4. Registrar actor, fecha, estado anterior y nuevo estado.
5. Verificar que solicitudes posteriores no puedan usar permisos revocados.

## PR-04 — Reactivar Usuario

1. Revisar elegibilidad institucional y vínculo con Persona.
2. Revalidar identificación, estado y responsable de autorización.
3. Revisar roles y permisos desde cero; **no restaurar automáticamente** roles antiguos.
4. Reactivar bajo controles vigentes, auditar y verificar acceso autorizado.

## PR-05 — Baja y reingreso de Persona

1. Registrar la baja/reingreso en el dominio Persona conforme a sus reglas.
2. Evaluar, mediante política formal, su impacto sobre la cuenta vinculada.
3. Cuando la baja retire legitimación de acceso: suspender usuario y revocar sesiones.
4. En reingreso: revisar cuenta, roles, unidad/ámbito y responsable, sin concesiones automáticas.
5. Registrar cada cambio en su dominio, evitando confundir baja de Persona con borrado de Usuario.

## PR-06 — Revisar y auditar autorizaciones

1. Identificar cuenta, roles asignados y permisos efectivos consultados en Laravel.
2. Revisar conflictos `ALLOW`/`DENY`, asignaciones privilegiadas y excepciones.
3. Verificar trazabilidad de actor, objeto, operación, fecha y resultado.
4. No incluir contraseñas, tokens ni secretos en registros o exportaciones.
5. Conservar evidencia de revisión y correcciones autorizadas.

## Respuestas de aplicación

| Situación | Respuesta esperada del frontend | Verificación real |
|---|---|---|
| HTTP 401 | Sesión inválida; dirigir a acceso y limpiar contexto | Laravel |
| HTTP 403 | Acceso denegado sin presumir cierre de sesión | Laravel |
| HTTP 0 / 5xx | Problema temporal; bloquear navegación no verificada y permitir reintento | Laravel al recuperarse |
| HTTP 423 en operación sensible | Informar reautenticación requerida, sin reintento automático | Laravel |

## Condiciones para poner en marcha

- Aprobar formalmente políticas POL-01 a POL-10 pertinentes.
- Verificar modelo de datos, servicios y endpoints existentes.
- Implementar pruebas de permisos, denegación, sesiones y trazabilidad.
- Revisar funciones de administradores y segregación de responsabilidades.
- No desplegar nuevas operaciones administrativas sin evaluación de seguridad.
