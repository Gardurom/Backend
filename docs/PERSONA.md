# SIGA — Persona

Última actualización: 2026-10-08

## 1. Propósito

Persona es el núcleo de identidad institucional de SIGA.

No existe un borrador canónico separado: se registra una Persona únicamente cuando la operación cumple las reglas vigentes.

## 2. Tabla

`institutional.persons`

Clave primaria:

`id_persona uuid`

Generación:

`uuidv7()`

## 3. Campos actuales principales

- `curp`;
- `rfc`;
- `num_expediente`;
- `nombres`;
- `apellido_paterno`;
- `apellido_materno`;
- `fecha_nacimiento`;
- `sexo`;
- `estado_civil`;
- `correo_institucional`;
- `correo_personal`;
- `id_pais_origen`;
- `id_pais_nacimiento`;
- `id_territorio_nacimiento`;
- `fecha_alta`;
- `fecha_baja`;
- `estatus`;
- timestamps.

La fotografía se mantiene como necesidad funcional de archivos y su integración con LibreFS sigue pendiente; no existe como columna binaria en la tabla actual.

## 4. Dominios

Sexo:

- `MASCULINO`;
- `FEMENINO`;
- NULL.

Estado civil:

- `SOLTERO`;
- `CASADO`;
- NULL.

Estatus:

- `ACTIVO`;
- `INACTIVO`;
- `SUSPENDIDO`;
- `BAJA`.

Si `estatus=BAJA`, `fecha_baja` debe existir.

Si el estatus no es BAJA, `fecha_baja` debe ser NULL.

## 5. Integridad

- expediente único;
- CURP único cuando existe;
- RFC único cuando existe;
- correo institucional único de forma normalizada;
- territorio de nacimiento requiere país;
- FKs de país/territorio;
- fecha de nacimiento no futura;
- fecha de baja no anterior a fecha de alta.

## 6. Acciones

- `RegisterPerson`;
- `FindPerson`;
- `UpdatePerson`;
- `WithdrawPerson`;
- `ReinstatePerson`.

Las operaciones de modificación registran auditoría cuando corresponde.

## 7. API

- POST `/api/personas`;
- GET `/api/personas/{id_persona}`;
- PATCH `/api/personas/{id_persona}`;
- POST `/api/personas/{id_persona}/baja`;
- POST `/api/personas/{id_persona}/reingreso`.

Todas las operaciones requieren:

- `auth:sanctum`;
- permiso específico de Persona.

## 8. Datos descartados

No forman parte de Persona salvo nueva decisión formal:

- huella dactilar;
- firma autógrafa digitalizada;
- campo género.

## 9. Usuario

Persona puede tener como máximo un User asociado.

El usuario es la identidad de acceso; Persona es la identidad institucional.

## 10. Pruebas

Se prueban:

- dominios;
- unicidad;
- fechas;
- ciclo de vida;
- transacciones;
- rollback;
- API;
- autorización;
- auditoría;
- relación con User.
