#!/usr/bin/env python3
"""
El codigo de error tiene que ir en el HTTP, no solo dentro del cuerpo.

Esto existe por un bug real: se podia "crear" un usuario con un email que ya existia.
El backend lo detectaba y contestaba {"code":409,"message":"El email ya esta
registrado"}, pero lo mandaba con HTTP 200. El frontend hace `if (!response.ok)
throw`, y 200 es ok, asi que daba el error por bueno y pintaba exito. No llegaba a
crearse nada (la columna email es UNIQUE): la UI mentia.

La causa estaba en la clase Respuesta, que rellenaba $this->code pero no llamaba a
http_response_code(): habia que acordarse en cada sitio, y en 28 no se hizo. Ahora lo
aplica ella.

Se prueba por HTTP de verdad (contra el Apache del contenedor) porque es justo lo que
no se puede comprobar en PHPUnit: alli las cabeceras ya salieron.

Uso:  export ESC_JWT_ADMIN='...'
      python3 testing/integracion/codigos_http.py
"""
import sys, os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from comun import CLIENTE1, api, check, resumen, sql, titulo, token_admin

ADMIN = token_admin()

titulo("EL CODIGO DE ERROR VA EN EL HTTP, NO SOLO EN EL CUERPO")

# El chequeo de email duplicado esta ANTES de llamar a Zoho, asi que esta prueba no
# crea usuarios ni toca el CRM. Por eso se puede repetir sin ensuciar nada.
hc, r = api(
    ADMIN, "POST", "/usuarios",
    body={
        "email": CLIENTE1["email"],   # ya existe
        "password": "LoQueSea1234!",
        "nombre": "Duplicado",
        "apellido": "Prueba",
        "clase": "usuario",
    },
)
check(
    f"email repetido -> HTTP {hc} (no 200, que el front daba por bueno)",
    hc == 409,
    f"esperaba 409 y llego {hc}",
)
check(
    "y el cuerpo dice por que",
    bool(r) and "registrado" in str(r.get("message", "")).lower(),
    f"mensaje inesperado: {(r or {}).get('message')!r}",
)
check(
    "cuerpo y HTTP coinciden",
    bool(r) and r.get("code") == hc,
    f"el cuerpo dice {(r or {}).get('code')} y el HTTP {hc}",
)
check(
    "status=false",
    bool(r) and r.get("status") is False,
    "esperaba status=false",
)

# Sin token no se puede: tambien tiene que verse en el HTTP.
hc, _ = api("", "GET", "/plants")
check(f"sin token -> HTTP {hc}", hc in (401, 403), f"esperaba 401/403 y llego {hc}")

titulo("UN ALTA QUE REVIENTA NO PUEDE DECIR QUE FUE BIEN")

# Un nombre de 300 caracteres en un varchar(255), con STRICT_TRANS_TABLES: MySQL da
# error en vez de recortar, asi que insertUser() revienta y devuelve false. Es la forma
# limpia de provocar un fallo de escritura sin tocar nada.
#
# origen=crm + idApp a proposito: es la rama que NO llama a Zoho, asi que esto no crea
# clientes en el CRM ni gasta cuota.
#
# Antes esto contestaba 200 status=true "Usuario creado localmente desde Zoho" con
# usuario_id=null y CERO filas en la base: el control era isset($result), y como
# $result se asigna siempre, isset(false) daba true y el alta fallida seguia adelante.
EMAIL_FALLO = "fallo.insert.pruebas@galagaagency.com"
hc, r = api(ADMIN, "POST", "/usuarios", body={
    "email": EMAIL_FALLO,
    "password": "LoQueSea1234!",
    "nombre": "N" * 300,          # no cabe en varchar(255)
    "apellido": "X",
    "clase": "usuario",
    "origen": "crm", "idApp": "999",
})
check(f"el alta falla y se dice: HTTP {hc}", hc == 500, f"esperaba 500 y llego {hc}")
check(
    "no se anuncia como creado",
    bool(r) and r.get("status") is False,
    f"contesto status={(r or {}).get('status')}: {(r or {}).get('message')!r}",
)
check(
    "y no queda nada en la base",
    len(sql(f"SELECT usuario_id FROM usuarios WHERE email='{EMAIL_FALLO}';")) == 0,
    "se creo el usuario a pesar del fallo",
)

titulo("OTROS CODIGOS")

# Una ruta que no existe no puede contestar 200.
#
# Contesta 400, no 404. Semanticamente deberia ser 404 (400 es "peticion mal
# formada"), pero es lo que hace hoy y de forma consistente: cada `switch` de metodo
# tiene su `default:` que devuelve 400 y marca $handled = true. Eso deja el
# `if (!$handled)` del final de rutas.php, que si pone un 404, como codigo muerto.
#
# Se afirma lo que hace de verdad, no lo que deberia hacer: cambiarlo es otro asunto
# y afecta a los clientes. Lo que se comprueba aqui es lo importante, que NO es 200.
hc, _ = api(ADMIN, "GET", "/esto-no-existe-de-ninguna-manera")
check(f"ruta inexistente -> HTTP {hc} (no 200)", hc == 400, f"esperaba 400 y llego {hc}")

resumen()
