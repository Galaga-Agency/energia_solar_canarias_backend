# Plan de mejoras — Energía Solar Canarias

Inventario actualizado a **22/10/2026**. **Hecho** indica que la implementación aparece en
el código; no implica por sí solo que cada flujo tenga una prueba end-to-end vigente. En esta
revisión no fue posible levantar Docker ni contactar servicios externos. Los cambios están
en una rama local, **sin commit, push ni despliegue**.

Prioridades: **P0** urgente (rompe producción pronto) · **P1** importante · **P2** mejora ·
**P3** cuando haya tiempo.

## Resumen

| # | Área | Medida | Prioridad | Estado |
|---|---|---|---|---|
| 0 | Seguridad | **Producción sirve `config/.env` y `.git/config`**: arreglado en `.htaccess`, falta desplegar y rotar | **P0** | Pendiente (despliegue + rotación) |
| 1 | SolarEdge | Migrar de la API V1 (se retira el 1-nov-2026) a la V2 | **P0** | Pendiente del cliente (credencial) |
| 2 | Despliegue | Subir backend + frontend con todo lo de abajo | **P0** | Pendiente |
| 3 | Autenticación | Sesiones renovables (15 min + refresh rotatorio) y fin del modo antiguo | P1 | Hecho |
| 4 | Autenticación | Login con email + contraseña, métodos por usuario, reautenticación y aviso por correo | P1 | Hecho |
| 5 | GoodWe | Gráficas desde SEMS+ (el portal antiguo se dio de baja) | P1 | Hecho |
| 6 | GoodWe | Sesión compartida, caché, enfriamiento de logins, estado "parcial" | P1 | Hecho |
| 7 | Sungrow | Sumar todos los inversores (no solo el primero) y ventana diaria completa | P1 | Hecho |
| 8 | Victron | Flujo, plantas sin datos, total histórico y estado de planta | P1 | Hecho |
| 9 | Zoho | Desconectado en local y pruebas; alta sin CRM; colección Bruno | P1 | Hecho |
| 10 | CI/CD | PHPStan real, versionado, release, Dependabot de imágenes | P2 | Hecho |
| 11 | Local | docker-compose completo (crons, healthchecks, Mailpit) | P2 | Hecho |
| 12 | Documentación | API viva, guías, Bruno | P2 | Hecho |
| 13 | Operación | Revisar plantas caídas (GoodWe, Victron) y un inversor que nunca produjo | P2 | Pendiente (soporte) |
| 14 | Alertas | Columna "Detectada" vacía (Sungrow, Sigenergy) | P3 | Pendiente |
| 15 | Ajustes | "Actividad de la cuenta" muestra líneas técnicas del log | P3 | Pendiente |
| 16 | Diseño | Tabla de plantas y anillo de batería en anchos de ~800 px | P3 | Pendiente |
| 17 | Sesiones | Dos pestañas con logins distintos: la segunda cierra la primera | P3 | Pendiente |
| 18 | Seguridad | Quitar el acceso de revisión de Apple (`valid_token.php`) | P3 | Hecho |

---

## 0. Ficheros internos servidos por la web (P0, comprobado el 22/10/2026)

**Problema.** El `.htaccess` solo bloqueaba `.json/.css/.js/.pdf/.php/.txt` y tenía el listado
de directorios activado. En producción responden **200** `config/.env` (claves de firma de los
JWT: `SECRET_KEY`, `SECRET_KEY_VOLATIL`), `.git/config` (URL del remoto **con credencial de
GitHub**), el resto de `.git/` (código e historia descargables), `docker-compose.yml`,
`composer.lock` y los listados de `config/` y `storage/`. Con las claves JWT se puede fabricar un
token de cualquier usuario, admin incluido (el código actual de producción no exige sesión).

**Arreglo (en la rama, sin desplegar).** `.htaccess` responde 404 a todo segmento oculto
(`.env`, `.git`...), a las carpetas internas (`config`, `vendor`, `testing`, `bruno`,
`storage` salvo las capturas, `app/cron`, `app/services`...), a extensiones de configuración y
scripts, y quita el listado de directorios. `.well-known/` y la API siguen igual.
Prueba: `testing/integracion/ficheros_expuestos.py` (también contra producción con `ESC_BASE_URL`).

**Lo que hay que hacer en producción, en este orden:**
1. Desplegar el `.htaccess` (o bloquear ya `/.git` y `/config` en el proxy).
2. Revocar el token de GitHub del remoto y dejar el remoto sin credencial (deploy key o SSH).
3. Rotar `SECRET_KEY` y `SECRET_KEY_VOLATIL` (cierra todas las sesiones: avisar a los usuarios).
4. Revisar en los logs del proxy quién ha pedido `/config/.env` o `/.git/` antes del arreglo.

