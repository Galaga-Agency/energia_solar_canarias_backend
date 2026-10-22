# Backend — Energía Solar Canarias

Backend en **PHP nativo** (sin framework) que unifica varias plataformas de
monitorización fotovoltaica (GoodWe, SolarEdge, Sungrow, Victron, Sigenergy) tras una
sola API, gestiona los usuarios/clientes y los mantiene sincronizados con **Zoho CRM**.

- **Frontend:** https://app.energiasolarcanarias.com
- **Stack:** PHP 8.2 + Apache · MySQL 8 · Composer · Docker
- **Documentación viva:** con el stack arriba, `http://localhost:8080/?page=endpoints-verificados`
  (tabla de todos los endpoints, autorización y matriz de qué ofrece cada proveedor).
- **Guías:** [documentacion/](documentacion/README.md): autenticación, Zoho, GoodWe, pruebas,
  versionado y CI.
- **Versión:** [`VERSION`](VERSION) · cambios en [`CHANGELOG.md`](CHANGELOG.md).

---

## Puesta en marcha (local)

Todo el entorno va con Docker. No hace falta PHP ni MySQL nativos.

```bash
docker compose up -d --build frontend
```

Levanta frontend, backend, MySQL y Mailpit en la misma red Docker. El frontend
requiere el repositorio hermano `../energia_solar_canarias_frontend`.

Servicios disponibles:

| Servicio          | Contenedor            | Puerto host | Qué es                                                 |
| ----------------- | --------------------- | ----------- | ------------------------------------------------------ |
| `app`             | `esc_app`             | `8080`      | Backend PHP 8.2 + Apache                               |
| `frontend`        | `esc_frontend`        | `3001`      | Frontend Next en desarrollo, con recarga al editar |
| `mysql`           | `esc_mysql`           | `3307`      | MySQL 8 (importa `db_init/*.sql` al primer arranque)   |
| `phpmyadmin`      | `esc_phpmyadmin`      | `8081`      | Gestión de la BD por navegador                         |
| `mailpit`         | `esc_mailpit`         | `8025`      | Buzón de correo local: recoge todo lo que envía la app |
| `token-refresher` | `esc_token_refresher` | —           | Refresca los tokens OAuth (Sungrow/Sigenergy) cada 6 h |

- La API queda en `http://localhost:8080/`.
- La aplicacion se abre en `http://localhost:3001/es/login`. Next envia las llamadas
  del navegador desde `/backend-api` a `http://app` dentro de `esc_net`, la misma red
  del backend y MySQL. La URL del navegador nunca debe ser `http://app`.
- `phpmyadmin` y `token-refresher` son opcionales: se levantan si se piden
  explicitamente. No hacen falta para probar el acceso local.
- Si cambian las dependencias del frontend, reconstruye y renueva su volumen de
  dependencias: `docker compose up -d --build --renew-anon-volumes frontend`.
- El código va **montado** en local (recarga en caliente); en producción va **dentro
  de la imagen** (ver Despliegue).
- El contexto Docker excluye los ficheros de secretos, las credenciales Bruno y los
  dumps. El despliegue debe montar la configuración real en /var/www/html/config;
  construir la imagen sin ese montaje no deja una configuración ejecutable.
- Al arrancar, el `entrypoint.sh` aplica las migraciones pendientes **antes** de
  levantar Apache. Si una migración falla, el contenedor no arranca (a propósito).

### Configuración

Los ficheros con credenciales están en `.gitignore` y **no** viajan al repo. Se parte
de los `*_example`:

| Fichero real (gitignored)  | Ejemplo en el repo                  | Qué guarda                       |
| -------------------------- | ----------------------------------- | -------------------------------- |
| `config/.env`              | `config/claves_example.env`         | Claves de proveedores y `ZOHO_*` |
| `config/smtp.json`         | `config/smtp_example.json`          | SMTP real (solo producción¹)     |
| `config/conexion.json`     | —                                   | Conexión MySQL (host `mysql`)    |
| `bruno/environments/*.bru` | `bruno/environments/EU.example.bru` | Entorno de la colección Bruno    |

¹ En local el compose monta `docker/smtp-local.json` encima de `config/smtp.json`: todo
el correo va a Mailpit y **nunca sale un correo real** desde local, tengas lo que tengas
en tu `config/smtp.json`.

