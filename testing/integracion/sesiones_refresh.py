#!/usr/bin/env python3
"""Prueba local de sesiones: HTTP + MySQL, sin proveedores ni correos externos.
Ejecutar desde el repo: python3 testing/integracion/sesiones_refresh.py
Crea una cuenta aislada y la elimina al terminar. Solo localhost:3001/8080.
"""
import base64
import concurrent.futures
import hashlib
import http.cookiejar
import json
import os
import subprocess
import urllib.error
import urllib.request
import uuid

BASE = os.environ.get('ESC_BASE_URL', 'http://localhost:3001/backend-api')
if not BASE.startswith(('http://localhost:', 'http://127.0.0.1:')):
    raise SystemExit('Esta prueba solo admite Docker local')
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '../..'))
EMAIL = 'refresh-' + uuid.uuid4().hex + '@example.invalid'
PASSWORD = 'RefreshLocal2026!'
checks = 0


def php(code):
    r = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', '-r',
        'require "vendor/autoload.php"; require "app/models/conexion.php"; $db=Conexion::getInstance()->getConexion();' + code],
        cwd=ROOT, capture_output=True, text=True, check=True)
    return r.stdout.strip()


def sql(query):
    # Only generated fixture IDs/hex values enter these local SQL statements.
    return php('$r=$db->query(' + json.dumps(query) + '); if(!$r)throw new Exception($db->error); if($r instanceof mysqli_result)echo json_encode($r->fetch_all(MYSQLI_ASSOC));')


def client():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar)), jar


def call(opener, path, body=None, token=None, headers=None):
    h = {'Content-Type': 'application/json', 'Origin': 'http://localhost:3001'}
    if token: h['Authorization'] = 'Bearer ' + token
    h.update(headers or {})
    request = urllib.request.Request(BASE + path, data=json.dumps(body).encode() if body is not None else None, headers=h)
    try: response = opener.open(request, timeout=20)
    except urllib.error.HTTPError as error: response = error
    return response.status, json.loads(response.read()), response.headers


def check(label, condition):
    global checks
    if not condition: raise AssertionError(label)
    checks += 1
    print('OK', label)


def login(opener, modern=True):
    sql("DELETE FROM login_attempts WHERE identificador='" + EMAIL + "'")
    body = {'email': EMAIL, 'password': PASSWORD}
    if modern: body['sessionMode'] = 'refresh'
    status, payload, headers = call(opener, '/auth/password', body)
    check('login sin sessionMode' if not modern else 'login renovable', status == 200)
    return payload['data'], headers


uid = int(php('$hash=password_hash(' + json.dumps(PASSWORD) + ', PASSWORD_BCRYPT); $email=' + json.dumps(EMAIL) + ';'
    '$st=$db->prepare("INSERT INTO usuarios(email,password_hash,clase_id,nombre,activo,eliminado)VALUES(?,?,2,\'Refresh test\',1,0)");'
    '$st->bind_param("ss",$email,$hash);$st->execute();echo $db->insert_id;'))
