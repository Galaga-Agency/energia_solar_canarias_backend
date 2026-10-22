<?php

/**
 * Cache de respuestas de APIs de proveedores, para no chocar con sus limites
 * de frecuencia.
 *
 * El caso que lo motiva: la Openapi de Sigenergy permite 1 acceso por estacion
 * cada 5 minutos. Sin cache, abrir el panel dos veces seguidas devuelve un error
 * de rate-limit del proveedor. Como sus datos tampoco se refrescan antes de esos
 * 5 minutos, servir la ultima respuesta buena no pierde informacion: solo evita
 * una llamada inutil.
 *
 * Politica:
 *   1. Si hay cache fresca (dentro del TTL) -> se sirve y NO se llama al proveedor.
 *   2. Si no la hay -> se llama, se guarda (solo si la respuesta es buena) y se sirve.
 *   3. Si la llamada falla pero hay cache VIEJA -> se sirve la vieja marcada como
 *      obsoleta, en vez de devolver un error. Un dato de hace 7 minutos es mejor
 *      que una pantalla rota.
 *
 * Cada respuesta lleva un bloque `_cache` para que el frontend sepa si el dato es
 * fresco y cuanto falta para poder refrescar (esperar / esperar_seg).
 */
class CacheApiService
{
    /**
     * Segundos que un peticion espera su turno para llamar al proveedor cuando otra
     * ya lo esta haciendo. Debe cubrir lo que tarda la llamada (1-3 s) con holgura.
     */
    private const ESPERA_TURNO_SEG = 12;

    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Devuelve la respuesta cacheada si sigue fresca; si no, ejecuta $fetch.
     *
     * La cache es DEL SERVIDOR y la clave no incluye al usuario, asi que dos personas
     * mirando la misma planta (movil y PC) comparten la misma entrada: la segunda
     * aprovecha lo que trajo la primera.
     *
     * Ademas hay un TURNO (lock de MySQL): si las dos llegan a la vez y no hay cache,
     * sin el se lanzarian las dos a la API y la segunda se comeria el rate-limit.
     * Con el, una llama y la otra espera y reutiliza su resultado.
     *
     * @param string   $proveedor  p.ej. 'Sigenergy'
     * @param string   $ruta       ruta completa con query (identifica la peticion)
     * @param int      $ttlSeg     segundos de validez (Sigenergy: 300)
     * @param callable $fetch      function(): array  -> respuesta del proveedor
     * @param callable $esValida   function(array): bool -> si se debe cachear
     * @return array respuesta del proveedor con el bloque `_cache` añadido
     */
    public function recordar($proveedor, $ruta, $ttlSeg, callable $fetch, ?callable $esValida = null)
    {
        $esValida = $esValida ?? [$this, 'sePuedeCachear'];
        $clave = $proveedor . ':' . hash('sha256', $ruta);

        // 1) Cache fresca: ni tocamos al proveedor ni pedimos turno.
        $fresca = $this->siEstaFresca($clave);
        if ($fresca !== null) return $fresca;

        // 2) Hay que llamar: pedimos turno para que no llamen varios a la vez.
        //    El nombre del lock cabe en los 64 caracteres que admite MySQL.
        $turno = 'esc_' . substr(hash('sha256', $clave), 0, 40);
        $tengoTurno = $this->pedirTurno($turno);

        try {
            // Al entrar (o al agotarse la espera), otra peticion pudo dejarla lista.
            $fresca = $this->siEstaFresca($clave);
            if ($fresca !== null) return $fresca;

            $ahora = (int) (microtime(true) * 1000);
            try {
                $resp = $fetch();
            } catch (Throwable $e) {
                $resp = ['code' => -1, 'msg' => $e->getMessage(), 'data' => null];
            }

            if ($esValida($resp)) {
                $this->guardar($clave, $proveedor, $ruta, $resp, $ttlSeg, $ahora);
                return $this->conMeta($resp, false, 0, $ttlSeg, false);
            }

            // 3) Fallo, pero teniamos algo guardado: mejor un dato viejo que un error.
            $fila = $this->leer($clave);
            if ($fila) {
                $edadSeg = (int) (($ahora - (int) $fila['creado_en']) / 1000);
                $viejo = json_decode($fila['respuesta'], true);
                return $this->conMeta($viejo, true, $edadSeg, (int) $fila['ttl_seg'], true);
            }

            return $this->conMeta($resp, false, 0, $ttlSeg, false);
        } finally {
            if ($tengoTurno) $this->soltarTurno($turno);
        }
    }

