#!/usr/bin/env python3
"""
Segunda tanda del control de acceso: los 5 proveedores, el esquema de API key y
los casos raros.

Uso:  export ESC_JWT_ADMIN='...'
      python3 testing/integracion/acceso_proveedores_y_apikey.py
"""
import sys, os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from comun import CLIENTE1, api, check, login, resumen, sql, titulo, token_admin

ADMIN = token_admin()
C1 = login(CLIENTE1["id"], CLIENTE1["email"])
U1 = CLIENTE1["id"]

SIG = "WGANI1733406267"
AJENA = "QSBJJ1734089362"

titulo("6) LOS 5 PROVEEDORES: una planta ajena de cada uno debe dar 403")
# Se deja a cliente1 sin ninguna planta, asi TODAS le son ajenas. Las de muestra se
# piden como admin, para no inventar ids que igual no existen en este entorno.
sql(f"DELETE FROM plantas_asociadas WHERE usuario_id={U1};")
for prov in ["goodwe", "solaredge", "victronenergy", "sungrow", "sigenergy"]:
    hc, r = api(ADMIN, "GET", f"/plants?proveedor={prov}")
    plantas = (r or {}).get("data") or []
    if not plantas:
        check(f"{prov}: obtener una planta de muestra", False, "el admin no devolvio ninguna")
        continue
    pid = str(plantas[0]["id"])
    hc_cli, _ = api(C1, "GET", f"/plant/power/realtime/{pid}?proveedor={prov}")
    hc_adm, _ = api(ADMIN, "GET", f"/plant/power/realtime/{pid}?proveedor={prov}")
    check(f"{prov:<14} cliente1 -> planta ajena {pid[:12]}: HTTP {hc_cli}", hc_cli == 403, "esperaba 403")
    check(f"{prov:<14} admin    -> la misma planta:      HTTP {hc_adm}", hc_adm == 200, "esperaba 200")

titulo("7) API KEY (esquema Token) respeta lo mismo que el JWT")
api(ADMIN, "POST", f"/usuarios/relacionar?idusuario={U1}&idplanta={SIG}&proveedor=Sigenergy")

filas = sql(f"SELECT api_key FROM api_accesos WHERE usuario_id={U1} LIMIT 1;")
if not filas:
    # La crea el propio usuario con su JWT.
    hc, r = api(C1, "GET", "/usuario/bearerToken")
    filas = sql(f"SELECT api_key FROM api_accesos WHERE usuario_id={U1} LIMIT 1;")

if filas:
    KEY = filas[0][0]   # se guarda ya con el prefijo: "Token <uuid>"
    hc, r = api(KEY, "GET", "/plants")
    vistas = {str(p.get("id")) for p in ((r or {}).get("data") or [])}
    check(f"api key cliente1: /plants devuelve solo la suya (ve {sorted(vistas)})", vistas == {SIG})
    hc, _ = api(KEY, "GET", f"/plant/power/realtime/{SIG}?proveedor=sigenergy")
    check(f"api key cliente1 -> planta suya:  HTTP {hc}", hc == 200)
    hc, _ = api(KEY, "GET", f"/plant/power/realtime/{AJENA}?proveedor=sigenergy")
    check(f"api key cliente1 -> planta ajena: HTTP {hc}", hc == 403)
else:
    check("api key de cliente1 existe", False, "no hay fila en api_accesos")

filas = sql("SELECT api_key FROM api_accesos WHERE api_scope='scope1' LIMIT 1;")
if filas:
    hc, _ = api(filas[0][0], "GET", f"/plant/power/realtime/{AJENA}?proveedor=sigenergy")
    check(f"api key ADMIN -> cualquier planta: HTTP {hc}", hc == 200)

titulo("8) CASOS RAROS")
hc, _ = api("", "GET", f"/plant/power/realtime/{SIG}?proveedor=sigenergy")
check(f"sin token: HTTP {hc}", hc in (401, 403), "deberia rechazar")

hc, _ = api("Bearer basura.no.valida", "GET", f"/plant/power/realtime/{SIG}?proveedor=sigenergy")
check(f"token invalido: HTTP {hc}", hc in (401, 403), "deberia rechazar")

hc, _ = api(C1, "POST", f"/usuarios/relacionar?idusuario={U1}&idplanta={AJENA}&proveedor=Sigenergy")
check(f"cliente NO puede auto-asignarse una planta: HTTP {hc}", hc == 403, "esperaba 403")
filas = sql(f"SELECT COUNT(*) FROM plantas_asociadas WHERE usuario_id={U1} AND planta_id='{AJENA}';")
check("y no se creo la fila en la BD", filas[0][0] == "0", f"hay {filas[0][0]}")

sql(f"UPDATE usuarios SET activo=0 WHERE usuario_id={U1};")
hc, _ = api(C1, "GET", f"/plant/power/realtime/{SIG}?proveedor=sigenergy")
check(f"usuario DESACTIVADO no entra ni a la suya: HTTP {hc}", hc in (401, 403), "esperaba rechazo")
sql(f"UPDATE usuarios SET activo=1 WHERE usuario_id={U1};")
hc, _ = api(C1, "GET", f"/plant/power/realtime/{SIG}?proveedor=sigenergy")
check(f"y al reactivarlo vuelve a entrar: HTTP {hc}", hc == 200)

# Al cliente se le responde igual exista o no la planta: decir "no es tuya"
# confirmaria que existe, y con ids correlativos eso permite mapear el parque ajeno.
hc, _ = api(C1, "GET", "/plant/power/realtime/NOEXISTE123?proveedor=sigenergy")
check(f"cliente -> planta inexistente: HTTP {hc}", hc == 403, "esperaba 403, sin confirmar si existe")
hc, _ = api(ADMIN, "GET", "/plant/power/realtime/NOEXISTE123?proveedor=sigenergy")
check(f"admin -> planta inexistente: HTTP {hc} (1111 de sigenergy traducido)", hc == 404)

resumen()
