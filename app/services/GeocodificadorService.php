<?php
require_once __DIR__ . '/../models/conexion.php';

/**
 * Direccion -> coordenadas, para las plantas cuyo proveedor no las da.
 *
 * Sigenergy no expone coordenadas en su Openapi y el detalle de GoodWe tampoco
 * las trae, pero los cinco proveedores dan una direccion. Una planta con
 * direccion tiene que poder salir en el mapa, venga de donde venga.
 *
 * Se apoya en Nominatim (OpenStreetMap): gratuito y sin clave, a cambio de dos
 * condiciones que aqui se respetan —
 *
 *   · User-Agent identificable. Sin el, bloquean la IP.
 *   · Maximo 1 peticion por segundo, y no repetir consultas ya hechas. De ahi
 *     la tabla `geocodificacion_cache` y la pausa entre llamadas.
 *
 * NO reutiliza app/models/Nominatim.php a proposito: aquel hace `die()` cuando
 * la peticion falla o no hay resultado, lo que aqui tumbaria la lista entera de
 * plantas por una sola direccion mala. Aqui un fallo devuelve null y la planta
 * simplemente no sale en el mapa.
 */
class GeocodificadorService
{
    /** Segundos entre llamadas a Nominatim (su politica: maximo 1/s). */
    private const PAUSA_ENTRE_LLAMADAS = 1;

    /**
     * Cuantas direcciones NUEVAS se resuelven como maximo en una peticion.
     *
     * Estaba en 8, pensado para Nominatim, que obliga a esperar un segundo
     * entre llamadas: resolver mas habria dejado la pantalla colgada. Con esa
     * cifra y una flota de 130 plantas hacian falta media docena de cargas
     * para llenar el mapa, y el usuario veia 44 instalaciones sin ubicar sin
     * entender por que.
     *
     * Google responde en unos 200 ms y no pide pausa, asi que 40 direcciones
     * nuevas suponen unos ocho segundos en el PEOR caso — la primera vez y
     * solo para las que aun no esten cacheadas. A partir de ahi es cero.
     *
     * El tope diario de 500 sigue siendo el freno de gasto de verdad.
     */
    private const MAXIMO_POR_PETICION = 40;

    private mysqli $db;
    /** Cache en memoria, para no releer la tabla dentro de la misma peticion. */
    private array $memoria = [];

    /**
     * Compartidos entre instancias, a proposito.
     *
     * `rellenarCoordenadasPorDireccion` crea un geocodificador por llamada, asi
     * que un contador de instancia no limitaba nada: se vieron ~40 consultas en
     * 30 segundos, muy por encima del maximo de 1/s que pide Nominatim. Pasado
     * ese ritmo responde vacio, y esas respuestas vacias se cacheaban como
     * "esta direccion no existe" — por eso direcciones perfectamente validas
     * quedaban sin punto en el mapa.
     */
    private static float $ultimaLlamada = 0.0;
    private static int $llamadasEstaPeticion = 0;

    public function __construct()
    {
        $this->db = Conexion::getInstance()->getConexion();
        // Las direcciones llevan tildes y enes. Sin fijar el charset, la
        // conexion usa latin1 y se guardan (y se leen) rotas: "Gu�a" en vez de
        // "Guía". Se enviaban asi a Nominatim, que no las reconocia.
        $this->db->set_charset('utf8mb4');
    }

    /**
     * Normaliza antes de hashear: "Calle X, Arucas" y "calle x,  arucas" son la
     * misma consulta y no deben ocupar dos filas ni gastar dos peticiones.
     */
    private function normalizar(string $direccion): string
    {
        $limpia = trim(preg_replace('/\s+/u', ' ', $direccion));
        return mb_strtolower($limpia, 'UTF-8');
    }

