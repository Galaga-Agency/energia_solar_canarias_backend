# Backend — Energía Solar Canarias

Backend en **PHP nativo** (sin framework) que unifica varias plataformas de
monitorización fotovoltaica (GoodWe, SolarEdge, Sungrow, Victron, Sigenergy) tras una
sola API, gestiona los usuarios/clientes y los mantiene sincronizados con **Zoho CRM**.

- **Frontend:** https://app-energiasolarcanarias.com
- **Stack:** PHP 8.2 + Apache · MySQL 8 · Composer · Docker
- **Documentación viva:** con el stack arriba, `http://localhost:8080/?page=endpoints-verificados`
  (tabla de todos los endpoints, autorización y matriz de qué ofrece cada proveedor).

---

## Puesta en marcha (local)

Todo el entorno va con Docker. No hace falta PHP ni MySQL nativos.

```bash
docker compose up -d --build
```

Levanta cuatro servicios:

| Servicio            | Contenedor            | Puerto host | Qué es                                            |
|---------------------|-----------------------|-------------|---------------------------------------------------|
| `app`               | `esc_app`             | `8080`      | Backend PHP 8.2 + Apache                          |
| `mysql`             | `esc_mysql`           | `3307`      | MySQL 8 (importa `db_init/*.sql` al primer arranque) |
| `phpmyadmin`        | `esc_phpmyadmin`      | `8081`      | Gestión de la BD por navegador                    |
| `token-refresher`   | `esc_token_refresher` | —           | Refresca los tokens OAuth (Sungrow/Sigenergy) cada 6 h |

- La API queda en `http://localhost:8080/`.
- El código va **montado** en local (recarga en caliente); en producción va **dentro
  de la imagen** (ver Despliegue).
- Al arrancar, el `entrypoint.sh` aplica las migraciones pendientes **antes** de
  levantar Apache. Si una migración falla, el contenedor no arranca (a propósito).

### Configuración

Los ficheros con credenciales están en `.gitignore` y **no** viajan al repo. Se parte
de los `*_example`:

| Fichero real (gitignored)     | Ejemplo en el repo            | Qué guarda                          |
|-------------------------------|-------------------------------|-------------------------------------|
| `config/.env`                 | `config/claves_example.env`   | Claves de proveedores y `ZOHO_*`    |
| `config/smtp.json`            | `config/smtp_example.json`    | SMTP para los correos de login      |
| `config/conexion.json`        | —                             | Conexión MySQL (host `mysql`)       |
| `bruno/environments/*.bru`    | `bruno/environments/EU.example.bru` | Entorno de la colección Bruno |

> Las variables `ZOHO_*` pueden venir del entorno del contenedor (`$_ENV`) o de
> `config/.env`. **Con las `ZOHO_*` puestas, el backend escribe en el CRM real** — ojo
> al probar altas/bajas/modificaciones de usuario en local.

---

## Estructura

```
index.php                 Documentación web (?page=...). NO es la entrada de la API.
app/routers/rutas.php     Entrada real de la API. .htaccess enruta aquí todo lo que no sea un fichero.
app/
  controllers/            Un controlador por proveedor + login, token, usuarios, Zoho, logs, apiAccesos.
  services/               Lógica de negocio. ApiControladorService y ProveedorApiService orquestan.
  proveedores/            Contrato común Adaptador + Registro (ver más abajo).
    adaptadores/          Traducen el vocabulario de cada API al contrato. Uno por proveedor.
  models/                 Acceso a APIs externas y a la BD (conexion.php = singleton mysqli).
  DBObjects/              Acceso a datos por tabla (usuarios, clases, logs, plantas_asociadas, api_accesos, proveedores).
  middlewares/            autenticacion.php: valida Bearer (JWT) y Token (API key).
  utils/                  respuesta.php (respuestas HTTP+JSON), HttpClient, captcha, imágenes, errores Sigenergy.
  cron/                   Refresco de tokens de proveedor y llamada nocturna a Zoho.
  enums/ helpers/ clases/ Apoyos (Logs, RequestHelper, ZohoClient).
  pages/                  Páginas de la documentación web.
config/                   Configuración y credenciales (gitignored).
db_init/                  Volcado base que MySQL importa al primer arranque (con datos reales — nunca commitear).
db_migrations/            Migraciones .sql idempotentes + migrar.php + seeds.
docker/                   entrypoint.sh (migra y arranca) y cors-dev.conf (CORS solo en local).
testing/                  PHPUnit (unitarios) + integracion/ (suites Python end-to-end).
bruno/                    Colección Bruno de la API y de cada proveedor.
```

