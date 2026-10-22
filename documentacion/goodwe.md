# GoodWe

## Situación (octubre de 2026)

GoodWe ha dado de baja su portal antiguo, **SEMS** (`www.semsportal.com`). Al entrar sale un
aviso de *"Platform Service Discontinuation"* que remite a **SEMS+**
(`eu-semsplus.goodwe.com`). La API del portal antiguo, en la que se basaba la integración
(una API sin documentar, la de su app móvil), ha quedado así:

| Qué | Endpoint del portal antiguo | Estado |
|---|---|---|
| Login | `v1/Common/CrossLogin` | Funciona |
| Listado de plantas | `PowerStationMonitor/QueryPowerStationMonitor` | Funciona (máx. 50 por página) |
| Detalle | `v3/PowerStation/GetPlantDetailByPowerstationId` | Funciona |
| Flujo de energía | `v2/PowerStation/GetPowerflow` | Funciona |
| Equipos | `v3/PowerStation/GetInverterAllPoint` | Funciona |
| Alarmas | `SmartOperateMaintenance/GetPowerStationWariningInfoByMultiCondition` | Funciona |
| **Curva de potencia** | `v2/Charts/GetPlantPowerChart` | **`lines: null` en todas las plantas** |
| **Energía por día/mes/año** | `v2/Charts/GetChartByPlant` | **`lines: null` en todas las plantas** |

Con las gráficas vacías se quedaban sin datos el bloque "Hoy", la pestaña "Producción y
consumo" y el cálculo de ingresos reales con la tarifa del cliente.

## Cómo está resuelto

SEMS+ sirve esos datos desde su gateway, `https://eu-gateway.semsportal.com/web/sems/sems-plant/api/`,
y **acepta el mismo token que da `CrossLogin`** en la cabecera `token` (JSON con `uid`,
`timestamp`, `token`, `client`, `version` y `language`). La web de SEMS+ manda además una
`X-Signature`, pero el gateway no la exige. Endpoints sacados del inspector de red de SEMS+:

| Qué | Endpoint SEMS+ | Respuesta |
|---|---|---|
| Curva de potencia | `POST v1/hems/power/statisticsAndPreV2` | Por minuto, en kW: `pSystem` (producción), `pConsum` (consumo), `pGrid` (red) |
| Energía | `POST stations/statistics` (`dimension`: `day`, `month` o `year`) | kWh por periodo + totales (`production`, `proGrid`, `proPurchase`, `proSelfConsum`...) |
| Flujo | `GET stations/flow?stationId=` | kW ahora: `pSystem`, `pGrid`, `pConsum` (sin batería) |

`app/services/GoodWeSemsPlus.php` **traduce SEMS+ a la forma de respuesta del portal
antiguo** (`data.lines[]` con `xy`). Así ni el frontend ni `precioRealDelCliente()` han
tenido que cambiar. Esto es lo que hace con cada cosa:

- **Curva del día:** agrupa los minutos en tramos de 15 min (96 puntos, lo que espera el
  frontend), pasa a vatios como el portal antiguo y no manda las horas que aún no han pasado.
- **Energía:** la ventana es la que daba el portal antiguo (31 días, 12 meses o 10 años
  hasta la fecha pedida). La producción se llama `PVGeneration`, que es la que usa el
  cálculo de ingresos.
- **Estadísticas** (pestaña de totales): `modelData` a partir de los totales de SEMS+.
- **Flujo:** solo de respaldo, cuando el portal antiguo dice que la planta no tiene flujo
  (plantas con contador de terceros, como Duna Golf). SEMS+ no da aquí la batería, y el
  portal antiguo sí.

El resto de endpoints sigue en el portal antiguo **mientras funcionen**. Cuando dejen de
hacerlo, el camino es el mismo: buscar la llamada equivalente en el inspector de red de
SEMS+ y traducirla en `GoodWeSemsPlus`.

## Cosas que conviene saber

- **Sesion persistida y renovacion:** `GoodWeSesionService` obtiene la sesion de
  `goodwe_sesiones`, separada por una huella de cuenta y portal. Se guarda la cabecera
  completa del login (`token`, `uid`, `timestamp`, `client`, `version`, `language`),
  sin guardar la contraseña en esa tabla. El token tambien se actualiza en
  `bearertoken` dentro de la misma transaccion para conservar la compatibilidad.
