#!/bin/bash
# Saca el JWT de un usuario haciendo el login 2FA completo.
# En local el correo no sale (SMTP roto), pero el codigo se guarda en la BD ANTES
# de intentar enviarlo, asi que se lee de ahi.
# Uso: ./login.sh <usuario_id> <email> <password>
UID_="$1"; EMAIL="$2"; PASS="$3"
COMPOSE="/Volumes/l/Proyectos Energía Solar Canarias/energia_solar_canarias_backend/docker-compose.yml"

curl -s -o /dev/null -X POST -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}" "http://localhost:8080/login"

CODE=$(docker compose -f "$COMPOSE" exec -T mysql mysql -N -uescuser -pescpassword esc \
  -e "SELECT token_login FROM token WHERE usuario_id=$UID_ ORDER BY time_token_login DESC LIMIT 1;" 2>/dev/null | tr -d '[:space:]')

if [ -z "$CODE" ]; then echo "ERROR: no hay codigo en BD para $UID_" >&2; exit 1; fi

curl -s -X POST -H "Content-Type: application/json" \
  -d "{\"id\":$UID_,\"token\":\"$CODE\"}" "http://localhost:8080/token" \
  | python3 -c "
import sys, json
d = json.load(sys.stdin)
t = (d.get('data') or {}).get('tokenIdentificador')
if not t:
    print('ERROR:', json.dumps(d)[:200], file=sys.stderr); sys.exit(1)
print(t)
"
