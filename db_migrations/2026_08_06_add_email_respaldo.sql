-- Correo de respaldo.
--
-- Un usuario que pierde el acceso a su correo principal pierde la cuenta
-- entera: el login es un enlace magico y no hay contrasena que recuperar.
-- Con un segundo correo puede seguir entrando.
--
-- No es UNIQUE: dos personas de la misma empresa pueden compartir un buzon de
-- respaldo, y forzar unicidad convertiria eso en un error incomprensible.
--
-- IDEMPOTENTE a proposito. MySQL no admite `ADD COLUMN IF NOT EXISTS`, asi que
-- se consulta information_schema y se prepara la sentencia solo si hace falta.
-- Sin esto, una base donde la columna ya existe falla con "Duplicate column
-- name", el entrypoint corta la migracion y el contenedor entra en bucle de
-- reinicio: es exactamente lo que pasaba en local.

SET @existe_columna := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'email_respaldo'
);

SET @sql := IF(
  @existe_columna = 0,
  'ALTER TABLE usuarios ADD COLUMN email_respaldo VARCHAR(255) NULL DEFAULT NULL AFTER email',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- El login busca por cualquiera de los dos, asi que la columna necesita indice.
SET @existe_indice := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND INDEX_NAME = 'idx_usuarios_email_respaldo'
);

SET @sql := IF(
  @existe_indice = 0,
  'CREATE INDEX idx_usuarios_email_respaldo ON usuarios (email_respaldo)',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
