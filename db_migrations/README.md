# Migraciones

Cambios de esquema que se aplican **encima** del volcado base.

El esquema base sale de `db_init/esc_dump.sql`, que MySQL importa al primer arranque. Lo de aquí son los cambios posteriores. **No construye la base desde cero**: hay migraciones con claves foráneas a tablas del volcado (`proveedor_oauth` → `proveedores`), así que contra una base vacía fallan. El orden siempre es: volcado primero, migraciones después.

## Se aplican solas

El contenedor de la app las aplica al arrancar (`docker/entrypoint.sh`), antes de levantar Apache. No hay que acordarse de nada al desplegar.

Si una migración falla, **el contenedor no arranca**. Es a propósito: es preferible un despliegue que se cae de forma evidente a uno que levanta con el esquema a medias y va fallando por sitios raros. Sin la tabla `api_cache`, por ejemplo, Sigenergy responde 400 en *todas* las llamadas — y eso no es obvio mirando el log de Apache.

Si el contenedor entra en bucle de reinicios, arregla la migración y haz `docker compose up -d --force-recreate app`: un `restart` a secas se queda esperando el backoff de Docker.

Se puede saltar con `ESC_MIGRAR=0` (solo para depurar un arranque).

## A mano

```bash
# Ver qué falta sin tocar nada
docker compose exec -T app php db_migrations/migrar.php --estado

# Aplicar
docker compose exec -T app php db_migrations/migrar.php
```

Devuelve 0 si va bien y 1 si algo falla, así que sirve tal cual en un paso de despliegue.

## Cómo añadir una

Un `.sql` con **fecha delante**: `2026_07_20_add_columna_x.sql`. Se aplican por orden alfabético, y la fecha es lo que garantiza ese orden.

**Hazla idempotente**: `CREATE TABLE IF NOT EXISTS`, `DROP ... IF EXISTS`. No es un capricho: MySQL **no hace rollback de los `CREATE`/`ALTER`**, así que si una migración con varias sentencias falla a la mitad, lo que ya se ejecutó se queda. Al relanzarla después de arreglarla, tiene que poder pasar por encima de lo ya aplicado sin reventar.

**No edites una migración ya aplicada.** El migrador guarda un checksum y avisa, pero no la vuelve a ejecutar: en tu máquina "funcionaría" porque la aplicaste antes del cambio, y en producción entraría la versión nueva. Si el esquema cambia, migración nueva.

## Cómo sabe qué aplicar

Tabla `migraciones`: nombre, checksum y fecha de cada una aplicada. Se ejecuta lo que esté en `*.sql` y no figure ahí.

Coge un `GET_LOCK` de MySQL mientras migra, así que si arrancan varios contenedores a la vez solo uno aplica y los demás esperan (el lock es del servidor MySQL, no del proceso). Es el mismo truco que usa `CacheApiService`.

## Lo que NO toca

Solo los `.sql`. Los `.php` de esta carpeta son **seeds**, no migraciones: `seed_sungrow_oauth.php` lee `config/.env` y vuelca configuración, y se lanza a mano.
