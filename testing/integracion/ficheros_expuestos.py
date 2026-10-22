#!/usr/bin/env python3
"""
Ficheros internos que el servidor web NUNCA debe servir.

Lo que se protege (.htaccess de la raiz):
  - Antes se servia todo lo que no fuera .json/.php/.txt, con listado de directorios:
    config/.env (claves de firma de los JWT), .git/config (credencial de GitHub),
    docker-compose.yml, credenciales de Bruno, scripts de pruebas...
  - Todo eso responde ahora 404, y la API y los ficheros publicos siguen igual.

Solo mira codigos HTTP: no descarga ni imprime el contenido de nada.

Uso:  python3 testing/integracion/ficheros_expuestos.py
      ESC_BASE_URL=https://... python3 testing/integracion/ficheros_expuestos.py
"""
import os
import sys
import urllib.error
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from comun import BASE, check, resumen, titulo

PRIVADOS = [
    "config/.env", "config/", "config/conexion.json", "config/claves_example.env",
    ".git/config", ".git/HEAD", ".gitignore", ".gitleaks.toml", ".env",
    "docker-compose.yml", "Dockerfile", "composer.json", "composer.lock", "VERSION",
    "bruno/environments/EU.bru", "bruno/", "storage/", "storage/esc.apk",
    "vendor/", "testing/", "testing/integracion/comun.py", "db_init/", "db_migrations/",
    "documentacion/", "README.md", "CHANGELOG.md", "phpstan-baseline.neon",
    "bin_codigo_login.sh", "prueba.json", "app/cron/", "app/services/", "app/routers/rutas.php",
]

PUBLICOS = [
    ("", 200),
    ("?page=endpoints-verificados", 200),
    (".well-known/apple-app-site-association", 200),
    (".well-known/assetlinks.json", 200),
    ("storage/img_screens_frontend/", 200),
    # Sin token la API contesta ella misma (JSON 403), no Apache.
    ("usuario", 403),
    ("plants", 403),
]


def codigo(ruta):
    req = urllib.request.Request(BASE.rstrip("/") + "/" + ruta, method="GET")
    try:
        with urllib.request.urlopen(req, timeout=15) as r:
            return r.status
    except urllib.error.HTTPError as e:
        return e.code


titulo(f"1) ficheros internos -> 404 ({BASE})")
for ruta in PRIVADOS:
    hc = codigo(ruta)
    check(f"/{ruta} -> HTTP {hc}", hc in (403, 404))

titulo("2) lo publico y la API siguen respondiendo")
for ruta, esperado in PUBLICOS:
    hc = codigo(ruta)
    check(f"/{ruta} -> HTTP {hc}", hc == esperado, f"se esperaba {esperado}")

resumen()