    /**
     * Cachea una ruta pero comparte un intervalo minimo entre varias rutas de un
     * mismo recurso remoto. Sirve para APIs que limitan por planta y no por URL:
     * cambiar `level` o `date` no debe disparar otra peticion durante la ventana.
     *
     * Si el recurso sigue en cooldown, devuelve la respuesta anterior de ESTA ruta
     * marcada como obsoleta. Si nunca se pidio esta ruta, devuelve el error remoto
     * equivalente a rate-limit con el tiempo restante; nunca inventa datos de otro
     * periodo para que parezca que la consulta tuvo exito.
     *
     * El lock y el marcador viven en api_cache/MySQL, por lo que se comparten entre
     * peticiones y contenedores. El intervalo empieza al terminar el fetch.
     */
    public function recordarConIntervalo(
        string $proveedor,
        string $ruta,
        int $ttlSeg,
        string $grupo,
        int $intervaloSeg,
        callable $fetch,
        ?callable $esValida = null
    ): array {
        $esValida = $esValida ?? [$this, 'sePuedeCachear'];
        $ttlSeg = max(1, $ttlSeg);
        $intervaloSeg = max(1, $intervaloSeg);
        $clave = $proveedor . ':' . hash('sha256', $ruta);
        $claveIntervalo = $proveedor . ':intervalo:' . hash('sha256', $grupo);

        $fresca = $this->siEstaFresca($clave);
        if ($fresca !== null) return $fresca;

        // Todas las variantes de la misma planta comparten este lock.
        $turno = 'esc_' . substr(hash('sha256', $claveIntervalo), 0, 40);
        if (!$this->pedirTurno($turno)) {
            $espera = max(5, $this->esperaRestante($claveIntervalo));
            return $this->respuestaEnEspera($clave, $espera);
        }

        try {
            // Otra petición pudo rellenar la ruta o abrir el intervalo mientras
            // esperábamos el lock.
            $fresca = $this->siEstaFresca($clave);
            if ($fresca !== null) return $fresca;

            $espera = $this->esperaRestante($claveIntervalo);
            if ($espera > 0) return $this->respuestaEnEspera($clave, $espera);

            try {
                $resp = $fetch();
            } catch (Throwable $e) {
                $resp = ['code' => -1, 'msg' => $e->getMessage(), 'data' => null];
            }

            // Incluso un timeout puede haber llegado al proveedor. Por seguridad
            // consume el intervalo; un 1201 remoto queda así aislado de las otras
            // variantes de la ruta.
            $fin = $this->ahoraMs();
            $this->guardar(
                $claveIntervalo,
                $proveedor,
                'intervalo:' . $grupo,
                ['intervalo' => true],
                $intervaloSeg,
                $fin
            );

            if ($esValida($resp)) {
                $this->guardar($clave, $proveedor, $ruta, $resp, $ttlSeg, $fin);
                return $this->conMeta($resp, false, 0, $ttlSeg, false);
            }

            $fila = $this->leer($clave);
            if ($fila) {
                $edadSeg = max(0, intdiv($fin - (int) $fila['creado_en'], 1000));
                $viejo = json_decode($fila['respuesta'], true);
                if (is_array($viejo)) {
                    return $this->conEspera(
                        $this->conMeta($viejo, true, $edadSeg, (int) $fila['ttl_seg'], true),
                        $intervaloSeg
                    );
                }
            }

            return $this->conEspera($this->conMeta($resp, false, 0, $ttlSeg, false), $intervaloSeg);
        } finally {
            $this->soltarTurno($turno);
        }
    }

    /** Respuesta correcta para una variante que llega mientras el recurso espera. */
    private function respuestaEnEspera(string $clave, int $espera): array
    {
        $fila = $this->leer($clave);
        if ($fila) {
            $edadSeg = max(0, intdiv($this->ahoraMs() - (int) $fila['creado_en'], 1000));
            $viejo = json_decode($fila['respuesta'], true);
            if (is_array($viejo)) {
                return $this->conEspera(
                    $this->conMeta($viejo, true, $edadSeg, (int) $fila['ttl_seg'], true),
                    $espera
                );
            }
        }

        $espera = max(1, $espera);
        return [
            'code' => 1201,
            'msg' => 'Access restriction',
            'data' => [],
            '_cache' => [
                'cacheado' => false,
                'obsoleto' => false,
                'edad_seg' => 0,
                'esperar_seg' => $espera,
                'esperar' => sprintf('%02d:%02d', intdiv($espera, 60), $espera % 60),
                'proxima_actualizacion' => date('c', intdiv($this->ahoraMs(), 1000) + $espera),
                'limitado_localmente' => true,
            ],
        ];
    }

    private function conEspera(array $respuesta, int $espera): array
    {
        $espera = max(0, $espera);
        $cache = is_array($respuesta['_cache'] ?? null) ? $respuesta['_cache'] : [];
        $cache['esperar_seg'] = $espera;
        $cache['esperar'] = sprintf('%02d:%02d', intdiv($espera, 60), $espera % 60);
        $cache['proxima_actualizacion'] = date('c', intdiv($this->ahoraMs(), 1000) + $espera);
        $respuesta['_cache'] = $cache;
        return $respuesta;
    }

