# Documentación del backend

Por dónde empezar:

1. **[README del repo](../README.md)**: qué es, cómo se levanta en local, estructura, despliegue.
2. Las guías de abajo, según lo que vayas a tocar.

## Guías

| Guía | Para qué |
|---|---|
| [plan-mejoras.md](plan-mejoras.md) | **Todas las medidas de mejora** por prioridad y estado (empezar aquí). |
| [autenticacion.md](autenticacion.md) | Formas de entrar (enlace, contraseña, login antiguo, claves de API), métodos por usuario, seguridad, tipos de JWT. |
| [zoho.md](zoho.md) | Sincronización con Zoho CRM: qué hace cada operación, la baja lógica, cómo comprobar si un usuario ya está en el CRM, y Zoho desconectado en local. |
| [goodwe.md](goodwe.md) | GoodWe tras la baja del portal SEMS: qué sale ya de SEMS+, la sesión única y la paginación. |
| [pruebas.md](pruebas.md) | PHPUnit, PHPStan, pruebas de integración y Bruno: cómo lanzarlo todo y qué protege cada cosa. |
| [versionado.md](versionado.md) | SemVer, changelog, cómo publicar una versión, workflows de CI/CD, Dependabot y secretos. |

## Otras referencias del repo

| Dónde | Qué |
|---|---|
| [app/proveedores/README.md](../app/proveedores/README.md) | Modelo de proveedores (Adaptador + Registro) y cómo añadir uno. |
| [db_migrations/README.md](../db_migrations/README.md) | Migraciones: cuándo se aplican y cómo desplegarlas. |
| [bruno/README.md](../bruno/README.md) | Colección Bruno: nuestra API, Zoho CRM y cada proveedor. |
| [testing/integracion/README.md](../testing/integracion/README.md) | Detalle de las pruebas de integración. |
| [sungrow/openapi-sungrow.md](sungrow/openapi-sungrow.md) | Documentación de la API de Sungrow. |
| `sigenergy/Sigen Developer Portal.pdf` | Documentación de la API de Sigenergy. |
| `http://localhost:8080/?page=endpoints-verificados` | Con el stack levantado: tabla viva de endpoints y matriz de qué ofrece cada proveedor. |
| [CHANGELOG.md](../CHANGELOG.md) | Qué cambia en cada versión. |

## Mapa rápido

```
Navegador / app móvil / programa con clave de API
        │  Authorization: Bearer <jwt> | Token <uuid>
        ▼
.htaccess → app/routers/rutas.php            una sola entrada; switch por método y ruta
        │
        ├─ middlewares/autenticacion.php      quién es y si es admin; puedeVerPlanta()
        ├─ controllers/                       un controlador por área (acceso, enlace, usuarios, proveedores, Zoho, logs)
        ├─ services/                          lógica: ProveedorApiService + adaptadores, GoodWeSemsPlus, MetodosAcceso, correo...
        ├─ proveedores/adaptadores/           GoodWe, SolarEdge, Sungrow, Victron, Sigenergy → contrato común
        └─ DBObjects/ + models/conexion.php   MySQL (mysqli, singleton)
        │
        ├─► APIs de proveedores (tokens en `bearertoken`/`proveedor_oauth`, caché en `api_cache`)
        ├─► Zoho CRM (Accounts, Plantas), salvo con ZOHO_DESACTIVADO=1
        └─► SMTP (Mailpit en local)

Cron (app/cron/):
  cron_refresh_tokens_proveedores.php   renueva tokens de proveedor (servicio token-refresher, cada 6 h)
  cron_avisos_alertas.php               un correo por usuario con sus alertas nuevas (opt-in)
  cron_llamar_endpoint_zoho.php         sincronización programada con Zoho
```
