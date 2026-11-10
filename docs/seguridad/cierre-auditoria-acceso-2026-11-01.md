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

## Adenda de evidencias - 2026-11-10

**Objeto:** incorporar las intervenciones USR-01 a USR-04 sin
modificar el dictamen del acta del 01/11/2026.

**Estado documental:** CIERRE TECNICO CON RESERVAS DOCUMENTADAS.
No constituye certificacion integral de seguridad ni liberacion
a produccion.

### USR-01 - Consulta y concentracion de roles

- Consulta HTTP autenticada de 3 usuarios habilitados.
- 3 consultas individuales de roles con respuesta HTTP 200.
- 0 inconsistencias entre listado y detalle de roles.
- 0 catalogos invalidos.
- 1 advertencia preventiva de concentracion superior al 60 %.
- Logout HTTP 204.
- No se modificaron roles.
- La verificacion visual final en navegador Angular es evidencia separada.

### USR-02 - Separacion de facultades

Permisos independientes:

1. usuarios.asignar_roles
2. usuarios.retirar_roles
3. usuarios.retirar_roles_masivamente

- El permiso nuevo es usuarios.retirar_roles.
- Se concedio exclusivamente a ROL_ADMIN_SISTEMA, efecto ALLOW.
- Las autorizaciones anteriores se conservaron.
- 12 pruebas Angular aprobadas.
- Compilacion Angular aprobada.
- Ruta DELETE protegida con nuevo permiso y reautenticacion.
- Migracion aplicada:
  2026_11_10_172829_separate_usr02_role_removal.php
- Conciliacion de 3 permisos y 3 reglas administrativas ALLOW.
- 11 asignaciones de roles conservadas.
- 81 eventos de auditoria conservados.
- Respaldo previo:
  USR-02_matriz_20261110_173005.sql
- Commit Backend: fb688d990a5261f230d6fa1acb677999d61bcd09
- Commit Frontend: 30c56a55b26072cf79c355d2a17b7073a51cfa22

### USR-03 - Administracion de perfiles

- Autorizacion administrativa usuarios.asignar_perfiles validada.
- 1 regla ALLOW para ROL_ADMIN_SISTEMA.
- Login, consulta autorizada, rechazo de solicitud invalida
  y logout verificados.
- La solicitud invalida no modifico las asignaciones.
- No se ejecutaron altas ni bajas reales de perfiles.
- Migracion publicada:
  2026_11_10_160502_grant_usr03_profiles_to_admin.php
- Commit Backend: 8d9bb68f1d951f0978666020d2f31fb4e10036e0

### USR-04 - Verificacion operativa

- Comando siga:verificar-operacion fortalecido y publicado.
- 6 controles operativos aprobados.
- Persona-Usuario: 0 no elegibles, 0 versiones invalidas
  y 0 referencias de Persona ausentes.
- Sesiones: 3 autenticadas, 3 invitadas,
  0 cuentas no elegibles.
- Perfiles: 0 asignaciones huerfanas y
  0 predeterminados invalidos.
- Auditoria: 81 eventos, 0 actores huerfanos
  y 0 usuarios sin bitacora.
- No se inspecciono el contenido cifrado de sesiones.
- Commit Backend: 150205bffdeb9ed35d8f51e528ab9c297a2b003c

### Reservas que permanecen

Se mantienen sin declararlas subsanadas automaticamente:

- H1, H2 y H5: certificaciones funcionales negativas pendientes
  en un entorno expresamente autorizado.
- H4: vinculos institucionales y concesiones vigentes pendientes
  de integracion y comprobacion.
- H6: formalizacion de recuperacion de emergencia y evidencia
  transaccional aplicable.
- Restauracion real del respaldo en entorno aislado autorizado.
- Ratificacion normativa institucional de POL-03.
- Condiciones y responsabilidades del monitoreo operativo.
- Advertencia de concentracion de roles para revision humana.
- Pruebas reales de operaciones de perfiles no autorizadas
  en esta intervencion.

### Continuidad: Fase 3 - F3-P01

Objeto: inventariar y conciliar vinculos institucionales y sus
concesiones vigentes, de acuerdo con H4.

Principios:

- Persona conserva la identidad institucional unica.
- Usuario conserva la autenticacion interactiva.
- Los vinculos academicos y laborales son independientes.
- Una Persona puede tener funciones simultaneas.
- Matricula, expediente academico y numero de trabajador no
  sustituyen a Persona ni son credenciales de acceso.
- Seleccionar contexto o perfil no concede permisos.
- Laravel verifica permiso efectivo, relacion, ambito y vigencia.
- DENY prevalece sobre ALLOW.
- No se inventan relaciones o modulos aun no entregados.
- No se crean bases adicionales ni cuentas automaticamente.

Primer entregable F3-P01:
inventario de tablas, contratos y relaciones ya implementadas;
identificacion de ausencias reales y dependencias de integracion.

### Dictamen de esta adenda

Las intervenciones USR-01 a USR-04 quedan documentadas en sus
alcances ejecutados. Se mantienen las reservas del cierre original.
La continuidad hacia Fase 3 no equivale a certificacion integral
de Fase 2 ni a liberacion productiva.
