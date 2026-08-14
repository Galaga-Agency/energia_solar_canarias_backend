-- Cache de geocodificacion: direccion -> coordenadas.
--
-- No todos los proveedores dan latitud y longitud. Sigenergy no las expone en
-- su Openapi, y el detalle de GoodWe tampoco las trae. Pero TODOS dan una
-- direccion, asi que una planta con direccion puede salir en el mapa aunque su
-- proveedor no de coordenadas.
--
-- Se cachea porque Nominatim (OpenStreetMap) es gratuito pero pide un maximo de
-- 1 peticion por segundo y que no se repitan consultas ya hechas. Sin esta
-- tabla, cada carga de la lista de plantas geocodificaria las mismas
-- direcciones una y otra vez: lento para el usuario y abusivo con un servicio
-- que nos deja usarlo gratis.
--
-- La clave es un hash de la direccion NORMALIZADA (minusculas, sin espacios de
-- sobra), no la direccion en crudo: "Calle X, Arucas" y "calle x,  arucas" son
-- la misma consulta y no deben ocupar dos filas ni dos peticiones.
CREATE TABLE IF NOT EXISTS geocodificacion_cache (
  direccion_hash  CHAR(64)      NOT NULL PRIMARY KEY,

  -- Se guarda tambien la direccion legible, para poder auditar que se pidio.
  direccion       VARCHAR(512)  NOT NULL,

  -- NULL = se consulto y Nominatim no encontro nada. Es un resultado valido y
  -- se cachea igual: sin esto, una direccion irreconocible se reintentaria en
  -- cada carga para siempre.
  latitud         DECIMAL(10,7) NULL,
  longitud        DECIMAL(10,7) NULL,

  consultado_en   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
