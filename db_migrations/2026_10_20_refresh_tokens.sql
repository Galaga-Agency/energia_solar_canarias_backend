CREATE TABLE IF NOT EXISTS auth_sesiones (
    id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    usuario_id INT NOT NULL,
    credencial_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    creado_en BIGINT NOT NULL,
    ultimo_uso BIGINT NOT NULL,
    limite_absoluto BIGINT NOT NULL,
    revocada_en BIGINT NULL,
    PRIMARY KEY (id),
    KEY idx_auth_usuario (usuario_id),
    KEY idx_auth_limite (limite_absoluto),
    CONSTRAINT fk_auth_sesion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (usuario_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_refresh_tokens (
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sesion_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    creado_en BIGINT NOT NULL,
    expira_en BIGINT NOT NULL,
    consumido_en BIGINT NULL,
    PRIMARY KEY (token_hash),
    KEY idx_auth_refresh_sesion (sesion_id),
    CONSTRAINT fk_auth_refresh_sesion FOREIGN KEY (sesion_id) REFERENCES auth_sesiones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
