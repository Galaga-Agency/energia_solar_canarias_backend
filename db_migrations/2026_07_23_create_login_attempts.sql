-- Registro de intentos de acceso para el anti-fuerza-bruta. ISO 27001:2022 A.8.5.
-- Cada fila es UN intento fallido de (identificador, ip). El identificador es el
-- email en /login o "user:<id>" en /token. Sin clave foranea a proposito: guarda
-- tambien intentos de emails/ids que no existen (que es justo lo que hay que frenar).
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identificador` VARCHAR(190)    NOT NULL,
    `ip`            VARCHAR(45)     NOT NULL,
    `creado_en`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ident_ip_creado` (`identificador`, `ip`, `creado_en`),
    -- Para la purga periodica por antiguedad (DELETE ... WHERE creado_en < X).
    KEY `idx_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
