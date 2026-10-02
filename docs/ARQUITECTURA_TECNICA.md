# SIGA — Arquitectura técnica

## 1. Propósito

Este documento describe la arquitectura técnica actual y prevista del Sistema Integral de Gestión Académica (SIGA).

La arquitectura se documenta separando:

- componentes implementados y validados;
- componentes configurados;
- componentes previstos;
- responsabilidades de cada capa;
- mecanismos de integración;
- principios de seguridad y trazabilidad.

Este documento debe reflejar el estado real del repositorio y de la infraestructura confirmada.

## 2. Estado de la arquitectura

### Implementado y validado

Actualmente SIGA cuenta con:

- backend Laravel;
- PostgreSQL como sistema de gestión de base de datos;
- separación lógica mediante esquemas PostgreSQL;
- roles especializados de base de datos;
- núcleo de Persona;
- auditoría transaccional;
- autenticación mediante Laravel Sanctum;
- sesiones almacenadas en PostgreSQL;
- protección CSRF;
- configuración CORS para futura SPA;
- pruebas Unit, Feature, Installation y Functional;
- GeoServer configurado para acceso geoespacial.

### Previsto

Todavía están pendientes:

- frontend Angular;
- integración Leaflet en frontend;
- API HTTP completa de Persona;
- módulos funcionales posteriores;
- integración definitiva con almacenamiento de archivos;
- despliegue en servidor productivo;
- Passkeys/WebAuthn.

## 3. Vista general

La arquitectura prevista de SIGA es:

```text
┌─────────────────────────────────────┐
│            Usuario SIGA             │
└──────────────────┬──────────────────┘
                   │
                   ▼
┌─────────────────────────────────────┐
│          Frontend Angular           │
│             [PREVISTO]              │
│                                     │
│  Interfaz de usuario                │
│  Leaflet para cartografía           │
└──────────────────┬──────────────────┘
                   │
                   │ HTTPS
                   │ sesión/cookie
                   │ CSRF
                   ▼
┌─────────────────────────────────────┐
│          Backend Laravel            │
│          [IMPLEMENTADO]             │
│                                     │
│  API HTTP                           │
│  Sanctum                            │
│  autenticación                      │
│  validación                         │
│  acciones de aplicación             │
│  transacciones                      │
│  auditoría                          │
└───────────┬───────────────┬─────────┘
            │               │
            │               │
            ▼               ▼
┌──────────────────────┐  ┌──────────────────────┐
│      PostgreSQL      │  │      GeoServer       │
│    [IMPLEMENTADO]    │  │    [CONFIGURADO]     │
│                      │  │                      │
│ system               │  │ Workspace: siga      │
│ institutional        │  │ PostGIS datastore    │
│ PostGIS              │  │                      │
└──────────────────────┘  └──────────────────────┘
            │
            ▼
┌─────────────────────────────────────┐
│       Almacenamiento de archivos    │
│             [PREVISTO]              │
│                                     │
│ libreFS para archivos asociados     │
│ como fotografía de Persona          │
└─────────────────────────────────────┘