## 1. SolarEdge: migración a la API V2 (P0)

**Problema.** La API V1 que usa la app se retira el **1-nov-2026** (las cabeceras dicen 3-nov;
contar con el 1). Hasta entonces va en **cortes programados**: responde 503 *"Scheduled V1
deprecation window"* a ratos (y 200 en otros). En esos cortes, SolarEdge desaparece del panel y
del listado, también en producción.

**Qué pide la V2.** `https://monitoringapi.solaredge.com/v2/…`, cabecera `X-API-Key`. La clave
V1 **no vale** (401, comprobado). Nuestra clave es de cuenta → toca **Fleet Access, "My Fleet"**
(sin OAuth).

**Lo que tiene que hacer el cliente (≈1 hora):** el admin de la cuenta de instalador de ESC
entra con SSO en https://developer.solaredge.com, activa el acceso de desarrollador, crea una
Application *Monitoring API · Fleet Access · My Fleet* y copia la **App API Key** (solo se ve
una vez). Elegir tier:

| Tier | Precio/mes | Créditos/mes | Llamadas/min |
|---|---|---|---|
| Free | 0 $ | 2.000 | 10 |
| Starter | 100 $ | 20.000 | 25 |
| Growth | 400 $ | 100.000 | 50 |
| Pro | 1.000 $ | 400.000 | 100 |

Con 17 plantas y refresco en vivo, Free se queda corto; **Starter o Growth** si se usa `/power`
en lugar de `/power-flow` (este último solo en Business Pro/Enterprise). El cobro empieza el 1-nov.

**Equivalencias V1 → V2:**

| Uso en la app | V1 | V2 |
|---|---|---|
| Listado | `sites/list` | `GET /sites` (paginado; `id` → `siteId`) |
| Detalle | `site/{id}/details` | `GET /sites/{id}` (`systemSize` → `peakPower`) |
| Resumen | `overview` | `GET /sites/{id}/overview` + `/lifetime-energy` |
| Tiempo real | `currentPowerFlow` | `/power-flow` (Pro) o último valor de `/power` |
| Energía | `site/{id}/energy` | `GET /sites/{id}/energy?from&to&resolution` (fechas ISO con zona) |
| Energía de varias plantas | `sites/{ids}/energy` | **No existe**: una llamada por planta (con caché) |
| Consumo/vertido/compra | `energyDetails?meters=` | `GET /sites/{id}/meters/telemetry` |
| Batería | `storageData` | `GET /sites/{id}/storage/telemetry` (+ `/storage/status`) |
| Equipos | `inventory` | `GET /sites/{id}/devices?types=…` |
| Beneficios | `envBenefits` | `GET /sites/{id}/environmental-benefits` |
| Sensores / dashboard propio | `sensors`, `solaredge-apigw/…` | Sin equivalente: retirar o avisar |
| (nuevo) | — | `/alerts`: permitiría avisos de SolarEdge en el cron |

**Plan técnico (3–5 días):** clave en `proveedores` y `config/.env` → `SolarEdge.php` a `/v2/`
con `X-API-Key` → migrar `SolarEdgeService` método a método → bucle por planta con `api_cache`
y reintento ante 429 → adaptar controlador/adaptador a las nuevas formas → Bruno en verde →
preproducción → producción **antes del 1-nov**.

**Bruno:** carpeta `SolarEdge V2` (12 peticiones). Con la clave nueva en `EU.bru`, deben pasar a 200.

## 1b. Seguridad de la imagen (P0, antes de desplegar)

- **Docker**: `.dockerignore` excluye configuración real, credenciales Bruno, dumps,
  dependencias locales y artefactos de pruebas. Antes de desplegar, el compose de producción
  también debe montar almacenamiento persistente para `app/utils/img/`; el compose de
  producción está fuera de este repositorio y no se modificó en esta revisión.

## 2. Despliegue (P0)

1. Commits por tema en los dos repos y PR a `main`.
2. **Backend primero** (aplica solas las migraciones `2026_10_15_metodos_acceso`,
   `2026_10_20_refresh_tokens` y `2026_10_20_goodwe_sesion`).
3. **Frontend después.**
4. Avisar a los usuarios: los tokens antiguos dejan de valer y hay que volver a entrar.
5. El proxy de producción ya devuelve `Access-Control-Allow-Credentials: true` y el origen
   correcto (verificado el 21/10).

## 3–4. Autenticación (hecho)

- Toda entrada (enlace, contraseña, `/token`) crea una **sesión renovable**: acceso de 15 min y
  refresh en cookie HttpOnly, rotatorio, revocable. Ya no valen JWT sin sesión. **Las claves de API
  siguen igual.**
