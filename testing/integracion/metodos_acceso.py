#!/usr/bin/env python3
"""
Metodos de acceso: enlace por correo y email + contraseña, cada uno con su interruptor.

Lo que se protege:
  - Por defecto se entra SOLO por enlace; la contraseña esta apagada.
  - /auth/password no deja entrar con el metodo apagado, aunque la contraseña sea buena,
    y contesta lo mismo que con una contraseña mala (no delata nada).
  - Encender la contraseña exige elegir una valida en la misma peticion.
  - Nunca se puede quedar una cuenta sin ninguna forma de entrar.
  - Con el enlace apagado no se manda correo, pero la respuesta es la de siempre.

Usa el cliente de pruebas 2 (1087) y lo deja como estaba: enlace si, contraseña no,
y la contraseña de pruebas de siempre (la que usa login.sh).

Uso:  python3 testing/integracion/metodos_acceso.py
"""
import json
import os
import subprocess
import sys
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from comun import BASE, CLIENTE2, MAILPIT, PASS_PRUEBAS, api, check, jwt_por_enlace, php, resumen, sql, titulo

UID = CLIENTE2["id"]
EMAIL = CLIENTE2["email"]


def entrar(email, password):
    r = subprocess.run(["curl", "-s", "-w", "\n%{http_code}", "-X", "POST", "-H", "Content-Type: application/json",
                        "-d", json.dumps({"email": email, "password": password}), BASE + "/auth/password"],
                       capture_output=True, text=True).stdout
    cuerpo, _, hc = r.rpartition("\n")
    try:
        return int(hc), json.loads(cuerpo)
    except Exception:
        return int(hc or 0), None


def limpiar_intentos():
    # Tambien el email inventado: si no, varias ejecuciones seguidas acaban en 429.
    sql(f"DELETE FROM login_attempts WHERE identificador IN ('{EMAIL}', 'no.existe.nunca@galagaagency.com');")


def correos_en_mailpit():
    return json.loads(urllib.request.urlopen(MAILPIT + "/api/v1/messages").read()).get("total", 0)


def restaurar():
    sql(f"DELETE FROM usuarios_metodos_acceso WHERE usuario_id={UID};")
    limpiar_intentos()


restaurar()
AJENAS_ANTES = sql("SELECT COUNT(*) FROM usuarios_metodos_acceso WHERE usuario_id <> %d;" % UID)[0][0]
C = "Bearer " + jwt_por_enlace(EMAIL)

titulo("1) por defecto: enlace si, contraseña no")
hc, r = api(C, "GET", "/usuario/metodos-acceso")
check(f"GET -> {(r or {}).get('data')}", hc == 200 and (r or {}).get("data") == {"enlace": True, "password": False})

hc, r = entrar(EMAIL, PASS_PRUEBAS)
check(f"con la contraseña apagada no entra aunque sea buena -> HTTP {hc}", hc == 401)
mensaje_apagado = (r or {}).get("message")

