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

# 3. Un JWT de administrador (el usuario admin cambia según el entorno, así que
#    no está en el repo). Sácalo del login: ver bruno/README.md
export ESC_JWT_ADMIN='eyJ0eXAiOiJKV1Qi...'
```

Los JWT de los clientes **no** hacen falta: los scripts hacen el login solos con `login.sh`.

En local el correo no sale (la app password de Gmail está revocada), pero da igual: el código 2FA se guarda en la tabla `token` **antes** de intentar enviarlo, así que `login.sh` lo lee de la BD.

## Ejecutar

```bash
python3 testing/integracion/acceso_por_usuario.py
python3 testing/integracion/acceso_proveedores_y_apikey.py
```

Terminan con código distinto de 0 si algo falla. Entre los dos son 62 comprobaciones.

| Script | Qué cubre |
|---|---|
| `acceso_por_usuario.py` | Dos clientes con plantas distintas: cada uno ve solo las suyas, ninguno entra en las del otro, los datos son de la planta pedida, y relacionar/desrelacionar/reasignar se reflejan al instante. |
| `acceso_proveedores_y_apikey.py` | Los 5 proveedores, el esquema `Token` (API key), y casos raros: sin token, token basura, usuario desactivado, cliente intentando auto-asignarse una planta. |

## Ojo

**Escriben en la BD.** Borran y recrean las filas de `plantas_asociadas` de los usuarios 1086 y 1087, y desactivan/reactivan temporalmente al 1086. No los lances contra una base de datos que te importe.

**Gastan cuota de Sigenergy.** Su API limita los accesos por estación (error `1201`). La caché lo absorbe, pero si los lanzas muchas veces seguidas puedes ver algún 429.

**Los ids de planta están escritos en el código** (`WGANI1733406267`, `3145637`…). Si cambian las plantas de la cuenta, hay que actualizarlos.
