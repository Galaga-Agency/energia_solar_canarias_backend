# Sungrow iSolarCloud OpenAPI — notas de integración

Referencia práctica de la OpenAPI de Sungrow (iSolarCloud) **tal como responde a
nuestra cuenta**. La documentación oficial vive en el portal
`https://developer-api.isolarcloud.com` (SPA autenticada; no se puede exportar sin
sesión iniciada). Aquí se recoge lo verificado contra la API real, que es lo que
importa para el backend.

- Base (EU): `https://gateway.isolarcloud.eu`
- Credenciales en BD: `proveedores` (url, account, pwd) + `proveedor_oauth`
  (appkey, secret_key, application_id, cloud_id, redirect_uri).

## Envoltura común de toda llamada

Todas las peticiones son `POST` con JSON y llevan:

| Dónde | Campo | Valor |
|-------|-------|-------|
| Header | `x-access-key` | secret_key |
| Header | `Content-Type` | application/json |
| Body | `appkey` | appkey |
| Body | `sys_code` | `901` |
| Body | `lang` | `_en_US` (con guion bajo delante; si no, responde en chino) |
| Body | `token` | access token (Login V1) |

### Rarezas confirmadas

- **`result_code` `"1"` = ÉXITO**, no error. Los errores de token llegan como
  `010` / `er_token_login_invalid` con **HTTP 200**.
- **`result_code` `"009"` = falta un parámetro obligatorio**; el `result_msg` dice
  cuál (`er_missing_parameter:<campo>`). Se resuelve añadiéndolo.
- La API **autoescala** cada medida por planta (kWh/MWh, 万 = 10.000). Se normaliza
  en `SungrowService::normalizarMedida()`.
- **`lang` mal escrito** (`en_US` sin guion) no da error de idioma: devuelve `010`
  (token inválido) y dispara un refresh de token para nada.

## Autenticación

- **Login V1** (usuario/contraseña, sin OAuth navegador) es lo que usa el backend
  (`ProveedorTokenService::refreshSungrow`). El token va en el **body** como
  `token`, no como Bearer. El `x-access-key` es obligatorio siempre.
- El token **no se persiste** en BD: se genera al vuelo.

## Endpoints usados por el backend

| Función | Endpoint | Notas |
|---------|----------|-------|
| Lista de plantas | `/openapi/getPowerStationList` | trae KPI en tiempo real |
| Detalle de planta | `/openapi/getPowerStationDetail` | por `ps_id` |
| Equipos (inventario) | `/openapi/getDeviceList` | de aquí sale el `ps_key` del inversor |
| Serie intradía (gráfica) | `/openapi/getDevicePointMinuteDataList` | ver abajo |
| Serie día/mes/año | `/openapi/getDevicePointsDayMonthYearDataList` | ver abajo |

### getDeviceList — equipos y `ps_key`

Las series son **por dispositivo**, no por planta, así que primero hay que sacar el
`ps_key` del inversor. Formato del `ps_key`: `psId_deviceType_typeId_deviceCode`
(p.ej. `5921072_7_1_1` → el `7` es el `device_type`).

`device_type`: **1 = Inverter**, **14 = Hybrid inverter** (con batería). El backend
solo acepta 1 y 14 como inversor (`SungrowService::TIPOS_INVERSOR`). Otros tipos
(medidor, combiner…) no dan serie de inversor.

### getDevicePointMinuteDataList — intradía (Day / Custom)

Datos por minuto de un punto del inversor. Es lo que hay detrás de
`/plants/graficas?proveedor=sungrow` hoy.

```jsonc
{
  "appkey": "...", "sys_code": "901", "lang": "_en_US", "token": "...",
  "ps_key_list": ["5921072_7_1_1"],
  "points": "p24",                 // p24 = potencia activa (W)
  "start_time_stamp": "20260727000000",  // YmdHis (14 dígitos)
  "end_time_stamp":   "20260727235959",
  "minute_interval": 5             // 5 | 15 | 30 | 60
}
```

- La API **limita la ventana** ("exceeds maximum limit"): el backend la trocea en
  bloques de 2 h y capa el total a 24 h.
- Para **híbridos** (`device_type` 14) responde `success` pero con **cero filas**
  (limitación observada; pendiente de confirmar con Sungrow).

### getDevicePointsDayMonthYearDataList — agregado (Week / Month / Year)

**Endpoint AUTORIZADO y VERIFICADO** contra la API real (para inversores tipo 1).

```jsonc
{
  "appkey": "...", "sys_code": "901", "lang": "_en_US", "token": "...",
  "ps_key_list": ["6020058_1_1_1"],
  "data_point": "p24",   // id del punto
  "data_type": "1",      // acompaña a query_type (mismo valor funciona)
  "query_type": "1",     // GRANULARIDAD (ver tabla) -> tambien es la clave del valor
  "start_time": "20260701",  // formato SEGUN query_type (ver tabla)
  "end_time":   "20260727"
}
```