    /**
     * Coordenadas de una direccion, o null si no se pueden obtener.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function coordenadas(?string $direccion): ?array
    {
        if ($direccion === null) { return null; }

        $normalizada = $this->normalizar($direccion);
        // Una direccion de tres letras no es una direccion; preguntarlo solo
        // gasta cuota y devuelve cualquier cosa.
        if (mb_strlen($normalizada) < 5) { return null; }

        if (array_key_exists($normalizada, $this->memoria)) {
            return $this->memoria[$normalizada];
        }

        $hash = hash('sha256', $normalizada);

        $cacheada = $this->leerCache($hash);
        if ($cacheada !== false) {
            // Puede ser null: "ya se pregunto y no existe". Se respeta igual,
            // porque si no se reintentaria en cada carga para siempre.
            $this->memoria[$normalizada] = $cacheada;
            return $cacheada;
        }

        // Tope por peticion: un cliente con cuarenta plantas sin coordenadas no
        // puede convertir una carga de pantalla en cuarenta segundos de espera.
        // Las que falten se resolveran en las siguientes cargas, y quedaran
        // cacheadas para siempre.
        if (self::$llamadasEstaPeticion >= self::MAXIMO_POR_PETICION) {
            return null;
        }

        // Tope DIARIO, la red de seguridad de verdad.
        //
        // Todo lo de arriba evita repetir consultas, pero solo funciona si la
        // cache se puede escribir. Si la tabla desaparece o la BD rechaza el
        // INSERT, cada carga de pantalla volveria a preguntar por las mismas
        // direcciones y la factura crece sola. Este contador vive en la propia
        // BD, cuenta llamadas reales y corta el grifo pase lo que pase.
        if (!$this->quedaCuotaHoy()) {
            return null;
        }

        $coordenadas = $this->resolverConReintentos($direccion);
        self::$llamadasEstaPeticion++;
        $this->apuntarLlamada();

        // Si la cache NO se pudo escribir, se apaga el geocodificador para el
        // resto de la peticion: sin escritura no hay memoria entre cargas, y
        // seguir preguntando seria pagar lo mismo una y otra vez.
        if (!$this->guardarCache($hash, $direccion, $coordenadas)) {
            error_log('GeocodificadorService: no se pudo escribir en cache; se detienen las consultas');
            self::$llamadasEstaPeticion = self::MAXIMO_POR_PETICION;
        }

        $this->memoria[$normalizada] = $coordenadas;

        return $coordenadas;
    }

    /**
     * @return array{lat: float, lng: float}|null|false  false = no cacheada
     */
    private function leerCache(string $hash)
    {
        $stmt = $this->db->prepare(
            "SELECT latitud, longitud FROM geocodificacion_cache
              WHERE direccion_hash = ? LIMIT 1"
        );
        if (!$stmt) { return false; }

        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$fila) { return false; }
        if ($fila['latitud'] === null || $fila['longitud'] === null) { return null; }

