# Autenticación y formas de entrar

## Resumen

| Forma de entrar | Quién la usa | Endpoints | Credencial que devuelve |
|---|---|---|---|
| **Enlace por correo** (enlace mágico) | Web, por defecto | `POST /auth/magic-link` → correo → `GET /auth/magic` → confirmación → `POST /auth/magic/consume` → `POST /auth/handoff` | Sesión renovable: acceso **15 min** + refresh en cookie |
| **Email + contraseña** | Web, si el usuario la activa | `POST /auth/password` | Sesión renovable: acceso **15 min** + refresh en cookie |
| Contraseña + código por correo | Login antiguo (sin uso en producción desde agosto de 2026) | `POST /login` → correo → `POST /token` | Sesión renovable, igual que la web |
| Clave de API | Programas de terceros | `GET /usuario/bearerToken` (con sesión) | `Token <uuid>`, no caduca |

Todas las credenciales viajan en la cabecera `Authorization` (`Bearer <jwt>` o
`Token <uuid>`), que se lee sin distinguir mayúsculas (`app/middlewares/autenticacion.php`).

Control de acceso, igual con cualquier credencial:

- **Admin** (clase 1): todas las plantas y la gestión de usuarios.
- **Usuario** (clase 2): solo sus plantas (`plantas_asociadas`). Una planta ajena da **403**,
  exista o no.

## Métodos de acceso por usuario

Cada usuario elige en **Ajustes → Formas de entrar** con qué métodos puede entrar en la web:

| Método | Clave | Por defecto | Cómo se entra |
|---|---|---|---|
| Enlace por correo | `enlace` | **Activado** | Pide el enlace y lo abre desde el correo. |
| Email + contraseña | `password` | **Desactivado** | Escribe email y contraseña; entra directo, sin correo. |

Reglas (`app/services/MetodosAcceso.php`):

- **Nunca puede quedar una cuenta sin ninguna forma de entrar.** Apagar el último método
  activo da 400; en la web ese interruptor sale bloqueado.
- **Activar la contraseña exige elegir una en el mismo paso** (mínimo 10 caracteres,
  máximo 72 bytes, el límite de bcrypt). Las cuentas creadas sin contraseña tienen una
  aleatoria que nadie conoce.
- Con el método ya activo, mandar otra contraseña la cambia. Apagar el método no la borra.
- **Cambiar cualquier método exige volver a identificarse**: haber entrado hace menos de
  10 minutos (la hora del login sale de la sesión renovable, o del `iat` de los JWT antiguos),
  o mandar `passwordActual` si el acceso con contraseña ya está activo. Si no, **428** con
  `data.reautenticar: true`, y el panel pide la contraseña actual o volver a entrar. Los
  intentos con `passwordActual` cuentan para el límite anti fuerza bruta (`reauth:<id>`).
  Así, un token robado no basta para poner una contraseña propia y apagar el enlace.
