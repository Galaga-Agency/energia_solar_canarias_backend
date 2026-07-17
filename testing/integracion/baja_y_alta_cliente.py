#!/usr/bin/env python3
"""
Dar de baja a un cliente y volver a darlo de alta tiene que recuperar SU ficha.

A un cliente no se le borra de verdad: se le pone eliminado=1 y en Zoho baja la marca
de "esta en la app". Si vuelve, se le quita el borrado y la marca sube otra vez. Se
restaura SIEMPRE la misma fila y nunca se crea otra, y eso no es un capricho: el JWT
lleva dentro el usuario_id y el email y dura 180 dias (el de las API keys no caduca),
asi que darle un id nuevo dejaria sin valor los tokens vivos del cliente y le soltaria
las plantas asociadas.

Lo que se probaba aqui era un fallo real: crearUser() le pasaba a usuarioEliminado()
la FILA que devuelve getIdUserPorEmail() en vez del usuario_id. PHP convierte ese array
a 1, asi que la comprobacion preguntaba SIEMPRE por el usuario 1. Salia bien de chiripa
-porque ese usuario no esta de baja y la respuesta coincidia con la buena- y encima
usuarioEliminado() devolvia lo contrario de lo que dice su nombre, con lo que los dos
fallos se tapaban entre si. Con el usuario 1 de baja, reactivar a un cliente contestaba
200 "Usuario creado" sin restaurar nada.

Se usa origen=crm + idApp a proposito: es la unica rama del alta que NO llama a Zoho.
Asi estas pruebas no crean clientes en el CRM ni gastan cuota, ni siquiera en un
entorno que tuviera las claves ZOHO_* puestas.

Uso:  export ESC_JWT_ADMIN='...'
      python3 testing/integracion/baja_y_alta_cliente.py
"""
import sys, os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from comun import CLIENTE2, api, check, php, resumen, sql, titulo, token_admin

ADMIN = token_admin()
UID = CLIENTE2["id"]
EMAIL = CLIENTE2["email"]

LLAMAR = (
    'require_once "/var/www/html/app/DBObjects/usuariosDB.php";'
    '$db = new UsuariosDB();'
    '$r = $db->usuarioEliminado(%s);'
    'echo var_export($r, true);'
)


def estado(uid):
    filas = sql(f"SELECT eliminado FROM usuarios WHERE usuario_id={uid};")
    return filas[0][0] if filas else None


def restaurar():
    sql(f"UPDATE usuarios SET eliminado=0 WHERE usuario_id IN (1,{UID});")


titulo("1) usuarioEliminado() dice lo que su nombre promete")

sql(f"UPDATE usuarios SET eliminado=1 WHERE usuario_id={UID};")
check(
    f"cliente {UID} de baja -> usuarioEliminado({UID}) = true",
    php(LLAMAR % UID) == "true",
    f"devolvio {php(LLAMAR % UID)!r}: antes devolvia false justo cuando SI estaba de baja",
)

sql(f"UPDATE usuarios SET eliminado=0 WHERE usuario_id={UID};")
check(
    f"cliente {UID} activo -> usuarioEliminado({UID}) = false",
    php(LLAMAR % UID) == "false",
    f"devolvio {php(LLAMAR % UID)!r}",
)

check(
    "un usuario que no existe -> null",
    php(LLAMAR % 99999999) == "NULL",
    f"devolvio {php(LLAMAR % 99999999)!r}",
)

titulo("2) el flujo de verdad: baja -> alta otra vez -> misma ficha")