### Modelo de proveedores (Adaptador + Registro)

Cada plataforma habla su propio idioma para lo mismo ("dame los equipos" =
`GetInverterAllPoint` / `getSiteEquipo` / `getInventario`…). Para no repetir los cinco
casos en cada endpoint:

- **`Proveedor`** define el contrato: `plantas, detalle, tiempoReal, graficas,
  inventario, alertas, beneficios, resumen`.
- **`ProveedorBase`**: por defecto **nada** soportado. Cada adaptador declara solo lo
  que su API sabe hacer; lo demás lanza `OperacionNoSoportada` → **404** (no es un
  fallo, es que ese proveedor no lo ofrece).
- **`RegistroProveedores`** resuelve `?proveedor=` de forma **perezosa** (instancia solo
  el que hace falta).
- **`ProveedorApiService`** es el único sitio donde se decide qué HTTP devolver.
- **`ErrorProveedor`** ("lo intenté y me dijo que no") es distinto de
  `OperacionNoSoportada`. Sigenergy responde siempre 200 con el error en `code`; esa
  traducción vive en su adaptador.

---

## Autenticación y acceso

Dos esquemas, ambos por la cabecera `Authorization` (leída **sin distinguir
mayúsculas**, RFC 7230 — funciona por HTTP/1.1 y HTTP/2):

- **Bearer (JWT):** `Authorization: Bearer <jwt>`. Se obtiene con `/login` (envía un
  código al email) + `/token` (lo valida y devuelve el JWT). Dura **180 días**.
- **Clave de API:** `Authorization: Token <uuid>`. La crea el propio usuario con su
  Bearer en `/usuario/bearerToken`. No caduca.

Control de acceso:

- **Admin (clase 1):** todas las plantas y usuarios.
- **Usuario (clase 2):** solo sus plantas asociadas. Pedir una planta ajena devuelve
  **403**, exista o no. Mismo control por Bearer y por clave de API.

> El JWT lleva dentro `usuario_id` + `email`. Por eso una baja es un **borrado lógico**
> (`eliminado=1`) y una re-alta **restaura la misma fila**: darle un id nuevo invalidaría
> los tokens vivos del cliente y le soltaría las plantas.

### Endpoints principales

La referencia completa y verificada está en `?page=endpoints-verificados`. En resumen:

- **Auth/usuario:** `/login`, `/token`, `/usuario`, `/usuario/bearerToken`
- **Admin:** `/usuarios` (GET/POST/PUT/DELETE), `/usuarios/relacionar` (POST/DELETE),
  `/logs`
- **Catálogo:** `/proveedores`, `/clases`, `/clima` (POST, `{name}` o `{lat,long}`)
- **Plantas:** `/plants`, `/plants/details/{id}`, `/plant/power/realtime/{id}`,
  `/plant/inventario/{id}`, `/plant/overview/{id}`, `/plant/benefits/{id}`,
  `/plant/alert`, `/plants/graficas`, `/plants/energy/{ids}`,
  `/plant/grafica/bateria/{id}`, `/plant/grafica/comparacion/{id}`

---

## Sincronización con Zoho CRM

- Los **usuarios/clientes** se sincronizan con el módulo `Accounts`. Alta, modificación
  y baja son **atómicas**: si Zoho no acepta, en local tampoco se aplica (transacción
  InnoDB). El campo `idApp` mapea al `usuario_id` de la app.