- Tras cada cambio se envía un **correo de aviso** al dueño ("Han cambiado las formas de
  entrar en tu cuenta"). Un fallo del correo no deshace el cambio.
- Cambiar la contraseña cierra todas las sesiones, la actual incluida; el panel lo avisa y
  manda al login.
- Con el **enlace apagado**, `POST /auth/magic-link` no envía correo, pero responde igual que
  siempre (no delata cómo entra cada cuenta).

Se guardan en la tabla `usuarios_metodos_acceso` (una fila por usuario y método). Sin fila,
vale el valor por defecto: no hay que sembrar nada para los usuarios existentes ni para los
nuevos.

**Para añadir otro método** (TOTP, SMS, Google, Microsoft...): se declara en
`MetodosAcceso::METODOS` con su valor por defecto, se implementa su entrada, y se añade su
texto en el panel del frontend (`LoginMethodsPanel.tsx` y `locales/*/ajustes.json`). La
tabla no cambia.

El login antiguo `/login` + `/token` **no** depende de estos interruptores (pero ya emite sesiones renovables, ver abajo).

### Endpoints

```
GET  /usuario/metodos-acceso          -> {"enlace": true, "password": false}
PUT  /usuario/metodos-acceso          {"metodo": "password", "activo": true, "password": "..."}
POST /auth/password                   {"email": "...", "password": "..."}
```

El `usuario_id` sale siempre del token: un usuario solo puede tocar sus propios métodos.

`POST /auth/password` devuelve exactamente lo mismo que `/auth/handoff` (datos del usuario
y `tokenIdentificador`), así que el frontend guarda la sesión igual venga de donde venga.

## Seguridad

- **Respuesta única ante cualquier fallo** de `/auth/password` (email que no existe,
  contraseña mala, método apagado, cuenta inactiva): el mismo 401 con el mismo mensaje.
  Cuando el email no existe se compara igualmente contra un hash de relleno, para que el
  tiempo de respuesta tampoco lo delate.
- **Límite de intentos** (ISO 27001 A.8.5): 5 fallos en 15 minutos por email + IP dan 429.
  Lo comparten el enlace, la contraseña y el login antiguo (`IntentosLoginDB`).
- **Registro de eventos de seguridad** en el log de PHP: `password_ok`, `password_fallido`
  (con el motivo real, que no sale en la respuesta), `metodo_acceso_cambiado`,
  `magiclink_enviado`, `magiclink_metodo_desactivado`...
- El enlace dura 15 minutos y es de un solo uso. Lo que se guarda es su hash. El GET del
  enlace solo valida y presenta una confirmación: los escáneres de correo pueden abrir
  enlaces automáticamente y no deben gastar el token. El formulario hace el POST
  `/auth/magic/consume`, que lo consume de forma atómica y redirige al frontend con un
  código de traspaso (1 minuto, un solo uso). `lang=en` muestra la confirmación en
  inglés; los enlaces anteriores sin idioma siguen mostrándola en español. El JWT no
  aparece nunca en una URL.

## Tipos de JWT (`app/models/conexion.php`)

| Función | Duración | Para qué |
|---|---|---|
| `jwtAcceso` | 15 min, ligado a una sesión (`sid`) | Toda sesión: enlace, contraseña y `/token` |
| `jwtVolatil` | corta, clave aparte (`SECRET_KEY_VOLATIL`) | Enlace de recuperar contraseña |

El JWT lleva `usuario_id` y `email`. Por eso dar de baja a un usuario es un borrado lógico
(`eliminado = 1`) y volver a darlo de alta restaura la misma fila.

## En local

Los correos (enlaces y códigos) llegan a **Mailpit**, en http://localhost:8025 (ver el README).
Para probar el acceso con contraseña: entra por enlace, actívalo en Ajustes y cierra sesión.

## Pendiente

- Hay un acceso de revisión para Apple en `app/models/valid_token.php` que abre una sesión
  de una cuenta fija sin contraseña (ahora una sesión renovable y revocable, no un JWT de
  180 días). Se decidió mantenerlo de momento. Hay que quitarlo cuando deje de hacer falta.


## Sesiones web renovables (2026-10-20)

`POST /auth/password`, `POST /auth/handoff` y `POST /token` crean **siempre** una sesión
renovable (el frontend manda `"sessionMode":"refresh"`, pero ya no es necesario). Conservan `data.tokenIdentificador` y los campos del
perfil. Añaden `refreshEnabled: true`, `expiresIn: 900`, `expiresAt` (Unix, segundos)
y `sessionId`. El token de acceso sigue siendo un **JWT Bearer**, con `data.id` y
`data.email`, y añade `sid` y `token_use: access`. Esto es gestión de sesiones con
refresh tokens; no incorpora un servidor OAuth2 ni cambia las APIs de proveedores.

- Acceso: **15 minutos**. El cliente renueva cuando faltan 30 segundos, o tras
  un 401 / 403 que indique token inválido. Reintenta la petición una sola vez.
- Refresh: secreto aleatorio de 256 bits, únicamente en cookie **HttpOnly,
  SameSite=Strict, Path=/**, sin Domain. En HTTPS usa `__Host-esc_refresh` y Secure.
  Solo HTTP loopback local usa `esc_refresh_local` sin Secure.
- En MySQL solo se guarda SHA-256 del refresh. Cada renovación consume el anterior
  bajo bloqueo de la familia en una transacción y genera uno nuevo.
- Duración deslizante: **90 días sin renovación**. Máximo absoluto: **180 días**
  desde el login. Alcanzado cualquiera, hay que entrar otra vez.
- La rotación es atómica: el perfil y la cookie nueva se preparan **dentro** de la
  transacción, antes del COMMIT. Si algo falla ahí, se deshace y se retira el `Set-Cookie`:
  la cookie actual sigue valiendo. Antes se confirmaba primero, y un error después (o una
  respuesta perdida) dejaba al navegador con un token gastado y acababa en logout.
- **Carreras de renovación:** si dos pestañas presentan el mismo refresh a la vez,
  la segunda recibe 409 durante 5 segundos, sin secreto, sin JWT y sin borrar la cookie.
  El frontend espera y reintenta como máximo dos veces; el navegador ya habrá guardado
  la cookie rotada por la primera respuesta.
- Reutilizar un refresh consumido pasada esa ventana revoca la familia. No se devuelve
  el sucesor a quien presente el token anterior: eso permitiría a quien robe un refresh
  vigente apropiarse de la sesión durante un margen de gracia.
- Las llamadas simultáneas comparten una renovación por pestaña. Web Locks serializa
  cambios de cookie entre pestañas donde el navegador lo soporta. El servidor protege
  la rotación también donde Web Locks no está disponible.
- `POST /auth/refresh {}` devuelve el perfil y el nuevo acceso; no requiere Bearer.
- El frontend solo manda credenciales (`credentials: "include"`) a `/auth/*`. El resto de
  llamadas van con el Bearer y no dependen de que el proxy permita credenciales.
- Un token caducado o revocado en una ruta de planta responde con el mensaje de token
  (no "no tienes acceso a esta planta"), para que el frontend renueve en vez de quedarse en 403.
  `POST /auth/logout {}` revoca la familia identificada por la cookie y la elimina.
  Ambos exigen JSON y comprueban Origin y Sec-Fetch-Site. Respuestas: no-store.
- Cada JWT moderno comprueba la sesión en DB: salir, desactivar/eliminar la cuenta,
  cambiar contraseña o cambiar email invalida inmediatamente su acceso y su refresh.
  Cambiar la contraseña desde Ajustes requiere volver a entrar.
- El frontend mantiene el acceso corto en el almacenamiento de sesión existente por
  compatibilidad. **El refresh nunca pasa por JavaScript ni localStorage**. Esto no
  elimina el riesgo XSS del acceso corto: seguir evitando scripts no confiables.
- Errores temporales de red/servidor al renovar conservan la sesión. Un refresh
  inválido la cierra. Un 403 de propiedad de planta no provoca renovación ni logout.
  Las peticiones pendientes no se reintentan bajo otra cuenta tras cambiar de sesión.
- El cierre espera a la renovación en curso y confirma revocación en backend antes de
  borrar la sesión local y la caché de datos. Un fallo de red se muestra para reintentar.

### Compatibilidad y despliegue

1. Desplegar **backend primero**, aplicando `2026_10_20_refresh_tokens.sql` con el
   migrador habitual. Después desplegar frontend. Docker local ya tiene la migración.
2. Configurar `FRONTEND_URL` y `BACKEND_URL` con los orígenes reales HTTPS, incluidos
   puertos si existen. Frontend y backend de producción deben ser del mismo sitio
   (actualmente subdominios de energiasolarcanarias.com).
3. Si el navegador llama directamente a otro origen, el vhost/proxy debe devolver
   `Access-Control-Allow-Credentials: true` y el origen concreto del frontend en
   `Access-Control-Allow-Origin` (no `*`), incluyendo preflight. Permitir Content-Type,
   Authorization y las cabeceras de identificación ya existentes. No eliminar
   Set-Cookie en el proxy. Es infraestructura externa al repositorio. **Verificado el
   2026-10-21** en `app-backend.energiasolarcanarias.com` (Apache): el preflight devuelve
   el origen concreto del frontend y `Access-Control-Allow-Credentials: true`.
4. El cron existente de proveedores limpia familias caducadas/inactivas/revocadas,
   con un día de margen. Sus hashes se eliminan por FK. Conserva los tokens consumidos
   de familias vivas para detectar reutilización; no purgarlos por separado.

**Sin modo antiguo (2026-10-21):** el backend ya no emite ni acepta JWT sin sesión. Los
JWT web de 30 días y los de 180 días del login antiguo dejaron de valer al desplegar: quien
tuviera uno vuelve a entrar y recibe una sesión renovable. **Las claves de API
(`Authorization: Token <uuid>`) no cambian**: se validan contra `api_accesos`, no con
`verifyJwt`, y siguen sin caducar (`acceso_proveedores_y_apikey.py` lo comprueba).

### Verificación local

- Backend: PHPUnit, `testing/SesionWebTest.php` y análisis PHPStan.
- HTTP + MySQL + proxy Next: `python3 testing/integracion/sesiones_refresh.py`.
  Crea y elimina una cuenta propia; cubre ambos logins, expiración, hashes, cookies,
  CSRF, rotación, replay, concurrencia, logout, contraseña y estado de cuenta.
- Frontend: `npm run verify`; las pruebas `refresh.test.ts` y `refresh-store.test.ts`
  cubren renovación, fallos transitorios, aislamiento de cuentas y carreras al salir.
- Referencias: [RFC 9700, rotación](https://www.rfc-editor.org/rfc/rfc9700.html#section-4.14),
  [atributos de cookie](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Set-Cookie).