- **Caducidad:** `timestamp` es la fecha de emision de GoodWe, no una fecha de
  vencimiento. Se reutiliza la sesion hasta que el portal responde `100002` o
  SEMS+ responde `C0602`. Entonces se hace login con `GOODWE_ACCOUNT` y
  `GOODWE_PASSWORD`, se sustituye la sesion y se repite la consulta una sola vez.
- **Concurrencia:** un bloqueo MySQL por cuenta serializa las renovaciones. Tras
  obtenerlo se relee la base: si otra peticion ya sustituyo el token rechazado,
  se utiliza el nuevo sin volver a hacer login. El bloqueo se libera tambien
  cuando falla el login o la persistencia. Si falla el guardado, no se devuelve
  el token nuevo como si estuviera persistido.
- **Enfriamiento:** si GoodWe rechaza un token guardado hace menos de 60 s
  (`GoodWeSesionService::ENFRIAMIENTO_SEG`), otra instancia acaba de entrar con la misma
  cuenta. No se vuelve a hacer login (empezaría un ping-pong de logins entre entornos): se
  lanza `GoodWeSesionEnConflicto` y la caché sirve la última lectura buena.
- **Despliegue:** aplicar `2026_10_20_goodwe_sesion.sql` con el migrador habitual.
  La primera consulta crea una sesion completa si aun no existe en la tabla nueva;
  los registros antiguos de `bearertoken` no tienen el `uid` necesario para
  reconstruirla. Ese primer login puede invalidar otra instancia con la misma cuenta.

- **Una sola sesión por cuenta.** Cada `CrossLogin` invalida el token anterior de la misma
  cuenta (`100002` en el portal antiguo, `C0602` en SEMS+). Producción, cualquier entorno
  local, Bruno o una persona en la app de GoodWe se van echando entre sí. El backend lo
  recupera solo: hace login y reintenta **una vez**. Antes reintentaba sin límite, y dos
  instancias podían quedarse en un bucle de logins.
- **Paginación:** `QueryPowerStationMonitor` devuelve como mucho 50 plantas por página
  aunque se pidan 200. `GetAllPlants` pide páginas hasta tener `record` (91 en octubre de 2026).
