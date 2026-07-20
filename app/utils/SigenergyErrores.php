<?php

/**
 * Catalogo de codigos de error de la Openapi de Sigenergy, traducidos.
 *
 * Sigenergy responde SIEMPRE con HTTP 200 y mete el error real en el campo `code`
 * del cuerpo (0 = exito). Sin traducir, un fallo llega al frontend como
 * "Station not permitted" y nadie sabe si es culpa nuestra, de permisos o de la
 * planta. Aqui cada codigo se convierte en: que ha pasado, por que, y que hacer.
 *
 * Fuente: "Error Code List" de la documentacion oficial (Sigen Developer Portal,
 * pag. 114-115) + los codigos de autenticacion de la seccion "Based On Sigen
 * Account" + los observados en produccion que NO estan documentados (ver abajo).
 *
 * Cada entrada:
 *   mensaje     texto corto para enseñar al usuario final
 *   causa       por que ocurre (para el que depura)
 *   http        codigo HTTP con el que deberiamos responder nosotros
 *   transitorio true = reintentar tiene sentido; NO cachear la respuesta
 */
class SigenergyErrores
{
    /**
     * Codigos vistos en real que NO aparecen en la documentacion oficial.
     * Se descubrieron probando contra la API con plantas reales, asi que si algun dia
     * dejan de existir o cambian de significado, es aqui donde hay que mirar.
     */
    private const NO_DOCUMENTADOS = [13008];

