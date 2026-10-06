# Checkpoint de diseño: Usuarios, Roles, Perfiles y Autenticación

**Proyecto:** Sistema Integral de Gestión Académica (SIGA)  
**Fecha del checkpoint:** 2026-10-06  
**Estado:** Propuesta aceptada con adecuaciones para la arquitectura actual de SIGA  
**Base técnica verificada al iniciar este checkpoint:** commit `4724c5a` (`feat: agregar relaciones de usuarios y roles`)

---

## 1. Objetivo

Este documento consolida y adecua la propuesta de usuarios, roles, perfiles y autenticación para SIGA.

La propuesta original se adopta como base conceptual, pero se ajusta para:

- respetar la arquitectura ya implementada en Laravel 13 y PostgreSQL;
- mantener separación entre identidad institucional y autorización;
- aplicar mínimo privilegio y separación de funciones;
- evitar duplicidad entre rol, perfil y permiso;
- permitir múltiples roles de acceso por usuario;
- mantener trazabilidad de cambios;
- evolucionar la autenticación por fases sin adelantar todavía Passkeys/WebAuthn;
- alinear la política de contraseñas con prácticas actuales de seguridad.

Este checkpoint es una **decisión de diseño**. No implica que todos los roles, perfiles, permisos, módulos o controles descritos estén ya implementados.

---

## 2. Decisión principal: identidad institucional y acceso son conceptos distintos

SIGA mantendrá separados los siguientes conceptos.

### 2.1 Persona

`institutional.persons` representa la identidad institucional registrada.

La Persona conserva la información de identidad y datos personales correspondiente al dominio institucional.

La condición institucional, función académica o vínculo con la institución no debe utilizarse por sí solo como mecanismo de autorización técnica.

Ejemplos de condiciones o vínculos institucionales que podrán modelarse en los módulos correspondientes:

- cadete/alumno;
- instructor/docente;
- personal administrativo;
- directivo;
- externo/aspirante.

La definición definitiva de estas condiciones pertenece al dominio institucional y académico, no al subsistema de autenticación.

### 2.2 Usuario

`system.users` representa la cuenta utilizada para autenticarse en SIGA.

La relación vigente es:

```text
institutional.persons
        1
        │
        │ 0..1
        ▼
system.users
```

Una Persona puede existir sin cuenta de acceso. Una cuenta debe vincularse, cuando corresponda, con una Persona existente.

### 2.3 Rol de acceso

Un rol responde a la pregunta:

> ¿Qué función de acceso puede ejercer este usuario dentro de SIGA?

Los roles pertenecen al esquema `system` y no deben confundirse con la condición institucional de la Persona.

La arquitectura ya implementada permite múltiples roles por usuario:

```text
system.users
      │
      │ N:M
      ▼
system.user_roles
      ▲
      │
system.roles
```

Esta decisión permite escenarios válidos como un instructor que también ejerce temporalmente funciones de coordinación, sin duplicar cuentas.

---

## 3. Estado técnico ya implementado

Al momento de este checkpoint existen y están probados:

### 3.1 `system.roles`

Campos actuales:

- `id`;
- `name`;
- `code` único;
- `created_at`;
- `updated_at`.

El rol de base de datos `siga_app` posee acceso de solo lectura al catálogo de roles.

### 3.2 `system.user_roles`

Tabla puente N:M con:

- `user_id`;
- `role_id`;
- `created_at`;
- `updated_at`;
- clave primaria compuesta `(user_id, role_id)`;
- FK a `system.users(id)`;
- FK a `system.roles(id)`.

Permisos actuales de `siga_app`:

- SELECT: permitido;
- INSERT: permitido;
- UPDATE: denegado;
- DELETE: permitido.

La ausencia de UPDATE es deliberada: una asignación de rol se agrega o se elimina; no se transforma en otra asignación.

### 3.3 Relaciones Eloquent

Están implementadas:

- `User::roles()`;
- `Role::users()`.

---

## 4. Rol, permiso y perfil

Se adopta una separación explícita para evitar ambigüedad.

### 4.1 Rol

Agrupa una responsabilidad funcional de acceso.

Ejemplos propuestos:

- `ROL_CADETE`;
- `ROL_INSTRUCTOR`;
- `ROL_TUTOR`;
- `ROL_CAPTURISTA`;
- `ROL_SECRETARIA_ACAD`;
- `ROL_SECRETARIA_GEN`;
- `ROL_JEFE_ACADEMIA`;
- `ROL_CONTROL_ESCOLAR`;
- `ROL_SERVICIOS_BENEFICIOS`;
- `ROL_BIBLIOTECARIO`;
- `ROL_RECURSOS_HUMANOS`;
- `ROL_RECTOR`;
- `ROL_VICERRECTOR`;
- `ROL_DIRECTOR_ACADEMIA`;
- `ROL_ADMIN_SISTEMA`;
- `ROL_AUDITOR`;
- `ROL_ASPIRANTE`.

Este catálogo se acepta como **catálogo objetivo sujeto a validación por módulo**. No se insertará completo en la base únicamente por aparecer en este documento.

Cada rol deberá incorporarse cuando exista un caso de uso real, permisos definidos y pruebas de autorización.

### 4.2 Permiso

El permiso será la unidad técnica que autoriza una acción concreta.

Ejemplos futuros:

- `personas.ver`;
- `personas.crear`;
- `personas.actualizar`;
- `personas.baja`;
- `personas.reingreso`;
- `usuarios.crear`;
- `usuarios.asignar_roles`;
- `auditoria.ver`.

Regla:

> Los permisos se asignarán a roles; no directamente a Personas.

La asociación rol-permiso será diseñada en un bloque posterior.

### 4.3 Perfil

En la propuesta original, el perfil agrupa pantallas, acciones y alcance.

Para SIGA se conserva el término **perfil** únicamente como una agrupación funcional o de experiencia de usuario, por ejemplo:

- landing;
- menú visible;
- módulos habilitados;
- presentación de funciones;
- alcance mostrado en la interfaz.

El perfil **no será una segunda fuente autónoma de autorización**.

La seguridad del backend deberá decidirse con roles, permisos, políticas y alcance de datos. Ocultar una opción del menú no constituye control de seguridad.

---

## 5. Alcance de datos

Un permiso por sí solo no siempre es suficiente.

SIGA deberá distinguir el alcance sobre el cual puede ejercerse una acción. Se conservan como categorías de diseño:

- propio;
- grupo o materia asignada;
- tutorados asignados;
- academia asignada;
- área asignada;
- global.

Ejemplo:

`personas.ver` con alcance propio no equivale a `personas.ver` con alcance global.

El alcance deberá resolverse en políticas, consultas y relaciones de dominio, evitando confiar en parámetros enviados por el cliente.

---

## 6. Principios transversales aceptados

Se adoptan los siguientes principios de la propuesta:

1. **Mínimo privilegio.** Cada usuario recibe solo los accesos necesarios.
2. **Separación de funciones.** Quien captura no necesariamente valida; quien valida no debe convertirse automáticamente en auditor.
3. **Múltiples roles.** Un usuario puede tener N roles cuando exista justificación funcional.
4. **Autorización en backend.** Menús, botones o pantallas no sustituyen las verificaciones del servidor.
5. **Auditoría.** Las operaciones sensibles deben conservar usuario ejecutor, fecha y datos necesarios para trazabilidad.
6. **Asignación explícita.** La condición institucional no otorga automáticamente un rol de acceso.
7. **Revocación.** Los roles deben poder retirarse sin eliminar la identidad de la Persona ni su cuenta.
8. **Revisión periódica.** Las asignaciones y permisos deberán revisarse de forma periódica conforme a la política institucional.
9. **Delegación temporal.** Se reconoce como requisito futuro, pero no se implementará hasta definir vigencia, autorización y auditoría.
10. **Compatibilidad de roles.** La coexistencia de roles estará permitida; los conflictos por separación de funciones deberán tratarse explícitamente y no resolverse únicamente sumando privilegios.

### Ajuste importante

La propuesta original indicaba que los permisos de roles simultáneos “se suman y nunca se restan”.

Para SIGA esto se modifica:

> La combinación de roles podrá ampliar permisos solo cuando no exista una regla explícita de incompatibilidad, separación de funciones, restricción de alcance o prohibición superior.

Esto evita que la acumulación de roles invalide controles de seguridad.

---

## 7. Catálogo funcional propuesto

El catálogo completo del documento original se conserva como referencia objetivo, con los siguientes alcances resumidos.

