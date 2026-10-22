# Changelog

Cambios del backend por versión. Formato [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/),
versionado [SemVer](https://semver.org/lang/es/): MAYOR cuando se rompe el contrato de la API,
MENOR cuando se añade algo, PARCHE cuando se arregla algo. Cómo publicar una versión:
[documentacion/versionado.md](documentacion/versionado.md).

## [Sin publicar]

### Añadido
- `documentacion/plan-mejoras.md`: todas las medidas de mejora por prioridad.
- docker-compose local: cron de avisos de alertas (cada 10 min, correo a Mailpit), sincronización
  con Zoho en perfil opcional, healthchecks y phpMyAdmin con versión fija.
- Bruno: carpetas `Victron VRM` y `SolarEdge V2`, diagnóstico multi-inversor de Sungrow y estado
  por inversor de GoodWe.

### Seguridad
- **Fin del modo antiguo:** ya no se emiten ni aceptan JWT sin sesión (los web de 30 días y
  los de 180 días de `/token`). Toda entrada crea una sesión renovable y revocable; quien
  tuviera un token antiguo vuelve a entrar. Las claves de API no cambian.
- Cambiar las formas de entrar exige un login de hace menos de 10 minutos o la contraseña
  actual (428 si no), y avisa al dueño por correo.
- `totalrealprice` comprueba que la planta sea del usuario (antes devolvía los ingresos de
  cualquier planta).
- **`.htaccess`:** la web servía `config/.env`, `.git/`, `docker-compose.yml`, Bruno y scripts,
  con listado de directorios. Ahora todo eso responde 404 (prueba
  `testing/integracion/ficheros_expuestos.py`). Tras desplegar hay que rotar las claves JWT.
- Quitado el acceso de revisión de Apple de `/token` (código fijo que entraba como un usuario).
- `.dockerignore`: la imagen ya no lleva configuración real, credenciales de Bruno, volcados
  ni pruebas. Las pruebas leen las credenciales de MySQL del contenedor, no del repo.
- Historial purgado (JWT antiguo de SolarWeb, volcados `prueba.json` con datos de clientes).

### Arreglado
- **Sungrow:** tiempo real y gráficas suman todos los inversores (antes solo el primero); la
  vista diaria cubre el día entero (se cortaba a las 12:00).
- **Victron:** la red del flujo se lee de `g1–g3`/`from_to_grid` (se pintaba el consumo);
  plantas sin datos >1 h marcadas como desconectadas con "Sin datos desde…"; total histórico
  real; el estado de batería ya no es el estado de la planta ni se hereda entre plantas.
- **GoodWe:** una planta con algún inversor caído pero generando sale como aviso ("parcial"),
  no como desconectada.
- Renovar la sesión es atómico: un fallo al preparar la respuesta ya no deja la cookie
  gastada ni cierra la sesión. Sin margen de gracia: dos renovaciones simultáneas reciben 409
  durante 5 s y reutilizar después un refresh gastado revoca toda la familia.
- Un token caducado en rutas de planta se responde como fallo de token, para que el
  frontend renueve.
- Logout sin el orden de bloqueos inverso a la rotación (posible deadlock).
- GoodWe: enfriamiento de 60 s ante tokens recién emitidos y rechazados (sin ping-pong de
  logins), ayer ya no se congela 24 h en caché, listado incompleto marcado como tal,
  alarmas con GoodWe caído responden 503 en vez de "sin alarmas".

## [1.1.0] - 2026-10-15

### Añadido
- **Entrar con email + contraseña** (`POST /auth/password`), sin pasar por el correo.
  Por usuario y apagado por defecto: cada uno lo enciende en Ajustes
  (`GET`/`PUT /usuario/metodos-acceso`), que también permite apagar el enlace por correo.
  Nunca puede quedar una cuenta sin ninguna forma de entrar. Tabla nueva
  `usuarios_metodos_acceso` (migración `2026_10_15_metodos_acceso.sql`).
- `ZOHO_DESACTIVADO=1` corta toda conexión con Zoho CRM. El `docker-compose.yml` local lo
  pone por defecto, y las pruebas de integración se niegan a correr sin él.
- Mailpit en el entorno local: los correos de acceso se ven en http://localhost:8025.
- Colección Bruno: carpetas **Zoho CRM** (lecturas; las escrituras bloqueadas salvo
  `zoho_permitir_escritura = si`) y **GoodWe SEMS+**, y peticiones de login con contraseña.
- Pruebas: `GoodWeSemsPlusTest`, `MetodosAccesoTest`, `CorreoCifradoTest`
  y `testing/integracion/metodos_acceso.py`. Las de integración sacan solas el JWT de admin.
- CI: PHPStan ahora se instala y rompe el build con avisos nuevos (baseline con los 93 que
  había), comprobación de versión, workflow de release y Dependabot para imágenes Docker.

### Cambiado
- **GoodWe**: las gráficas, el bloque "Hoy" y los ingresos reales salen de SEMS+
  (`eu-gateway.semsportal.com`). GoodWe dio de baja el portal antiguo y sus gráficas
  devolvían `lines: null` para todas las plantas.

### Arreglado
- GoodWe: se listaban 50 de las 91 plantas (la API pagina de 50 en 50).
- GoodWe: plantas con contador de terceros salían sin flujo de energía.
- GoodWe: un token caducado podía provocar un bucle infinito de logins, y `setToken()`
  escribía el token en el `uid`.
- Las pruebas de integración daban de baja en el CRM real a un cliente cuyo `idApp`
  coincidía con el id del usuario de pruebas.

## [1.0.0] - 2026-08-14

Primera versión numerada: el estado de `main` en `5ac4bf0`. La historia anterior está en
`git log`.
