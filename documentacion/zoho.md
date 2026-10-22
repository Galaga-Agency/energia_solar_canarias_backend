# Zoho CRM

Cómo se sincronizan los usuarios de la app con el CRM de Energía Solar Canarias, cómo
comprobar a mano si un usuario ya está en el CRM, y cómo trabajar en local **sin escribir
en el CRM real**.

## Qué es cada cosa

| En la app | En Zoho CRM v2 | Notas |
|---|---|---|
| Usuario / cliente | Módulo **`Accounts`** | El backend lo llama `Clientes` (`ZohoController::$routes`). |
| `usuarios.usuario_id` | Campo **`idApp`** (texto) | La clave que une las dos fichas. |
| Usuario dado de baja (`eliminado = 1`) | Campo **`Usuario_en_la_app`** = `Inactivo` | La ficha **no se borra**. |
| Planta | Módulo `Plantas` | Solo se crean (create-only), nunca se actualizan. |

Todas las llamadas usan `https://www.zohoapis.eu/crm/v2/...` (el `api_domain` que da el
token) con la cabecera `Authorization: Zoho-oauthtoken <access_token>`. El access token
dura una hora; se guarda en la tabla `zoho_tokens` y se renueva solo con
`ZOHO_REFRESH_TOKEN` (`ZohoService::getAccessToken()`).

## Qué hace el backend en cada operación

| Operación en la app | Llamada a Zoho | Si Zoho falla |
|---|---|---|
| **Alta** desde la app (`POST /usuarios`) | `POST /crm/v2/Accounts` con los datos y `idApp` | **Se deshace el alta local** y responde 500: el alta es atómica. |
| **Alta** desde el CRM (`origen: "crm"`) | Con `idApp`: ninguna. Sin `idApp`: `PUT` para escribirlo en el CRM | Sin `idApp`, se deshace el alta local. |
| **Modificación** (`PUT /usuarios/{id}`) | Busca por `idApp` y `PUT /crm/v2/Accounts/{id}` | Se guarda en local: *"Usuario actualizado. El CRM no se pudo sincronizar."* |
| **Baja** (`DELETE /usuarios/{id}`) | Busca por `idApp` y **`PUT`** con `Usuario_en_la_app: "Inactivo"` | Se da de baja en local: *"Usuario eliminado. El CRM no se pudo sincronizar."* |

### La baja es una actualización, no un borrado

Comprobado con la documentación oficial de Zoho CRM v2:

