-- Conserva el origen de cada coordenada para respetar la retención de cada proveedor.
-- Google Maps Platform permite guardar lat/lng hasta 30 días; la caché compartida
-- no usa la excepción de almacenamiento indefinido por usuario. Nominatim requiere
-- caché para evitar repetir consultas y evitar carga innecesaria en su servicio.
SET @tiene_origen_cache := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'geocodificacion_cache'
     AND COLUMN_NAME = 'origen'
);
SET @sql_cache := IF(
  @tiene_origen_cache = 0,
  'ALTER TABLE geocodificacion_cache ADD COLUMN origen VARCHAR(20) NOT NULL DEFAULT ''legacy'' AFTER longitud',
  'DO 0'
);
PREPARE stmt_cache FROM @sql_cache;
EXECUTE stmt_cache;
DEALLOCATE PREPARE stmt_cache;

SET @tiene_origen_llamada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'geocodificacion_llamadas'
     AND COLUMN_NAME = 'origen'
);
SET @sql_llamada := IF(
  @tiene_origen_llamada = 0,
  'ALTER TABLE geocodificacion_llamadas ADD COLUMN origen VARCHAR(20) NOT NULL DEFAULT ''legacy'' AFTER momento',
  'DO 0'
);
PREPARE stmt_llamada FROM @sql_llamada;
EXECUTE stmt_llamada;
DEALLOCATE PREPARE stmt_llamada;
