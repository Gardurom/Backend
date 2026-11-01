# SIGA — Cierre técnico de auditoría de control de acceso

**Fecha:** 01/11/2026
**Origen:** Informe_Auditoria_SIGA_2026-10-29.md
**Dictamen:** CIERRE TÉCNICO CON RESERVAS DOCUMENTADAS
**Alcance:** controles de Personas, Usuarios, autorización, sesiones y operación actuales.

## Evidencia consolidada

- Backend: Gardurom/Backend, main, checkpoint inicial de cierre b14444e.
- Frontend: SysNayarit/frontend, main, checkpoint b3a90bf.
- F4: 657 pruebas Angular y compilación aprobadas; seis verificaciones T19/T20 reportadas satisfactoriamente en navegador.
- F5.1: 30/30 rutas API con autenticación, elegibilidad y sesión absoluta; ocho operaciones críticas verificadas.
- F5.3: siete rutas de respuesta a incidentes verificadas y registro de evento Laravel confirmado.
- F5.5: 130 pruebas unitarias aprobadas, 730 aserciones.
- F5.6: comando siga:verificar-operacion implementado, ejecutado y publicado.
- F5.7: monitoreo horario registrado en Windows; ejecución satisfactoria observada.
- F5.9: respaldo institucional siga_20261101_144506.backup generado en D:\proyecto_siga\respaldo, 95,702 bytes, 47 tablas y 24 entradas de datos.
- F5.10: huella SHA-256 comprobada e inventario y generación de SQL de recuperación aprobados.

## Matriz de hallazgos

| ID | Dictamen |
|---|---|
| H1 | Control implementado; certificación de autenticación real negativa pendiente. |
| H2 | Revocación implementada; certificación de baja y sesión preexistente pendiente. |
| H3 | Control administrativo implementado; reglas institucionales complementarias por ratificar. |
| H4 | Diferido a integración de vínculos institucionales; trabajo académico asignado a Manuel. |
| H5 | Validación de Persona ACTIVA implementada; prueba funcional negativa pendiente. |
| H6 | Administrador vinculado a Persona ACTIVA; recuperación de emergencia por formalizar. |
| H7 | Corrección frontend y verificaciones T19/T20 reportadas aprobadas. |

## POL-03

POL-03, versión 1.0, aprobada para operación del proyecto SIGA el 01/11/2026.
La ratificación normativa por autoridad institucional competente permanece pendiente.
La aprobación no altera la base de datos ni concede permisos nuevos.

## Reservas y condiciones de reapertura

1. La restauración del respaldo no se ha ejecutado en un entorno aislado autorizado. Su legibilidad y generación de SQL no demuestran restauración integral.
2. H4 requiere entrega, integración y pruebas de los vínculos institucionales correspondientes. No se exige inventar alumnos, docentes o relaciones inexistentes.
3. Las pruebas reales pendientes de H1, H2, H5 y de protección transaccional del último administrador requieren un entorno autorizado y aislado.
4. La ratificación institucional de POL-03, responsables, retención y procedimiento de recuperación permanece pendiente.
5. El monitoreo actual depende de una tarea de Windows asociada al usuario que mantiene la sesión iniciada; no equivale a supervisión de producción permanente.

## Decisión de cierre

Se concluye el trabajo técnico ejecutado en la auditoría de acceso y se conservan las reservas anteriores como pendientes verificables. No se declara certificación integral de seguridad, restauración probada ni liberación a producción.

No se han creado bases adicionales ni modificado cuentas o roles mediante este cierre documental.