    /**
     * Modo protegido para GoodWe: nunca consulta sin lock ni durante una pausa
     * por error. Solo guarda respuestas validas; una entrada separada contiene
     * el plazo de reintento. Los datos antiguos tienen una edad maxima acotada.
     */
    public function recordarProtegido(string $proveedor, string $ruta, int $ttlSeg, int $maxEdadSeg, callable $fetch, callable $esValida): array
    {
        $clave = $proveedor . ':' . hash('sha256', $ruta);
        $pausa = $clave . ':pausa';
        $fresca = $this->siEstaFresca($clave);
        if ($fresca !== null) return $fresca;
        if (($espera = $this->esperaRestante($pausa)) > 0) {
            return $this->respaldo($clave, $maxEdadSeg, $espera);
        }

        $turno = 'esc_' . substr(hash('sha256', $clave), 0, 40);
        if (!$this->pedirTurno($turno)) {
            // El lock puede tardar mas que la espera: no lanzar otra consulta.
            return $this->siEstaFresca($clave) ?? $this->respaldo($clave, $maxEdadSeg, 5);
        }
        try {
            $fresca = $this->siEstaFresca($clave);
            if ($fresca !== null) return $fresca;
            if (($espera = $this->esperaRestante($pausa)) > 0) {
                return $this->respaldo($clave, $maxEdadSeg, $espera);
            }
            // Armar la pausa ANTES de consultar tambien protege si un worker muere.
            if (!$this->guardar($pausa, $proveedor, $ruta, ['pausa' => true], 60, $this->ahoraMs())) {
                return $this->respaldo($clave, $maxEdadSeg, 60);
            }
            try {
                $resp = $fetch();
            } catch (Throwable $e) {
                $resp = null;
            }
            if (is_array($resp) && $esValida($resp)) {
                // El TTL empieza cuando termina la consulta, no antes de esperar.
                unset($resp['_cache']);
                if ($this->guardar($clave, $proveedor, $ruta, $resp, $ttlSeg, $this->ahoraMs())) {
                    $this->borrarClave($pausa);
                }
                return $this->conMeta($resp, false, 0, $ttlSeg, false);
            }
            // Un fallo tras una llamada lenta sigue teniendo un minuto de pausa.
            $this->guardar($pausa, $proveedor, $ruta, ['pausa' => true], 60, $this->ahoraMs());
            return $this->respaldo($clave, $maxEdadSeg, 60);
        } finally {
            $this->soltarTurno($turno);
        }
    }

    /** @phpstan-impure La BD y el reloj pueden cambiar mientras se espera el lock. */
    private function esperaRestante(string $clave): int
    {
        $fila = $this->leer($clave);
        if (!$fila) return 0;
        $edad = max(0, intdiv($this->ahoraMs() - (int) $fila['creado_en'], 1000));
        return max(0, (int) $fila['ttl_seg'] - $edad);
    }

    private function respaldo(string $clave, int $maxEdadSeg, int $espera): array
    {
        $fila = $this->leer($clave);
        $resp = $fila ? json_decode($fila['respuesta'], true) : null;
        $edad = $fila ? max(0, intdiv($this->ahoraMs() - (int) $fila['creado_en'], 1000)) : 0;
        $disponible = is_array($resp) && $edad <= $maxEdadSeg;
        if (!$disponible) {
            $resp = ['code' => -1, 'error' => 'GoodWe temporalmente no disponible; reintenta mas tarde', 'data' => null];
        }
        $respuesta = $this->conMeta($resp, $disponible, $edad, 0, $disponible);
        $respuesta['_cache']['esperar_seg'] = $espera;
        $respuesta['_cache']['esperar'] = sprintf('%02d:%02d', intdiv($espera, 60), $espera % 60);
        $respuesta['_cache']['proxima_actualizacion'] = date('c', intdiv($this->ahoraMs(), 1000) + $espera);
        return $respuesta;
    }

