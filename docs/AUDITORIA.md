# SIGA — Auditoría

## 1. Propósito

Este documento describe el mecanismo de auditoría actualmente implementado en el Sistema Integral de Gestión Académica (SIGA).

La auditoría tiene como objetivo conservar evidencia de las operaciones relevantes realizadas sobre las entidades del sistema, identificando:

- usuario responsable;
- entidad afectada;
- registro afectado;
- acción realizada;
- valores anteriores;
- valores nuevos;
- campos modificados;
- fecha de la actividad.

La implementación actual utiliza:

`system.activities`

## 2. Principio general

SIGA separa la información operativa de la evidencia de auditoría.

Ejemplo actual:

```text
institutional.persons
        │
        │ operación
        ▼
acción de aplicación
        │
        ├── modifica Persona
        │
        └── registra evidencia
                 │
                 ▼
        system.activities