<?php

require_once __DIR__ . '/../services/MagicLinkService.php';
require_once __DIR__ . '/../services/correo.php';
require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../utils/respuesta.php';
require_once __DIR__ . '/../utils/seguridad.php';
require_once __DIR__ . '/../DBObjects/intentosLoginDB.php';
require_once __DIR__ . '/../DBObjects/metodosAccesoDB.php';
require_once __DIR__ . '/AccesoController.php';

/**
 * Acceso sin contraseña por enlace magico.
 *
 * Tres pasos:
 *
 *   POST /auth/magic-link   { email }        pide el enlace, lo manda por correo
 *   GET  /auth/magic?token= (el del correo)  valida y muestra confirmacion
 *   POST /auth/magic/consume { token }       consume el enlace y redirige
 *   POST /auth/handoff      { code }         canjea el codigo por el JWT
 *
 * El JWT nunca viaja en una URL. La redireccion del paso 2 lleva un handoff de
 * un solo uso y 60 segundos de vida; el frontend lo cambia por el JWT en el
 * paso 3 y lo borra de la barra de direcciones.
 *
 * @see MagicLinkService
 */
class MagicLinkController
{
    private $servicio;
    private $correo;

    public function __construct()
    {
        $this->servicio = new MagicLinkService();
        $this->correo = new Correo();
    }

    private function frontendUrl()
    {
        return rtrim($_ENV['FRONTEND_URL'] ?? 'https://app.energiasolarcanarias.com', '/');
    }

    /**
     * URL publica de ESTE backend. El enlace del correo apunta aqui, no a una
     * pagina del frontend: el secreto aparece brevemente en la URL del backend,
     * pero no se expone a scripts, analitica o recursos del frontend.
     */
    private function backendUrl()
    {
        return rtrim(
            $_ENV['BACKEND_URL'] ?? 'https://app-backend.energiasolarcanarias.com',
            '/'
        );
    }

    /**
     * Paso 1: pedir un enlace.
     *
     * Responde SIEMPRE lo mismo, exista el email o no.
     *
     * Sin contraseña de por medio, este endpoint es lo unico que separa a un
     * desconocido de saber quien es cliente. Si contestara "ese correo no
     * existe" seria un enumerador de clientes: pruebas una lista de correos y
     * te quedas con los que dan otra respuesta. El correo solo sale si el
     * usuario existe y puede entrar, pero la respuesta HTTP es identica.
     *
     * Ojo al mantenerlo: cualquier `return` temprano con otro mensaje, o un
     * tiempo de respuesta muy distinto, reintroduce la fuga.
     */
    public function solicitarEnlace($datosJson)
    {
        $respuesta = new Respuesta;
        $datos = json_decode($datosJson, true);
        $email = strtolower(trim((string) ($datos['email'] ?? '')));
        $idioma = $datos['idiomaUsuario'] ?? 'es';
        $idioma = is_string($idioma) && str_starts_with(strtolower($idioma), 'en') ? 'en' : 'es';
        $ip = ipCliente();

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $respuesta->_400();
            $respuesta->message = 'Email no valido';
            http_response_code(400);
            echo json_encode($respuesta);
            return;
        }

        // Anti fuerza bruta (ISO 27001:2022 A.8.5). Aqui frena sobre todo el
        // spam de correos: sin limite, cualquiera puede hacer que a un cliente
        // le lleguen cien enlaces.
        $intentos = new IntentosLoginDB();
        if ($intentos->estaBloqueado($email, $ip)) {
            registrarEventoSeguridad('magiclink_bloqueado', 'email=' . $email);
            $respuesta->_429();
            $respuesta->message = 'Demasiadas solicitudes. Espera unos minutos e inténtalo de nuevo.';
            http_response_code(429);
            echo json_encode($respuesta);
            return;
        }

        $usuario = $this->servicio->buscarUsuarioPorEmail($email);

