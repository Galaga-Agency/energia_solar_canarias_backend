-- Gestion de claves de API.
--
-- La tabla solo guardaba usuario, clave y scope: no habia forma de saber
-- cuando se creo una clave, si se ha usado nunca, ni de revocarla sin borrar
-- la fila. Un usuario que sospecha que su clave se ha filtrado no tenia nada
-- que mirar ni nada que pulsar.
--
-- IDEMPOTENTE a proposito, igual que el resto de migraciones. MySQL no admite
-- `ADD COLUMN IF NOT EXISTS`, asi que cada columna se consulta en
-- information_schema y solo se anade si falta. Sin esto, una base donde ya
-- existen falla con "Duplicate column name", el entrypoint corta y el
-- contenedor entra en bucle de reinicio.
--
-- Cada columna va por separado a proposito: una base a medio migrar puede
-- tener unas si y otras no, y un unico ALTER con las cuatro fallaria entero
-- por culpa de una sola.

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE api_accesos ADD COLUMN nombre VARCHAR(80) NULL DEFAULT NULL AFTER api_scope',
    'DO 0'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_accesos'
    AND COLUMN_NAME = 'nombre'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE api_accesos ADD COLUMN creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER nombre',
    'DO 0'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_accesos'
    AND COLUMN_NAME = 'creado_en'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE api_accesos ADD COLUMN ultimo_uso DATETIME NULL DEFAULT NULL AFTER creado_en',
    'DO 0'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_accesos'
    AND COLUMN_NAME = 'ultimo_uso'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE api_accesos ADD COLUMN revocado_en DATETIME NULL DEFAULT NULL AFTER ultimo_uso',
    'DO 0'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_accesos'
    AND COLUMN_NAME = 'revocado_en'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- La verificacion filtra por clave Y por no revocada en cada peticion.
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'CREATE INDEX idx_api_accesos_activas ON api_accesos (api_key, revocado_en)',
    'DO 0'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_accesos'
    AND INDEX_NAME = 'idx_api_accesos_activas'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
