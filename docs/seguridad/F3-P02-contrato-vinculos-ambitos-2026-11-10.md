# SIGA - Fase 3 - F3-P02
# Contrato de vinculos institucionales y autorizacion contextual

**Fecha:** 10/11/2026
**Estado:** PROPUESTA TECNICA PARA INTEGRACION.
**Hallazgo relacionado:** H4 - Vinculos institucionales.
**Base institucional:** siga.

Este documento especifica contratos logicos de integracion.
No aprueba nombres fisicos de tablas, migraciones ni permisos
adicionales. No modifica las decisiones funcionales vigentes.

## 1. Objetivo

Integrar las relaciones academicas y laborales de una Persona
con las operaciones autorizadas de SIGA, respetando:

- Identidad institucional unica.
- Autenticacion exclusiva por Usuario.
- Contextos institucionales independientes.
- Autorizacion efectiva comprobada en Laravel.
- Alcance y vigencia de cada relacion.
- Historial y auditoria de cambios.

H4 permanece abierto hasta disponer de implementacion,
integracion y evidencias funcionales autorizadas.

## 2. Fuentes institucionales

Documentos y contratos de referencia:

- docs/ACADEMICO.md.
- docs/seguridad/F3-P01-H4-conciliacion-2026-11-10.md.
- docs/seguridad/cierre-auditoria-acceso-2026-11-01.md.
- routes/academic.php.
- RegisterAcademicIdentity.
- PermissionResolver.

Las decisiones aprobadas en ACADEMICO.md prevalecen sobre
propuestas tecnicas que no hayan sido ratificadas.

## 3. Arquitectura de identidad

### 3.1 Persona

institutional.persons es la identidad central.

Una Persona puede existir sin Usuario y puede mantener
simultaneamente relaciones academicas y laborales.

No se crearan Personas duplicadas por cada funcion.

### 3.2 Usuario

system.users representa la cuenta de autenticacion.

Reglas:

- El correo institucional y la contrasena autentican.
- La matricula no es un identificador de acceso.
- El expediente central no es una credencial.
- El numero de trabajador no es un alias de login.
- Registrar una relacion no crea Usuario automaticamente.
- Una reincorporacion no habilita por si sola la cuenta.

Los controles de elegibilidad Persona-Usuario permanecen
como condicion previa para operar.

### 3.3 Roles y perfiles

Los roles y permisos son fuente de autorizacion.

Los perfiles organizan la experiencia y el contexto visible.

Seleccionar un perfil o contexto nunca:

- Concede permisos.
- Elimina un DENY.
- Habilita una cuenta.
- Omite una restriccion de acceso.
- Amplia un ambito institucional.

## 4. Entidades existentes y comprobadas

Se observaron en la base institucional:

- institutional.persons: 12 registros.
- system.users: 3 registros.
- system.user_roles: 11 registros.
- institutional.academic_participants: 0 registros.
- institutional.academic_trainers: 0 registros.
- institutional.academic_programs: 0 registros.
- institutional.academic_cohorts: 0 registros.
- institutional.academic_sites: 0 registros.
- institutional.academic_groups: 0 registros.
- Historiales de adscripcion de cohortes y grupos: 0.

Las cantidades corresponden al inventario F3-P01-A,
no representan estados permanentes.

Las tablas de participantes y formadores ya mantienen
referencias a Persona y unicidad dentro de cada tipo.

Estas entidades se reutilizaran, sin crear duplicados.

## 5. Contrato logico de incorporacion academica

Entidad conceptual: INCORPORACION_ACADEMICA.

Finalidad: representar una incorporacion concreta de un
participante a una generacion o escalon de un programa.

Relaciones minimas propuestas:

- Participante academico existente.
- Programa academico.
- Cohorte, generacion o escalon.
- Expediente academico de esa incorporacion.
- Estado documental.
- Situacion academica.
- Vigencia y antecedentes administrativos.
- Resolucion de confirmacion o reingreso, cuando corresponda.

Una Persona podra tener varias incorporaciones historicas.

No se utilizaran los cambios de grupo como sustituto
del registro de incorporacion.

### 5.1 Matricula

Se conservaran las decisiones M01 a M09:

- Una matricula por incorporacion.
- Formula: programa + numero de generacion/escalon +
  anio de inicio + tipo de participante + consecutivo.