try:
    sql(f"UPDATE usuarios SET eliminado=1 WHERE usuario_id={UID};")
    check(f"el cliente {UID} queda de baja", estado(UID) == "1", "no se pudo dar de baja")

    hc, r = api(ADMIN, "POST", "/usuarios", body={
        "email": EMAIL, "password": "PruebasESC2026!", "nombre": "Cliente",
        "apellido": "Pruebas Dos", "clase": "usuario",
        "origen": "crm", "idApp": str(UID),   # rama que no toca Zoho
    })
    check(f"vuelve a darse de alta: HTTP {hc}", hc == 200, f"esperaba 200 y llego {hc}")
    check("se le quita el borrado logico", estado(UID) == "0", f"eliminado sigue en {estado(UID)}")
    check(
        "y conserva SU usuario_id (si cambiara, sus tokens de 180 dias dejarian de valer)",
        bool(r) and (r.get("data") or {}).get("usuario_id") == UID,
        f"devolvio usuario_id={(r or {}).get('data', {}).get('usuario_id')}",
    )
    check(
        "no se ha creado una ficha nueva con ese email",
        len(sql(f"SELECT usuario_id FROM usuarios WHERE email='{EMAIL}';")) == 1,
        "hay mas de una fila con el mismo email",
    )

    titulo("3) y sigue funcionando aunque el usuario 1 este de baja")
    # El caso que destapaba el fallo: la comprobacion preguntaba siempre por el usuario
    # 1, asi que el estado de un admin que no pinta nada decidia si un cliente se podia
    # recuperar o no.
    sql(f"UPDATE usuarios SET eliminado=1 WHERE usuario_id={UID};")
    sql("UPDATE usuarios SET eliminado=1 WHERE usuario_id=1;")

    hc, r = api(ADMIN, "POST", "/usuarios", body={
        "email": EMAIL, "password": "PruebasESC2026!", "nombre": "Cliente",
        "apellido": "Pruebas Dos", "clase": "usuario",
        "origen": "crm", "idApp": str(UID),
    })
    check(
        "con el usuario 1 de baja, el cliente se recupera igual",
        estado(UID) == "0",
        f"eliminado sigue en {estado(UID)}: la reactivacion depende de un usuario ajeno",
    )
finally:
    # Pase lo que pase, la BD se queda como estaba: el usuario 1 es un admin real.
    restaurar()

check("el usuario 1 queda como estaba", estado(1) == "0", "OJO: se quedo de baja")
check(f"el cliente {UID} queda como estaba", estado(UID) == "0", "se quedo de baja")

titulo("4) dar de baja: si Zoho no acepta, aqui tampoco")

# appCrearClienteFalse() devolvia los errores como STRING (json_encode) y el exito como
# array. Su llamante los mira con isset($r['error']), que sobre un string es SIEMPRE
# false, asi que TODOS los fallos de Zoho se tragaban: la API contestaba 200 "Usuario
# eliminado" con el error escondido dentro de data, y el cliente quedaba de baja aqui y
# activo en el CRM.
#
# Se le pasa 0 a proposito: entra por la guarda `if (!$idApp)` y devuelve sin llamar a
# Zoho. Asi se comprueba el tipo de retorno sin tocar el CRM ni gastar cuota.
TIPO = (
    'require_once "/var/www/html/app/controllers/ZohoController.php";'
    '$z = new ZohoController();'
    '$r = $z->appCrearClienteFalse(0);'
    'echo is_array($r) ? "array" : gettype($r);'
    'echo "|";'
    'echo isset($r["error"]) ? "detectado" : "IGNORADO";'
)
tipo = php(TIPO)
check(
    f"appCrearClienteFalse devuelve un array, no un json string ({tipo.split('|')[0]})",
    tipo.startswith("array"),
    "si vuelve a ser string, isset($r['error']) es false y el error se traga",
)
check(
    "y su llamante puede ver el error",
    tipo.endswith("detectado"),
    "isset($r['error']) da false: el fallo de Zoho pasaria por exito",
)

try:
    # El cliente de pruebas es solo local (lo crea crear_usuarios_prueba.php, que no
    # sincroniza con Zoho), asi que NUNCA esta en el CRM: la busqueda no lo encuentra y
    # no se llega a hacer el PUT. Es seguro incluso con las claves ZOHO_* puestas.
    hc, r = api(ADMIN, "DELETE", f"/usuarios/{UID}")
    check(
        f"Zoho no acepta la baja -> HTTP {hc} (antes: 200 'Usuario eliminado')",
        hc == 500,
        f"esperaba 500 y llego {hc}: {(r or {}).get('message')!r}",
    )
    check(
        "y el cliente NO se queda de baja aqui",
        estado(UID) == "0",
        "quedo de baja en la app y activo en el CRM: los dos lados descuadrados",
    )
finally:
    restaurar()

resumen()
