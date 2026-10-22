#!/usr/bin/env python3
"""
Utilidades comunes de las pruebas de integracion.

Estas pruebas NO son unitarias: hablan con el backend levantado, con MySQL y con las
APIs reales de los proveedores. Por eso viven aparte de phpunit (que corre sin stack)
y se lanzan a mano. Ver el README de esta carpeta.
"""
import json
import os
import subprocess
import sys

BASE = os.environ.get("ESC_BASE_URL", "http://localhost:8080")
RAIZ = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
COMPOSE = os.path.join(RAIZ, "docker-compose.yml")

# Usuarios de prueba (clase 2 = usuario, solo vista). Se crean con crear_usuarios.php.
CLIENTE1 = {"id": 1086, "email": "cliente.pruebas@galagaagency.com"}
CLIENTE2 = {"id": 1087, "email": "cliente.pruebas2@galagaagency.com"}
PASS_PRUEBAS = os.environ.get("ESC_PASS_PRUEBAS", "PruebasESC2026!")

_ok = 0
_fallos = 0


def sql(q):
    """Lanza SQL contra el MySQL del docker-compose y devuelve filas ya troceadas."""
    r = subprocess.run(
        ["docker", "compose", "-f", COMPOSE, "exec", "-T", "mysql",
         # Credenciales del propio contenedor (MYSQL_* del compose / .env), no del repo.
         "sh", "-c", 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -N -u"$MYSQL_USER" "$MYSQL_DATABASE" -e "$1"', "sql", q],
        capture_output=True, text=True)
    return [l.split("\t") for l in r.stdout.strip().split("\n") if l.strip()]


def php(codigo):
    """
    Ejecuta PHP dentro del contenedor de la app y devuelve lo que imprima.

    Sirve para probar cosas de UsuariosDB y compañia contra la BD de verdad. En
    PHPUnit no se puede: esas clases abren la conexion en el constructor y las
    pruebas unitarias corren en el host, sin base de datos.
    """
    r = subprocess.run(
        ["docker", "compose", "-f", COMPOSE, "exec", "-T", "app", "php", "-r", codigo],
        capture_output=True, text=True)
    return r.stdout.strip()


def api(token, metodo, ruta, body=None):
    """Llama al backend. Devuelve (codigo_http, json_decodificado_o_None)."""
    cmd = ["curl", "-s", "-w", "\n%{http_code}", "-X", metodo, "-H", f"Authorization: {token}"]
    if body is not None:
        cmd += ["-H", "Content-Type: application/json", "-d", json.dumps(body)]
    cmd.append(BASE + ruta)
    out = subprocess.run(cmd, capture_output=True, text=True).stdout
    cuerpo, _, hc = out.rpartition("\n")
    try:
        return int(hc), json.loads(cuerpo)
    except Exception:
        return (int(hc) if hc.strip().isdigit() else 0), None


def check(etiqueta, cond, detalle=""):
    global _ok, _fallos
    if cond:
        _ok += 1
        print(f"  OK    {etiqueta}")
    else:
        _fallos += 1
        print(f"  FALLO {etiqueta}   {detalle}")


def titulo(t):
    print()
    print("=" * 78)
    print(t)
    print("=" * 78)


def resumen():
    """Imprime el recuento y termina con codigo != 0 si hubo fallos (util en CI)."""
    print()
    print("=" * 78)
    print(f"RESULTADO: {_ok} correctas, {_fallos} fallos")
    print("=" * 78)
    sys.exit(1 if _fallos else 0)


def login(usuario_id, email, password=PASS_PRUEBAS):
    """
    Devuelve un JWT recien hecho para ese usuario.

    Hace el login 2FA completo. En local el correo NO sale (la app password de Gmail
    esta revocada), pero da igual: el codigo se guarda en la tabla `token` ANTES de
    intentar enviarlo, asi que se lee de la BD. Es el mismo truco que documenta el
    README de bruno/.
    """
    sh = os.path.join(os.path.dirname(__file__), "login.sh")
    r = subprocess.run([sh, str(usuario_id), email, password], capture_output=True, text=True)
    jwt = r.stdout.strip()
    if not jwt:
        print(f"ERROR: no se pudo hacer login como {email}: {r.stderr.strip()}", file=sys.stderr)
        sys.exit(2)
    return "Bearer " + jwt


ADMIN_LOCAL = os.environ.get("ESC_ADMIN_EMAIL", "admin.pruebas@galagaagency.com")
MAILPIT = os.environ.get("ESC_MAILPIT_URL", "http://localhost:8025")


def jwt_por_enlace(email):
    """
    JWT de `email` por el flujo real del enlace magico, leyendo el correo de Mailpit.

    Es lo que hace un usuario: pide el enlace, lo abre (GET sin consumirlo), confirma
    el acceso (POST de un solo uso) y el frontend canjea el handoff. El JWT no se
    imprime: se devuelve para usarlo en las llamadas.
    """
    import re
    import time
    import urllib.error
    import urllib.parse
    import urllib.request

    # El limite anti-spam (5 enlaces / 15 min) salta enseguida al repetir pruebas.
    sql(f"DELETE FROM login_attempts WHERE identificador='{email}';")
    urllib.request.urlopen(urllib.request.Request(MAILPIT + "/api/v1/messages", method="DELETE"))
    subprocess.run(["curl", "-s", "-o", "/dev/null", "-X", "POST", "-H", "Content-Type: application/json",
                    "-d", json.dumps({"email": email, "idioma": "es"}), BASE + "/auth/magic-link"])

    enlace = None
    for _ in range(15):
        try:
            cuerpo = urllib.request.urlopen(MAILPIT + "/api/v1/message/latest").read().decode(errors="replace")
        except urllib.error.HTTPError:
            # 404 mientras el buzon esta vacio: el correo aun no ha llegado.
            cuerpo = ""
        m = re.search(r'https?://[^" ]+/auth/magic\?token=[a-f0-9]+', cuerpo)
        if m:
            enlace = m.group(0)
            break
        time.sleep(1)
    if not enlace:
        print(f"ERROR: no llego el enlace de {email} a Mailpit ({MAILPIT}). Existe el usuario?", file=sys.stderr)
        sys.exit(2)

    # Un GET debe mostrar confirmacion y dejar el secreto intacto. Los escaneres
    # de correo visitan este enlace antes que la persona.
    with urllib.request.urlopen(enlace) as pagina:
        html = pagina.read().decode(errors="replace")
        if pagina.status != 200 or "Confirma tu acceso" not in html:
            print("ERROR: abrir el enlace no mostro la confirmacion", file=sys.stderr)
            sys.exit(2)

    token = urllib.parse.parse_qs(urllib.parse.urlparse(enlace).query).get("token", [""])[0]
    consume = urllib.request.Request(
        BASE + "/auth/magic/consume",
        data=urllib.parse.urlencode({"token": token}).encode(),
        headers={"Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )
    with urllib.request.urlopen(consume) as response:
        destino = response.geturl()
    codigo = urllib.parse.parse_qs(urllib.parse.urlparse(destino).query).get("code", [""])[0]
    r = subprocess.run(["curl", "-s", "-X", "POST", "-H", "Content-Type: application/json",
                        "-d", json.dumps({"code": codigo}), BASE + "/auth/handoff"],
                       capture_output=True, text=True)
    jwt = ((json.loads(r.stdout or "{}").get("data")) or {}).get("tokenIdentificador")
    if not jwt:
        print(f"ERROR: el canje del enlace de {email} fallo", file=sys.stderr)
        sys.exit(2)
    return jwt


def token_admin():
    """
    JWT de admin: de ESC_JWT_ADMIN si esta, y si no por enlace magico con el admin
    de pruebas local (docker/crear_admin_local.php). Asi no hay que copiar tokens.
    """
    t = os.environ.get("ESC_JWT_ADMIN") or jwt_por_enlace(ADMIN_LOCAL)
    return t if t.startswith(("Bearer ", "Token ")) else "Bearer " + t


def exigir_zoho_desactivado():
    """
    Para en seco si el backend puede hablar con el Zoho real.

    Estas pruebas dan de baja y de alta usuarios. La baja marca al cliente como
    inactivo en el CRM buscandolo por idApp, y los ids de los usuarios de pruebas
    (creados sobre una copia de produccion) pueden coincidir con clientes reales:
    el 2026-10-15 una ejecucion escribio en el CRM de produccion. El
    docker-compose local pone ZOHO_DESACTIVADO=1; si alguien lo quita, aqui se para.
    """
    estado = php('require "/var/www/html/vendor/autoload.php";'
                 'require "/var/www/html/app/services/ZohoService.php";'
                 'echo ZohoService::desactivado() ? "off" : "ON";')
    if estado != "off":
        print("ERROR: Zoho esta CONECTADO en este backend (ZOHO_DESACTIVADO no esta a 1).\n"
              "       Estas pruebas escribirian en el CRM real. Levanta el stack con el\n"
              "       docker-compose del repo (lo pone a 1) o exporta ZOHO_DESACTIVADO=1.",
              file=sys.stderr)
        sys.exit(2)


exigir_zoho_desactivado()