try:
    sql(f"INSERT INTO usuarios_metodos_acceso(usuario_id,metodo,activo)VALUES({uid},'password',1)")
    op, jar = client()
    sin_modo, _ = login(op, False)
    check('sin sessionMode tambien se crea una sesion renovable', sin_modo.get('refreshEnabled') is True and bool(sin_modo.get('sessionId')))
    # Un JWT largo sin sesion, como los que se emitian antes, ya no vale.
    antiguo = php('echo Firebase\\JWT\\JWT::encode(["iat"=>time(),"exp"=>time()+86400*30,"data"=>["id"=>' + str(uid) + ',"email"=>"' + EMAIL + '"]], $_ENV["SECRET_KEY"], $_ENV["ALGORITHM"]);')
    check('JWT antiguo sin sesion se rechaza', call(op, '/usuario', token=antiguo)[0] == 403)
    data, headers = login(op)
    jwt = data['tokenIdentificador']; sid = data['sessionId']; old = list(jar)[0].value
    claims = json.loads(base64.urlsafe_b64decode(jwt.split('.')[1] + '=='))
    check('acceso dura 15 minutos', claims['exp'] - claims['iat'] == 900 and claims['sid'] == sid)
    cookie = headers.get('Set-Cookie', '')
    check('cookie HttpOnly Strict sin dominio', 'HttpOnly' in cookie and 'SameSite=Strict' in cookie and 'Domain=' not in cookie)
    check('refresh no aparece en JSON', old not in json.dumps(data))
    stored = json.loads(sql(f"SELECT token_hash FROM auth_refresh_tokens WHERE sesion_id='{sid}'"))[0]['token_hash']
    check('solo SHA256 en base de datos', stored == hashlib.sha256(old.encode()).hexdigest())
    check('acceso autenticado', call(op, '/usuario', token=jwt)[0] == 200)
    expired = php('echo Firebase\\JWT\\JWT::encode(["iat"=>time()-1000,"exp"=>time()-100,"sid"=>"' + sid + '","token_use"=>"access","data"=>["id"=>' + str(uid) + ',"email"=>"' + EMAIL + '"]], $_ENV["SECRET_KEY"], $_ENV["ALGORITHM"]);')
    check('acceso expirado se rechaza sin esperar 15 minutos', call(op, '/usuario', token=expired)[0] == 403)

    check('rechaza origen ajeno', call(op, '/auth/refresh', {}, headers={'Origin':'https://evil.example'})[0] == 403)
    check('rechaza formulario CSRF', call(op, '/auth/logout', {}, headers={'Content-Type':'text/plain'})[0] == 403)
    check('renovacion valida', call(op, '/auth/refresh', {})[0] == 200)
    check('rota el secreto', list(jar)[0].value != old)
    raw, _ = client()
    status, _, replay_headers = call(raw, '/auth/refresh', {}, headers={'Cookie':'esc_refresh_local='+old})
    check('carrera devuelve conflicto sin secreto ni borrar cookie', status == 409 and not replay_headers.get('Set-Cookie'))
    h = lambda secreto: hashlib.sha256(secreto.encode()).hexdigest()
    # Un token ya usado hace mas de 5 s es robo o replay: no hay reintento, se revoca la familia.
    sql(f"UPDATE auth_refresh_tokens SET consumido_en=UNIX_TIMESTAMP()-10 WHERE token_hash='{h(old)}'")
    otro, otro_jar = client()
    status, _, _ = call(otro, '/auth/refresh', {}, headers={'Cookie':'esc_refresh_local='+old})
    check('token ya usado (fuera de la carrera) se rechaza', status == 401)
    check('y no entrega ningun secreto nuevo', not list(otro_jar))
    check('la familia queda revocada: la cookie vigente tambien falla', call(op, '/auth/refresh', {})[0] == 401)
    check('JWT revocado deja de funcionar', call(op, '/usuario', token=jwt)[0] == 403)
    # Replay de verdad: el sucesor ya se uso, alguien mas tiene la familia.
    login(op)
    primero = list(jar)[0].value
    check('renovacion valida tras login', call(op, '/auth/refresh', {})[0] == 200)
    sql(f"UPDATE auth_refresh_tokens SET consumido_en=UNIX_TIMESTAMP()-10 WHERE token_hash='{h(primero)}'")
    check('renovacion con el sucesor', call(op, '/auth/refresh', {})[0] == 200)
    check('replay con sucesor ya usado revoca familia', call(client()[0], '/auth/refresh', {}, headers={'Cookie':'esc_refresh_local='+primero})[0] == 401)
    check('refresh vigente queda revocado', call(op, '/auth/refresh', {})[0] == 401)
    # Si falla algo al preparar la respuesta (perfil, cabeceras), no se consume nada.
    login(op)
    actual = list(jar)[0].value
    php('require "app/services/SesionWebService.php"; try { (new SesionWebService())->renovar(' + json.dumps(actual) + ', function () { throw new RuntimeException("fallo simulado"); }); } catch (RuntimeException $e) {}')
    fila = json.loads(sql(f"SELECT consumido_en FROM auth_refresh_tokens WHERE token_hash='{h(actual)}'"))[0]
    check('un fallo al preparar la respuesta no consume el refresh', fila['consumido_en'] is None)
    check('y la cookie actual sigue renovando', call(op, '/auth/refresh', {})[0] == 200)
    data, _ = login(op)
    jwt = data['tokenIdentificador']
    check('logout revoca', call(op, '/auth/logout', {})[0] == 200)
    check('logout borra cookie', len(list(jar)) == 0)
    check('logout invalida acceso inmediatamente', call(op, '/usuario', token=jwt)[0] == 403)
    data, _ = login(op)
    sid = data['sessionId']
    sql(f"UPDATE auth_sesiones SET limite_absoluto=UNIX_TIMESTAMP()-1 WHERE id='{sid}'")
    check('limite absoluto', call(op, '/auth/refresh', {})[0] == 401)
    data, _ = login(op)
    sql(f"UPDATE auth_sesiones SET ultimo_uso=UNIX_TIMESTAMP()-90*86400-1 WHERE id='{data['sessionId']}'")
    check('limite inactividad', call(op, '/auth/refresh', {})[0] == 401)
    data, _ = login(op)
    sql(f'UPDATE usuarios SET activo=0 WHERE usuario_id={uid}')
    check('cuenta inactiva invalida JWT', call(op, '/usuario', token=data['tokenIdentificador'])[0] == 403)
    check('cuenta inactiva invalida refresh', call(op, '/auth/refresh', {})[0] == 401)
    sql(f'UPDATE usuarios SET activo=1 WHERE usuario_id={uid}')
    data, _ = login(op)
    sql(f"UPDATE usuarios SET password_hash=CONCAT(password_hash,'x') WHERE usuario_id={uid}")
    check('cambio contraseña invalida JWT', call(op, '/usuario', token=data['tokenIdentificador'])[0] == 403)
    check('cambio contraseña invalida refresh', call(op, '/auth/refresh', {})[0] == 401)
    code = php('require "app/services/MagicLinkService.php"; echo (new MagicLinkService())->emitirHandoff(' + str(uid) + ');')
    status, data, _ = call(op, '/auth/handoff', {'code':code,'sessionMode':'refresh'})
    check('handoff tambien crea sesion renovable', status == 200 and data['data']['refreshEnabled'])
    check('handoff de un solo uso', call(op, '/auth/handoff', {'code':code,'sessionMode':'refresh'})[0] == 401)
    # Concurrent requests use the exact same pre-rotation secret, without sharing cookie jars.
    old = list(jar)[0].value
    def rotate(_): return call(client()[0], '/auth/refresh', {}, headers={'Cookie':'esc_refresh_local='+old})[0]
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool: statuses = list(pool.map(rotate, range(4)))
    check('renovacion concurrente exactamente un ganador', statuses.count(200) == 1 and statuses.count(409) == 3)
    print(f'{checks} comprobaciones correctas')
finally:
    sql(f'DELETE FROM auth_sesiones WHERE usuario_id={uid}')
    sql(f'DELETE FROM auth_handoffs WHERE usuario_id={uid}')
    sql(f'DELETE FROM usuarios_metodos_acceso WHERE usuario_id={uid}')
    sql(f'DELETE FROM usuarios WHERE usuario_id={uid}')
    sql("DELETE FROM login_attempts WHERE identificador='" + EMAIL + "'")
