# SIGA — Documentación técnica

## 1. Propósito

Este directorio contiene la documentación técnica del Sistema Integral de Gestión Académica (SIGA).

Su objetivo es conservar de forma estructurada:

- el estado actual del proyecto;
- la arquitectura técnica;
- el diseño de base de datos;
- las reglas implementadas para Persona;
- la auditoría;
- la autenticación y seguridad;
- las pruebas y controles de calidad;
- las decisiones técnicas adoptadas;
- y el historial de implementación.

La documentación debe mantenerse alineada con el código, las migraciones, las pruebas y los commits del repositorio.

## 2. Estado actual

El backend de SIGA se encuentra actualmente en desarrollo sobre:

- PHP 8.5.6;
- Laravel 13.34.0;
- Laravel Sanctum 4.3.3;
- PostgreSQL 18.4;
- PostGIS;
- GeoServer 3.0.1.

El frontend Angular todavía no ha sido creado en esta implementación.

Leaflet está previsto para la integración cartográfica del frontend.

La autenticación SPA mediante Laravel Sanctum, sesión, cookies y CSRF ya fue incorporada y validada.

El siguiente bloque de desarrollo corresponde a la API HTTP de Persona protegida mediante `auth:sanctum`.

## 3. Documentos

### `ESTADO_SIGA.md`

Checkpoint técnico actual del proyecto.

Contiene:

- versiones;
- componentes terminados;
- último commit relevante;
- resultados de pruebas;
- decisiones cerradas;
- siguiente bloque de trabajo.

Debe mantenerse compacto.

### `ARQUITECTURA_TECNICA.md`

Describe la arquitectura general de SIGA:

- backend Laravel;
- frontend previsto;
- PostgreSQL;
- GeoServer;
- separación de responsabilidades;
- flujo de aplicación;
- integración de componentes;
- principios técnicos.

### `BASE_DATOS.md`

Documenta la arquitectura PostgreSQL:

- base de datos;
- esquemas;
- roles;
- permisos;
- tablas;
- secuencias;
- funciones;
- migraciones;
- principio de mínimo privilegio.

### `PERSONA.md`

Documenta el núcleo de identidad Persona:

- estructura;
- campos;
- reglas de integridad;
- estados;
- modelo Eloquent;
- acciones de aplicación;
- registro;
- actualización;
- baja;
- reingreso;
- comportamiento transaccional.

### `AUDITORIA.md`

Describe el mecanismo de auditoría de SIGA:

- tabla `system.activities`;
- acciones auditables;
- relación con usuarios;
- datos anteriores y nuevos;
- campos modificados;
- inmutabilidad;
- permisos;
- comportamiento transaccional.

### `AUTENTICACION_SEGURIDAD.md`

Documenta la autenticación y controles de seguridad:

- Laravel Sanctum;
- autenticación SPA;
- sesiones;
- cookies;
- CSRF;
- CORS;
- login;
- logout;
- `auth:sanctum`;
- cifrado de sesión;
- origen de `id_usuario`;
- decisiones relacionadas con Passkeys/WebAuthn.

### `PRUEBAS_Y_CALIDAD.md`

Describe la estrategia de pruebas y calidad:

- Unit;
- Feature;
- Installation;
- Functional;
- entornos de prueba;
- PostgreSQL de instalación;
- transacciones reversibles;
- Laravel Pint;
- Composer Audit;
- resultados confirmados.

### `HISTORIAL_IMPLEMENTACION.md`

Conserva la cronología técnica de la implementación:

- bloques desarrollados;
- decisiones relevantes;
- commits;
- cambios estructurales;
- hitos alcanzados.

No sustituye el historial de Git.

### `DECISIONES_TECNICAS.md`

Registra decisiones técnicas y funcionales que no deben reabrirse sin una razón explícita.

Entre ellas:

- estructura de roles PostgreSQL;
- mínimo privilegio;
- estrategia de auditoría;
- tratamiento de Persona;
- datos descartados;
- Sanctum para SPA;
- no uso actual de Personal Access Tokens;
- Passkeys/WebAuthn diferidos;
- estrategia de pruebas;
- método de trabajo.

## 4. Orden recomendado de lectura

Para conocer rápidamente el proyecto:

1. `ESTADO_SIGA.md`
2. `ARQUITECTURA_TECNICA.md`
3. `BASE_DATOS.md`
4. `PERSONA.md`
5. `AUDITORIA.md`
6. `AUTENTICACION_SEGURIDAD.md`
7. `PRUEBAS_Y_CALIDAD.md`
8. `DECISIONES_TECNICAS.md`
9. `HISTORIAL_IMPLEMENTACION.md`

## 5. Principio de trazabilidad

SIGA debe mantener trazabilidad entre:

`necesidad → requisito → diseño → implementación → prueba → aceptación`

Cuando resulte aplicable, cada documento debe relacionar:

- decisión o necesidad;
- componente implementado;
- archivo o migración;
- prueba que lo valida;
- commit relacionado.

## 6. Reglas de mantenimiento documental

La documentación debe actualizarse cuando:

- se cierre un bloque relevante;
- cambie la arquitectura;
- se agregue una migración estructural;
- se modifique una regla de negocio;
- cambie una decisión técnica;
- se incorpore un componente de seguridad;
- cambien los resultados de pruebas relevantes.

No debe actualizarse después de cada comando.

`ESTADO_SIGA.md` debe conservarse como resumen operativo y no convertirse en documentación extensa.

## 7. Convenciones del proyecto

El desarrollo de SIGA mantiene actualmente las siguientes prácticas:

- Windows PowerShell;
- sin Docker;
- un paso por vez;
- validación antes de continuar;
- mínimo privilegio;
- migraciones con rol especializado;
- pruebas antes de integrar;
- Pint antes del commit;
- `composer test:all` al cerrar bloques relevantes;
- commit y push después de validar un bloque estable.

## 8. Fuente de verdad

Ante una diferencia entre documentación y código, deben revisarse conjuntamente:

- migraciones;
- modelos;
- acciones;
- configuración;
- pruebas;
- historial Git.

La documentación debe corregirse para reflejar el estado efectivamente implementado y validado.

---

Este índice forma parte de la documentación técnica viva de SIGA.