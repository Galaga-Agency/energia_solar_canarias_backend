# Colección Bruno — ESC

Carpetas disponibles:

- **ESC Backend** — nuestra API (login y endpoints protegidos por token).
- **Sigenergy**, **Sungrow**, **SolarEdge V2** y **Victron VRM** — documentación oficial y requests directos de lectura a los proveedores.
- **Zoho CRM** — requests directos; las escrituras necesitan habilitar una variable de seguridad.
- **GoodWe SEMS+** — endpoints observados en el portal y usados por el backend. No ejecutar el login: invalida la sesión compartida con el backend.

Desde la terminal, sin abrir la app: `npx @usebruno/cli run "<carpeta>" --env EU` dentro de `bruno/`.

## Uso

1. Abre Bruno → *Open Collection* → selecciona la carpeta `bruno/`.
2. Arriba a la derecha, selecciona el environment **EU**.
3. Las credenciales reales van en `environments/EU.bru` (no se versiona; usa `EU.example.bru` como plantilla).

---

## ESC Backend

`base_url_esc` apunta por defecto a `http://localhost:8080` (el contenedor `esc_app` del `docker-compose.yml`). Todos los requests del backend llevan un guard que bloquea cualquier host que no sea loopback; la colección es para el stack local.

### Login

Tres formas de sacar el JWT (`esc_jwt`):

- **25 - Login con contraseña (directo)**: `POST /auth/password` con `esc_email` + `esc_password`.
  Devuelve el JWT al momento. Solo si el usuario tiene activado el método `password`
  (por defecto está apagado): actívalo con la **27** o en *Ajustes* de la web.
- **Enlace por correo** (lo que usa la web): en local, `docker/login-local.sh <email>` lo
  hace entero. El correo llega a Mailpit, en http://localhost:8025.
- **01 → 02** (login antiguo de la app móvil): `POST /login` manda un código al correo
  (en local, a Mailpit; también está en la tabla `token`). Pégalo en `esc_login_code` y
  ejecuta la **02** antes de 5 minutos. Si te equivocas de código, repite la 01.

Todas dan una **sesión renovable**: `esc_jwt` dura 15 minutos y Bruno guarda la cookie
de refresh. Renueva con la **28** (`POST /auth/refresh`) y sal con la **29**
(`POST /auth/logout`); después de salir, la 28 da 401.

La **26** muestra con qué métodos puede entrar el usuario y la **27** los cambia
(ver [documentacion/autenticacion.md](../documentacion/autenticacion.md)).

### Usuarios de pruebas tipo cliente

Para probar el filtro de "solo mis plantas" hay dos usuarios de clase 2 (`usuario`, solo vista). Son **dos** a propósito: con uno solo no se puede comprobar que un cliente no vea las plantas de otro.

| email | usuario_id | plantas asignadas |
|---|---|---|
| `cliente.pruebas@galagaagency.com` | `1086` | `WGANI1733406267` (Sigenergy), `3145637` (SolarEdge) |
| `cliente.pruebas2@galagaagency.com` | `1087` | `QSBJJ1734089362` (Sigenergy) |

La contraseña y el JWT del primero están en `environments/EU.bru` (`esc_cliente_password`, `esc_cliente_jwt`), que no se versiona. Los dos comparten contraseña. Son usuarios **solo de local**: si los necesitas en otro entorno, créalos allí.

Para renovar su JWT: `docker/login-local.sh cliente.pruebas@galagaagency.com` (enlace por Mailpit), o el 01 → 02 con su contraseña.

Con ese token, `GET /plants` devuelve solo las plantas asignadas, sin `?proveedor=`. Los requests 21 y 22 asignan y quitan plantas (hace falta el JWT de admin).

### Autenticación de los endpoints protegidos

El backend acepta **dos** esquemas en la cabecera `Authorization`:

| Cabecera | De dónde sale | Caduca |
|---|---|---|
| `Bearer {{esc_jwt}}` | login 01→02, 25 o enlace | 15 min (se renueva con la 28; la sesión dura hasta 180 días) |
| `Token {{esc_api_key}}` | request 03 | no |

El resto de requests usan `Bearer`. El **20** es el mismo endpoint pero con `Token`, como ejemplo.

- **03 - Crear API Key permanente**: `GET /usuario/bearerToken`. Solo funciona con Bearer JWT (con `Token` responde 403 a propósito). El scope depende del usuario: admin → `scope1`, cliente → `scope2`.

