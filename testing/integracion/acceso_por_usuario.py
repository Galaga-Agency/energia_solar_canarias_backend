#!/usr/bin/env python3
"""
Comprueba la regla de acceso: el admin ve todas las plantas, el usuario normal solo
las suyas.

La BD manda: se lee `plantas_asociadas` y de ahi se DEDUCE que debe ver cada usuario;
luego se compara con lo que devuelve la API. Asi no se contrasta contra lo que uno
cree que deberia pasar, sino contra el dato real.

Hacen falta DOS clientes: con uno solo no se detecta lo importante, que es que un
cliente no vea las plantas de otro.

Uso:  export ESC_JWT_ADMIN='...'
      python3 testing/integracion/acceso_por_usuario.py
"""
import sys, os, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from comun import (CLIENTE1, CLIENTE2, api, check, login, resumen, sql, titulo,
                   token_admin)

ADMIN = token_admin()
C1 = login(CLIENTE1["id"], CLIENTE1["email"])
C2 = login(CLIENTE2["id"], CLIENTE2["email"])
U1, U2 = CLIENTE1["id"], CLIENTE2["id"]

SIG_A, SIG_B = "WGANI1733406267", "QSBJJ1734089362"   # dos plantas de Sigenergy
SE_A = "3145637"                                       # una de SolarEdge

titulo("PREPARACION: reparto plantas entre los dos clientes")
sql(f"DELETE FROM plantas_asociadas WHERE usuario_id IN ({U1},{U2});")
for u, planta, prov in [(U1, SIG_A, "Sigenergy"), (U1, SE_A, "SolarEdge"), (U2, SIG_B, "Sigenergy")]:
    hc, r = api(ADMIN, "POST", f"/usuarios/relacionar?idusuario={u}&idplanta={planta}&proveedor={prov}")
    check(f"relacionar {planta} ({prov}) -> usuario {u}", hc == 200 and r.get("status"), f"HTTP {hc}")

print("\nLo que dice la BD (fuente de verdad):")
esperado = {}
for uid, planta, prov in sql(
        f"SELECT pa.usuario_id, pa.planta_id, p.nombre FROM plantas_asociadas pa "
        f"JOIN proveedores p ON p.id=pa.proveedor_id WHERE pa.usuario_id IN ({U1},{U2}) "
        f"ORDER BY pa.usuario_id;"):
    esperado.setdefault(int(uid), set()).add(planta)
    print(f"  usuario {uid}: {planta:<20} ({prov})")

titulo("1) /plants -> cada uno ve EXACTAMENTE lo que dice la BD")
for uid, tok, nombre in [(U1, C1, "cliente1"), (U2, C2, "cliente2")]:
    hc, r = api(tok, "GET", "/plants")
    vistas = {str(p.get("id")) for p in (r.get("data") or [])}
    esp = esperado.get(uid, set())
    check(f"{nombre} ve {sorted(esp)}", vistas == esp, f"ve {sorted(vistas)}")

hc, r = api(ADMIN, "GET", "/plants?proveedor=sigenergy")
n = len(r.get("data") or [])
check(f"admin ve todas las de sigenergy ({n})", n > 1, "el admin no deberia estar filtrado")

titulo("2) AISLAMIENTO ENTRE CLIENTES: la planta de uno es ajena para el otro")
casos = [
    ("cliente1", C1, SIG_A, 200, "suya"),
    ("cliente1", C1, SIG_B, 403, "de cliente2"),
    ("cliente2", C2, SIG_B, 200, "suya"),
    ("cliente2", C2, SIG_A, 403, "de cliente1"),
]
endpoints = [
    ("realtime",   "GET",  "/plant/power/realtime/{id}?proveedor=sigenergy", None),
    ("inventario", "GET",  "/plant/inventario/{id}?proveedor=sigenergy", None),
    ("alert",      "GET",  "/plant/alert?siteId={id}&proveedor=sigenergy", None),
    ("details",    "GET",  "/plants/details/{id}?proveedor=sigenergy", None),
    ("graficas",   "POST", "/plants/graficas?proveedor=sigenergy", {"level": "Day"}),
]
for quien, tok, planta, esp_hc, nota in casos:
    for nombre, met, ruta, body in endpoints:
        b = dict(body, id=planta) if body else None
        hc, _ = api(tok, met, ruta.format(id=planta), b)
        # `details` deniega con 404 en vez de 403 porque usa su rama de cliente
        # antigua, anterior al guardian. Deniega bien, pero son dos codigos para la
        # misma situacion; si algun dia se unifica, quitar esta excepcion.
        aceptado = (hc == esp_hc) or (nombre == "details" and esp_hc == 403 and hc == 404)
        check(f"{quien} -> {nombre} de {planta[:8]} ({nota}): HTTP {hc}", aceptado, f"esperaba {esp_hc}")
    time.sleep(0.2)

