# SIGA — Estado técnico del proyecto

Última actualización: 2026-10-02

## 1. Repositorio y ruta de trabajo

- Proyecto: SIGA
- Backend: `D:\proyecto_siga\SIGA\Backend`
- Rama principal: `main`
- Repositorio remoto: `https://github.com/Gardurom/Backend.git`
- Último commit confirmado:
  - `afbc4c1 feat: agregar autenticacion con Sanctum y sesiones`
- Árbol de trabajo confirmado limpio después del push.

## 2. Plataforma actual

- PHP: 8.5.6
- Laravel: 13.34.0
- Laravel Sanctum: 4.3.3
- PostgreSQL: 18.4
- Base principal: `siga`
- Base de pruebas funcionales/instalación: `siga_installation_test`
- PostgreSQL/PostGIS configurados.
- GeoServer 3.0.1 configurado con workspace `siga`.
- Frontend Angular todavía no creado en esta implementación.
- Leaflet previsto para el frontend.

## 3. Roles PostgreSQL

Roles principales:

- `siga_owner`
  - propietario de objetos
  - sin LOGIN

- `siga_migrator`
  - ejecuta migraciones
  - puede asumir `siga_owner`

- `siga_app`
  - rol de ejecución de la aplicación
  - mínimo privilegio

- `siga_readonly`
  - acceso de solo lectura

- `siga_geoserver`
  - acceso exclusivo para GeoServer

## 4. Esquemas principales

### `institutional`

Contiene actualmente:

- `institutional.countries`
- `institutional.territories`
- `institutional.persons`

### `system`

Contiene componentes de infraestructura, entre ellos:

- `system.users`
- `system.password_reset_tokens`
- `system.sessions`
- `system.cache`
- `system.cache_locks`
- `system.jobs`
- `system.job_batches`
- `system.failed_jobs`
- `system.counters`
- `system.activities`

También existe:

- `system.next_expediente_number()`

## 5. Persona

Modelo:

- `App\Models\Person`

Acciones implementadas:

- `RegisterPerson`
- `UpdatePerson`
- `WithdrawPerson`
- `ReinstatePerson`

Comportamiento confirmado:

- registro transaccional
- generación automática de expediente
- actualización auditada
- baja auditada
- reingreso auditado
- rollback si falla la auditoría
- conservación de identidad, expediente y fecha de alta

Estados admitidos actualmente:

- `ACTIVO`
- `INACTIVO`
- `SUSPENDIDO`
- `BAJA`

Para `BAJA`, `fecha_baja` debe ser consistente con el estado.

## 6. Auditoría

Tabla:

- `system.activities`

Acciones registradas:

- `CREACION`
- `ACTUALIZACION`
- `BAJA`
- `REINGRESO`

La aplicación puede:

- `SELECT`
- `INSERT`

La aplicación no puede:

- `UPDATE`
- `DELETE`

La auditoría identifica al usuario mediante `id_usuario`.

El campo `motivo` fue descartado por decisión del proyecto.

## 7. Autenticación

Implementado Laravel Sanctum para SPA mediante sesión/cookie.

No se utilizan actualmente Personal Access Tokens.

Componentes confirmados:

- `statefulApi()` activado
- `routes/api.php`
- `auth:sanctum`
- `/sanctum/csrf-cookie`
- `POST /login`
- `POST /logout`
- `GET /api/user`

Sesiones:

- driver: `database`
- tabla: `system.sessions`
- `SESSION_ENCRYPT=true`

CORS:

- configuración publicada en `config/cors.php`
- preparado para credenciales
- orígenes permitidos se definirán cuando exista el frontend real

Flujo actual:

`CSRF -> login -> sesión Laravel -> auth:sanctum -> API`

El usuario autenticado deberá proporcionar internamente el `id_usuario` para auditoría.

El cliente no deberá enviar ni controlar manualmente `id_usuario`.

## 8. Pruebas

Última validación global:

`composer test:all`

Resultado:

- Unit + Feature: 2 pruebas / 2 assertions
- Installation: 22 pruebas / 135 assertions
- Functional: 58 pruebas / 677 assertions

Total:

- 82 pruebas
- 814 assertions
- 0 fallos

Autenticación funcional validada:

- invitado recibe `401` en `/api/user`
- usuario autenticado recibe `200`
- Sanctum entrega cookie CSRF
- login válido autentica
- login inválido no autentica
- logout invalida la sesión

`composer audit`:

- sin vulnerabilidades conocidas al momento de esta actualización

## 9. Entornos

Entornos principales:

- `.env`
- `.env.example`
- `.env.installation`
- `.env.installation-migration`
- `.env.migration`

Aplicación:

- `.env`
- `.env.example`
- `.env.installation`

usan:

`SESSION_ENCRYPT=true`

Los archivos de migración mantienen su propósito exclusivo para `siga_migrator`.

## 10. Decisiones cerradas

No reabrir salvo necesidad técnica o decisión explícita:

- huella dactilar descartada
- firma digital/autógrafa digitalizada descartada
- género descartado
- sexo limitado a valores definidos por el proyecto
- estado civil limitado a valores definidos por el proyecto
- país/territorio ya cuenta con integridad suficiente
- no agregar validaciones adicionales de formato CURP/RFC por ahora
- Persona no maneja borradores
- auditoría no utiliza campo `motivo`
- Personal Access Tokens no se requieren para la SPA actual
- Passkeys/WebAuthn se posponen hasta después del primer módulo completo

## 11. Método de trabajo

- Windows PowerShell
- sin Docker
- un paso por vez
- validar resultado antes de continuar
- mínimo privilegio
- pruebas antes de integrar
- Pint antes del commit
- `composer test:all` antes del cierre de un bloque
- commit y push al finalizar cada bloque estable

## 12. Próximo bloque

Integrar Persona con la API HTTP protegida por Sanctum.

Primer objetivo previsto:

`POST /api/personas`

Debe:

- requerir `auth:sanctum`
- validar la petición
- obtener `id_usuario` exclusivamente del usuario autenticado
- ejecutar `RegisterPerson`
- registrar Persona y auditoría dentro de la misma transacción
- no aceptar `id_usuario` proporcionado por el cliente
- contar con pruebas HTTP funcionales antes de implementar la ruta

Después se incorporarán de forma controlada las operaciones de consulta, actualización, baja y reingreso.

---

Este archivo es un checkpoint técnico.

Debe actualizarse únicamente al cerrar bloques relevantes de desarrollo, no después de cada comando.