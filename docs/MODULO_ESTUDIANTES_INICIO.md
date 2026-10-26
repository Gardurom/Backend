# SIGA - Guía de inicio del módulo Estudiantes

## 1. Objetivo

Desarrollar un módulo de gestión de estudiantes independiente,
modular, escalable y compatible con la arquitectura de SIGA.

Rama de trabajo:
feature/modulo-estudiantes

Backend:
https://github.com/Gardurom/Backend

Frontend:
https://github.com/SysNayarit/frontend

## 2. Base compartida

- Laravel 13 y PostgreSQL/PostGIS.
- Angular 22 y Tailwind CSS 4.
- Laravel Sanctum para autenticación.
- institutional.persons para identidad personal.
- system.users para cuentas de acceso.
- Roles y permisos para autorización.
- Perfiles exclusivamente para contexto y experiencia de usuario.

No duplicar Personas, Usuarios ni sistemas de autenticación.

Una Persona puede tener relaciones académicas diferentes.

## 3. Arquitectura Backend

Organizar el dominio Estudiantes mediante componentes independientes:

- Modelos.
- Acciones o servicios de aplicación.
- Validaciones.
- Controladores API.
- Rutas.
- Pruebas unitarias, funcionales y de instalación.

Reutilizar las estructuras compartidas existentes.

Crear nuevas migraciones versionadas cuando sean necesarias.

No modificar migraciones históricas.

No ejecutar cambios manuales en PostgreSQL.

## 4. Arquitectura Frontend

Aplicar Atomic Design:

- atoms
- molecules
- organisms
- templates
- pages

El módulo debe tener navegación propia y servicios independientes.

Utilizar Angular, Tailwind CSS y las convenciones del repositorio.

Evitar CSS y componentes no utilizados.

No modificar componentes del módulo Profesores.

## 5. Seguridad

- La autorización real se verifica en Laravel.
- El frontend no es fuente de autorización.
- Los perfiles no conceden permisos.
- DENY prevalece sobre ALLOW.
- Validar permisos en todas las operaciones protegidas.
- Validar acceso a registros propios cuando corresponda.
- No almacenar contraseñas ni credenciales en Git.

## 6. Alcance inicial propuesto

Diseñar antes de implementar:

- Identificación del estudiante.
- Relación con Persona.
- Matrícula o identificador académico.
- Situación académica.
- Alta y consulta de estudiantes.
- Actualización de datos propios del dominio.
- Baja o cambio de estado académico.
- Listado, búsqueda y paginación.

Las reglas institucionales, campos definitivos y
permisos deben acordarse antes de crear tablas.

No asumir que todos los estudiantes necesitan cuenta de acceso.

## 7. Coordinación

El equipo de Estudiantes trabaja exclusivamente en:

feature/modulo-estudiantes

El equipo de Profesores trabaja exclusivamente en:

feature/modulo-profesores

No realizar commits directamente sobre main.

Integrar mediante Pull Requests revisados.

Coordinar previamente cualquier modificación de:

- Personas.
- Usuarios.
- Autenticación.
- Roles y permisos compartidos.
- Navegación general.
- Configuración global.
- Esquema o migraciones compartidas.

## 8. Pruebas y aceptación

Agregar pruebas para cada operación nueva.

Verificar:

- Autenticación y autorización.
- Validación de datos.
- Relaciones con Persona.
- Restricciones de unicidad.
- Transacciones.
- Auditoría cuando corresponda.
- Respuestas de API.
- Pruebas de componentes Angular.
- Build de producción.

No incorporar cambios a main si la regresión falla.

## 9. Punto de partida

Backend base: 334cb17
Frontend base: e27eb39

Base compartida verificada antes de la separación:

- Backend: 455 pruebas aprobadas.
- Frontend: 290 pruebas aprobadas.
- Build Angular correcto.

Estos resultados son de la base compartida,
no del módulo Estudiantes todavía.

## 10. Primera actividad del compañero

Revisar las estructuras existentes de Persona,
Usuario, autorización y navegación.

Presentar la propuesta de entidades y operaciones
del módulo Estudiantes antes de implementar migraciones.

La propuesta debe ser compatible con Profesores y
evitar duplicaciones de datos institucionales.