- Las búsquedas de cliente usan el sub-endpoint **`/search`** — sin él Zoho ignora el
  `criteria` y devuelve los primeros 200 registros (se operaba sobre el cliente
  equivocado). Corregido.
- Las **plantas** en Zoho son ficha genérica de referencia: el sync es **create-only** a
  propósito (no es la fuente de verdad, no se actualiza).
- El token de acceso vive en `zoho_tokens` y se refresca solo con `ZOHO_REFRESH_TOKEN`.

---

## Migraciones

`db_migrations/*.sql` se aplican solas al arrancar el contenedor (`entrypoint.sh` →
`migrar.php`):

- Aplica en orden alfabético (de ahí la fecha en el nombre) las que no figuren en la
  tabla `migraciones`.
- `GET_LOCK` mientras migra (si arrancan varios contenedores, solo uno aplica).
- Guarda un checksum y avisa si se edita una migración ya aplicada.
- Se para en la primera que falla y devuelve 1 (el despliegue se cae de forma evidente).
- **No** construye el esquema desde cero: la base sale de `db_init/`.

Manual (idempotente): `php db_migrations/migrar.php`.

---

## Pruebas

**Unitarias (PHPUnit, sin BD ni stack):**

```bash
./vendor/bin/phpunit
```

Cubren el catálogo de errores de Sigenergy, la normalización de Sungrow (factor
万 = 10.000), el contrato de proveedores, la cabecera de autorización, etc.

**Integración (Python, requieren el stack levantado):**

```bash
export ESC_JWT_ADMIN='...'
python3 testing/integracion/acceso_por_usuario.py
python3 testing/integracion/acceso_proveedores_y_apikey.py
python3 testing/integracion/baja_y_alta_cliente.py
python3 testing/integracion/codigos_http.py
python3 testing/integracion/configuracion_php.py
```

Comprueban el control de acceso contra `plantas_asociadas` (no contra suposiciones), el
flujo de baja/alta de cliente, que los códigos de error viajen en el HTTP y no solo en el
cuerpo, y la configuración de PHP. Usuarios de prueba local: `crear_usuarios_prueba.php`.

También hay una colección **Bruno** en `bruno/` (API completa + cada proveedor).

---

## Despliegue (producción)

El compose de producción es el compartido de `/home/galagaagency/proyectos/`; el
servicio se llama **`esc_backend`** y lo único que monta es `backend-config →
/var/www/html/config`.

Como el código va **dentro de la imagen** (`COPY . /var/www/html/`), un `git pull` **no
despliega**: hace falta reconstruir.

```bash
cd /home/galagaagency/proyectos
git pull
docker compose up -d --build esc_backend
```

- Reconstruir arranca el contenedor → el entrypoint corre → **las migraciones se aplican
  solas**.
- El `.env` bueno es `backend-config/.env` (tapa al `config/.env` del repo por el
  montaje) y entra al reiniciar, sin reconstruir.
- Las cabeceras CORS de producción las pone el vhost del VPS (fuera del repo);
  `docker/cors-dev.conf` es solo para local.

---

## Notas de configuración PHP

- Se activa `php.ini-production` para que `display_errors=Off` (un warning antes del JSON
  rompía la respuesta). Los errores se ven con `docker compose logs app`.
- Se restaura la **E** en `variables_order = "EGPCS"` (el `php.ini-production` la quita):
  sin ella las variables del contenedor (`ZOHO_*`) no llegan a `$_ENV` y el sync con el
  CRM se caería en el primer despliegue.

---

## Seguridad — recordatorios

- `config/.env`, `config/smtp.json`, `config/conexion.json`, `bruno/environments/*.bru` y
  `db_init/*.sql` están en `.gitignore` y contienen credenciales reales. Verificar con
  `git check-ignore` antes de commitear cualquier secreto.
- `db_init/*.sql` es un volcado de producción (emails de clientes, un hash, un refresh
  token de Zoho): **nunca** debe llegar al repo.
- Rotar las claves que se hayan compartido para pruebas (p. ej. la app-password de Gmail)
  tanto en `config/.env` local como en `backend-config/.env` del VPS.
