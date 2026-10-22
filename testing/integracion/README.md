# Pruebas de integración

Estas pruebas **no son unitarias**: hablan con el backend levantado, con MySQL y con las APIs reales de los proveedores. Por eso viven fuera de PHPUnit — la suite de `testing/` corre sin stack y en CI, y estas no podrían.

Lo que protegen es la regla de acceso: **el admin ve todas las plantas; el usuario normal solo las suyas**. Se comprueba contra `plantas_asociadas`, que es la fuente de verdad: se lee de la BD qué debería ver cada uno y se compara con lo que devuelve la API, en lugar de contra lo que uno supone.

Merece la pena tenerlas porque este agujero estuvo abierto: `/plants` sí filtraba por usuario, pero los endpoints de datos (`realtime`, `inventario`, `alert`, gráficas…) iban directos al proveedor con el id de la URL, sin mirar de quién era la planta. Por la interfaz no se notaba, porque la lista salía bien filtrada.

## Preparación

```bash
# 1. El stack levantado
docker compose up -d

# 2. Los dos usuarios de prueba (clase 2 = usuario, solo vista)
docker compose exec -T app php /var/www/html/testing/integracion/crear_usuarios_prueba.php

# 3. El admin de pruebas
docker compose exec -T app php /var/www/html/docker/crear_admin_local.php
```

**No hay que copiar ningún JWT.** El de admin lo sacan solas con el flujo real del enlace:
lo piden para `admin.pruebas@galagaagency.com` y leen el correo de Mailpit
(`comun.jwt_por_enlace`). Si prefieres otro admin, exporta `ESC_JWT_ADMIN`. Los de los
clientes salen del login antiguo con `login.sh`: el código se lee de la tabla `token`.

**Zoho tiene que estar desactivado.** Las pruebas dan de alta y de baja usuarios, y con Zoho
conectado eso escribe en el CRM real: el 2026-10-15 una baja marcó como inactivo a un
cliente real cuyo `idApp` coincidía con el usuario de pruebas 1087. El docker-compose del
repo pone `ZOHO_DESACTIVADO=1`, y `comun.py` **para la ejecución** si el backend lo tiene
conectado. Ver [documentacion/zoho.md](../../documentacion/zoho.md).

## Ejecutar

```bash
python3 testing/integracion/acceso_por_usuario.py
python3 testing/integracion/acceso_proveedores_y_apikey.py
```

Y el resto (`baja_y_alta_cliente.py`, `codigos_http.py`, `configuracion_php.py`,
`metodos_acceso.py`) igual. Terminan con código distinto de 0 si algo falla. En total son
más de 110 comprobaciones.

| Script | Qué cubre |
|---|---|
| `acceso_por_usuario.py` | Dos clientes con plantas distintas: cada uno ve solo las suyas, ninguno entra en las del otro, los datos son de la planta pedida, y relacionar/desrelacionar/reasignar se reflejan al instante. |
| `acceso_proveedores_y_apikey.py` | Los 5 proveedores, el esquema `Token` (API key), y casos raros: sin token, token basura, usuario desactivado, cliente intentando auto-asignarse una planta. |
| `baja_y_alta_cliente.py` | La baja es lógica y la re-alta restaura la misma fila; un fallo de Zoho no impide la baja pero se avisa; con Zoho desactivado, el alta se queda en local. |
| `codigos_http.py` | El código de error viaja en el HTTP, no solo en el cuerpo. |
| `configuracion_php.py` | La configuración de PHP del contenedor. |
| `metodos_acceso.py` | Enlace y contraseña: valores por defecto, entrar con contraseña, misma respuesta ante cualquier fallo, nunca sin forma de entrar, enlace apagado sin correo. |

## Ojo

**Escriben en la BD local.** Borran y recrean las filas de `plantas_asociadas` de los usuarios 1086 y 1087, desactivan/reactivan temporalmente al 1086, dan de baja/alta al 1087, cambian sus métodos de acceso, y crean y borran un usuario de prueba. Lo dejan todo como estaba. No los lances contra una base de datos que te importe.

**Gastan cuota de Sigenergy.** Su API limita los accesos por estación (error `1201`). La caché lo absorbe, pero si los lanzas muchas veces seguidas puedes ver algún 429.

**Los ids de planta están escritos en el código** (`WGANI1733406267`, `3145637`…). Si cambian las plantas de la cuenta, hay que actualizarlos.