try:
    titulo("2) encender la contraseña exige elegir una")
    hc, r = api(C, "PUT", "/usuario/metodos-acceso", {"metodo": "password", "activo": True})
    check(f"sin contraseña -> HTTP {hc}", hc == 400, (r or {}).get("message"))
    hc, r = api(C, "PUT", "/usuario/metodos-acceso", {"metodo": "password", "activo": True, "password": "corta"})
    check(f"demasiado corta -> HTTP {hc}", hc == 400, (r or {}).get("message"))
    hc, r = api(C, "PUT", "/usuario/metodos-acceso", {"metodo": "inventado", "activo": True})
    check(f"metodo desconocido -> HTTP {hc}", hc == 400)

    hc, r = api(C, "PUT", "/usuario/metodos-acceso", {"metodo": "password", "activo": True, "password": PASS_PRUEBAS})
    check(f"con una valida se enciende -> {(r or {}).get('data')}", hc == 200 and (r or {}).get("data") == {"enlace": True, "password": True})
    # Poner contraseña cambia la credencial: se cierran todas las sesiones, esta incluida.
    hc, _ = api(C, "GET", "/usuario/metodos-acceso")
    check(f"poner contraseña cierra la sesion que la puso -> HTTP {hc}", hc == 403)
    C = "Bearer " + jwt_por_enlace(EMAIL)

    titulo("3) entrar con email + contraseña")
    limpiar_intentos()
    hc, r = entrar(EMAIL, PASS_PRUEBAS)
    datos = (r or {}).get("data") or {}
    check(f"entra -> HTTP {hc}", hc == 200 and bool(datos.get("tokenIdentificador")))
    check("devuelve la misma sesion que el enlace (usuario_id, clase...)", datos.get("usuario_id") == UID and "clase" in datos)
    if datos.get("tokenIdentificador"):
        hc2, _ = api("Bearer " + datos["tokenIdentificador"], "GET", "/plants")
        check(f"y ese token sirve -> HTTP {hc2}", hc2 == 200)

    hc, r = entrar(EMAIL, "otra-contraseña-mala")
    check(f"contraseña mala -> HTTP {hc}", hc == 401)
    check("mismo mensaje que con el metodo apagado (no delata cual de las dos fallo)",
          (r or {}).get("message") == mensaje_apagado)
    hc, r = entrar("no.existe.nunca@galagaagency.com", PASS_PRUEBAS)
    check(f"email que no existe -> HTTP {hc}, mismo mensaje", hc == 401 and (r or {}).get("message") == mensaje_apagado)
    limpiar_intentos()

    titulo("4) apagar el enlace: deja de llegar el correo, la respuesta no cambia")
    hc, r = api(C, "PUT", "/usuario/metodos-acceso", {"metodo": "enlace", "activo": False})
    check(f"se apaga -> {(r or {}).get('data')}", hc == 200 and (r or {}).get("data") == {"enlace": False, "password": True})
    urllib.request.urlopen(urllib.request.Request(MAILPIT + "/api/v1/messages", method="DELETE"))
    hc, r = api(C, "POST", "/auth/magic-link", {"email": EMAIL, "idioma": "es"})
    check(f"pedir enlace contesta igual -> HTTP {hc}", hc == 200)
    check("pero no sale ningun correo", correos_en_mailpit() == 0)

    titulo("5) no se puede quedar sin ninguna forma de entrar")
    hc, r = api(C, "PUT", "/usuario/metodos-acceso", {"metodo": "password", "activo": False})
    check(f"apagar la ultima -> HTTP {hc}", hc == 400, (r or {}).get("message"))
    hc, r = api(C, "GET", "/usuario/metodos-acceso")
    check("y sigue como estaba", (r or {}).get("data") == {"enlace": False, "password": True})

    titulo("6) con un login antiguo hay que volver a identificarse")
    # Un token robado no basta para poner una contraseña propia y apagar el enlace.
    # Se abre otra sesion y se envejece su login una hora (como una sesion robada o vieja).
    # El enlace esta apagado desde el paso 4: la segunda sesion se abre con contraseña.
    limpiar_intentos()
    hc, r = entrar(EMAIL, PASS_PRUEBAS)
    viejo = ((r or {}).get("data") or {}).get("tokenIdentificador") or ""
    check(f"se abre otra sesion con contraseña -> HTTP {hc}", hc == 200 and bool(viejo))
    sql(f"UPDATE auth_sesiones SET creado_en = creado_en - 3600 WHERE usuario_id = {UID} AND revocada_en IS NULL AND creado_en = (SELECT m FROM (SELECT MAX(creado_en) AS m FROM auth_sesiones WHERE usuario_id = {UID}) t);")
    V = "Bearer " + viejo
    hc, _ = api(V, "GET", "/usuario/metodos-acceso")
    check(f"el token viejo es valido para leer -> HTTP {hc}", hc == 200)
    hc, r = api(V, "PUT", "/usuario/metodos-acceso", {"metodo": "enlace", "activo": True})
    check(f"pero no para cambiar nada -> HTTP {hc}", hc == 428 and ((r or {}).get("data") or {}).get("reautenticar") is True)
    limpiar_intentos(); sql(f"DELETE FROM login_attempts WHERE identificador='reauth:{UID}';")
    hc, r = api(V, "PUT", "/usuario/metodos-acceso", {"metodo": "enlace", "activo": True, "passwordActual": "no-es-la-buena"})
    check(f"con una contraseña actual mala tampoco -> HTTP {hc}", hc == 428)
    urllib.request.urlopen(urllib.request.Request(MAILPIT + "/api/v1/messages", method="DELETE"))
    hc, r = api(V, "PUT", "/usuario/metodos-acceso", {"metodo": "enlace", "activo": True, "passwordActual": PASS_PRUEBAS})
    check(f"con la contraseña actual buena si -> HTTP {hc}", hc == 200 and (r or {}).get("data") == {"enlace": True, "password": True})
    import time
    asuntos = []
    for _ in range(10):
        asuntos = [m.get("Subject") for m in json.loads(urllib.request.urlopen(MAILPIT + "/api/v1/messages").read()).get("messages", [])]
        if asuntos: break
        time.sleep(0.5)
    check(f"y al dueño le llega un aviso por correo ({asuntos})", any("formas de entrar" in (a or "") for a in asuntos))
    sql(f"DELETE FROM login_attempts WHERE identificador='reauth:{UID}';")

    titulo("6) cada usuario toca solo lo suyo")
    ajenas = sql("SELECT COUNT(*) FROM usuarios_metodos_acceso WHERE usuario_id <> %d;" % UID)[0][0]
    check("no se ha escrito nada de otros usuarios", ajenas == AJENAS_ANTES, f"antes {AJENAS_ANTES}, ahora {ajenas}")
finally:
    restaurar()

hc, r = api(C, "GET", "/usuario/metodos-acceso")
check("queda como estaba (enlace si, contraseña no)", (r or {}).get("data") == {"enlace": True, "password": False})

resumen()
