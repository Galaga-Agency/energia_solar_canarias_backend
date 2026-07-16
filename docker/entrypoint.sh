#!/bin/sh
# Arranque del contenedor de la app: primero pone la base al dia, luego Apache.
#
# El objetivo es que un despliegue no dependa de que alguien se acuerde de aplicar
# las migraciones a mano. Olvidarse rompe cosas en silencio: sin la tabla api_cache,
# por ejemplo, Sigenergy responde 400 en todas las llamadas.
#
# Si las migraciones fallan, el contenedor NO arranca. Es a proposito: es preferible
# un despliegue que se cae de forma evidente a uno que levanta con el esquema a
# medias y va fallando por sitios raros.
#
# Se puede saltar con ESC_MIGRAR=0 (util para depurar un arranque).
set -e

if [ "${ESC_MIGRAR:-1}" = "1" ]; then
    # La BD puede tardar en aceptar conexiones aunque el contenedor ya este arriba.
    # Se reintenta un rato: eso es un problema pasajero, no una migracion rota.
    intentos=0
    until php -r '
        $c = json_decode(file_get_contents("/var/www/html/config/conexion.json"), true)[0];
        $db = @new mysqli($c["server"], $c["user"], $c["password"], $c["database"], (int) $c["port"]);
        exit($db->connect_errno ? 1 : 0);
    ' 2>/dev/null; do
        intentos=$((intentos + 1))
        if [ "$intentos" -ge 30 ]; then
            echo "[entrypoint] La base de datos no responde tras 60s. Se aborta." >&2
            exit 1
        fi
        echo "[entrypoint] Esperando a la base de datos... ($intentos)"
        sleep 2
    done

    echo "[entrypoint] Aplicando migraciones pendientes..."
    php /var/www/html/db_migrations/migrar.php
fi

exec "$@"