### Endpoints

Casi todos llevan `?proveedor=` (`goodwe|solaredge|victronenergy|sungrow|sigenergy`). Cambia `esc_proveedor` y `esc_plant_id` en el environment para alternar sin tocar los requests.

| # | Endpoint | Proveedores |
|---|---|---|
| 04 | `GET /usuario` | — |
| 05 | `GET /proveedores` | — (admin) |
| 06 | `GET /plants` | agregado (BD) |
| 07 | `GET /plants?proveedor=` | goodwe, solaredge, victronenergy |
| 08 | `GET /plants/details/{id}` | los 5 |
| 09 | `GET /plant/power/realtime/{id}` | los 5 |
| 10 | `GET /plant/alert?siteId=` | goodwe, victronenergy, sungrow* |
| 11 | `GET /plant/benefits/{id}` | solaredge, sungrow |
| 12 | `GET /plant/inventario/{id}` | goodwe, solaredge, victronenergy, sungrow |
| 13 | `GET /plant/overview/{id}` | goodwe, solaredge, victronenergy |
| 14 | `POST /plants/graficas` | sungrow, sigenergy** |
| 15 | `POST /plants/energy/{ids}` | solaredge |
| 16 | `POST /plant/grafica/comparacion/{id}` | solaredge |
| 17 | `POST /plant/grafica/bateria/{id}` | solaredge |
| 18 | `GET /logs` | — (admin) |
| 19 | `GET /usuarios` | — (admin) |
| 21 | `POST /usuarios/relacionar` | los 5 (admin) |
| 22 | `DELETE /usuarios/relacionar` | los 5 (admin) |

**En 21 y 22 los parámetros van en la QUERY, no en el body.** `RequestHelper::getParam()` solo mira query params y cabeceras; un body JSON se ignora en silencio y todo llega a `null` → responde 404 "El usuario no existe". Es el error más fácil de cometer con estos dos.

\* Sungrow devuelve solo el resumen de alarmas (contadores); el listado detallado necesita permisos E900 que la cuenta no tiene.
\*\* Sigenergy ya devuelve el histórico con la API oficial (`level` = `Day|Week|Month|Year|Lifetime`, `date` en `yyyy-MM-dd`).

Sigenergy está enrutado en 06, 07, 08, 09, 10, 12 y 14. **No** en 11 (benefits) ni 13 (overview): responden `404 - El proveedor no es valido`.

### Sigenergy: inventario (12) y alertas (10)

**Inventario** (`openapi/system/{id}/devices`) devuelve inversores y baterías con `serialNumber`, `deviceType`, `status`, `firmwareVersion` y `attrMap` (características según el tipo: los inversores traen `ratedActivePower`/`ratedVoltage`, las baterías `ratedEnergy`/`ratedChargePower`). Contrastado: las baterías de Coagrisan suman 8,06+5,38+8,06+8,06 = **29,56 kWh**, que es exactamente el `batteryCapacity` de la lista.

Ojo con la forma: **Sigenergy manda JSON dentro de JSON**. En este endpoint `data` es una *lista de strings*, y dentro cada `attrMap` es *otro string* con JSON. `SigenergyService::desanidar()` lo deshace, así que a la API llegan objetos de verdad.

**Alertas**: la Openapi **no tiene endpoint REST de alarmas** — las alarmas reales (con `alarmCode`, hora y `generation`/`recovery`) solo llegan por **push MQTT** y requieren suscripción + un receptor MQTT montado. Mientras tanto, `/plant/alert?proveedor=sigenergy` devuelve **incidencias deducidas** del estado de la planta y de sus equipos:

```json
"items": [
  {"deviceType": "Planta",   "status": "Faulty", "descripcion": "Planta averiado",   "origen": "estado_planta"},
  {"deviceType": "Inverter", "status": "Faulty", "descripcion": "Inverter averiado", "origen": "estado_equipo"}
],
"alarmas_en_tiempo_real": false
```

`alarmas_en_tiempo_real: false` y `origen` están para que el frontend **no las presente como alarmas reales**: no tenemos el código de alarma ni desde cuándo lleva fallando.

Se mira el estado de la planta **y** el de los equipos porque no son lo mismo: una planta caída (`Disconnection`) tiene sus equipos en `Normal` — el último estado conocido antes de perder la comunicación. Mirando solo los equipos, una planta incomunicada saldría con 0 incidencias. Verificado contra las 20 plantas: 20/20 coherentes con el `status` de la lista.

