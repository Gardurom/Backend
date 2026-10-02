# SIGA — Pruebas y calidad

## 1. Propósito

Este documento describe la estrategia de pruebas y los controles de calidad actualmente implementados en el backend del Sistema Integral de Gestión Académica (SIGA).

El objetivo es asegurar que los cambios del sistema puedan verificarse de forma repetible y trazable antes de considerarse terminados.

La estrategia actual contempla:

- pruebas Unit;
- pruebas Feature;
- pruebas de instalación;
- pruebas funcionales;
- pruebas HTTP funcionales;
- PostgreSQL real para las pruebas que requieren infraestructura;
- aislamiento mediante transacciones;
- comprobación de permisos;
- comprobación de funciones y secuencias;
- pruebas del núcleo Persona;
- pruebas de auditoría;
- pruebas de autenticación;
- análisis de dependencias;
- revisión de estilo.

## 2. Herramientas

Las principales herramientas de pruebas y calidad configuradas actualmente son:

```text
PHPUnit
Laravel testing
Laravel Pint
Composer
PostgreSQL