    private const CATALOGO = [
        0 => [
            'mensaje' => 'Correcto',
            'causa' => 'La llamada fue bien.',
            'http' => 200,
            'transitorio' => false,
        ],

        // --- Transitorios: reintentar tiene sentido -------------------------------
        -1 => [
            'mensaje' => 'No se pudo contactar con Sigenergy',
            'causa' => 'Fallo de red nuestro (timeout, DNS o respuesta ilegible). No llego a la API.',
            'http' => 504,
            'transitorio' => true,
        ],
        1 => [
            'mensaje' => 'Error interno de Sigenergy',
            'causa' => 'Fallo generico y pasajero de su plataforma.',
            'http' => 502,
            'transitorio' => true,
        ],
        1109 => [
            'mensaje' => 'Sigenergy no pudo hablar con el equipo',
            'causa' => 'RPC fail: su nube no obtuvo respuesta del inversor. Suele ser cobertura.',
            'http' => 504,
            'transitorio' => true,
        ],
        1110 => [
            'mensaje' => 'Sigenergy esta limitando las peticiones',
            'causa' => 'Interface is current-limited: se ha superado el cupo global de la API.',
            'http' => 429,
            'transitorio' => true,
        ],
        1201 => [
            // El importante: es el rate-limit por estacion, y por eso existe la cache.
            'mensaje' => 'Demasiado pronto: espera unos minutos',
            'causa' => 'Access restriction: solo se puede consultar cada estacion 1 vez cada 5 min. '
                . 'Si sale esto es que la cache no cubrio la llamada (ver CacheApiService).',
            'http' => 429,
            'transitorio' => true,
        ],
        1501 => [
            'mensaje' => 'Sigenergy no pudo ejecutar la orden',
            'causa' => 'Failed to execute command: el equipo rechazo o no completo el comando.',
            'http' => 502,
            'transitorio' => true,
        ],
        1502 => [
            'mensaje' => 'Error interno de Sigenergy',
            'causa' => 'Sigenergy system internal error: fallo de su plataforma.',
            'http' => 502,
            'transitorio' => true,
        ],
        1601 => [
            'mensaje' => 'Error del sistema de cuentas de Sigenergy',
            'causa' => 'Account system error: fallo en su gestion de cuentas.',
            'http' => 502,
            'transitorio' => true,
        ],
        11002 => [
            'mensaje' => 'Cuenta de Sigenergy bloqueada temporalmente',
            'causa' => '5 contraseñas mal en 60 min bloquean la cuenta 3 min. Revisa la contraseña '
                . 'guardada en la tabla proveedores antes de reintentar, o la seguira bloqueando.',
            'http' => 503,
            'transitorio' => true,
        ],
        11003 => [
            'mensaje' => 'Fallo al autenticar con Sigenergy',
            'causa' => 'authentication failed: usuario/contraseña incorrectos o token invalido.',
            'http' => 502,
            'transitorio' => true,
        ],

        // --- Estables: reintentar da lo mismo; se pueden cachear -------------------
        1000 => [
            'mensaje' => 'Parametros incorrectos',
            'causa' => 'Param illegal: falta un parametro o tiene formato malo. Suele ser bug nuestro '
                . '(p.ej. `level` fuera de Day/Week/Month/Year/Lifetime, o `date` que no es yyyy-MM-dd).',
            'http' => 400,
            'transitorio' => false,
        ],
        1104 => [
            'mensaje' => 'El equipo esta desconectado',
            'causa' => 'Device offline: el inversor no esta en linea.',
            'http' => 200,
            'transitorio' => false,
        ],
        1106 => [
            'mensaje' => 'Planta no encontrada',
            'causa' => 'Station was not found: el systemId no existe.',
            'http' => 404,
            'transitorio' => false,
        ],
        1108 => [
            'mensaje' => 'No hay informacion de la planta',
            'causa' => 'Station info not found: la planta existe pero no tiene datos asociados.',
            'http' => 404,
            'transitorio' => false,
        ],
        1111 => [
            'mensaje' => 'Planta no encontrada o sin acceso',
            'causa' => 'Station not permitted: el systemId no existe o no pertenece a nuestra cuenta. '
                . 'Es tambien lo que responde ante un id inventado.',
            'http' => 404,
            'transitorio' => false,
        ],
        1302 => [
            'mensaje' => 'Estado de la planta anomalo',
            'causa' => 'Station status anomaly.',
            'http' => 409,
            'transitorio' => false,
        ],
        1304 => [
            'mensaje' => 'Version de firmware incompatible',
            'causa' => 'Firmware version mismatch: el equipo necesita actualizarse.',
            'http' => 409,
            'transitorio' => false,
        ],
        1401 => [
            'mensaje' => 'Sin permiso sobre esta planta',
            'causa' => 'No permission to operate this station: la cuenta no tiene permisos sobre ella.',
            'http' => 403,
            'transitorio' => false,
        ],
        1402 => [
            'mensaje' => 'Sin permiso',
            'causa' => 'No permission: la cuenta no tiene acceso a este recurso.',
            'http' => 403,
            'transitorio' => false,
        ],
        1603 => [
            'mensaje' => 'Cuenta pendiente de revision',
            'causa' => 'Account unReviewed: Sigenergy aun no ha aprobado la cuenta.',
            'http' => 403,
            'transitorio' => false,
        ],
        1604 => [
            'mensaje' => 'Desarrollador no aprobado',
            'causa' => 'Developer not approved: falta que Sigenergy apruebe la app en el portal.',
            'http' => 403,
            'transitorio' => false,
        ],

        // Este NO esta en la documentacion: lo devuelven plantas apagadas o sin
        // cobertura. Importante cachearlo (transitorio=false): el limite de 1 acceso
        // cada 5 min se aplica igual aunque la respuesta sea un error, asi que
        // reintentarlo machacaria la API justo con las plantas que no responden.
        13008 => [
            'mensaje' => 'La planta esta desconectada',
            'causa' => 'station disconnect: sin comunicacion con la nube. NO documentado por Sigenergy; '
                . 'observado en plantas apagadas.',
            'http' => 200,
            'transitorio' => false,
        ],

        // --- Del catalogo oficial, propios de VPP/onboarding: no los usamos hoy,
        //     pero si aparecen queremos leerlos y no un numero pelado.
        1101 => ['mensaje' => 'Numero de serie incorrecto', 'causa' => 'Wrong serial.', 'http' => 400, 'transitorio' => false],
        1102 => ['mensaje' => 'Registro incompleto', 'causa' => 'Registration incomplete.', 'http' => 409, 'transitorio' => false],
        1103 => ['mensaje' => 'La planta ya esta en otro VPP', 'causa' => 'In other VPP.', 'http' => 409, 'transitorio' => false],
        1105 => ['mensaje' => 'El software del equipo no admite VPP', 'causa' => 'Current software version does not support VPP.', 'http' => 409, 'transitorio' => false],
        1107 => ['mensaje' => 'Solo equipos AIO e inversores', 'causa' => 'AIO units and Inverters only.', 'http' => 400, 'transitorio' => false],
        1112 => ['mensaje' => 'La planta ya esta en otro VPP (Evergen)', 'causa' => 'In other VPP (Evergen).', 'http' => 409, 'transitorio' => false],
        1301 => ['mensaje' => 'Cliente no encontrado', 'causa' => 'Client not found.', 'http' => 404, 'transitorio' => false],
        1303 => ['mensaje' => 'El cliente ya existe', 'causa' => 'Client has existed.', 'http' => 409, 'transitorio' => false],
        1503 => ['mensaje' => 'La planta tiene el antivertido activado', 'causa' => 'Anti-backflow setting enabled.', 'http' => 409, 'transitorio' => false],
        1504 => ['mensaje' => 'La planta tiene peak shaving activado', 'causa' => 'Peak shaving enabled.', 'http' => 409, 'transitorio' => false],
        1600 => ['mensaje' => 'La invitacion no es valida', 'causa' => 'The invitation is invalid.', 'http' => 400, 'transitorio' => false],
        1602 => ['mensaje' => 'La cuenta ya esta registrada', 'causa' => 'Account already registered.', 'http' => 409, 'transitorio' => false],
    ];