### Su enum de estados NO es el que documentan

La doc dice que los estados son `Standby | Normal | Fault | Offline`. Los valores **reales** son:

| Doc | Realidad |
|---|---|
| `Fault` | **`Faulty`** |
| `Offline` | **`Disconnection`** |

Se aceptan las dos grafías por si algún día lo alinean. Esto no es cosmético: con la grafía de la doc, una planta averiada devolvía **0 incidencias en silencio**.

Ojo con dos detalles del router: en `/plant/alert` el id va como query `siteId`, no en la ruta; y `/plants/graficas` usa un body distinto según el proveedor.

Los endpoints de admin devuelven 403 si el usuario del token no es administrador. Cliente y admin ven ramas distintas en `/plants` y `/plants/details` (el cliente solo sus plantas).

### Quién ve qué

La regla, aplicada en `Autenticacion::puedeVerPlanta()`: **el admin ve todas las plantas; el usuario normal solo las que tiene asignadas** en `plantas_asociadas`. Vale igual con `Bearer <jwt>` que con `Token <api_key>`.

Si la planta no es suya responde `403 - No tienes acceso a esta planta`. Es el mismo mensaje tanto si la planta no existe como si es de otro: decir "no es tuya" confirmaría que existe, y con ids correlativos eso permite mapear el parque ajeno planta por planta.

El guardián está en los endpoints que reciben una planta: `realtime`, `inventario`, `alert`, `overview`, `benefits`, `graficas`, `plants/energy`, `grafica/bateria` y `grafica/comparacion`. `/plants` y `/plants/details` ya filtraban por su cuenta.

Ojo con una incoherencia: ante una planta ajena, **`/plants/details` responde `404` y el resto `403`**. Los dos deniegan bien, pero el frontend tiene que contemplar los dos códigos para la misma situación. Es porque `details` usa su rama de cliente antigua, anterior al guardián.

`GET /plant/alert?proveedor=goodwe` es el caso raro: no lleva id porque devuelve las alarmas de **todo** el parque de GoodWe. Queda **solo para admin** hasta que se filtren por las plantas del usuario.

### Ojo con relacionar (21/22)

**No se comprueba que la planta exista.** Un id mal escrito responde `200` y crea una relación muerta: el admin cree que la asignó, el cliente no ve nada y nadie avisa. Aplica a los 5 proveedores.

---

## Zoho CRM

Orden: **01** (token) → **02** / **03** / **04** (lecturas).

- **02 - Buscar cliente por idApp (¿es nuevo?)**: pon el `usuario_id` de la app en
  `zoho_id_app`. `204` = no está en el CRM (nuevo); `200` = ya existe (guarda su id en
  `zoho_account_id` y muestra `Usuario_en_la_app`).
- **05 / 06** cambian `Usuario_en_la_app` a `Inactivo` / `Activo` **en el CRM real**. Están
  bloqueadas: pon `zoho_permitir_escritura = si` en el environment solo cuando de verdad
  quieras hacerlo.

Las credenciales `zoho_*` son las mismas que `ZOHO_*` de `config/.env`. Contexto completo:
[documentacion/zoho.md](../documentacion/zoho.md).

---

## GoodWe SEMS+

Orden: **01** (login) → cualquiera. Pon una planta en `goodwe_station_id` y un día en `goodwe_fecha`.

- **02** listado (portal antiguo; máximo 50 por página).
- **03 / 04 / 05**: curva de potencia, energía por día/mes/año y flujo, desde SEMS+. Son los
  que usa el backend desde que GoodWe dio de baja las gráficas del portal antiguo.

**Cada login invalida el token de producción** (GoodWe solo admite una sesión por cuenta).
Se recupera solo, pero no lances la 01 en bucle. Contexto:
[documentacion/goodwe.md](../documentacion/goodwe.md).

---

## Sigenergy

Orden: **01 → (03/04/05)**. El `02` renueva el token cuando caduque (~12 h).