        $yaContado = false;
        // Si el usuario ha apagado el acceso por enlace en Ajustes, no se le manda.
        // La respuesta es la misma de siempre: no puede delatar como entra cada cuenta.
        if ($usuario && !(new MetodosAccesoDB())->permitido((int) $usuario['id'], MetodosAcceso::ENLACE)) {
            $intentos->registrarFallo($email, $ip);
            registrarEventoSeguridad('magiclink_metodo_desactivado', 'email=' . $email);
            $usuario = null;
            $yaContado = true;
        }

        if ($usuario) {
            // Se cuenta TAMBIEN cuando el correo existe.
            //
            // Antes solo se registraba el caso desconocido, asi que el limite
            // nunca saltaba para una cuenta real: se podian pedir enlaces sin
            // freno y llenarle el buzon a un cliente, que es justo lo que este
            // bloque dice arriba que quiere evitar.
            //
            // Se registra ANTES de enviar: si el correo falla, el intento ya
            // cuenta igualmente y no queda un hueco por el que reintentar.
            $intentos->registrarFallo($email, $ip);

            $token = $this->servicio->emitirMagicLink($usuario['id'], $ip);
            $enlace = $this->backendUrl() . '/auth/magic?token=' . urlencode($token)
                . '&lang=' . urlencode($idioma);

            $this->correo->enlaceMagico($usuario, $enlace, $idioma);
            registrarEventoSeguridad('magiclink_enviado', 'email=' . $email);
        } elseif (!$yaContado) {
            // No se envia nada, pero se registra el intento: un pico de estos
            // en los logs es exactamente como se ve una enumeracion de correos.
            $intentos->registrarFallo($email, $ip);
            registrarEventoSeguridad('magiclink_email_desconocido', 'email=' . $email);
        }

