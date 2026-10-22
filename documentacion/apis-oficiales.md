# APIs externas y reglas de uso

Consultado el 21 de octubre de 2026. Este documento distingue la API que usa el código
de las versiones que el proveedor publica actualmente. La colección Bruno está bajo
bruno/ y estas páginas son la referencia para autenticación y cuotas. La verificación
local se hizo con Docker y el usuario cliente.pruebas; no se accedió al servidor remoto.

## Verificación de datos en ejecución (21 de octubre de 2026)

Se contrastó la ficha Sigenergy `WGANI1733406267` en el navegador con las respuestas
guardadas localmente por el backend. A las 14:33 de Canarias, la interfaz mostraba
1,60 kW de potencia FV, 96 % de batería, 0,10 kW de consumo, 4,79 kWh hoy,
153,55 kWh en el mes, 3,33 MWh en el año, 7,76 MWh acumulados y 3,69 t de CO₂.
La caché del backend contenía 1,60 kW, 96,2 % SOC, 0,10 kW de carga, y los mismos
totales de hoy y del mes (4,79 y 153,55); los valores de año, vida y beneficios
redondean a lo que se ve en pantalla. El balance instantáneo también cierra: 1,60 kW
FV = 1,50 kW hacia batería + 0,10 kW de consumo, con red en 0 kW.

La gráfica diaria usa otro endpoint. Su última copia local era de las 14:25, tenía
173 puntos hasta las 14:20 y sumaba 4,65 kWh, frente a 4,79 kWh en el resumen de
las 14:30. Esa fila persistida ya había superado el TTL de 315 s. Si el backend sirve
una copia caducada durante el enfriamiento de cuota, `CacheApiService` la marca con
`_cache.obsoleto`; no se hizo una llamada adicional para inspeccionar la metadata de esa
respuesta concreta. La ficha de gráficas no monta `StaleDataNotice` y puede conservar la
serie en la caché de cliente, por lo que no señala esa posible antigüedad. Es una diferencia
de frescura entre endpoints, no una conversión de unidades. No se forzó otra consulta a
Sigenergy para evitar consumir su cuota durante la revisión.

Los datos vivos visibles en esta sesión corresponden a una instalación Sigenergy. Para
SolarEdge se normalizaron los flujos W/kW a kW y las series Wh/kWh a kWh, atendiendo
a la unidad declarada por cada respuesta; seis pruebas cubren conversión, no doble escala
y unidad desconocida. La suite completa pasó (225 tests, 1.160 aserciones) y PHPStan no
reportó errores. Sungrow, Victron y GoodWe se revisaron contra respuestas persistidas y
documentación/payloads del proyecto, pero no se afirma que esas plantas tuvieran una
lectura en vivo durante esta revisión.

Hallazgo de cuota pendiente: `SigenergyService::getPlantSummary` hace dos llamadas por
planta (summary + energyFlow), aunque el comentario del listado afirma que hace una. La
documentación oficial limita la cuenta a 10 llamadas por minuto. El TTL evita repetir cada
ruta durante 315 s, pero no regula el pico agregado de una lista fría con muchas plantas.
No se validó ese caso con varias estaciones reales; hace falta una cola/planificador de
cuenta para mantener la flota completa fresca sin exceder el cupo.

## Proveedores de energía

| Proveedor | API en el backend | Autenticación | Caché del backend |
|---|---|---|---|
| SolarEdge | La aplicación aún llama a Monitoring API V1 en varias operaciones. Bruno contiene ejemplos directos de Monitoring API V2, con X-API-Key. | V1 API key; V2 Fleet Access API key. No confundirlas. | 60 s en tiempo real, 5 min en resumen/inventario y 1 h en beneficios. |
| Sungrow | iSolarCloud OpenAPI; login oficial con credenciales, refresh token almacenado y operaciones de planta normalizadas en SungrowService. | OAuth de iSolarCloud y compatibilidad con el login V1 usado por esta cuenta. | 60 s tiempo real, 5 min inventario y 2 min alertas. |
| Sigenergy | SigenCloud OpenAPI oficial: lista, resumen, flujo, histórico y dispositivos. | Token de acceso OAuth administrado por ProveedorTokenService. | 315 s por ruta para datos sujetos a la ventana de estación; 24 h para períodos históricos cerrados. El TTL por ruta no limita por sí solo las 10 llamadas/min de toda la cuenta. |
| Victron | VRM API v2. | Token VRM en el encabezado x-authorization: Token … | 60 s tiempo real, 5 min inventario y 2 min alertas. |
| GoodWe | SEMS+ del portal web observado por el usuario; el backend replica las peticiones que expone su interfaz y renueva el login si caduca. | Usuario y contraseña GoodWe; sesión almacenada y compartida. | Política propia: flujo 60 s; detalle/alertas 120 s; lista 10 min; histórico cerrado 24 h. |
| Zoho CRM | El código actual usa CRM API v2. La documentación pública de Zoho ya anuncia v8; no se cambia la versión del código sin validar un sandbox CRM. | OAuth 2.0: refresh token de servidor para obtener access tokens de corta duración, en el dominio de cuentas de la organización. | No se cachean escrituras. Los valores de configuración y tokens no se guardan en Bruno real versionado. |

### Referencias oficiales