- **`GOODWE_API_KEY`** está en el `.env` pero **el código no la usa**. Es la clave de la
  OpenAPI oficial de GoodWe, que es de acceso restringido: los endpoints y la forma de
  autenticarse vienen en la documentación privada que GoodWe da con el acceso
  ([documento técnico público](https://community.goodwe.com/static/images/2024-08-20597794.pdf)).
  Con esa documentación se podría dejar de depender de APIs sin documentar.

## Cache de datos

`GoodWeService` cachea las respuestas brutas del portal en `api_cache`, antes de
traducirlas o aplicar precios y filtros de usuario. No requiere otra migracion.
La identidad incluye cuenta GoodWe (como hash), URL, metodo y todos los parametros:
planta, fechas, series, pagina, estado de alarmas, etc. No contiene contraseñas,
tokens de sesion ni JWT. Dos pestañas que necesitan las mismas estadisticas
comparten la consulta al portal aunque representen graficas diferentes.

| Datos | Validez |
|---|---|
| Potencia y flujo actual | 60 segundos |
| Detalle de planta y alarmas | 120 segundos |
| Curvas y estadisticas con periodos abiertos | 300 segundos |
| Listado de plantas | 600 segundos |
| Inventario de equipos | 1.800 segundos |
| Curvas y estadisticas con fecha final anterior a **ayer** en Canarias | 86.400 segundos |

Ayer todavía cuenta como periodo abierto (300 s): los inversores suben datos con retraso y
GoodWe consolida durante el día siguiente.

Si falla alguna página del listado, la respuesta se marca `_cache.obsoleto` e `incompleto`
en vez de parecer completa. Si GoodWe devuelve un error en las alarmas, se responde **503**
("no disponible") y no una lista vacía, que el cliente leería como "todo correcto".

Los tiempos son una politica de la aplicacion, no cuotas oficiales de GoodWe.
Se definen en `GoodWeCachePolitica`. Los logins no se cachean como datos.

**Aislamiento:** se comparten datos brutos de la misma planta, nunca respuestas
personalizadas. El router autentica cada peticion y comprueba su permiso antes de
servir datos. Detalle, tiempo real, inventario y graficas de una planta ajena se
deniegan incluso con cache caliente. Los listados y alarmas del cliente se filtran
con sus asociaciones actuales; retirar una planta se refleja inmediatamente. Las
alarmas se limitan a asociaciones del proveedor GoodWe y sus recuentos se calculan
despues del filtro, sin exponer instalaciones ni totales de otros clientes.

**Concurrencia y fallos:** solo quien obtiene el bloqueo MySQL puede consultar al
proveedor. Los demas reutilizan el resultado o esperan al siguiente intento; un
timeout del bloqueo no dispara otra llamada. Antes del fetch se registra una pausa
de seguridad. Un fallo deja 60 segundos de pausa compartida por la misma peticion,
sin guardar errores de autenticacion o de limite como si fueran datos correctos.
El TTL empieza al completar una consulta correcta.

Si el proveedor falla, se puede usar la ultima lectura buena marcada en `_cache`
con `obsoleto`, `edad_seg` y `esperar_seg`. La edad maxima es el mayor de 15 minutos
y tres TTL para datos abiertos, o siete dias para historicos cerrados. Superada esa
edad, se devuelve indisponibilidad. La metadata tambien se conserva al traducir SEMS+.

Las lecturas durante el TTL evitan llamadas repetidas. Muchas consultas distintas
todavia pueden consumir cuota; esta cache no sustituye un limite global de la cuenta.

Para probar sin acceder a GoodWe, con el stack local arriba:

```bash
docker compose exec -T \
  -e GOODWE_URL=http://127.0.0.1:9/ \
  -e GOODWE_PLUS_URL=http://127.0.0.1:9/sems \
  -e GOODWE_ACCOUNT=goodwe-cache-test@example.invalid \
  -e GOODWE_PASSWORD=solo-pruebas \
  app php testing/integracion/goodwe_cache.php
```

La prueba usa MySQL y HTTP locales, dos procesos y dos usuarios temporales. Comprueba
TTL, concurrencia, cache caliente ajena, retirada de acceso y filtrado de alarmas,
y elimina sus datos al terminar. Las URLs y credenciales ficticias impiden consultar
la cuenta real. Las pruebas unitarias estan en `testing/GoodWeCacheTest.php`.

## Variables

| Variable | Qué es |
|---|---|
| `GOODWE_URL` | Portal antiguo, `https://www.semsportal.com/` |
| `GOODWE_ACCOUNT`, `GOODWE_PASSWORD` | Cuenta de SEMS (la misma en SEMS+) |
| `GOODWE_PLUS_URL` | Opcional. Gateway de SEMS+; por defecto `https://eu-gateway.semsportal.com/web/sems/sems-plant/api` |
| `GOODWE_API_KEY` | OpenAPI oficial. No se usa todavía. |

En producción las tres primeras están en el `docker-compose.yml` compartido del servidor
(servicio `esc_backend`), no en `backend-config/.env`.

## Probar

- **Unitarias:** `testing/GoodWeSemsPlusTest.php` (traducciones, ventanas y unidades)
  y `testing/GoodWeSesionTest.php` (persistencia entre instancias, sesion completa,
  renovacion concurrente, fallos de login y de guardado, reintentos de SEMS/SEMS+).
- **Persistencia y concurrencia con MySQL local:**
  `docker compose exec -T app php testing/integracion/goodwe_sesion.php`.
  Simula el login sin conectar con GoodWe; verifica dos procesos simultaneos,
  sustitucion, rollback (tambien con errores SQL silenciados) y primera asociacion.
  Restaura el token local y elimina los datos y restricciones de la prueba al terminar.
- **Bruno:** carpeta `bruno/GoodWe SEMS+` (login, listado y los tres endpoints de SEMS+).
  Recuerda que cada login de Bruno invalida el token de producción.
- **A mano:** con el admin de pruebas local, abre una planta GoodWe que esté produciendo
  y comprueba "Hoy", "Producción y consumo" (las cinco pestañas) y el flujo.