        $respuesta->success([]);
        $respuesta->message = 'Si ese correo pertenece a una cuenta, recibirás un enlace de acceso.';
        http_response_code(200);
        echo json_encode($respuesta);
    }

    /**
     * Paso 2: el usuario pulsa el enlace del correo.
     *
     * Valida el enlace sin consumirlo y muestra confirmacion. Los filtros de
     * correo suelen visitar enlaces con GET; consumirlo aqui haria que el enlace
     * fallase cuando la persona lo abre despues.
     */
    public function confirmarEnlace()
    {
        $token = $_GET['token'] ?? '';
        $frontend = $this->frontendUrl();
        $idioma = strtolower((string) ($_GET['lang'] ?? 'es')) === 'en' ? 'en' : 'es';

        $this->cabecerasPaginaEnlace();
        if (!$this->servicio->validarMagicLink($token)) {
            registrarEventoSeguridad('magiclink_invalido', 'ip=' . ipCliente());
            $this->redirigirEnlaceInvalido($frontend);
            return;
        }

        // Ruta relativa: conserva el mismo origen aunque BACKEND_URL tenga un
        // alias externo, y satisface la politica form-action 'self'.
        $accion = '/auth/magic/consume';
        $tokenHtml = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $texto = $idioma === 'en'
            ? ['Confirm your sign-in', 'To protect your account, confirm that you want to sign in.', 'Continue to my account']
            : ['Confirma tu acceso', 'Para proteger tu cuenta, confirma que eres tú quien quiere iniciar sesión.', 'Acceder a mi cuenta'];
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="' . $idioma . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $texto[0] . '</title><style>'
            . 'body{font:16px system-ui,sans-serif;margin:0;background:#f5f7fa;color:#182230;min-height:100vh;display:grid;place-items:center}'
            . 'main{max-width:28rem;margin:1rem;padding:2rem;background:white;border:1px solid #dce2e9;border-radius:1rem;box-shadow:0 8px 32px #18223012}'
            . 'h1{font-size:1.5rem;margin-top:0}p{line-height:1.55;color:#485465}'
            . 'button{border:0;border-radius:.6rem;background:#087f5b;color:white;font:inherit;font-weight:600;padding:.8rem 1.1rem;cursor:pointer}'
            . '</style></head><body><main><h1>' . $texto[0] . '</h1>'
            . '<p>' . $texto[1] . '</p>'
            . '<form method="post" action="' . $accion . '"><input type="hidden" name="token" value="' . $tokenHtml . '">'
            . '<button type="submit">' . $texto[2] . '</button></form></main></body></html>';
    }

    /** Consume el enlace solo tras la accion explicita en el formulario. */
    public function consumirEnlace($token)
    {
        $frontend = $this->frontendUrl();
        $this->cabecerasPaginaEnlace();
        $usuarioId = $this->servicio->canjearMagicLink($token);

        if (!$usuarioId) {
            registrarEventoSeguridad('magiclink_invalido', 'ip=' . ipCliente());
            $this->redirigirEnlaceInvalido($frontend);
            exit;
        }

        $handoff = $this->servicio->emitirHandoff($usuarioId);
        registrarEventoSeguridad('magiclink_ok', 'usuario=' . $usuarioId);

        header('Location: ' . $frontend . '/auth/callback?code=' . urlencode($handoff), true, 302);
        exit;
    }

    /** No permitir que proxy, navegador o referer almacenen/filtren el secreto. */
    private function cabecerasPaginaEnlace(): void
    {
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        $origenFrontend = $this->origenFrontend();
        $formAction = "'self'" . ($origenFrontend !== null ? ' ' . $origenFrontend : '');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action $formAction; base-uri 'none'; frame-ancestors 'none'");
    }

    /** Origen exacto del destino de confianza al que redirige el POST. */
    private function origenFrontend(): ?string
    {
        $partes = parse_url($this->frontendUrl());
        if (!is_array($partes)) return null;
        $esquema = strtolower((string) ($partes['scheme'] ?? ''));
        $host = (string) ($partes['host'] ?? '');
        if (!in_array($esquema, ['http', 'https'], true) || $host === '') return null;

        $origen = $esquema . '://' . $host;
        if (isset($partes['port'])) $origen .= ':' . (int) $partes['port'];
        return $origen;
    }

    /** Mantener indistinguibles los enlaces inexistentes, usados y caducados. */
    private function redirigirEnlaceInvalido(string $frontend): void
    {
        header('Location: ' . $frontend . '/login?error=enlace_invalido', true, 302);
    }

    /**
     * Paso 3: el frontend canjea el handoff por el JWT de sesion.
     */
    public function canjearHandoff($datosJson)
    {
        $respuesta = new Respuesta;
        if (!SesionWebController::permitida()) {
            $respuesta->_403();
            $respuesta->message = 'Origen o formato de acceso no permitido';
            http_response_code(403); echo json_encode($respuesta); return;
        }
        $datos = json_decode($datosJson, true);
        $codigo = (string) ($datos['code'] ?? '');

        $usuarioId = $this->servicio->canjearHandoff($codigo);

        if (!$usuarioId) {
            $respuesta->_401();
            $respuesta->message = 'Código de acceso no válido o caducado';
            http_response_code(401);
            echo json_encode($respuesta);
            return;
        }

        // Se vuelve a comprobar el estado del usuario: entre pedir el enlace y
        // canjearlo pueden pasar 15 minutos, y en ese hueco se le puede haber
        // dado de baja. datosSesion() devuelve null en ese caso.
        $datosUsuario = AccesoController::datosSesion((int) $usuarioId);
        if (!$datosUsuario) {
            $respuesta->_401();
            $respuesta->message = 'La cuenta ya no está disponible';
            http_response_code(401);
            echo json_encode($respuesta);
            return;
        }

        $respuesta->success($datosUsuario);
        $respuesta->message = 'Sesión iniciada';
        http_response_code(200);
        echo json_encode($respuesta);
    }
}
