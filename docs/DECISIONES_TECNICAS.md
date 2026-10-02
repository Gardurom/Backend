# SIGA — Decisiones técnicas y funcionales

## 1. Propósito

Este documento registra las decisiones técnicas, funcionales y de arquitectura adoptadas para el Sistema Integral de Gestión Académica (SIGA).

Su objetivo es evitar que decisiones ya tomadas se pierdan, se contradigan o se vuelvan a discutir accidentalmente durante fases posteriores del desarrollo.

Cada decisión indica su estado actual:

| Estado | Significado |
|---|---|
| `IMPLEMENTADA` | La decisión ya está reflejada en código, base de datos, configuración o infraestructura verificada. |
| `PARCIALMENTE IMPLEMENTADA` | Existe parte de la solución, pero quedan componentes pendientes. |
| `PLANIFICADA` | La decisión está aceptada, pero todavía no existe implementación completa. |
| `DESCARTADA` | Se decidió explícitamente no incorporar el elemento al diseño actual. |

Una decisión `PLANIFICADA` no debe documentarse en otros archivos como funcionalidad terminada.

---

## 2. DT-001 — PostgreSQL como base de datos principal

**Estado:** `IMPLEMENTADA`

SIGA utiliza PostgreSQL como sistema gestor principal.

Versión actualmente utilizada:

`PostgreSQL 18.4`

La aplicación Laravel se configura para utilizar:

`pgsql`

Las pruebas Installation y Functional utilizan PostgreSQL real cuando necesitan validar comportamiento específico del motor.

### Motivo

SIGA requiere características como:

- esquemas;
- roles;
- permisos granulares;
- `CHECK`;
- claves foráneas;
- `JSONB`;
- UUID;
- funciones PL/pgSQL;
- `SECURITY DEFINER`;
- PostGIS;
- control explícito de secuencias.

---

## 3. DT-002 — Separación mediante esquemas PostgreSQL

**Estado:** `IMPLEMENTADA`

Los objetos se separan actualmente en:

```text
system
institutional