| Rol | Función principal | Alcance típico |
|---|---|---|
| ROL_CADETE | Portal personal académico | Propio |
| ROL_INSTRUCTOR | Docencia y grupos asignados | Grupos/materias |
| ROL_TUTOR | Seguimiento tutorial | Tutorados |
| ROL_CAPTURISTA | Captura operativa | Academia asignada |
| ROL_SECRETARIA_ACAD | Gestión académica de academia | Academia |
| ROL_SECRETARIA_GEN | Gestión institucional transversal | Global restringido |
| ROL_JEFE_ACADEMIA | Coordinación académica | Academia |
| ROL_CONTROL_ESCOLAR | Expediente y control escolar | Global académico |
| ROL_SERVICIOS_BENEFICIOS | Servicios y beneficios | Módulo específico |
| ROL_BIBLIOTECARIO | Biblioteca | Biblioteca |
| ROL_RECURSOS_HUMANOS | Expediente laboral y RH | RH |
| ROL_RECTOR | Dirección y aprobaciones | Global |
| ROL_VICERRECTOR | Dirección por área | Área asignada |
| ROL_DIRECTOR_ACADEMIA | Dirección de academia | Academia |
| ROL_ADMIN_SISTEMA | Administración técnica | Técnico |
| ROL_AUDITOR | Auditoría y consulta | Global lectura |
| ROL_ASPIRANTE | Proceso de reclutamiento | Propio y temporal |

### Regla para `ROL_ADMIN_SISTEMA`

El administrador técnico no debe convertirse por defecto en superusuario funcional.

Administrar cuentas, parámetros, integraciones o permisos no implica derecho automático a consultar o modificar expedientes académicos, datos de servicios o información sensible de negocio.

El acceso excepcional de soporte deberá quedar justificado y auditado.

---

## 8. Roles iniciales para la Fase 1

No se poblará todavía el catálogo completo.

Antes de insertar roles se elaborará la matriz:

```text
ROL
  ↓
PERMISOS
  ↓
ALCANCE
  ↓
POLÍTICA / RESTRICCIÓN
  ↓
PRUEBA DE AUTORIZACIÓN
```

La Fase 1 priorizará exclusivamente los roles necesarios para proteger los módulos ya existentes:

- Persona;
- Usuario;
- administración de roles;
- auditoría.

La selección exacta de los primeros roles se aprobará en un checkpoint posterior.

---

## 9. Autenticación actual y evolución

### 9.1 Fase vigente

La autenticación actual de SIGA utiliza Laravel Sanctum con sesión/cookie para la aplicación web.

No existe registro público.

La creación de usuarios se mantiene como una operación interna controlada.

### 9.2 MFA / Passkeys

La propuesta considera MFA obligatorio para funciones sensibles.

Se acepta como dirección de seguridad, pero su implementación queda fuera de la Fase 1 actual.

Passkeys/WebAuthn y la administración de credenciales se mantienen como mejora posterior, una vez concluido el primer módulo funcional completo.

Hasta entonces, no deberán introducirse flujos parciales de passwordless que compliquen el modelo de autenticación actual.

---

## 10. Política de contraseñas adecuada

La tabla original proponía reglas distintas de complejidad y caducidad por tipo de usuario.

SIGA adopta una política más actual y uniforme.

### 10.1 Longitud

Mientras la contraseña opere como factor único, la longitud mínima objetivo será de **15 caracteres**.

El sistema deberá permitir contraseñas o frases de contraseña de al menos **64 caracteres** de longitud máxima soportada.

Cuando en el futuro una contraseña forme parte de un proceso MFA, la política podrá revisarse conforme al nivel de aseguramiento requerido.

### 10.2 Sin reglas artificiales de composición

No se impondrá obligatoriamente una mezcla de:

- mayúsculas;
- minúsculas;
- números;
- símbolos.

Se priorizará longitud y resistencia frente a contraseñas comunes o comprometidas.

### 10.3 Sin caducidad periódica arbitraria

No se obligará a cambiar la contraseña cada 45, 60, 90, 120 o 180 días únicamente por tiempo transcurrido.

Se forzará cambio cuando:

- exista evidencia de compromiso;
- una contraseña temporal deba sustituirse;
- un administrador autorizado ejecute un restablecimiento por causa justificada;
- una política institucional o normativa aplicable exija expresamente una acción compatible con el análisis de riesgo.

### 10.4 Lista de contraseñas no aceptables

Deberá rechazarse el uso de valores:

- comunes;
- previsibles;
- conocidos como comprometidos;
- relacionados de forma trivial con SIGA o con la institución;
- derivados de datos personales obvios cuando puedan identificarse razonablemente.

### 10.5 Recuperación

No se utilizarán preguntas de seguridad como factor de recuperación.

La recuperación deberá utilizar mecanismos verificables, tokens de corta vigencia y canales institucionales aprobados.

El correo electrónico podrá servir como canal para recuperación de cuenta cuando el flujo haya sido diseñado para ello, pero no deberá confundirse con un segundo factor de autenticación.