**`query_type` fija la granularidad y el formato de fecha** (verificado):

| Vista        | query_type | start/end   | Límite de rango | time_stamp |
|--------------|:----------:|-------------|-----------------|------------|
| Día / Semana | `1`        | `yyyyMMdd`  | ≤ 100 días      | `yyyyMMdd` |
| Mes          | `2`        | `yyyyMM`    | (meses)         | `yyyyMM`   |
| Año          | `3`        | `yyyy`      | (años)          | `yyyy`     |

Forma del `result_data` (el valor va bajo una clave = query_type):

```jsonc
{ "6020058_1_1_1": { "p24": [
    { "1": "1323.0000", "time_stamp": "20260701" },   // query_type 1 -> clave "1"
    { "1": "1336.7569", "time_stamp": "20260702" }
]}}
```

Errores útiles como guía: `010 the query time interval is more than 100` (rango
diario > 100 días); `010 Parameter:start_time and end_time length` (formato de fecha
no coincide con el query_type).

## ⛔ HALLAZGO CLAVE: los inversores híbridos NO devuelven series

**Verificado con prueba comparativa directa contra la API** (mismo endpoint, misma
ventana de 2 h, mismo punto p24, ambos ONLINE):

| Planta / inversor | device_type | Serie de minuto | Serie día/mes/año |
|-------------------|:-----------:|-----------------|-------------------|
| 6020058 (tipo 1)  | 1           | ✅ datos        | ✅ datos          |
| Arturo 6145767 (híbrido) | 14   | ❌ `{}` vacío   | ❌ `{}` vacío     |

La OpenAPI de esta cuenta **NO sirve series temporales de los `device_type 14`
(híbridos)**, ni por minuto ni agregadas, con ningún punto (probados p24,p1,p2,
p13,p83). Los tipo 1 las devuelven sin problema. No es el codigo, ni los parametros,
ni que esten offline: es una **limitacion del plan de API de Sungrow para hibridos**.

**Impacto:** de las 12 plantas de la cuenta, **9 son hibridas** (tipo 14) y solo 3
son tipo 1 (6020058, 5566627, 5178170). Por eso "las graficas no van": la mayoria
del parque no devuelve datos por la OpenAPI. El panel de iSolarCloud (captura de
"Arturo") pinta esas curvas porque usa el acceso INTERNO de Sungrow, no la OpenAPI
de terceros.

**Acción:** reclamar a Sungrow habilitar el acceso a series de inversores hibridos
(device_type 14) en el plan de la cuenta / API package. Es gestion con Sungrow, no
desarrollo. Para inversores tipo 1 el panel completo (Day/Week/Month/Year + intervalo)
es construible YA.

### Catálogo de puntos (pendiente)

Para el filtro de medidas del panel (MPPT V/A, fase A V/A, AC V/A, potencia total
DC/activa, potencia carga/descarga batería) hace falta mapear cada etiqueta a su
`pXX`. Hoy solo se conoce `p24` = potencia activa (W). Endpoints candidatos para
enumerarlos: `getPowerDevicePointNames`, `getDevicePoints`, `getPowerDevicePointInfo`.

## Panel objetivo (Day/Week/Month/Year/Custom + 5/15/30/60 min)

| Filtro UI | Endpoint | Intervalo 5/15/30/60 |
|-----------|----------|----------------------|
| Day / Custom (≤24 h) | getDevicePointMinuteDataList | ✅ aplica |
| Week / Month / Year | getDevicePointsDayMonthYearDataList | ❌ (agregado por día/mes/año) |

## Estado

- [x] Intradía (Day/Custom) con intervalo: funciona en **tipo 1**; vacío en híbridos.
- [x] Endpoint día/mes/año: **verificado** (query_type 1/2/3), funciona en **tipo 1**.
- [x] Causa de "las gráficas no van": híbridos (tipo 14) no dan series por la OpenAPI.
- [ ] Reclamar a Sungrow el acceso a series de híbridos (device_type 14).
- [ ] Catálogo de puntos para el filtro de medidas (getPowerDevicePointNames).
- [ ] Implementar `level` (Day/Week/Month/Year) en el backend para tipo 1.

## Cómo exportar la doc oficial a PDF

El portal no se puede scrapear sin sesión. Para tener el PDF oficial junto a este
archivo (como `documentacion/sigenergy/`): abre el portal logueado en el navegador,
navega a la API y usa **Imprimir → Guardar como PDF**, y déjalo en esta carpeta.