- La baja hace `PUT /crm/v2/Accounts/{record_id}` mandando **solo** `Usuario_en_la_app`.
  Es una [actualización de un registro](https://www.zoho.com/crm/developer/docs/api/v2/update-specific-record.html):
  Zoho responde `"code": "SUCCESS", "message": "record updated"` y el resto de la ficha no cambia.
- Borrar sería `DELETE /crm/v2/Accounts/{record_id}`
  ([documentación](https://www.zoho.com/crm/developer/docs/api/v2/delete-records.html)).
  **El backend no lo usa en ningún sitio.**
- Las búsquedas usan [`GET /crm/v2/Accounts/search?criteria=(idApp:equals:N)`](https://www.zoho.com/crm/developer/docs/api/v2/search-records.html).
  Sin `/search`, Zoho ignora el `criteria` y devuelve los primeros 200 registros.
  Cuando no hay coincidencias, Zoho contesta **204 sin cuerpo**.

Una baja se deshace volviendo a poner `Usuario_en_la_app = "Activo"`. En la app, la re-alta
del mismo email restaura la misma fila (mismo `usuario_id`; ver el README).

### Cuándo un fallo de Zoho bloquea y cuándo no

- **Alta: bloquea.** Si Zoho no acepta la ficha nueva se deshace el alta local, para no dejar
  un usuario que la API acaba de dar por fallido.
- **Modificación y baja: no bloquean** (desde agosto de 2026). La operación local se hace
  igual, el error queda en `logs` para reconciliarlo y la respuesta lo avisa. Antes, un
  usuario creado en la app y nunca sincronizado era imposible de borrar (500 sin salida).
- **Con `ZOHO_DESACTIVADO=1`** (ver abajo) nada bloquea: el alta se crea solo en local y
  responde *"Usuario creado. Zoho está desactivado en este entorno: no se ha creado en el CRM."*

## Comprobar a mano si un usuario ya está en el CRM (Bruno)

Carpeta **`bruno/Zoho CRM`** (ver [bruno/README.md](../bruno/README.md)):

1. **01 - Token (refresh)**: pide un access token con las credenciales `zoho_*` del
   environment (las mismas que `ZOHO_*` de `config/.env`).
2. **02 - Buscar cliente por idApp (¿es nuevo?)**: pon el `usuario_id` en `zoho_id_app`.
   - `204` → **no está en el CRM: es nuevo**.
   - `200` → ya existe; la consola muestra su `id`, `Usuario_en_la_app` y la última modificación.
3. **03 - Buscar cliente por email** y **04 - Ver cliente**: más lecturas.
4. **05 - Baja lógica** y **06 - Reactivar** **escriben en el CRM real**. Están bloqueadas:
   para usarlas hay que poner `zoho_permitir_escritura = si` en el environment.

Desde la terminal, con el [CLI de Bruno](https://www.npmjs.com/package/@usebruno/cli):

```bash
cd bruno
npx @usebruno/cli run "Zoho CRM/01 - Token (refresh).bru" "Zoho CRM/02 - Buscar cliente por idApp (es nuevo).bru" --env EU
```

## En local y en las pruebas, Zoho está desconectado

`ZOHO_DESACTIVADO=1` corta **toda** la conexión con el CRM: `ZohoService::getAccessToken()`
deja de dar token, así que cada llamada falla con su error normal y el backend sigue por la
rama de "el CRM no se pudo sincronizar". Es un único punto de corte porque todas las
llamadas a Zoho piden ahí el token.

- El `docker-compose.yml` del repo lo pone a `1` por defecto en `app` y `token-refresher`.
- Las pruebas de integración (`testing/integracion/comun.py`) **se niegan a arrancar** si
  el backend tiene Zoho conectado.
- En producción **no puede estar puesto**: la comprobación de humo del despliegue
  (`.github/workflows/php.yml`) falla si lo está.

Para probar Zoho a propósito en local (con cuidado, es el CRM de producción):

```bash
ZOHO_DESACTIVADO=0 docker compose up -d app
```

### Por qué existe

El 2026-10-15, la prueba `baja_y_alta_cliente.py` se lanzó en local con las claves reales
de Zoho en `config/.env`. Da de baja al usuario de pruebas **1087**, que se creó sobre una
copia de la BD de producción, y en el CRM **había un cliente real con `idApp = 1087`**
(`Accounts/794924000003618003`). La baja le puso `Usuario_en_la_app = Inactivo` dos veces.
No se borró nada (ver arriba), pero era una escritura real hecha desde unas pruebas.

De ahí las reglas:

- Los usuarios de pruebas se crean sobre un volcado de producción, así que **su id puede
  ser el `idApp` de un cliente real**. Compruébalo con la petición 02 antes de probar nada
  con Zoho conectado.
- Para probar bajas y modificaciones, usa usuarios que ya existan en local (1086 y 1087,
  de `crear_usuarios_prueba.php`) con Zoho desactivado.
- Para dar de alta usuarios nuevos sin el CRM, basta el `POST /usuarios` normal con Zoho
  desactivado: se crean solo en local. La otra rama sin Zoho es `origen: "crm"` + `idApp`.

## Variables

| Variable | Dónde | Qué es |
|---|---|---|
| `ZOHO_URL` | `config/.env` | Servidor de cuentas, p. ej. `https://accounts.zoho.eu` |
| `ZOHO_CLIENT_ID`, `ZOHO_CLIENT_SECRET` | `config/.env` | Cliente OAuth (Zoho API Console) |
| `ZOHO_REFRESH_TOKEN` | `config/.env` | Refresh token de larga duración |
| `ZOHO_REDIRECT_URI` | `config/.env` | La del cliente OAuth |
| `ZOHO_DESACTIVADO` | entorno del contenedor | `1` corta el CRM (local y pruebas). Vacío en producción. |