- SolarEdge [Developer Platform](https://developer.solaredge.com/) y [guía oficial de migración V1 a V2](https://api-docs.solaredge.com/docs/developer-platform/mxj41549n0nxk-migrating-from-v1-to-v2). El plan del backend documenta las operaciones V1 que aún faltan por migrar.
- Sungrow [iSolarCloud OpenAPI Developer Portal](https://developer-api.isolarcloud.com/). La guía local de payloads está en [sungrow/openapi-sungrow.md](sungrow/openapi-sungrow.md).
- Sigenergy [SigenCloud Developer Portal](https://developer.sigencloud.com/). El PDF facilitado para esta integración está dentro de documentacion/sigenergy/.
- Victron [VRM API documentation](https://vrm-api-docs.victronenergy.com/). La documentación actual pide x-authorization: Token …; los ejemplos Bearer están obsoletos. También publica límites de frecuencia, por lo que la caché debe mantenerse.
- Zoho [CRM API v2](https://www.zoho.com/crm/developer/docs/api/v2/) y [OAuth refresh tokens](https://www.zoho.com/crm/developer/docs/api/v2/refresh.html). [CRM API v8](https://www.zoho.com/crm/developer/docs/api/v8/) es la documentación de versión actual; confirmar módulos/campos y pruebas sandbox antes de migrar.
- GoodWe no se ha documentado con endpoints no oficiales encontrados en internet. Se mantiene la instrucción del proyecto: tratar SEMS+ como tráfico observado en su interfaz, no como API pública soportada.

### Política de caché y frescura

El TTL de caché no se presenta como garantía del proveedor salvo cuando se indica expresamente.
Se aplica para limitar repeticiones del frontend y respetar las ventanas conocidas. Los datos
de proveedor incluyen metadata _cache en la respuesta estándar; el cliente la conserva para
que la interfaz pueda distinguir una lectura obsoleta de una fresca.

| Fuente | Política |
|---|---|
| Sigenergy | 315 s en endpoints por estación para dejar margen al límite de 300 s. El flujo también respeta esta ventana; un poll del frontend no fuerza llamadas al proveedor. Histórico cerrado: 24 h. |
| GoodWe SEMS+ | TTL por endpoint en GoodWeCachePolitica. Los fallos activan enfriamiento y solo se usa un respaldo dentro de la edad máxima configurada. |
| SolarEdge, Sungrow, Victron | Caché compartida de la fachada para tiempo real, inventario, alertas, resumen y beneficios. Los TTL están centralizados en ProveedorCachePolitica; cualquier cambio requiere actualizar la prueba correspondiente. |
| Open-Meteo | 15 min por coordenadas, timeout de conexión 3 s y total 8 s. Se conserva su JSON original porque el frontend lee current y daily. |
| Google Geocoding | Coordenadas compartidas caducan a los 30 días conforme a los términos de Maps Platform aplicables al EEE. |
| Nominatim | Caché persistente; un lock en MySQL mantiene como máximo una consulta por segundo entre procesos del backend. |
| Sin resultado de geocodificación | 7 días antes de intentar de nuevo; fallos temporales, 5 min. El contador diario se incrementa por cada petición HTTP real, no por dirección. |

Referencias de geocodificación: [Google Geocoding](https://developers.google.com/maps/documentation/geocoding/),
[términos EEE de Google Maps Platform](https://cloud.google.com/terms/maps-platform/eea/maps-service-terms),
[Nominatim Search API](https://nominatim.org/release-docs/latest/api/Search/) y
[política oficial de uso de Nominatim](https://operations.osmfoundation.org/policies/nominatim/).

## Clima y mapas

- El endpoint POST /clima devuelve directamente la forma JSON de [Open-Meteo Forecast API](https://open-meteo.com/en/docs), no el envoltorio estándar. El frontend lo consume en el frontend vecino, services/weather.ts y utils/parse/shared/weather.ts.
- La consulta usa la zona horaria Atlantic/Canary; una respuesta inválida o caída del upstream produce un error HTTP JSON, nunca die() ni texto suelto.
- La geocodificación primero usa Google cuando hay clave y conserva Nominatim como alternativa. Cada coordenada guarda el origen para aplicar su expiración correspondiente.

## Comparación del contrato con la interfaz

La API del backend suele envolver datos en status, code, message y data. El cliente del frontend
desenvuelve data y conserva la metadata de caché. Algunas rutas de proveedores devuelven datos
anidados propios; los parsers separados de GoodWe, Sungrow, SolarEdge, Victron y Sigenergy
traducen esos campos a las vistas.

Casos que se comprobaron en el código del frontend:
- /clima es passthrough y el cliente no debe buscar data dentro de la respuesta.
- La vista de GoodWe lee el flujo, los valores de equipos y las curvas desde rutas distintas;
  las curvas intradía aceptan 24, 48 o 96 puntos según la resolución.
- La ficha Sigenergy separa summary/flow de histórico. Cada endpoint trae su propia edad;
  un resumen más nuevo puede coexistir temporalmente con una gráfica histórica anterior.
  El backend devuelve `_cache.obsoleto`, pero el componente visual de aviso solo está
  conectado al listado general y no a la gráfica de detalle.
- Victron puede devolver la última lectura histórica conocida. La interfaz no debe interpretarla
  como producción actual sin consultar su fecha.
- Las alertas inferidas de Sigenergy no son eventos de alarma en vivo; la API lo señala.

La comparación visual del detalle Sigenergy se realizó en el navegador local con datos de
prueba. La diferencia de edad del histórico diario queda documentada arriba. No se ha usado
ni modificado el servidor remoto.
