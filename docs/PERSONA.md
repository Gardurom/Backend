# SIGA — Persona

## 1. Propósito

Este documento describe el núcleo de identidad Persona actualmente implementado en el Sistema Integral de Gestión Académica (SIGA).

Documenta:

- representación persistente;
- modelo Eloquent;
- campos modificables;
- generación de identidad;
- registro;
- actualización;
- baja;
- reingreso;
- estados;
- auditoría;
- comportamiento transaccional;
- reglas de integridad;
- decisiones funcionales relevantes;
- límites actuales de implementación.

La fuente principal para este documento está formada por:

- la migración de `institutional.persons`;
- `App\Models\Person`;
- las acciones de aplicación de Persona;
- las pruebas funcionales correspondientes.

## 2. Concepto

Persona representa el núcleo básico de identidad dentro de SIGA.

Actualmente se almacena en:

`institutional.persons`

El registro de Persona constituye una identidad persistente dentro de la plataforma.

No se implementa actualmente un estado de borrador.

La creación efectiva ocurre cuando la operación de registro se completa correctamente.

## 3. Identificador principal

Clave primaria:

`id_persona`

Tipo:

UUID

Generación:

`uuidv7()`

El identificador:

- no es proporcionado por el cliente;
- es generado por PostgreSQL;
- no es modificable mediante el modelo;
- se conserva durante baja y reingreso.

## 4. Número de expediente

Campo:

`num_expediente`

Formato:

`YYYY-NNNNNNNN`

Ejemplo conceptual:

`2026-00000001`

Se genera mediante:

`system.next_expediente_number()`

El expediente:

- se genera automáticamente;
- es único;
- no es `fillable`;
- no debe ser controlado por el cliente;
- se conserva durante actualización, baja y reingreso.

## 5. Modelo Eloquent

Modelo:

`App\Models\Person`

Tabla:

`institutional.persons`

Clave primaria:

`id_persona`

Configuración:

```php
protected $table = 'institutional.persons';

protected $primaryKey = 'id_persona';

public $incrementing = false;

protected $keyType = 'string';