- Tipo C: cadete; tipo I: integrante de mandos.
- Consecutivo minimo de tres digitos y ampliable.
- Emision inicial conjunta, atomica e idempotente.
- Altas posteriores consumen el siguiente consecutivo.
- Las matriculas historicas nunca se reutilizan.
- Las emisiones previas se conservan literalmente.

No se fija aqui una serializacion nueva ni se generan
ejemplos que puedan confundirse con matriculas oficiales.

### 5.2 Incorporacion y reingreso

Antes de confirmar, una incorporacion es propuesta
sin matricula emitida.

El reingreso requiere resolucion administrativa individual
y no restablece automaticamente estudios, roles o acceso.

El estado academico y el estado de Persona son independientes.

La baja academica no equivale a baja institucional de Persona.

## 6. Contrato logico de adscripcion de participantes

Entidad conceptual: ADSCRIPCION_ACADEMICA.

Relaciones previstas:

- Incorporacion vigente.
- Grupo de la cohorte correspondiente.
- Fecha de inicio y termino.
- Historial de cambios.
- Especializacion, cuando proceda.

Reglas:

- La especializacion campo/gabinete se asigna
  individualmente antes de conformar grupos.
- Un cambio de sede de cohorte es independiente
  de un cambio de sede de grupo.
- Las adscripciones previas conservan su historial.
- Las actividades anteriores conservan el contexto
  que correspondia en ese momento.
- No se presupone que trasladar un grupo cambia
  automaticamente la incorporacion de una Persona.

Se deben resolver las cardinalidades, reglas de simultaneidad
y conflictos de vigencia antes de aprobar un esquema fisico.

## 7. Contrato logico de funciones de formadores

Entidad conceptual: ASIGNACION_FUNCIONAL_ACADEMICA.

Una Persona registrada como formadora puede cumplir
simultaneamente funciones de:

- Docente.
- Instructor.
- Coordinador.
- Responsable disciplinario.

La asignacion funcional debera conservar:

- Persona o registro formador.
- Funcion desempenada.
- Materia, practica o responsabilidad, cuando aplique.
- Grupo, cohorte, programa y periodo correspondientes.
- Inicio y fin de vigencia.
- Antecedente de sustitucion.
- Responsable historico de actividades y evaluaciones.

La funcion nominal no concede permisos.

El formador solo podra operar cuando coincidan
autorizacion, asignacion, ambito y vigencia.

El nuevo asignado no adquirira automaticamente la
facultad de modificar antecedentes de su predecesor.

La vinculacion de personal interno o externo
no concede acceso por si misma.

## 8. Contrato logico laboral

Entidad conceptual: VINCULACION_LABORAL.

Estado: PENDIENTE DE CONTRATO FISICO Y CONCILIACION.

Elementos por definir con la autoridad correspondiente:

- Persona institucional.
- Expediente personal o numero de trabajador.
- Organizacion, puesto y adscripcion laboral.
- Vigencias.
- Tipo de vinculacion.
- Historial de cambios.
- Relacion con funciones docentes o administrativas.

El expediente laboral coexistira con los identificadores
academicos y con la cuenta Usuario.

No se duplicaran Personas cuando un trabajador
tambien sea docente, instructor o participante academico.

La busqueda inicial no identifico tablas laborales por
nombres habituales, pero esto no acredita su ausencia
absoluta bajo otros nombres.

Ningun nombre fisico nuevo queda aprobado por este documento.

## 9. Autorizacion contextual

Cada operacion protegida debe comprobar en Laravel:

1. Usuario autenticado.
2. Sesion valida y elegibilidad de acceso.
3. Permiso efectivo especifico.
4. Ausencia de DENY aplicable.
5. Relacion Persona-contexto.
6. Ambito institucional asignado.
7. Vigencia del vinculo.
8. Pertenencia real del objeto sobre el que se opera.
9. Reautenticacion reciente cuando la operacion la requiera.
10. Auditoria y trazabilidad de la operacion autorizada.

La autorizacion de interfaz no sustituye a Laravel.

Un permiso ALLOW sin vinculacion vigente y alcance
suficiente no autoriza una operacion contextual.

### 9.1 Ambitos

Conservar las decisiones E03 y S01:

- Ambitos delimitados por programa, cohorte y sede.
- Exigir coincidencia de todas las dimensiones asignadas.
- Si solo una dimension es aplicable, comprobar esa dimension.
- No interpretar dimensiones ausentes como autorizacion global.

