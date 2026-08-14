-- Contador de llamadas reales al geocodificador, para no poder gastar de mas.
--
-- La cache (geocodificacion_cache) ya evita repetir consultas: cada direccion
-- se pregunta UNA vez, incluidos los fallos, y a partir de ahi sale de la base
-- de datos. En regimen normal esta tabla registra cero filas al dia.
--
-- Existe para el caso en que eso falle. Si la tabla de cache desaparece, se
-- queda sin permisos de escritura o el disco se llena, cada carga de pantalla
-- volveria a preguntar por las mismas direcciones y la factura de Google
-- crecería sola sin que nadie se entere. Este contador es un techo duro: son
-- llamadas facturables contadas de verdad, no una estimacion.
--
-- Una fila por llamada, no un contador con UPDATE: asi tambien queda el rastro
-- de CUANDO se gasto, que es lo que permite investigar una subida rara.
CREATE TABLE IF NOT EXISTS geocodificacion_llamadas (
  id       BIGINT   NOT NULL AUTO_INCREMENT PRIMARY KEY,

  -- Separada de `momento` y con indice: el limite se comprueba en cada
  -- geocodificacion, y contar por dia tiene que ser inmediato.
  fecha    DATE     NOT NULL,
  momento  DATETIME NOT NULL,

  INDEX idx_geocod_llamadas_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
