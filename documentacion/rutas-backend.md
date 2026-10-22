# Inventario de rutas del backend

Fuente: app/routers/rutas.php, cotejado con la colección bruno/ESC Backend.
Los parámetros de planta requieren el slug de proveedor esperado por la ruta.
Las respuestas suelen ser JSON con status, code, message y data; POST /clima
devuelve el JSON original de Open-Meteo.

## Rutas por método

| Método | Ruta | Acceso / observación |
|---|---|---|
| GET | /auth/magic?token=…&lang=… | Público; valida sin consumir y muestra confirmación en el idioma del correo |
| GET | /zoho/actualizarDatosPlantas | Admin; escribe datos de plantas en Zoho |
| GET | /zoho/verificarToken | Admin; renueva token OAuth y consulta Zoho |
| GET | /usuario/imagen | Bearer propio; imagen binaria; admin puede indicar id |
| GET | /plant/alert/silenciadas | Bearer; lectura local |
| GET | /plant/alert?proveedor=… | Bearer con acceso a la planta; GoodWe filtra por propiedad |
| GET | /plant/inventario/{id}?proveedor=… | Bearer propietario o admin |
| GET | /plant/overview/{id}?proveedor=… | Bearer propietario o admin |
| GET | /plant/benefits/{id}?proveedor=… | Bearer propietario o admin |
| GET | /planta/usuarios/{id}?proveedor=… | Solo admin; lectura local |
| GET | /usuario/metodos-acceso | Bearer; lectura propia |
| GET | /usuario/notificaciones | Bearer; lectura propia |
| GET | /usuario/actividad | Bearer; últimos 30 eventos propios |
| GET | /usuario/api-keys | Bearer; metadata, sin secreto |
| GET | /usuario/bearerToken | Bearer; crea/regenera API key aunque use GET |
| GET | /logs | Admin; paginación y filtro |
| GET | /clases | Bearer |
| GET | /proveedores | Bearer |
| GET | /plant/power/realtime/{id}?proveedor=… | Bearer propietario o admin |
| GET | /plants/details/{id}?proveedor=… | Bearer propietario o admin |
| GET | /usuarios | Admin |
| GET | /usuario | Bearer; perfil propio |
| GET | /usuarios/{id} | Admin |
| GET | /plants | Bearer; admin ve flota, usuario solo sus plantas |
| GET | /plants?proveedor=… | Admin |
| POST | /plant/alert/silenciar | Bearer con acceso a la planta; escritura local |
| POST | /zoho/historialprecios | Admin; lectura Zoho |
| POST | /zoho/crearCliente | Admin; puede escribir Zoho |
| POST | /plants/energy/{ids}?proveedor=… | Bearer propietario de todos los ids; SolarEdge |
| POST | /plant/grafica/bateria/{id}?proveedor=… | Bearer propietario o admin |
| POST | /plant/grafica/comparacion/{id}?proveedor=… | Bearer propietario o admin |
| POST | /forgot/password | Público; emite correo de recuperación |
| POST | /usuario/imagen | Bearer; carga archivo local |
| POST | /change/password | Público con token volátil; cambia contraseña |
| POST | /login | Login legado; puede enviar código por email |
| POST | /token | Canje del código legado por JWT |
| POST | /auth/magic-link | Público; envía email (respuesta uniforme) |
| POST | /auth/magic/consume | Público; consume el token tras confirmación y redirige |
| POST | /auth/refresh | Cookie HttpOnly; rota la sesión |
| POST | /auth/logout | Cookie HttpOnly; revoca la sesión |
| POST | /auth/password | Público; login email/contraseña |
| POST | /auth/handoff | Público; canjea código de un uso |
| POST | /usuarios | Admin; crea local y sincroniza Zoho salvo Zoho desactivado |
| POST | /clima | Bearer; passthrough Open-Meteo con timeout y caché |
| POST | /totalrealprice | Bearer propietario o admin |
| POST | /usuarios/relacionar | Admin; escribe relación local usuario/planta |
| POST | /plants/graficas?proveedor=… | Admin o dueño de una planta según rama |
| POST | /zoho/imprimirWebhook | Admin; sobrescribe webhook_data.txt |
| PUT | /usuario/metodos-acceso | Bearer propio; requiere reautenticación reciente |
| PUT | /usuario/notificaciones | Bearer propio; escribe preferencias locales |
| PUT | /zoho/imprimirWebhook | Admin; sobrescribe webhook_data.txt |
| PUT | /zoho/actualizarCliente | Admin; escribe Zoho |
| PUT | /usuario | Bearer propio; puede sincronizar Zoho |
| PUT | /usuarios | Admin; idApp en cuerpo, puede sincronizar Zoho |
| PUT | /usuarios/{id} | Admin; puede sincronizar Zoho |
| DELETE | /usuario/api-keys/{id} | Bearer propio; revoca API key |
| DELETE | /plant/alert/silenciar | Bearer con acceso a la planta; reactiva silencio local |
| DELETE | /zoho/imprimirWebhook | Admin; operación sobre archivo local |
| DELETE | /zoho/eliminarCliente/{id} | Deshabilitada; debe responder 501 y no llamar Zoho |
| DELETE | /usuario/imagen | Bearer propio; elimina imagen local |
| DELETE | /usuarios/relacionar | Admin; cambia relación local |
| DELETE | /usuario | Baja lógica propia; puede sincronizar Zoho |
| DELETE | /usuarios/{id} | Admin; baja lógica y posible sincronización Zoho |
| DELETE | /usuarios?idApp=… | Admin; misma baja lógica por idApp |

## Estado de verificación

La suite PHPUnit (219 pruebas) y PHPStan pasan localmente. En Docker local se verificó
el flujo de acceso con el usuario de pruebas: email en Mailpit, dos GET de validación
sin consumir el enlace, POST de consumo, handoff con JWT y cookie de refresh. También se
comprobó visualmente el acceso, el panel, la lista y el detalle de plantas, alertas y
ajustes; `/plants` coincidió con las relaciones de la BD local. Esta pasada no vuelve a
llamar a todos los proveedores ni ejercita rutas con escritura. No se hicieron llamadas
a producción ni se usó SSH; Zoho quedó desactivado.

Los endpoints con escritura en Zoho, GoodWe, imágenes, preferencias, API keys,
asociaciones, contraseñas o usuarios están marcados en su petición Bruno. Antes de
probar altas/actualizaciones/bajas de usuario se requiere el Compose local con
ZOHO_DESACTIVADO=1 y una base desechable. No usar GoodWe directamente: el login
de SEMS+ invalida la sesión compartida.
