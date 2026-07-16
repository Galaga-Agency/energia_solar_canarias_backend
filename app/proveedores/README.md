# Proveedores

Un contrato común para las cinco APIs, en vez de un `switch` de cinco ramas en cada endpoint.

## El problema que resuelve

Cada proveedor tenía su propio vocabulario para lo mismo. "Dame los equipos de esta planta" se llamaba:

| GoodWe | SolarEdge | VictronEnergy | Sungrow | Sigenergy |
|---|---|---|---|---|
| `GetInverterAllPoint` | `inventarioSolarEdge` | `getSiteEquipo` | `getInventario` | `getInventario` |

Cinco nombres. Y lo mismo con el detalle (`getPlantDetails` / `getSiteDetails`) y el tiempo real (`getPlantPowerRealtime` / `getSiteRealtime`). Sin contrato, cada endpoint tenía que enumerar los cinco casos: **43 métodos** en `ApiControladorService` y **49 ramas** en el router.

Eso no era solo feo, era la causa de los fallos: Sigenergy estaba en unas rutas y faltaba en otras, `getInventario()` y `getSiteAlarms()` existían pero eran inalcanzables, el control de acceso hubo que añadirlo en nueve sitios y la rama de cliente de `/plants/graficas` se quedó a medias con el comentario puesto.

## Cómo funciona

```
rutas.php ──> ProveedorApiService ──> RegistroProveedores ──> XAdaptador ──> XController (sin tocar)
              (1 método/endpoint)      (?proveedor= → clase)   (traduce)
```

- **`Proveedor`** — el contrato: `plantas`, `detalle`, `tiempoReal`, `graficas`, `inventario`, `alertas`, `beneficios`, `resumen`.
- **`ProveedorBase`** — por defecto **nada** está soportado. Cada adaptador sobrescribe solo lo que su API sabe hacer, así la clase se lee como la lista de lo que ofrece.
- **`adaptadores/`** — traducen el vocabulario de cada API al contrato. **No reescriben lógica**: envuelven los controladores que ya existían, así que el comportamiento de cada proveedor no cambia.
- **`RegistroProveedores`** — resuelve `?proveedor=sigenergy` a su adaptador. Es **perezoso**: antes `ApiControladorService` instanciaba los cinco controladores en cada petición aunque solo hiciera falta uno.
- **`ProveedorApiService`** — un método por endpoint (no por proveedor) y el único sitio donde se decide qué HTTP devolver.

## Quién ofrece qué

| | plantas | detalle | tiempoReal | graficas | inventario | alertas | beneficios | resumen |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| GoodWe | ✓ | ✓ | ✓ | ✓ | ✓ | | | |
| SolarEdge | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ | ✓ |
| VictronEnergy | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| Sungrow | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | |
| Sigenergy | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |

Este mapa antes estaba disperso en `case`s del router; ahora lo comprueba `ProveedorContratoTest`. Lo que no se ofrece lanza `OperacionNoSoportada`, que la fachada convierte en 404.

**GoodWe no tiene alertas por planta a propósito.** Su endpoint devuelve las de *todo* el parque ignorando el id. Fingir que las soporta sería darle a un cliente las alarmas de instalaciones ajenas, justo la fuga que cerró `puedeVerPlanta()`. El listado global sigue disponible para admin, fuera del contrato.

## Errores del proveedor

`ErrorProveedor` es "lo intenté y me dijo que no" (distinto de `OperacionNoSoportada`, que es "esta API no hace eso"). Cada adaptador traduce el formato de su API a esta excepción y la fachada la convierte en HTTP, en un solo sitio.

Sigenergy es el caso claro: responde **siempre HTTP 200** y mete el error real en un campo `code`. Esa traducción vive en `SigenergyAdaptador::oFalla()` y se aplica en **todas** sus salidas. Antes estaba en `ApiControladorService::fallaSigenergy()` y había que acordarse de llamarla endpoint por endpoint — y los que se olvidaban devolvían los fallos como éxitos.

## Añadir un proveedor

1. Un adaptador que extienda `ProveedorBase` e implemente solo lo que ofrezca.
2. Una línea en `RegistroProveedores::porDefecto()`.
3. Su fila en `ProveedorContratoTest::proveedoresYSoportes()`.

No hay que tocar el router ni la fachada. Ese es el objetivo.

## Lo que todavía NO usa el contrato

`/plants`, `/plants/details` y `/plants/graficas` siguen con sus ramas por proveedor: no solo despachan, también unifican formas distintas (`processPlants`) y tienen ramas separadas de admin y cliente. Migrarlos es más que mover llamadas y se dejó para una segunda fase, con los endpoints simples ya asentados.

## Cuidado: `getAllPlants` escribe en el CRM de Zoho

`/plants` no es solo de lectura. Un cron nocturno llama a `zoho/actualizarDatosPlantas`, que hace `getAllPlants(true)` y **crea las plantas en el Zoho CRM real**. Lo que salga de `processPlants` acaba en el CRM. Quien migre `/plants` en la fase 2 tiene que saberlo: no es un endpoint interno.

Dos detalles que lo hacen más delicado de lo que parece:

- **Es create-only.** `comprobarIdPlantasExistentes` busca por `idPlanta` y descarta las que ya están; `crearTodasLasPlantasEnZoho` solo crea. Nunca actualiza. **Lo que se escriba mal la primera vez se queda mal para siempre**, porque en la siguiente pasada la planta ya existe y se salta.
- **Sigenergy es nueva aquí.** Antes no estaba en `getAllPlants`, así que la primera pasada del cron tras desplegar creará sus plantas en el CRM de una tacada. Y por lo de arriba, con los datos que tengan ese día:
  - sin `latitude`/`longitude` (la Openapi oficial no las expone) → quedan vacías en el CRM,
  - con la `capacity` tal cual viene, y **hay 3 plantas cuyo `pvCapacity` está en vatios en vez de kW** → entrarían con la capacidad 1000× equivocada, de forma permanente.

Antes de desplegar conviene decidir si esas plantas se corrigen en Sigenergy, o si el sync debe actualizar además de crear.
