-- Cache de respuestas de APIs de proveedores.
--
-- Motivo: la Openapi de Sigenergy limita a 1 acceso por estacion cada 5 minutos
-- (y 10 peticiones/min por cuenta). Sin cache, dos visitas seguidas al panel
-- provocan un error de rate-limit del proveedor. Con cache servimos la ultima
-- respuesta buena y no volvemos a llamar hasta que expira el TTL.
--
-- La clave es el proveedor + la ruta completa (con query), hasheada para que
-- quepa en el indice: p.ej. sigenergy:openapi/systems/XXX/history?level=Day&date=...

CREATE TABLE IF NOT EXISTS `api_cache` (
    `clave`      VARCHAR(191) NOT NULL,
    `proveedor`  VARCHAR(50)  NOT NULL,
    `ruta`       TEXT         NOT NULL COMMENT 'Ruta original, solo para depurar',
    `respuesta`  LONGTEXT     NOT NULL COMMENT 'JSON de la respuesta del proveedor',
    `creado_en`  BIGINT       NOT NULL COMMENT 'Epoch en MILISEGUNDOS',
    `ttl_seg`    INT          NOT NULL DEFAULT 300,
    PRIMARY KEY (`clave`),
    KEY `idx_proveedor` (`proveedor`),
    KEY `idx_creado_en` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