Los mecanismos fisicos para asignar estos ambitos
requieren contrato independiente y aprobacion.

### 9.2 Separacion de administracion

Se conservan las facultades USR-02:

- usuarios.asignar_roles.
- usuarios.retirar_roles.
- usuarios.retirar_roles_masivamente.

Ninguna operacion academica debe usar estas facultades
como permiso implicito de administracion contextual.

## 10. Auditoria e historial

Cada operacion relevante debera distinguir:

- Actor autenticado.
- Persona o registro institucional afectado.
- Tipo de operacion.
- Vinculo y ambito.
- Estado anterior y nuevo cuando proceda.
- Fecha efectiva y fecha de registro.
- Resultado.
- Referencia a resolucion o autorizacion cuando aplique.

La modificacion de negocio y su auditoria
deberan mantener atomicidad cuando corresponda.

No se registraran contrasenas, sesiones, tokens
ni otros secretos en la auditoria.

No se presupone la existencia de acciones nuevas
admitidas por system.activities.

## 11. Contratos de API pendientes

Antes de agregar rutas debera documentarse:

- Operacion y entidad afectada.
- Metodo HTTP.
- Validacion de entrada.
- Permiso exigido.
- Ambito institucional.
- Reautenticacion cuando corresponda.
- Respuesta y errores controlados.
- Auditoria.
- Concurrencia e idempotencia.
- Efecto en el historial.

No se publican ni se reservan rutas nuevas
mediante este documento.

## 12. Evidencias que debera aportar la integracion

### Integridad

- Una identidad Persona por individuo.
- Incorporaciones vinculadas a participantes validos.
- No duplicar matriculas ni reutilizar consecutivos.
- No superponer adscripciones incompatibles.
- Conservar historial de movimientos y sustituciones.

### Seguridad

- Rechazar Usuario no elegible.
- Rechazar permiso ausente.
- Aplicar DENY sobre ALLOW.
- Rechazar operaciones fuera de ambito.
- Rechazar operaciones fuera de vigencia.
- No conceder permisos al cambiar contexto.
- Conservar protecciones del rol Administrador del Sistema.

### Operacion

- Incorporacion y confirmacion idempotentes.
- Emision de matriculas atomica.
- Historial de bajas y reingresos.
- Correcta separacion entre estado documental,
  situacion academica y estado de acceso.
- Auditoria del actor real.
- Integracion Angular-Laravel confirmada.

Las pruebas futuras no ejecutaran operaciones de escritura
sobre siga sin autorizacion institucional expresa.

## 13. Integracion y coordinacion

La integracion debera:

- Reutilizar las tablas y contratos existentes.
- Evitar duplicar trabajo de las ramas academicas.
- Revisar los cambios antes de cualquier fusion.
- Conservar los controles Persona-Usuario ya auditados.
- Entregar evidencia por contrato, no solo por pantalla.
- Mantener H4 abierto hasta su verificacion funcional.

Las ramas feature/modulo-estudiantes fueron observadas
en ambos repositorios durante la revision de esta fecha.
No se identificaron pull requests abiertos en ese momento.

Esto es una observacion puntual y no implica ausencia
de trabajo local o de avances posteriores.

## 14. Secuencia propuesta

F3-P02-A:
Contrato documental de vinculos y autorizacion contextual.

F3-P02-B:
Conciliacion del trabajo academico disponible con el contrato.

F3-P02-C:
Diseno fisico y contratos API de las entidades faltantes,
sujetos a autorizacion institucional.

F3-P02-D:
Integracion y validacion aislada de reglas de H4.

F3-P02-E:
Verificacion institucional autorizada y cierre condicionado.

Cada etapa requiere evidencias propias y no sustituye
la ratificacion pendiente de politicas institucionales.

## 15. Dictamen

**Contrato tecnico:** PROPUESTO PARA INTEGRACION.

**Infraestructura academica base:** INSTALADA,
segun inventario F3-P01-A.

**Vinculos operativos de incorporacion y funciones:**
PENDIENTES DE INTEGRACION Y ACREDITACION.

**Modelo laboral:** PENDIENTE DE CONCILIACION Y DISENO.

**H4:** ABIERTO.

Este contrato no constituye aprobacion de migraciones,
concesion de permisos, certificacion integral de seguridad
ni autorizacion de puesta en produccion.