> **En local Zoho está desconectado.** El compose pone `ZOHO_DESACTIVADO=1`, así que
> aunque `config/.env` traiga las claves reales, el backend no habla con el CRM: las altas
> se quedan en local, y las bajas y modificaciones avisan de que "el CRM no se pudo
> sincronizar". Para probar Zoho a propósito: `ZOHO_DESACTIVADO=0 docker compose up -d app`
> (es el CRM de **producción**). Detalle en [documentacion/zoho.md](documentacion/zoho.md).

### Entrar en local (Mailpit)

El login es por **enlace mágico**: escribes el email y llega un correo con un botón para
entrar. En local ese correo no sale a internet: lo atrapa **Mailpit**, un buzón de
pruebas que se ve en el navegador.

**Primera vez:** crea los usuarios de prueba (clase `usuario`, solo vista) en tu BD local:

```bash
docker compose exec -T app php /var/www/html/testing/integracion/crear_usuarios_prueba.php
```

Crea `cliente.pruebas@galagaagency.com` y `cliente.pruebas2@galagaagency.com`.

**Para entrar**, con el backend y el frontend (`http://localhost:3001`) levantados:

1. Abre `http://localhost:3001`, escribe el email de un usuario de la BD local y pulsa
   *Enviar enlace de acceso*.
2. Abre Mailpit en **http://localhost:8025**, abre el correo *Tu acceso a Energía Solar
   Canarias* y pulsa **Entrar en la aplicación**. Se abrirá una confirmación en el
   backend; pulsa **Acceder a mi cuenta** para completar el inicio de sesión.

O en un solo paso (pide el enlace, lo saca de Mailpit y lo abre en el navegador):

```bash
docker/login-local.sh                         # cliente.pruebas2@galagaagency.com
docker/login-local.sh alguien@ejemplo.com     # cualquier usuario de la BD local
```

El enlace caduca a los 15 minutos y es de un solo uso. Si no llega ningún correo, el
email no existe o está desactivado en la BD local (el endpoint responde lo mismo en ambos
casos, a propósito).

Cómo encaja: el compose pone `FRONTEND_URL=http://localhost:3001` y
`BACKEND_URL=http://localhost:8080` para que el enlace y la redirección se queden en
local, y `docker/smtp-local.json` apunta el SMTP a Mailpit con `"secure": "none"` (Mailpit
no habla TLS; sin esa clave `Correo` usa STARTTLS, que es lo que exige el SMTP real).

**Admin de pruebas** (ve todas las plantas de todos los proveedores y el menú Usuarios):

```bash
docker compose exec -T app php /var/www/html/docker/crear_admin_local.php
docker/login-local.sh admin.pruebas@galagaagency.com
```

No tiene contraseña conocida (se le pone una aleatoria): solo se entra por enlace.

**Entrar con contraseña:** entra por enlace, ve a *Ajustes → Formas de entrar*, activa
*Correo y contraseña* eligiendo una, y a partir de ahí en la pantalla de login usa
*Entrar con contraseña*. Por defecto está desactivado para todos
([documentacion/autenticacion.md](documentacion/autenticacion.md)).

> **Como admin, la app llama de verdad a las cinco APIs.** Con las credenciales de
> `config/.env` y de la tabla `proveedores`, el backend inicia sesión en Sungrow y
> Sigenergy con usuario/contraseña (no con el refresh token rotatorio), así que no
> invalida el token de producción. Lo que sí gasta es **cuota**: Sigenergy limita los
> accesos por estación (error `1201`); la caché lo amortigua, pero evita recargar en
> bucle. SolarEdge y Sigenergy tardan bastante más que el resto en listar plantas: si
> al entrar no aparecen sus pestañas, espera unos segundos y recarga.

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

- **Bearer (JWT):** `Authorization: Bearer <jwt>`. En la web se obtiene por **enlace al
  correo** (`/auth/magic-link` → `/auth/magic` → confirmación → `/auth/magic/consume` → `/auth/handoff`, por defecto) o por **email + contraseña**
  (`/auth/password`, si el usuario lo activa en Ajustes). Siempre es una **sesión
  renovable**: acceso de 15 minutos más refresh en cookie HttpOnly, revocable al salir o al
  cambiar la contraseña. El login antiguo `/login` + `/token` emite el mismo tipo de sesión.
  Ya no se aceptan JWT largos sin sesión.
