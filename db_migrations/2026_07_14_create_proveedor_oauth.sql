-- Tabla para la configuracion OAuth2 de proveedores (ej. Sungrow / iSolarCloud).
-- Enlaza 1:1 con `proveedores`. Guarda appkey, secret, clave RSA y datos del flujo OAuth
-- que no encajan en las columnas account/pwd de `proveedores`.

CREATE TABLE IF NOT EXISTS `proveedor_oauth` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `proveedor_id` INT NOT NULL,
  `appkey` VARCHAR(255) NOT NULL,
  `secret_key` VARCHAR(255) NOT NULL,
  `rsa_public_key` TEXT DEFAULT NULL,
  `authorization_url` VARCHAR(1000) DEFAULT NULL,
  `redirect_uri` VARCHAR(500) DEFAULT NULL,
  `application_id` VARCHAR(64) DEFAULT NULL,
  `cloud_id` VARCHAR(16) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_oauth_proveedor` FOREIGN KEY (`proveedor_id`)
    REFERENCES `proveedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