- **01 - Auth - Get Token**: cifra la contraseña con AES-128-CBC (key/iv `sigensigensigenp`) en un pre-request script, hace `POST /auth/oauth/token` con Basic auth `sigen:sigen`, y guarda `sigen_access_token` / `sigen_refresh_token`.
- **02 - Auth - Refresh Token**: renueva con `grant_type=refresh_token`.
- **03 - Station Home**: lista de plantas de la cuenta.
- **04 - Station Energy Flow**: flujo de energía en tiempo real (lo que usa `/plant/power/realtime`).
- **05 - Station List**: la lista que usa `SigenergyService::getAllPlants`; guarda el id de la primera en `sigen_station_id`.
- **06 - Station Statistics Energy**: **pendiente**. Es lo que falta para las gráficas.

### Caché y límite de frecuencia (importante para el frontend)

La Openapi de Sigenergy limita a **1 acceso por estación cada 5 minutos** (y 10 peticiones/min por cuenta). Para no comernos ese error, el backend **cachea 5 minutos** cada respuesta y sirve la copia guardada en lugar de volver a llamar. Como los datos del proveedor tampoco cambian antes, no se pierde información.

Las respuestas afectadas llevan un bloque con el estado de la caché — en `/plants?proveedor=sigenergy` va en la raíz como `cache`, y en realtime/gráficas dentro de `data._cache`:

```json
"cache": {
  "cacheado": true,
  "obsoleto": false,
  "edad_seg": 42,
  "esperar_seg": 258,
  "esperar": "04:18",
  "proxima_actualizacion": "2026-07-16T11:05:12+01:00"
}
```

- `cacheado: false` → dato recién traído del proveedor.
- `esperar` → cuánto falta para que tenga sentido volver a pedir (el frontend puede mostrar "próxima actualización en 04:18" y no molestar antes).
- `obsoleto: true` → el proveedor falló y se está sirviendo la última respuesta buena, aunque haya caducado. Mejor un dato viejo que una pantalla rota.

Los demás proveedores no tienen este límite: su `cache` va a `null`.

Detalles de implementación (`app/services/CacheApiService.php`, tabla `api_cache`):
- La clave es proveedor + ruta completa, así que cada planta y cada `level`/`date` se cachean por separado.
- Se cachean también los errores de negocio estables (p.ej. `13008 station disconnect` de una planta apagada): el límite se aplica igual aunque la respuesta sea un error, así que no cachearlos machacaría la API justo con las plantas que no responden.
- NO se cachean los transitorios (`1201` rate-limit, `11002`/`11003` auth, timeouts): esos conviene reintentarlos. Quién es transitorio lo decide `SigenergyErrores::esTransitorio()`, no la caché.
- **Los históricos de periodos cerrados se cachean 24 h** (`CACHE_TTL_HISTORICO_SEG`): lo que pasó ayer no cambia. El periodo en curso (hoy, este mes, `Lifetime`) mantiene el TTL corto. Sin esto, navegar entre pestañas Día/Semana/Mes gastaba una llamada por pestaña **cada 5 minutos**.

### Qué límite de frecuencia hay DE VERDAD

La documentación dice, endpoint por endpoint, *"One account can only access one station once every five minutes"* y *"a maximum of 10 accesses per minute"* por cuenta. **Medido contra la API real, eso no es literal**:

- Tres llamadas idénticas seguidas (misma estación, mismo `history?level=Day`) → **las tres OK**. Si la regla de 1 cada 5 min se aplicara, la 2ª habría fallado.
- El `1201` sí es real y es **por estación**: con una estación bloqueada, otra que no se había tocado respondía al instante.
- El bloqueo **dura más de 75 s** (esperar el minuto de la ventana documentada no basta).

No se ha llegado a fijar la fórmula exacta (parece un cupo por estación de unas pocas llamadas, no 1) porque caracterizarla consume el propio cupo. Lo importante en la práctica: **la caché de 315 s deja el uso normal muy por debajo del límite**, y si aun así salta, el `1201` se traduce a un `429` con el tiempo de espera en vez de romper la pantalla.

### Errores traducidos (`app/utils/SigenergyErrores.php`)

Sigenergy responde **siempre HTTP 200** y mete el error real en el campo `code` del cuerpo (`0` = éxito). Antes, un fallo llegaba al frontend como `200 status=true` con `data` vacío y era imposible distinguir "no hay datos" de "esta planta no es tuya". Ahora cada código se traduce a un HTTP y un mensaje en castellano:

