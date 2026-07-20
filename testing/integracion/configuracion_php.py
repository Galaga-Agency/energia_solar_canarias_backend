#!/usr/bin/env python3
"""
La configuracion de PHP de la imagen. Dos ajustes que se pisan entre si.

Esto existe por un fallo real, y de los caros: al activar php.ini-production para que
los warnings no rompieran el JSON, se colo ademas `variables_order = "GPCS"`, SIN la E
de Environment. Sin la E, las variables que inyecta el compose del VPS no llegan a
$_ENV (a getenv() si, pero el codigo lee $_ENV).

Y de ahi salen las ZOHO_* en produccion: no estan en ningun .env, las pone el compose
compartido de /home/galagaagency/proyectos/. Hoy funcionan porque la imagen de
produccion no tiene php.ini y el defecto de PHP es "EGPCS"; el primer despliegue con
php.ini las habria dejado a null y habria matado el sync con el CRM entero.

Los dos ajustes hacen falta a la vez, y por eso se comprueban juntos: quitar el
php.ini devuelve display_errors=On y rompe las respuestas JSON, y dejarlo sin tocar
variables_order tira Zoho. Ninguno de los dos se puede "simplificar" quitando el otro.

Uso:  python3 testing/integracion/configuracion_php.py
"""
import sys, os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from comun import check, php, resumen, titulo

titulo("CONFIGURACION DE PHP EN LA IMAGEN")

orden = php('echo ini_get("variables_order");')
check(
    f"variables_order lleva la E de Environment ({orden})",
    "E" in orden,
    "sin la E, las variables del contenedor no llegan a $_ENV y las ZOHO_* del "
    "compose se quedan a null: el sync con el CRM deja de funcionar",
)

# La prueba de verdad: una variable inyectada por docker-compose, vista desde PHP.
tz = php('echo $_ENV["TZ"] ?? "NO EXISTE";')
check(
    f"una variable del compose llega a $_ENV (TZ = {tz})",
    tz != "NO EXISTE",
    "docker-compose inyecta TZ; si PHP no la ve en $_ENV, tampoco vera las ZOHO_*",
)

mostrar = php('echo ini_get("display_errors") ?: "Off";')
check(
    f"display_errors apagado ({mostrar})",
    mostrar in ("", "Off", "0"),
    "con display_errors=On los warnings se imprimen ANTES del JSON y el cuerpo de la "
    "respuesta deja de ser JSON valido",
)

registra = php('echo ini_get("log_errors") ? "On" : "Off";')
check(
    f"log_errors encendido ({registra})",
    registra == "On",
    "si no se muestran NI se registran, los errores desaparecen del todo",
)

resumen()
