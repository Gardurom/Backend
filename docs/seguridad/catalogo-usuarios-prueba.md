# Catálogo de usuarios de prueba de SIGA

**Versión:** 0.1
**Fecha:** 23 de octubre de 2026
**Estado:** Estrategia de pruebas aprobada; cuentas pendientes de creación
**Ámbito:** Desarrollo local exclusivamente

## 1. Objetivo

Disponer de usuarios controlados para verificar los módulos de SIGA,
sus restricciones de acceso y el comportamiento de roles y permisos.

Las cuentas de este catálogo son ficticias y no representan usuarios
institucionales reales.

## 2. Cuentas funcionales

| Clave | Correo | Rol o condición | Escenario |
|---|---|---|---|
| SIGA-DEV-001 | prueba.admin@ejemplo.test | ROL_ADMIN_SISTEMA | Administración |
| SIGA-DEV-002 | prueba.gestor@ejemplo.test | ROL_GESTOR_PERSONAS | Gestión de Personas |
| SIGA-DEV-003 | prueba.consulta@ejemplo.test | ROL_CONSULTA_PERSONAS | Consulta de Personas |
| SIGA-DEV-004 | prueba.auditor@ejemplo.test | ROL_AUDITOR | Auditoría |
| SIGA-DEV-005 | prueba.sinrol@ejemplo.test | Sin roles | Acceso denegado |
| SIGA-DEV-006 | prueba.multirrol@ejemplo.test | Configuración controlada | Conflictos ALLOW/DENY |

## 3. Contraseñas

Las cuentas funcionales pueden compartir una contraseña temporal,
exclusiva para desarrollo local, por decisión del proyecto.

La contraseña no debe registrarse en este documento, en Git,
en migraciones ni en archivos versionados.

Debe proporcionarse mediante una variable de entorno temporal
o un mecanismo seguro equivalente.

Las pruebas automatizadas de seguridad utilizarán credenciales
independientes cuando el escenario lo requiera.

## 4. Reglas obligatorias

- Crear estas cuentas únicamente en un ambiente de desarrollo
  identificado y autorizado.
- No utilizar información personal real.
- No asignar privilegios implícitos por nombre de usuario o perfil.
- Validar autorizaciones siempre en Laravel.
- Mantener DENY por encima de ALLOW.
- Evitar duplicar usuarios y asignaciones existentes.
- Impedir que el procedimiento se ejecute en producción.
- Mantener evidencia de pruebas funcionales y negativas.
- Deshabilitar o eliminar las cuentas antes de liberar el sistema
  en ambientes institucionales.
- No almacenar contraseñas en bitácoras o resultados de pruebas.

## 5. Cuenta heredada de validación

La cuenta admin@imail.com existe en desarrollo con el identificador 1.

Durante el diagnóstico del 23 de octubre de 2026 tenía los roles
ROL_ADMIN_SISTEMA y ROL_GESTOR_PERSONAS, sin Persona vinculada.

Se conserva temporalmente sin modificar sus credenciales ni roles.

No sustituye las pruebas individuales de autorización por rol.

## 6. Estado inicial del catálogo

Las seis cuentas propuestas se encontraron disponibles al realizar
el diagnóstico de lectura del 23 de octubre de 2026.

Su creación, asignación de roles y ejecución de pruebas siguen
pendientes.

## 7. Criterios de aceptación

- Cada cuenta existe una sola vez en el ambiente autorizado.
- Cada cuenta tiene exactamente los roles previstos para su escenario.
- La cuenta sin roles carece de permisos efectivos.
- Las acciones permitidas y denegadas coinciden con la matriz.
- Los permisos efectivos excluyen las reglas afectadas por DENY.
- La eliminación o desactivación de cuentas de prueba no afecta
  usuarios institucionales.
- El procedimiento de preparación no se ejecuta fuera de desarrollo.

## 8. Observaciones

La cuenta multirrol debe prepararse utilizando reglas controladas.
No se modificará la matriz institucional permanente exclusivamente
para fabricar una prueba de conflicto DENY/ALLOW.

La configuración local de Laravel y el nombre de la base de datos
no son, por sí solos, evidencia suficiente de aislamiento.
La creación de cuentas requiere validar el destino antes de escribir.
