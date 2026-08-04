-- Acceso sin contraseña por enlace magico (magic link).
--
-- Sustituye al flujo actual de dos pasos (contraseña + codigo de 32 caracteres
-- que el usuario copia del correo a mano). Ahora el correo lleva un enlace: el
-- usuario pulsa y entra. No hay contraseña que recordar, reutilizar ni filtrar.
--
-- Dos tablas porque son dos cosas distintas con vidas distintas:
--
--   magic_links   el enlace que viaja por correo. Vive 15 minutos y es de UN
--                 SOLO USO. La tabla `token` actual no sirve: su token se borra
--                 solo DESPUES de canjearlo, asi que dentro de la ventana de 5
--                 minutos el mismo codigo vale varias veces. Aqui se marca como
--                 consumido en la misma transaccion en que se valida.
--
--   auth_handoffs el codigo de un solo uso que el backend le pasa al frontend
--                 en la redireccion. Vive 60 segundos. Existe para que el JWT
--                 de sesion NO viaje en la URL: una URL acaba en el historial
--                 del navegador, en la cabecera Referer y en los logs de
--                 cualquier proxy por el camino. El handoff se canjea por POST
--                 nada mas llegar y deja de valer.
--
-- Se guarda el HASH del token, nunca el token en claro. Si alguien lee la base
-- de datos no puede iniciar sesion como nadie: un volcado de `magic_links` no
-- vale de nada sin los enlaces originales, que solo estan en el correo del
-- usuario. Mismo motivo por el que no se guardan contraseñas en claro.

CREATE TABLE IF NOT EXISTS `magic_links` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`   INT             NOT NULL,
    -- SHA-256 en hexadecimal: 64 caracteres, longitud fija.
    `token_hash`   CHAR(64)        NOT NULL,
    `creado_en`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expira_en`    DATETIME        NOT NULL,
    -- NULL mientras no se use. Al canjearlo se pone la fecha: eso es lo que
    -- convierte el enlace en de un solo uso.
    `consumido_en` DATETIME        DEFAULT NULL,
    -- Para auditoria: desde donde se pidio el enlace.
    `ip_solicitud` VARCHAR(45)     DEFAULT NULL,
    PRIMARY KEY (`id`),
    -- UNIQUE y no solo KEY: dos enlaces con el mismo hash serian el mismo
    -- enlace, y el canje tiene que poder identificar UNA fila sin ambiguedad.
    UNIQUE KEY `uq_token_hash` (`token_hash`),
    KEY `idx_usuario` (`usuario_id`),
    -- Para la purga por antiguedad del cron.
    KEY `idx_expira_en` (`expira_en`),
    CONSTRAINT `fk_magic_usuario` FOREIGN KEY (`usuario_id`)
        REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `auth_handoffs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`   INT             NOT NULL,
    `code_hash`    CHAR(64)        NOT NULL,
    `creado_en`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- 60 segundos: el tiempo que tarda el navegador en seguir la redireccion y
    -- hacer el POST. No hay motivo para que viva mas.
    `expira_en`    DATETIME        NOT NULL,
    `consumido_en` DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code_hash` (`code_hash`),
    KEY `idx_expira_en` (`expira_en`),
    CONSTRAINT `fk_handoff_usuario` FOREIGN KEY (`usuario_id`)
        REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