        return ['lat' => (float) $fila['latitud'], 'lng' => (float) $fila['longitud']];
    }

    /** @return bool true si la fila quedo guardada. */
    private function guardarCache(string $hash, string $direccion, ?array $coordenadas): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO geocodificacion_cache (direccion_hash, direccion, latitud, longitud)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE latitud = VALUES(latitud), longitud = VALUES(longitud)"
        );
        if (!$stmt) { return false; }

        $lat = $coordenadas['lat'] ?? null;
        $lng = $coordenadas['lng'] ?? null;
        $stmt->bind_param('ssdd', $hash, $direccion, $lat, $lng);
        $ok = $stmt->execute();
        $stmt->close();

        return (bool) $ok;
    }

    /**
     * Tope diario de llamadas reales al geocodificador.
     *
     * 500 al dia es holgadisimo para lo que esto hace — las direcciones de una
     * flota se resuelven UNA vez y quedan cacheadas para siempre, asi que en
     * regimen normal esto marca cero — y a la vez es un techo duro: aunque todo
     * lo demas falle, no se pueden gastar mas de 500 geocodificaciones en un
     * dia. Con la tarifa de Google eso son unos 2,50 $, dentro del credito
     * mensual gratuito de 200 $.
     */
    private const MAXIMO_POR_DIA = 500;

    private function quedaCuotaHoy(): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total FROM geocodificacion_llamadas
              WHERE fecha = CURDATE()"
        );
        // Sin tabla de contador no se geocodifica. Es deliberado: preferimos
        // quedarnos sin puntos en el mapa a perder el unico freno de gasto.
        if (!$stmt) { return false; }

        $stmt->execute();
        $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();

        if ($total >= self::MAXIMO_POR_DIA) {
            error_log('GeocodificadorService: alcanzado el tope diario de ' . self::MAXIMO_POR_DIA . ' consultas');
            return false;
        }

        return true;
    }

    private function apuntarLlamada(): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO geocodificacion_llamadas (fecha, momento) VALUES (CURDATE(), NOW())"
        );
        if (!$stmt) { return; }
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Intenta la direccion completa y, si no la reconoce, va soltando detalle.
     *
     * Nominatim solo devuelve lo que existe en OpenStreetMap, y muchas calles
     * de los pueblos de Gran Canaria no estan mapeadas: "C. Juan Godoy Ramos,
     * 50, 35450 Guia" no da resultado, pero "Guia, Las Palmas" si. Un punto en
     * el municipio correcto es infinitamente mas util que ningun punto —
     * el usuario ve donde esta la instalacion aunque no sea el portal exacto.
     *
     * De ahi tambien `coordinates_approximate` en la respuesta: el front puede
     * distinguir un punto exacto de uno aproximado si algun dia hace falta.
     *
     * Las variantes se prueban de la mas precisa a la mas general y se para en
     * la primera que acierte.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function resolverConReintentos(string $direccion): ?array
    {
        // GOOGLE PRIMERO cuando hay clave.
        //
        // Nominatim solo conoce lo que este mapeado en OpenStreetMap, y muchas
        // calles de los pueblos de Gran Canaria no lo estan. Peor: es
        // incoherente — "Guia, Las Palmas" lo encuentra pero "35450 Guia, Las
        // Palmas" no, y "35213 Telde" si mientras "Telde" no. No hay forma de
        // reescribir la consulta que arregle eso.
        //
        // Google resuelve estas direcciones tal cual, en un solo intento. Se
        // deja Nominatim como respaldo gratuito para cuando no haya clave o
        // Google no conteste, que es mejor que quedarse sin nada.
        $clave = $_ENV['GOOGLE_GEOCODING_API_KEY'] ?? getenv('GOOGLE_GEOCODING_API_KEY') ?: null;

        if ($clave) {
            $coordenadas = $this->consultarGoogle($direccion, $clave);
            if ($coordenadas !== null) { return $coordenadas; }
        }

        foreach ($this->variantes($direccion) as $intento) {
            $coordenadas = $this->consultarNominatim($intento);
            if ($coordenadas !== null) { return $coordenadas; }
        }

        return null;
    }

    /**
     * Google Geocoding. Una sola consulta: entiende la direccion completa.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function consultarGoogle(string $direccion, string $clave): ?array
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json'
             . '?address=' . urlencode($direccion)
             // Sesga el resultado a Espana: sin esto, una direccion ambigua
             // puede caer en Sudamerica, que comparte muchos nombres de calle.
             . '&region=es'
             . '&key=' . urlencode($clave);

        $contexto = stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true],
        ]);

        $respuesta = @file_get_contents($url, false, $contexto);
        if ($respuesta === false) { return null; }

        $datos = json_decode($respuesta, true);
        $estado = $datos['status'] ?? '';

        // ZERO_RESULTS es una respuesta legitima: Google tampoco la conoce.
        // Cualquier otro estado que no sea OK es un problema de configuracion
        // (clave invalida, API sin habilitar, cuota agotada) y conviene que
        // quede en el log en vez de parecer "direccion no encontrada".
        if ($estado !== 'OK') {
            if ($estado !== 'ZERO_RESULTS') {
                error_log('Google Geocoding devolvio ' . $estado . ': ' . ($datos['error_message'] ?? ''));
            }
            return null;
        }

        $punto = $datos['results'][0]['geometry']['location'] ?? null;
        if (!isset($punto['lat'], $punto['lng'])) { return null; }

        return ['lat' => (float) $punto['lat'], 'lng' => (float) $punto['lng']];
    }

    /**
     * La direccion, y luego versiones cada vez mas cortas de ella.
     *
     * Las direcciones vienen separadas por comas, de lo particular a lo
     * general ("calle, numero, codigo postal, municipio, provincia, pais"), asi
     * que quitar el primer trozo es exactamente "olvida el detalle mas fino".
     *
     * @return string[]
     */
    private function variantes(string $direccion): array
    {
        $partes = array_values(array_filter(
            array_map('trim', explode(',', $direccion)),
            static fn ($parte) => $parte !== ''
        ));

        $variantes = [$direccion];

        // Se dejan SIEMPRE al menos dos trozos (tipicamente municipio + pais):
        // con uno solo, "España" pondria la planta en el centro del pais.
        while (count($partes) > 2) {
            array_shift($partes);
            $variantes[] = implode(', ', $partes);
        }

        return array_values(array_unique($variantes));
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function consultarNominatim(string $direccion): ?array
    {
        // Espera lo que falte desde la ULTIMA llamada real, sea de la instancia
        // que sea. Con un contador por instancia no se esperaba nada, porque
        // cada planta creaba un geocodificador nuevo.
        $desde = microtime(true) - self::$ultimaLlamada;
        if (self::$ultimaLlamada > 0.0 && $desde < self::PAUSA_ENTRE_LLAMADAS) {
            usleep((int) ((self::PAUSA_ENTRE_LLAMADAS - $desde) * 1_000_000));
        }
        self::$ultimaLlamada = microtime(true);

        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='
             . urlencode($direccion);

        $contexto = stream_context_create([
            'http' => [
                // Obligatorio en su politica de uso; sin el, bloquean la IP.
                'header'        => "User-Agent: ESC-Backend/1.0 (soporte@galagaagency.com)\r\n",
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]);

        // @ y no try/catch: file_get_contents avisa con warning, no con
        // excepcion. Un fallo de red no puede tumbar la lista de plantas.
        $respuesta = @file_get_contents($url, false, $contexto);
        if ($respuesta === false) { return null; }

        $datos = json_decode($respuesta, true);
        if (!is_array($datos) || !isset($datos[0]['lat'], $datos[0]['lon'])) {
            return null;
        }

        return ['lat' => (float) $datos[0]['lat'], 'lng' => (float) $datos[0]['lon']];
    }
}
