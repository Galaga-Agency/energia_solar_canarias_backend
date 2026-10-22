# Pruebas

Cuatro niveles. Solo el primero corre en el CI: los demás necesitan el stack levantado y
las APIs reales de los proveedores.

| Nivel | Dónde | Necesita | Lo lanza el CI |
|---|---|---|---|
| Unitarias (PHPUnit) | `testing/*Test.php` | Nada | Sí, y bloquea |
| Análisis estático (PHPStan) | `phpstan.neon.dist` + `phpstan-baseline.neon` | Nada | Sí, y bloquea |
| Integración (Python) | `testing/integracion/*.py` | Stack local + APIs reales | No |
| Colección Bruno | `bruno/` | Stack local y/o credenciales | No |

## Antes de empezar

```bash
docker compose up -d                                                           # stack local (Zoho desactivado)
docker compose exec -T app php /var/www/html/testing/integracion/crear_usuarios_prueba.php   # clientes 1086 y 1087
docker compose exec -T app php /var/www/html/docker/crear_admin_local.php                     # admin.pruebas@galagaagency.com
```

## Unitarias y PHPStan

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
```

PHPStan tiene un baseline con los avisos que ya había: falla solo con avisos **nuevos**.
Si arreglas uno de los antiguos, regenera el baseline con
`vendor/bin/phpstan analyse --memory-limit=1G --generate-baseline`.

## Integración

```bash
for t in acceso_por_usuario acceso_proveedores_y_apikey baja_y_alta_cliente codigos_http configuracion_php metodos_acceso; do
  python3 testing/integracion/$t.py
done
```

- **No hace falta copiar ningún JWT.** Las pruebas sacan el de admin con el flujo real del
  enlace (lo piden y lo leen de Mailpit). Si prefieres usar uno tuyo, ponlo en `ESC_JWT_ADMIN`.
- **Se niegan a arrancar si Zoho está conectado** (`ZOHO_DESACTIVADO` distinto de 1): dan de
  alta y de baja usuarios, y con Zoho conectado eso escribe en el CRM real
  (ver [zoho.md](zoho.md)).
- **Escriben en la BD local:** reasignan plantas de 1086 y 1087, los dan de baja y de alta,
  y cambian sus métodos de acceso. Lo dejan todo como estaba al terminar.
- **Gastan cuota** de los proveedores, sobre todo de Sigenergy (error `1201`). No las lances
  en bucle.

| Script | Qué protege |
|---|---|
| `acceso_por_usuario.py` | Cada cliente ve solo sus plantas; compartir y quitar el acceso se reflejan al momento. |
| `acceso_proveedores_y_apikey.py` | Los cinco proveedores, las claves de API y los casos raros (sin token, token basura, usuario desactivado). |
| `baja_y_alta_cliente.py` | La baja es lógica y la re-alta recupera la misma fila; un fallo de Zoho no se calla; el alta sin Zoho. |
| `codigos_http.py` | El código de error va en el HTTP, no solo en el cuerpo. |
| `configuracion_php.py` | La configuración de PHP del contenedor (variables de entorno, errores ocultos). |
| `metodos_acceso.py` | Enlace y contraseña: valores por defecto, entrar con contraseña, mensajes iguales, nunca sin forma de entrar. |

## Bruno

Ver [bruno/README.md](../bruno/README.md). Desde la terminal:

```bash
cd bruno
npx @usebruno/cli run "GoodWe SEMS+" --env EU
```

Las peticiones que escriben en servicios reales (Zoho) están bloqueadas por defecto.

## Frontend

El frontend tiene su propio repo y su propia suite (`npm run verify`: typecheck, lint,
arquitectura y vitest). Lo lleva otra persona: aquí solo se tocó lo necesario para el login
con contraseña y el panel de Ajustes.