```json
{
  "status": false,
  "code": 404,
  "message": "Planta no encontrada o sin acceso",
  "data": {
    "codigo_sigenergy": 1111,
    "msg_sigenergy": "Station not permitted",
    "causa": "el systemId no existe o no pertenece a nuestra cuenta...",
    "reintentable": false,
    "documentado": true
  }
}
```

- `reintentable` → si tiene sentido volver a intentarlo (y, por tanto, si la caché lo guarda o no).
- `documentado: false` → el código **no** está en el "Error Code List" oficial; lo hemos visto en real.

Los que salen en el día a día:

| code | HTTP | Significa | Reintentable |
|---|---|---|---|
| `0` | 200 | Correcto | — |
| `1201` | 429 | **Access restriction**: el rate-limit por estación. Es el que motiva la caché | sí |
| `1111` | 404 | Planta no encontrada o de otra cuenta (también ante un id inventado) | no |
| `13008` | 200 | Planta desconectada. **No documentado**, visto en plantas apagadas | no |
| `1000` | 400 | Parámetros mal (normalmente bug nuestro: `level` fuera de rango) | no |
| `11002` | 503 | Cuenta bloqueada: 5 contraseñas mal en 60 min → 3 min de bloqueo | sí |
| `11003` | 502 | Fallo de autenticación | sí |

El catálogo completo (30 códigos: VPP, onboarding, permisos) está en `SigenergyErrores::CATALOGO`. Un código desconocido se marca como reintentable y **no** se cachea: ante algo que no sabemos interpretar, preferimos reintentar antes que servirlo 5 minutos.

### El bloqueo de CloudFront

Si un endpoint de datos (`device/*`, `data-process/*`) responde **HTML con 403**, no es el token: es CloudFront bloqueando tu IP tras varias peticiones seguidas. El endpoint de auth sigue respondiendo aunque los de datos estén bloqueados — ese contraste es como se distingue de un token caducado (que llegaría como JSON con `code != 0`).

Por eso el 06 sigue sin cerrarse: no se puede iterar sobre los parámetros desde aquí. Usa `sigen_test_server.php` (raíz del proyecto) desde el servidor, que tiene otra IP.

## Sungrow (iSolarCloud)

Dos formas de autenticar:

- **OAuth** (00 → 01, el `02` renueva): el paso 00 es manual, hay que abrir la URL en el navegador y copiar el `code` del redirect a `sungrow_auth_code`.
- **Login V1** (04): directo con usuario/password, sin navegador. **Es el que usa el backend** (`ProveedorTokenService::refreshSungrow`), justo por no depender del paso manual.

Con el login V1 el token **va en el BODY** como campo `token`, no como Bearer. El header `x-access-key` (secret) es obligatorio siempre.

- **03 - Power Station List**: lista de plantas. Ya trae los KPI en tiempo real, de ahí que `/plant/power/realtime` salga de aquí.
- **05 - Power Station Detail**: detalle de una planta (`sungrow_ps_id`).
- **06 - Device List**: equipos. Guarda el `ps_key` del inversor (`device_type` 1) en `sungrow_ps_key`.
- **07 - Device Point Minute Data**: series temporales. Es lo que hay detrás de `/plants/graficas?proveedor=sungrow`.

### Rarezas de iSolarCloud

- **`result_code` "1" es ÉXITO**, no un error. Los errores de token llegan como `010` / `er_token_login_invalid` con **HTTP 200** — por eso el backend no puede fiarse del 401 para detectar token caducado.
- Las series temporales son **por dispositivo**, no por planta: `getStationRealKpi` y los endpoints de chart dan E900 Unauthorized con el plan de esta cuenta. Por eso hay que pasar por el 06 antes del 07. Además la API limita la ventana, así que el backend la trocea en bloques de 2 h y capa el total a 48 h (en hora local de la planta, sin holgura).

- **`lang` es obligatorio o responde en chino.** Sin él, las unidades llegan como `度`/`万度`/`欧元` y los textos del inventario también (`电表`, `阳光电源股份有限公司`). Con `"lang": "_en_US"` en el body sale todo en inglés. Tiene que llevar **el guion bajo delante**: un valor mal escrito (`en_US`) no da error de idioma, devuelve `result_code` 010 (token inválido) y dispararía el auto-refresh del token para nada. `_es_ES` se acepta pero devuelve lo mismo que el inglés (MWh/EUR/kg son símbolos universales).

