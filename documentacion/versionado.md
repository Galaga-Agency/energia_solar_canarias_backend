# Versionado, CI y releases

## Versiones

- La versión actual está en **`VERSION`** y sigue [SemVer](https://semver.org/lang/es/):
  - **MAYOR** cuando se rompe el contrato de la API (un endpoint cambia de forma o desaparece).
  - **MENOR** cuando se añade algo (un endpoint, un campo, un método de acceso).
  - **PARCHE** cuando se arregla algo sin cambiar el contrato.
- **`CHANGELOG.md`** lleva lo que cambia en cada versión. Lo nuevo se apunta en
  `## [Sin publicar]` según se va mergeando.

### Publicar una versión

1. Pasa lo de `## [Sin publicar]` a una sección `## [X.Y.Z] - AAAA-MM-DD`.
2. Pon `X.Y.Z` en `VERSION`.
3. Mergea a `main` (eso despliega).
4. Crea el tag y súbelo:

   ```bash
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```

5. El workflow **Release** comprueba que el tag coincide con `VERSION` y publica la release
   en GitHub con las notas de esa sección del changelog.

## Workflows (`.github/workflows/`)

### `php.yml` (CI/CD), en cada push y PR a `main`

| Job | Qué hace | Si falla |
|---|---|---|
| **Tests y calidad** | `composer install`, `composer audit` (CVE), PHPStan con baseline y PHPUnit, en PHP 8.2 como producción | No se despliega |
| **Escaneo de secretos** | gitleaks sobre el árbol (`.gitleaks.toml`) | No se despliega |
| **Versión y changelog** | `VERSION` es SemVer y tiene su sección en `CHANGELOG.md` | No se despliega |
| **Deploy** (solo push a `main`) | Backup de la BD, `git reset --hard` al commit probado, `docker compose up -d --build esc_backend` y comprobación de humo | Sale en rojo |

La comprobación de humo verifica que el contenedor está arrancado y responde por HTTP, que
las variables `ZOHO_*` llegan a PHP y que **Zoho no está desactivado** en producción.

### `release.yml`, al subir un tag `vX.Y.Z`

Publica la release con las notas del changelog. No despliega.

## Dependabot (`.github/dependabot.yml`)

Abre PRs semanales para:

- Dependencias de Composer. Sin saltos de versión mayor de PHPUnit: el proyecto está en PHP 8.2.
- Las acciones de GitHub de los workflows.
- La imagen base del `Dockerfile` (`php:8.2-apache`): solo parches. Pasar a 8.3 es una migración.
- Las imágenes del `docker-compose.yml` local (mysql, mailpit, phpmyadmin), una vez al mes.

## Secretos

- Nada con credenciales va al repo: `config/.env`, `config/smtp.json`,
  `config/conexion.json`, `bruno/environments/*.bru` y `db_init/*.sql` están en `.gitignore`.
- Las plantillas sí van: `config/claves_example.env`, `config/smtp_example.json` y
  `bruno/environments/EU.example.bru` (con valores `TU_...`).
- gitleaks rompe el CI si se cuela una credencial.