### 10.6 Almacenamiento

Las contraseñas nunca se almacenarán en texto plano.

Laravel deberá usar su mecanismo configurado de hash seguro. La selección y evolución del algoritmo se mantendrá centralizada en la configuración del framework y no mediante cifrado artesanal dentro de modelos o controladores.

---

## 11. Protección frente a intentos de acceso

Se conserva el requisito de controlar intentos fallidos, pero no se fija en este checkpoint una regla rígida de “5 intentos/15 minutos y 10 hasta desbloqueo”.

La implementación deberá incluir:

- rate limiting;
- protección frente a fuerza bruta;
- registro de eventos relevantes;
- recuperación segura;
- mecanismos que eviten convertir el bloqueo de cuentas en una vía sencilla de denegación de servicio.

El umbral concreto deberá probarse y documentarse cuando se implemente el control de login.

---

## 12. Sesiones

Se acepta como requisito futuro que el riesgo del rol influya en los controles de sesión.

Sin embargo, no se fija todavía una tabla definitiva de timeouts por rol.

La política deberá considerar:

- inactividad;
- cierre explícito;
- invalidación de sesión;
- elevación de privilegios para operaciones sensibles;
- concurrencia para roles de alto riesgo;
- eventos de seguridad;
- MFA cuando corresponda.

La opción “recordarme” no deberá habilitarse automáticamente para ningún rol sin análisis específico.

---

## 13. Auditoría

Se mantiene la arquitectura actual de auditoría de SIGA.

La tabla `system.activities` no incorporará un campo `motivo`, salvo que esa decisión sea reabierta explícitamente.

Las acciones críticas de autorización deberán generar evidencia suficiente para responder:

- quién realizó la acción;
- cuándo;
- sobre qué entidad;
- qué acción realizó;
- qué cambió cuando corresponda.

La IP o información del dispositivo se incorporará únicamente cuando el caso de uso, la política de seguridad y la protección de datos personales lo justifiquen.

---

## 14. Decisiones que NO se toman todavía

Este checkpoint no autoriza aún:

- poblar todos los roles del catálogo;
- crear todos los perfiles;
- crear automáticamente permisos por cada menú;
- exponer CRUD público de usuarios;
- permitir a cualquier usuario asignar roles;
- implementar MFA;
- implementar Passkeys/WebAuthn;
- implementar delegación temporal;
- implementar condición institucional dentro de `system.roles`;
- introducir reglas de negocio académicas aún no modeladas.

Cada punto deberá incorporarse mediante un bloque funcional con pruebas.

---

## 15. Próximo bloque recomendado

La siguiente etapa será diseñar el catálogo técnico de permisos y la matriz inicial de autorización para los módulos que ya existen.

Orden recomendado:

```text
1. Definir permisos atómicos.
2. Definir los roles mínimos de la Fase 1.
3. Relacionar roles ↔ permisos.
4. Probar la autorización.
5. Proteger endpoints de Persona.
6. Proteger creación y administración de usuarios.
7. Auditar asignación/revocación de roles.
8. Solo después ampliar el catálogo funcional.
```

---

## 16. Referencias de seguridad

- NIST SP 800-63B-4, *Digital Identity Guidelines: Authentication and Authenticator Management*, julio de 2025.
- NIST Digital Identity Guidelines Implementation Resources, requisitos de contraseñas de SP 800-63B-4.

Estas referencias se utilizan para adecuar la política de contraseñas y autenticación; no sustituyen la normativa institucional o gubernamental aplicable a SIGA.

---

## 17. Decisión del checkpoint

**ACEPTADO CON ADECUACIONES.**

Se conserva de la propuesta original:

- separación entre identidad y acceso;
- múltiples roles por usuario;
- mínimo privilegio;
- separación de funciones;
- catálogo funcional de roles como objetivo;
- alcance de datos;
- auditoría;
- controles reforzados para roles sensibles.

Se adecua:

- Perfil pasa a ser agrupación funcional/UX y no una fuente autónoma de autorización.
- Los permisos serán atómicos y se asignarán a roles.
- La acumulación de roles estará sujeta a incompatibilidades y restricciones.
- La política de contraseñas elimina caducidad periódica y reglas obligatorias de composición.
- Las preguntas de seguridad se descartan.
- MFA/Passkeys se mantienen como evolución posterior, no como implementación inmediata.
- El catálogo completo de roles se validará e incorporará de forma incremental.

Este checkpoint queda como base para continuar el diseño de autorización de SIGA.
