# SIGA - Fase 3 - F3-P01 - Conciliacion de vinculos H4

**Fecha:** 10/11/2026

**Estado:** INVENTARIO Y CONCILIACION DOCUMENTAL.

**Objetivo:** establecer la situacion real de los vinculos
institucionales y delimitar el trabajo pendiente del hallazgo H4.

Este documento no aprueba esquemas nuevos, no crea relaciones,
no modifica autorizaciones ni certifica operaciones inexistentes.

## 1. Fuentes de evidencia

- Inventario PostgreSQL F3-P01-A ejecutado contra la base siga.
- Conexion de consulta: siga_app.
- Transaccion: REPEATABLE READ, READ ONLY.
- Migraciones y modelos academicos publicados en Backend.
- Rutas del archivo routes/academic.php.
- Accion RegisterAcademicIdentity.
- Acuerdos funcionales docs/ACADEMICO.md.
- Cierre de auditoria con reservas del 01/11/2026 y su adenda.

No se utilizaron registros personales identificables en este informe.

## 2. Estructuras instaladas

| Relacion | Registros observados | Situacion |
|---|---:|---|
| institutional.persons | 12 | Identidades institucionales |
| system.users | 3 | Cuentas de acceso |
| system.user_roles | 11 | Asignaciones de roles |
| institutional.academic_participants | 0 | Estructura disponible |
| institutional.academic_trainers | 0 | Estructura disponible |
| institutional.academic_programs | 0 | Estructura disponible |
| institutional.academic_cohorts | 0 | Estructura disponible |
| institutional.academic_sites | 0 | Estructura disponible |
| institutional.academic_groups | 0 | Estructura disponible |
| institutional.academic_cohort_site_history | 0 | Estructura disponible |
| institutional.academic_group_site_history | 0 | Estructura disponible |

Las cantidades corresponden a la observacion F3-P01-A.
No constituyen garantia de que permanezcan iguales posteriormente.

## 3. Vinculos Persona y academia

Las tablas academic_participants y academic_trainers:

- Referencian institutional.persons mediante id_persona.
- Exigen referencia no nula.
- Establecen unicidad de Persona dentro de cada tipo.
- Admiten que una Persona participe en ambos contextos.
- No sustituyen el expediente central ni el identificador Usuario.

Resultados de la comprobacion:

- Participantes: 0.
- Formadores: 0.
- Referencias huerfanas: 0.
- Referencias duplicadas: 0.
- Personas presentes en ambos registros: 0.

El resultado cero no acredita comportamiento funcional sobre
registros reales, ya que ambas tablas se encuentran vacias.

## 4. Contratos academicos existentes

RegisterAcademicIdentity:

- Exige Persona existente y ACTIVA.
- Comprueba permiso efectivo del operador.
- Evita duplicar el registro del mismo tipo.
- Ejecuta registro y auditoria en una transaccion.
- No crea cuentas de Usuario automaticamente.

routes/academic.php:

- Protege las rutas con autenticacion y elegibilidad.
- Utiliza permisos academicos separados.
- Contiene consultas y altas de participantes y formadores.
- Incluye operaciones de programas, cohortes, sedes, grupos
  y cambios de adscripcion.

La existencia de una ruta o accion no acredita por si sola
que exista informacion operacional registrada.

## 5. Historial de sedes

Existen dos relaciones:

- academic_cohort_site_history.
- academic_group_site_history.

Ambas utilizan intervalos de vigencia y claves foraneas.
El codigo de migracion declara restricciones para impedir
vigencias superpuestas y varias adscripciones abiertas.

No se observaron movimientos registrados.

La adscripcion temporal de una cohorte o grupo a una sede
no equivale a la incorporacion de una Persona a ese grupo.

## 6. Ambitos pendientes de acreditar

### 6.1 Incorporacion de participantes

Se requiere un modelo de incorporacion que permita identificar:

- Persona y registro academico.
- Programa y generacion o escalon.
- Grupo y especializacion cuando corresponda.
- Fechas y estados de incorporacion.
- Historial de cambios.
- Matricula emitida segun acuerdos aprobados.
- Situacion academica separada de estado documental.

No se acredita todavia una relacion operativa de incorporacion
entre participantes, cohortes y grupos.

### 6.2 Asignaciones de formadores

Se requiere delimitar:

- Persona formadora.
- Funcion de docente o instructor.
- Actividad, materia o practica.
- Grupo, periodo y vigencia.
- Sustituciones y responsabilidad historica.
- Permiso efectivo y pertenencia al ambito.

Un registro de formador no concede autorizacion docente.

### 6.3 Contexto laboral

No se identificaron tablas laborales por los patrones
nominales del inventario ejecutado.

Pendiente confirmar el contrato institucional para:

- Persona trabajadora.
- Expediente personal o numero de trabajador.
- Adscripcion laboral y vigencias.
- Funciones laborales simultaneas.
- Vinculacion, cuando corresponda, con formador academico.

La ausencia en el inventario nominal no demuestra por si sola
que no exista ninguna estructura laboral bajo otro nombre.

### 6.4 Autorizacion contextual

Se necesita comprobar conjuntamente:

- Usuario autenticado y elegible.
- Permiso efectivo resuelto en Laravel.
- Precedencia DENY sobre ALLOW.
- Persona asociada a la participacion o funcion.
- Relacion y ambito institucional.
- Vigencia correspondiente a la operacion.

Elegir perfil, contexto, matricula o numero de trabajador
no puede conceder permisos automaticamente.

## 7. Acceso a metadatos

El rol siga_app no puede consultar system.migrations.

Las tablas academicas fueron observadas directamente.
No se requiere conceder acceso al historial de migraciones
para subsanar H4.

Se detecto la tabla system.academic_role_provisioning_audit
sin privilegio SELECT para siga_app.

No se solicitan nuevas concesiones sobre esa bitacora.

## 8. Contratos por integrar

Antes de crear nuevos objetos se debera:

1. Conciliar el trabajo academico de ambos repositorios.
2. Identificar tablas y relaciones que ya esten implementadas.
3. Definir contratos de incorporacion e historial.
4. Definir asignaciones temporales de formadores.
5. Concretar el modelo laboral sin duplicar Persona.
6. Especificar permisos y ambitos para cada operacion.
7. Definir pruebas aisladas sin alterar datos institucionales.
8. Someter migraciones y nuevas concesiones a autorizacion.

No se generaran Personas, usuarios, matriculas, trabajadores
ni adscripciones ficticias para cubrir los datos faltantes.

## 9. Criterios de aceptacion de H4

H4 podra considerarse tecnicamente subsanado cuando exista:

- Vinculacion institucional documentada e implementada.
- Integridad referencial comprobable.
- Vigencias y cambios historicos controlados.
- Autorizacion efectiva independiente de los perfiles.
- Rechazo de acceso fuera de ambito o vigencia.
- Auditoria de operaciones institucionales.
- Evidencia funcional autorizada sobre los contratos aplicables.
- Integracion backend/frontend confirmada.

La existencia de tablas vacias no satisface por si sola
estos criterios.

## 10. Dictamen F3-P01

**F3-P01-A:** inventario de estructuras ejecutado y aprobado.

**F3-P01-B:** conciliacion documental preparada con base en
inventario y contratos existentes.

**H4:** ABIERTO - pendiente diseno, integracion y certificacion
funcional de los vinculos institucionales aplicables.

**Continuidad propuesta:** F3-P02 - contrato de incorporaciones
academicas, funciones temporales y ambitos de autorizacion,
coordinado con los desarrollos academicos existentes.

No se declara certificacion integral de seguridad ni
liberacion a produccion.