- **La API AUTOESCALA cada medida por planta.** El mismo `total_energy` llega como `39.7 kWh` en una planta y `48.364 MWh` en otra; en chino era `1.814 万度` (万 = 10.000). Leer el `value` ignorando el `unit` mezcla escalas y falsea los totales por factores de 1.000 o 10.000. `SungrowService::normalizarMedida()` lo pasa todo a unidades canónicas (kWh, kW, EUR, kg) y `getAllPlants` lo aplica antes de que los datos lleguen a nadie, así que en el backend los valores ya vienen comparables. Si pruebas los endpoints del proveedor **directamente desde esta colección**, los verás autoescalados: eso es normal, la normalización vive en nuestro código.

### Limitación conocida: gráficas de inversores híbridos

Hay dos tipos de inversor: `device_type` 1 (`Inverter`) y 14 (`Hybrid inverter`, con batería). **La mayoría del parque es híbrido.**

Para los del tipo 1, `getDevicePointMinuteDataList` devuelve series con normalidad. Para los híbridos responde `success` pero con **cero filas**, sea cual sea el `point` (probados p24, p1, p2, p13, p83, p14, p18) y sea cual sea el `ps_key` (el del dispositivo y el de la planta). Parece el mismo límite de plan de API que los endpoints E900, no un fallo nuestro.

Consecuencia: `/plants/graficas?proveedor=sungrow` devuelve `series: []` para esas plantas. Queda pendiente confirmarlo con Sungrow.

### Diagnóstico: plantas con VARIOS inversores (caso 6165627, 2026-10-21)

URBASER GRAN CANARIA tiene **dos** SG33CX-P2 y ningún contador. `getDeviceList` devuelve primero `Inverter2` (`6165627_1_2_3`), que está a 0 W todo el día (`p2` total = 0), y después `Inverter1` (`6165627_1_1_3`), que es el que produce. El backend se queda con el **primer** inversor de la lista (`localizarDispositivos`, `getFlujoContador`), así que:

- `/plant/power/realtime` sobrescribe `curr_power` con el `p24` del inversor parado → 0 kW y "Sin producción" en el diagrama, mientras la lista (que no pasa por ahí) dice 2,74 kW.
- La gráfica "Diario" pide minutos solo de ese inversor → línea plana y 0,00 kWh.

Peticiones de diagnóstico (solo lectura, usan `sungrow_access_token`):

- **09** lista los inversores y guarda sus `ps_key` en `sungrow_diag_inv_keys`.
- **10** `getDeviceRealTimeData` de todos: uno a 0 y otro produciendo; la suma de `p24` es la potencia de la planta.
- **11** `getDevicePointMinuteDataList` con la ventana que usa el backend para el último bloque de "Diario". `ps_key_list` admite varios `ps_key` en una sola llamada.

Además: `time_stamp`/`device_time` del minuto vienen en **hora local de la planta**, no en +08:00. La vista "Diario" manda `fechaFin` como `yyyy-mm-dd`, el backend la toma como 00:00 y le suma 12 h de holgura, así que **nunca pide nada después de las 12:00** (comprobado con 6020058 el 20/10: la serie se corta a las 12:00).

**Corregido el 2026-10-21** en `SungrowService` (tests en `testing/SungrowMultiInversorTest.php`):

- Tiempo real: una sola `getDeviceRealTimeData` con **todos** los inversores (`ps_key_list`) y `curr_power` = suma de sus `p24`. Si no llega dato se conserva el `curr_power` de la lista (nunca un 0 forzado), y `curr_power_update_time` es el `device_time` de la lectura (hora de Canarias), no la hora de la petición.
- Gráficas (Diario/Custom y Semana/Mes/Año): misma llamada con todos los inversores y suma por `time_stamp`. Tensiones (`p18`-`p20`, `p44`) y SOC (`p13141`) no se suman: se promedian sin contar el 0 de un inversor parado. El contador (`p80xx`) sigue pidiéndose aparte.
- Ventana del día: una fecha sola es el día entero (`000000`-`235959`, o hasta ahora si es hoy), sin la holgura de ±12 h. Verificado: 6020058 el 20/10 da serie de 00:00 a 23:55; 6165627 da 3,6 kW en tiempo real y curva no nula.

---

## Notas

- Los tokens se guardan con `bru.setVar()` (runtime), no se escriben a disco.
- Fechas de Sungrow: formato `YmdHis` (14 dígitos), p.ej. `20260715143000`.
