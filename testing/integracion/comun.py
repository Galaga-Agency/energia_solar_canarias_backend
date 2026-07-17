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
         "mysql", "-N", "-uescuser", "-pescpassword", "esc", "-e", q],
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


def token_admin():
    """
    JWT de admin. Sale de ESC_JWT_ADMIN porque el usuario admin cambia segun el
    entorno y no queremos su contraseña en el repo.
    """
    t = os.environ.get("ESC_JWT_ADMIN")
    if not t:
        print("ERROR: falta la variable ESC_JWT_ADMIN con un JWT de administrador.\n"
              "       Sacala del login (ver bruno/README.md) y exportala:\n"
              "         export ESC_JWT_ADMIN='eyJ0eXAi...'", file=sys.stderr)
        sys.exit(2)
    return t if t.startswith(("Bearer ", "Token ")) else "Bearer " + t
