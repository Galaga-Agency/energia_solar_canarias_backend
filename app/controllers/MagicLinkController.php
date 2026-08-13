<?php

require_once __DIR__ . '/../services/MagicLinkService.php';
require_once __DIR__ . '/../services/correo.php';
require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../utils/respuesta.php';
require_once __DIR__ . '/../utils/seguridad.php';
require_once __DIR__ . '/../DBObjects/intentosLoginDB.php';

/**
 * Acceso sin contraseña por enlace magico.
 *
 * Tres pasos:
 *
 *   POST /auth/magic-link   { email }        pide el enlace, lo manda por correo
 *   GET  /auth/magic?token= (el del correo)  valida y redirige al frontend
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
     * URL publica de ESTE backend. El enlace del correo apunta aqui, no al
     * frontend: quien valida y consume el token es el backend, y asi el token
     * no llega nunca al navegador como parametro de una pagina.
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
            $enlace = $this->backendUrl() . '/auth/magic?token=' . urlencode($token);

            $this->correo->enlaceMagico($usuario, $enlace, $idioma);
            registrarEventoSeguridad('magiclink_enviado', 'email=' . $email);
        } else {
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
     * Valida, consume el token y redirige al frontend. Nunca devuelve JSON: el
     * navegador ha llegado aqui siguiendo un enlace, asi que la respuesta tiene
     * que ser una redireccion en ambos casos.
     */
    public function canjearEnlace()
    {
        $token = $_GET['token'] ?? '';
        $frontend = $this->frontendUrl();

        $usuarioId = $this->servicio->canjearMagicLink($token);

        if (!$usuarioId) {
            registrarEventoSeguridad('magiclink_invalido', 'ip=' . ipCliente());
            // Un solo motivo de error hacia fuera: caducado, ya usado o
            // inexistente son indistinguibles a proposito.
            header('Location: ' . $frontend . '/login?error=enlace_invalido', true, 302);
            exit;
        }

        $handoff = $this->servicio->emitirHandoff($usuarioId);
        registrarEventoSeguridad('magiclink_ok', 'usuario=' . $usuarioId);

        header('Location: ' . $frontend . '/auth/callback?code=' . urlencode($handoff), true, 302);
        exit;
    }

    /**
     * Paso 3: el frontend canjea el handoff por el JWT de sesion.
     */
    public function canjearHandoff($datosJson)
    {
        $respuesta = new Respuesta;
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

        $conn = Conexion::getInstance()->getConexion();
        $sql = "SELECT usuarios.usuario_id, usuarios.email, clases.nombre AS clase,
                       usuarios.nombre, usuarios.apellido, usuarios.movil, usuarios.imagen
                  FROM usuarios
            INNER JOIN clases ON clases.clase_id = usuarios.clase_id
                 WHERE usuarios.usuario_id = ?
                   AND usuarios.eliminado = 0
                   AND usuarios.activo = 1
                 LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Se vuelve a comprobar el estado del usuario: entre pedir el enlace y
        // canjearlo pueden pasar 15 minutos, y en ese hueco se le puede haber
        // dado de baja.
        if (!$fila) {
            $respuesta->_401();
            $respuesta->message = 'La cuenta ya no está disponible';
            http_response_code(401);
            echo json_encode($respuesta);
            return;
        }

        $datosUsuario = [
            'usuario_id' => (int) $fila['usuario_id'],
            'email'      => $fila['email'],
            'clase'      => $fila['clase'],
            'nombre'     => $fila['nombre'],
            'apellido'   => $fila['apellido'],
            'movil'      => $fila['movil'],
            'imagen'     => $fila['imagen'],
            // Mismo nombre de campo que el flujo antiguo, para que el frontend
            // no tenga que distinguir de donde vino la sesion.
            'tokenIdentificador' => Conexion::jwtSesion($fila['usuario_id'], $fila['email']),
        ];

        $respuesta->success($datosUsuario);
        $respuesta->message = 'Sesión iniciada';
        http_response_code(200);
        echo json_encode($respuesta);
    }
}
