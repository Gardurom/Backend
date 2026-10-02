# SIGA — Base de datos

## 1. Propósito

Este documento describe la arquitectura de base de datos actualmente implementada para el Sistema Integral de Gestión Académica (SIGA).

Su contenido se basa en las migraciones existentes en el repositorio y documenta:

- motor de base de datos;
- bases utilizadas;
- esquemas;
- roles;
- tablas;
- claves;
- relaciones;
- restricciones;
- índices;
- secuencias;
- funciones;
- permisos;
- principios de integridad;
- mecanismos de mínimo privilegio.

La documentación debe mantenerse alineada con las migraciones y las pruebas de instalación.

## 2. Tecnología

Motor principal:

- PostgreSQL 18.4.

Extensión geoespacial disponible:

- PostGIS.

Base principal:

`SIGA`

Nombre efectivo:

`siga`

Base utilizada para pruebas de instalación y funcionales:

`siga_installation_test`

## 3. Principios de diseño

La base de datos de SIGA mantiene actualmente los siguientes principios:

- separación de objetos mediante esquemas;
- propiedad estructural separada de la ejecución de aplicación;
- mínimo privilegio;
- integridad reforzada mediante PostgreSQL;
- uso de claves foráneas;
- restricciones `CHECK`;
- generación de identificadores en base de datos cuando corresponde;
- transacciones;
- baja lógica en lugar de eliminación física de Persona;
- auditoría separada;
- permisos explícitos;
- uso de funciones controladas para operaciones internas sensibles.

## 4. Roles PostgreSQL

### 4.1 `siga_owner`

Rol propietario de objetos.

Características confirmadas:

- `LOGIN=false`;
- propietario de la base y de objetos estructurales;
- no es utilizado directamente por Laravel durante operación normal.

Las migraciones estructurales utilizan:

```text
SET ROLE siga_owner
...
RESET ROLE