    /** True si la respuesta indica exito (code == 0). */
    public static function esExito($resp)
    {
        return is_array($resp) && isset($resp['code']) && (int) $resp['code'] === 0;
    }

    /**
     * Traduce un codigo. Si es desconocido devuelve una entrada generica y lo marca
     * como transitorio: ante algo que no conocemos, preferimos reintentar antes que
     * cachear durante 5 min una respuesta que no sabemos interpretar.
     *
     * @return array{codigo:int,mensaje:string,causa:string,http:int,transitorio:bool,documentado:bool}
     */
    public static function traducir($code, $msgOriginal = null)
    {
        $code = (int) $code;
        if (isset(self::CATALOGO[$code])) {
            $e = self::CATALOGO[$code];
            $e['codigo'] = $code;
            $e['documentado'] = !in_array($code, self::NO_DOCUMENTADOS, true);
            return $e;
        }
        return [
            'codigo' => $code,
            'mensaje' => 'Error desconocido de Sigenergy',
            'causa' => 'Codigo ' . $code . ' no catalogado'
                . ($msgOriginal ? ' (msg original: "' . $msgOriginal . '")' : '')
                . '. Revisa el "Error Code List" de la doc y añadelo a SigenergyErrores.',
            'http' => 502,
            'transitorio' => true,
            'documentado' => false,
        ];
    }

    /** Traduce directamente una respuesta cruda de la Openapi. */
    public static function deRespuesta($resp)
    {
        $code = is_array($resp) ? ($resp['code'] ?? -1) : -1;
        $msg = is_array($resp) ? ($resp['msg'] ?? null) : null;
        return self::traducir($code, $msg);
    }

    /**
     * Si merece la pena reintentar. Lo usa la cache para decidir que NO guardar:
     * cachear un 1201 seria el peor caso posible, serviriamos "Access restriction"
     * como si fuera un dato bueno durante 5 min a todos los usuarios.
     */
    public static function esTransitorio($code)
    {
        return (bool) self::traducir($code)['transitorio'];
    }
}