titulo("3) LOS DATOS SON LOS DE LA PLANTA PEDIDA (no los de otra)")
for quien, tok, planta in [("cliente1", C1, SIG_A), ("cliente2", C2, SIG_B)]:
    hc, r = api(tok, "GET", f"/plants/details/{planta}?proveedor=sigenergy")
    devuelto = (r.get("data") or {}).get("systemId")
    check(f"{quien}: details de {planta[:8]} devuelve ese systemId", devuelto == planta, f"devolvio {devuelto}")

    hc, r = api(tok, "GET", f"/plant/inventario/{planta}?proveedor=sigenergy")
    eq = (r.get("data") or {}).get("data") or []
    ids = {e.get("systemId") for e in eq if isinstance(e, dict)}
    check(f"{quien}: inventario de {planta[:8]} son equipos de esa planta ({len(eq)} equipos)",
          ids == {planta}, f"systemIds={ids}")

titulo("4) DESRELACIONAR: deja de verla al instante, y la BD lo refleja")
hc, r = api(ADMIN, "DELETE", f"/usuarios/relacionar?idusuario={U1}&idplanta={SIG_A}&proveedor=Sigenergy")
check("admin desrelaciona la sigenergy de cliente1", hc == 200 and r.get("status"), f"HTTP {hc}")

filas = sql(f"SELECT COUNT(*) FROM plantas_asociadas WHERE usuario_id={U1} AND planta_id='{SIG_A}';")
check("la fila ya no esta en la BD", filas[0][0] == "0", f"quedan {filas[0][0]}")

hc, r = api(C1, "GET", "/plants")
vistas = {str(p.get("id")) for p in (r.get("data") or [])}
check(f"cliente1 ya no la ve en /plants (ve {sorted(vistas)})", SIG_A not in vistas)

hc, _ = api(C1, "GET", f"/plant/power/realtime/{SIG_A}?proveedor=sigenergy")
check(f"cliente1 ya no puede leer sus datos: HTTP {hc}", hc == 403, "esperaba 403")

hc, _ = api(ADMIN, "GET", f"/plant/power/realtime/{SIG_A}?proveedor=sigenergy")
check(f"el admin si sigue pudiendo: HTTP {hc}", hc == 200)

titulo("5) REASIGNAR: al pasarla a cliente2, cliente1 la pierde")
hc, r = api(ADMIN, "POST", f"/usuarios/relacionar?idusuario={U2}&idplanta={SE_A}&proveedor=SolarEdge")
check("admin asigna a cliente2 la solaredge que era de cliente1", hc == 200 and r.get("status"), f"HTTP {hc}")

duenos = [f[0] for f in sql(f"SELECT usuario_id FROM plantas_asociadas WHERE planta_id='{SE_A}';")]
check(f"en la BD la planta tiene un unico dueno y es {U2} (tiene {duenos})", duenos == [str(U2)])

hc, r = api(C1, "GET", "/plants")
v1 = {str(p.get("id")) for p in (r.get("data") or [])}
check(f"cliente1 ya NO la ve (ve {sorted(v1)})", SE_A not in v1)

hc, r = api(C2, "GET", "/plants")
v2 = {str(p.get("id")) for p in (r.get("data") or [])}
check(f"cliente2 SI la ve (ve {sorted(v2)})", SE_A in v2)

hc, _ = api(C1, "GET", f"/plant/overview/{SE_A}?proveedor=solaredge")
check(f"cliente1 ya no lee sus datos: HTTP {hc}", hc == 403)

resumen()