    protected function ahoraMs(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /** Devuelve la respuesta cacheada si aun esta dentro del TTL, o null. */
    private function siEstaFresca($clave)
    {
        $fila = $this->leer($clave);
        if (!$fila) return null;

        $edadSeg = max(0, intdiv($this->ahoraMs() - (int) $fila['creado_en'], 1000));
        if ($edadSeg >= (int) $fila['ttl_seg']) return null;
        $datos = json_decode($fila['respuesta'], true);
        if (!is_array($datos)) return null;
        return $this->conMeta($datos, true, $edadSeg, (int) $fila['ttl_seg'], false);
    }

    /**
     * Pide el turno para llamar al proveedor (lock con nombre de MySQL).
     *
     * Se usa GET_LOCK porque el lock es del SERVIDOR MySQL, no del proceso PHP: asi
     * funciona aunque las peticiones caigan en contenedores o workers distintos.
     * Si no se consigue a tiempo, se sigue igualmente (peor rendimiento, nunca error).
     */
    protected function pedirTurno($nombre)
    {
        $stmt = $this->db->prepare("SELECT GET_LOCK(?, ?)");
        if (!$stmt) return false;
        $espera = self::ESPERA_TURNO_SEG;
        $stmt->bind_param('si', $nombre, $espera);
        if (!$stmt->execute()) { $stmt->close(); return false; }
        $resultado = $stmt->get_result();
        $fila = $resultado ? $resultado->fetch_row() : null;
        $stmt->close();
        return isset($fila[0]) && (int) $fila[0] === 1;
    }

    protected function soltarTurno($nombre)
    {
        $stmt = $this->db->prepare("SELECT RELEASE_LOCK(?)");
        if (!$stmt) return;
        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        $stmt->get_result();
        $stmt->close();
    }

    /**
     * Decide si una respuesta merece guardarse. Default GENERICO: cada proveedor
     * deberia pasar su propio $esValida a recordar(), porque solo el sabe que
     * significan sus codigos. Sigenergy lo hace via SigenergyErrores::esTransitorio().
     *
     * Se cachea TODO menos lo transitorio. Es importante cachear tambien los errores
     * de negocio estables (p.ej. 13008 "station disconnect" de una planta apagada):
     * el limite de 1 acceso/5min se aplica igual aunque la respuesta sea un error, asi
     * que no cachearlos significaria machacar la API en cada visita al panel para
     * justo las plantas que no van a responder.
     *
     * Cachear un rate-limit seria el peor error posible: serviriamos "Access
     * restriction" como si fuera un dato bueno durante 5 minutos, a todos los
     * usuarios. Al no cachearlo, cae en la politica 3 y se sirve la ultima lectura
     * buena.
     */
    public function sePuedeCachear($resp)
    {
        if (!is_array($resp) || !array_key_exists('code', $resp)) return false;
        // -1 = fallo de transporte nuestro (timeout, DNS, respuesta ilegible).
        return (int) $resp['code'] !== -1;
    }

    /** Añade el bloque _cache con el tiempo que falta para poder refrescar. */
    private function conMeta($resp, $cacheado, $edadSeg, $ttlSeg, $obsoleto)
    {
        if (!is_array($resp)) {
            $resp = ['code' => -1, 'msg' => 'respuesta no valida', 'data' => null];
        }
        $esperarSeg = max(0, $ttlSeg - $edadSeg);
        $resp['_cache'] = [
            'cacheado'              => $cacheado,
            'obsoleto'              => $obsoleto,  // true = el proveedor fallo y servimos lo ultimo bueno
            'edad_seg'              => $edadSeg,
            'esperar_seg'           => $esperarSeg,
            'esperar'               => sprintf('%02d:%02d', intdiv($esperarSeg, 60), $esperarSeg % 60),
            'proxima_actualizacion' => date('c', time() + $esperarSeg),
        ];
        return $resp;
    }

    protected function leer($clave)
    {
        $stmt = $this->db->prepare("SELECT respuesta, creado_en, ttl_seg FROM api_cache WHERE clave = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $clave);
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $resultado = $stmt->get_result();
        $fila = $resultado ? $resultado->fetch_assoc() : null;
        $stmt->close();
        return $fila ?: null;
    }

    protected function guardar($clave, $proveedor, $ruta, $resp, $ttlSeg, $ahora)
    {
        $json = json_encode($resp);
        $sql = "INSERT INTO api_cache (clave, proveedor, ruta, respuesta, creado_en, ttl_seg)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE respuesta=VALUES(respuesta), creado_en=VALUES(creado_en), ttl_seg=VALUES(ttl_seg)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('ssssii', $clave, $proveedor, $ruta, $json, $ahora, $ttlSeg);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    protected function borrarClave(string $clave): void
    {
        $stmt = $this->db->prepare('DELETE FROM api_cache WHERE clave = ?');
        if (!$stmt) return;
        $stmt->bind_param('s', $clave);
        $stmt->execute();
        $stmt->close();
    }

    /** Borra la cache de un proveedor (util para forzar refresco desde un cron o test). */
    public function invalidar($proveedor)
    {
        $stmt = $this->db->prepare("DELETE FROM api_cache WHERE proveedor = ?");
        if (!$stmt) return;
        $stmt->bind_param('s', $proveedor);
        $stmt->execute();
        $stmt->close();
    }
}
