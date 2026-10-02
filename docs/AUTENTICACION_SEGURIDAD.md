# SIGA — Autenticación y seguridad

## 1. Propósito

Este documento describe la autenticación y los controles de seguridad actualmente implementados en el backend del Sistema Integral de Gestión Académica (SIGA).

El alcance actual comprende:

- autenticación mediante Laravel;
- Laravel Sanctum;
- sesiones stateful;
- cookies;
- protección CSRF;
- CORS;
- login;
- logout;
- acceso autenticado a API;
- almacenamiento de sesiones en PostgreSQL;
- cifrado de sesiones;
- regeneración de sesión;
- invalidación de sesión;
- separación entre autenticación y autorización.

La implementación está orientada actualmente a una SPA propia que utilizará autenticación mediante sesión y cookies.

## 2. Tecnología

Componentes principales:

- Laravel 13;
- Laravel Sanctum 4;
- guard Laravel `web`;
- sesiones Laravel;
- PostgreSQL;
- middleware CSRF;
- CORS con credenciales.

La estrategia actual no utiliza Personal Access Tokens como mecanismo principal de autenticación de la SPA.

## 3. Modelo de autenticación

El flujo implementado es:

```text
SPA
 │
 │ 1. obtener cookie CSRF
 ▼
GET /sanctum/csrf-cookie
 │
 ▼
XSRF-TOKEN
 │
 │ 2. enviar credenciales
 ▼
POST /login
 │
 ▼
Auth::attempt()
 │
 ▼
regenerar sesión
 │
 ▼
sesión autenticada
 │
 │ 3. consumir API
 ▼
auth:sanctum
 │
 ▼
API protegida