- Rotación atómica; las carreras simultáneas reciben 409 durante 5 s y la reutilización posterior revoca la familia.
- **Email + contraseña** opcional por usuario (apagado por defecto); nunca sin forma de entrar;
  cambiar métodos exige login reciente (<10 min) o la contraseña actual, y avisa por correo.
- `totalrealprice` comprueba que la planta sea del usuario.
- Detalle: [autenticacion.md](autenticacion.md).

## 5–6. GoodWe (hecho)

- Gráficas, "Hoy" e ingresos reales desde **SEMS+** (el portal antiguo devuelve gráficas vacías).
- 91 plantas (antes 50 por la paginación).
- Sesión del proveedor compartida en BD, caché por tipo de dato, **enfriamiento** ante logins de
  otras instancias (sin ping-pong), alarmas 503 si GoodWe cae (no "sin alarmas").
- **Estado:** el `status` de GoodWe es el peor de sus inversores. Si la planta genera con algún
  inversor caído → **aviso** ("parcial", `status_detail: partial`), no "Desconectado".
- Detalle: [goodwe.md](goodwe.md).

## 7. Sungrow (hecho)

- Tiempo real y gráficas suman **todos** los inversores (antes solo el primero de la lista; en
  Urbaser ese nunca ha producido y todo salía a 0).
- La vista **Diario** cubre el día entero (antes se cortaba a las 12:00 en todas las plantas).
- Si la lectura en vivo falla, se mantiene la potencia del listado en vez de poner 0.

## 8. Victron (hecho)

- **Flujo:** la red se lee de `g1–g3` / `from_to_grid` (antes se pintaba el consumo como red);
  solar incluye `Pi*`, consumo incluye `i*`; se descartan lecturas de otro instante.
- **Plantas sin datos:** si el último dato tiene >1 h → "Sin datos desde DD/MM/AAAA" y estado
  **desconectada** (antes se mostraba la foto de 2023 como si fuera de hoy).
- **Total histórico** real desde VRM (antes un contador sin relación, 0 kWh).
- **Estado de planta** separado del estado de batería ("Descargando" ya no es un estado), y sin
  datos heredados de la planta anterior del listado.
- Ojo: **18 de 31** plantas Victron llevan meses o años sin reportar y ahora salen como
  desconectadas (ver punto 13).

## 9. Zoho (hecho)

- `ZOHO_DESACTIVADO=1` en local y pruebas; producción lo comprueba al desplegar.
- Alta de usuarios en local sin pasar por el CRM.
- Bruno `Zoho CRM` para saber si un usuario ya existe (escrituras bloqueadas).
- Detalle: [zoho.md](zoho.md).

## 10–12. CI/CD, entorno local y documentación (hecho)

- PHPStan bloquea con avisos nuevos (baseline), versión + changelog + release por tag,
  Dependabot también para imágenes Docker, gitleaks.
- **docker-compose local:** backend, frontend, MySQL, Mailpit, phpMyAdmin (versión fija),
  refresco de tokens, **avisos de alertas cada 10 min** (como producción), sincronización con
  Zoho en perfil opcional, healthchecks.
- API viva (`?page=endpoints-verificados`), guías en `documentacion/`, Bruno con carpetas por
  proveedor (`Victron VRM`, `Sungrow` diagnóstico, `GoodWe SEMS+`, `SolarEdge V2`, `Zoho CRM`).

## 13. Operación: plantas caídas (P2, soporte)

- **GoodWe:** 36 plantas desconectadas de verdad; 29 llevan más de 7 días (algunas desde
  2022–2025). Decidir si se dan de baja o se excluyen del recuento de incidencias.
- **Victron:** 18 plantas sin reportar desde hace meses/años.
- **Sungrow, Urbaser Gran Canaria:** el *Inverter2* no ha producido nunca desde la instalación
  (total 0 Wh). Posible problema físico o de configuración: avisar a mantenimiento.
- Idea: mostrar "desde cuándo" está caída cada planta para separar "caída hoy" de "inactiva".

## 14–18. Pendientes menores (P3)

- **Alertas "Detectada" vacía** en Sungrow y Sigenergy: el parser deja la fecha en blanco;
  revisar si sus APIs dan la hora del evento.
- **Actividad de la cuenta:** traducir los eventos del log a frases legibles para el cliente.
- **Diseño a ~800 px:** la tabla de plantas se corta y el anillo de batería de Victron tapa la
  etiqueta de estado.
- **Dos pestañas:** si es el mismo usuario, adoptar la sesión nueva en vez de cerrar la otra.
