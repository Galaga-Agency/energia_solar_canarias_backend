-- Silenciado de alertas.
--
-- Ningun proveedor permite silenciar en su lado: GoodWe, SolarEdge, Victron,
-- Sungrow y Sigenergy exponen las alertas en modo lectura. El silenciado es por
-- tanto NUESTRO, y se aplica al presentar la lista, no en origen.
--
-- Una fila = una alerta silenciada por un usuario hasta una fecha. Se guarda
-- (proveedor, planta, alerta) porque el id de alerta solo es unico dentro de su
-- proveedor: dos proveedores pueden emitir el mismo id.
--
-- La alerta sigue existiendo y sigue llegando del proveedor. Silenciar no la
-- borra ni la resuelve: solo dice "no me avises de esta hasta tal dia". Por eso
-- no hay clave foranea a ninguna tabla de alertas — no tenemos tabla de alertas,
-- las alertas viven en el proveedor.
CREATE TABLE IF NOT EXISTS `alertas_silenciadas` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`    INT             NOT NULL,
    `proveedor`     VARCHAR(32)     NOT NULL,
    `planta_id`     VARCHAR(255)    NOT NULL,
    `alerta_id`     VARCHAR(190)    NOT NULL,
    -- Hasta cuando. NULL = indefinido, hasta que alguien la reactive.
    `silenciada_hasta` DATETIME     DEFAULT NULL,
    -- Para auditoria: por que se silencio. ISO 27001 A.8.15.
    `motivo`        VARCHAR(255)    DEFAULT NULL,
    `creado_en`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- UNIQUE y no solo KEY: silenciar dos veces la misma alerta es actualizar
    -- la fecha, no crear una segunda fila. El INSERT usa ON DUPLICATE KEY.
    UNIQUE KEY `uq_alerta` (`proveedor`, `planta_id`, `alerta_id`),
    KEY `idx_usuario` (`usuario_id`),
    -- Para filtrar rapido las que siguen vigentes al listar.
    KEY `idx_silenciada_hasta` (`silenciada_hasta`),
    CONSTRAINT `fk_silenciada_usuario` FOREIGN KEY (`usuario_id`)
        REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
