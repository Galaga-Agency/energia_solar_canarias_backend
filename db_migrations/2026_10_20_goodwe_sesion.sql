-- Cabecera completa de la sesion del portal. timestamp es la fecha de emision,
-- no una caducidad: se renueva cuando SEMS/SEMS+ rechaza el token.
CREATE TABLE IF NOT EXISTS goodwe_sesiones (
    cuenta_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    cabecera JSON NOT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (cuenta_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