- **Clave de API:** `Authorization: Token <uuid>`. La crea el propio usuario con su
  Bearer en `/usuario/bearerToken`. No caduca.

Cada usuario elige con qué métodos puede entrar (`/usuario/metodos-acceso`); nunca puede
quedarse sin ninguno. Todo en [documentacion/autenticacion.md](documentacion/autenticacion.md).

Control de acceso:

- **Admin (clase 1):** todas las plantas y usuarios.
- **Usuario (clase 2):** solo sus plantas asociadas. Pedir una planta ajena devuelve
  **403**, exista o no. Mismo control por Bearer y por clave de API.

> El JWT lleva dentro `usuario_id` + `email`. Por eso una baja es un **borrado lógico**
> (`eliminado=1`) y una re-alta **restaura la misma fila**: darle un id nuevo invalidaría
> los tokens vivos del cliente y le soltaría las plantas.

### Endpoints principales

La referencia completa y verificada está en `?page=endpoints-verificados`. En resumen:

- **Auth/usuario:** `/auth/magic-link`, `/auth/magic`, `/auth/magic/consume`, `/auth/handoff`, `/auth/password`, `/auth/refresh`, `/auth/logout`,
  `/usuario/metodos-acceso` (GET/PUT), `/login`, `/token`, `/usuario`, `/usuario/bearerToken`
- **Admin:** `/usuarios` (GET/POST/PUT/DELETE), `/usuarios/relacionar` (POST/DELETE),
  `/logs`
- **Catálogo:** `/proveedores`, `/clases`, `/clima` (POST, `{name}` o `{lat,long}`)
- **Plantas:** `/plants`, `/plants/details/{id}`, `/plant/power/realtime/{id}`,
  `/plant/inventario/{id}`, `/plant/overview/{id}`, `/plant/benefits/{id}`,
  `/plant/alert`, `/plants/graficas`, `/plants/energy/{ids}`,
  `/plant/grafica/bateria/{id}`, `/plant/grafica/comparacion/{id}`

---

## Sincronización con Zoho CRM

- Los **usuarios/clientes** se sincronizan con el módulo `Accounts`. El campo `idApp`
  mapea al `usuario_id` de la app.
- El **alta** es atómica: si Zoho no la acepta, se deshace en local. La **modificación** y
  la **baja** no dependen de Zoho: se hacen en local y la respuesta avisa si el CRM no se
  pudo sincronizar.
- La **baja no borra la ficha del CRM**: le pone `Usuario_en_la_app = Inactivo` (un `PUT`,
  no un `DELETE`).
- `ZOHO_DESACTIVADO=1` corta toda conexión con el CRM (por defecto en local y obligatorio
  en las pruebas). Guía completa, con cómo comprobar en Bruno si un usuario ya está en el
  CRM: [documentacion/zoho.md](documentacion/zoho.md).
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

**Análisis estático:** `vendor/bin/phpstan analyse --memory-limit=1G` (con baseline: solo
fallan los avisos nuevos).

**Integración (Python, requieren el stack levantado y Zoho desactivado):**

```bash
python3 testing/integracion/acceso_por_usuario.py
python3 testing/integracion/acceso_proveedores_y_apikey.py
python3 testing/integracion/baja_y_alta_cliente.py
python3 testing/integracion/codigos_http.py
python3 testing/integracion/configuracion_php.py
python3 testing/integracion/metodos_acceso.py
```

Comprueban el control de acceso contra `plantas_asociadas` (no contra suposiciones), el
flujo de baja/alta de cliente, que los códigos de error viajen en el HTTP y no solo en el
cuerpo, la configuración de PHP y los métodos de acceso. Sacan solos el JWT de admin
(enlace + Mailpit) y no arrancan si Zoho está conectado. Usuarios de prueba local:
`crear_usuarios_prueba.php` y `docker/crear_admin_local.php`. Todo en
[documentacion/pruebas.md](documentacion/pruebas.md).

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
- Lo normal es no hacerlo a mano: el push a `main` lanza el CI/CD (tests, PHPStan,
  gitleaks, versión → deploy con backup de la BD y comprobación de humo). Versiones y
  releases: [documentacion/versionado.md](documentacion/versionado.md).
- **`ZOHO_DESACTIVADO` no puede estar puesto en producción**: el deploy lo comprueba.

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
