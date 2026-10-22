#!/usr/bin/env bash
# Entrar en la app en LOCAL sin buscar el correo a mano.
#
# Pide un enlace de acceso para el email, lo saca de Mailpit y lo abre en el
# navegador. Hay que confirmar el acceso en la página que se abre. El enlace
# caduca a los 15 minutos y es de un solo uso.
#
#   docker/login-local.sh                      -> cliente.pruebas2@galagaagency.com
#   docker/login-local.sh otro@email.com       -> cualquier usuario de la BD local
#
# Requiere el stack levantado (docker compose up -d) y el frontend en :3000.
# Ver README, "Entrar en local".
set -euo pipefail

EMAIL="${1:-cliente.pruebas2@galagaagency.com}"
BACKEND="${BACKEND_URL_LOCAL:-http://localhost:8080}"
MAILPIT="${MAILPIT_URL:-http://localhost:8025}"

# Se vacia el buzon para que "el ultimo correo" sea seguro el de esta peticion.
curl -fsS -X DELETE "$MAILPIT/api/v1/messages" >/dev/null

curl -fsS -X POST "$BACKEND/auth/magic-link" -H "Content-Type: application/json" \
  -d "$(printf '{"email":"%s","idioma":"es"}' "$EMAIL")" >/dev/null

# El endpoint responde lo mismo exista o no el email (a proposito, ver
# MagicLinkController), asi que la unica señal de que el usuario existe es que
# llegue el correo.
enlace=""
for _ in 1 2 3 4 5 6 7 8 9 10; do
  enlace=$(curl -fsS "$MAILPIT/api/v1/message/latest" 2>/dev/null \
    | grep -oE 'https?://[^" ]+/auth/magic\?token=[a-f0-9]+' | head -1 || true)
  [ -n "$enlace" ] && break
  sleep 1
done

if [ -z "$enlace" ]; then
  echo "No ha llegado ningun correo para $EMAIL." >&2
  echo "  - Existe y esta activo en la BD local? (phpMyAdmin: http://localhost:8081)" >&2
  echo "  - Mira los logs: docker compose logs app mailpit" >&2
  exit 1
fi

echo "Enlace de acceso para $EMAIL (un solo uso, 15 min):"
echo "  $enlace"
if command -v open >/dev/null 2>&1; then open "$enlace"
elif command -v xdg-open >/dev/null 2>&1; then xdg-open "$enlace"
fi
