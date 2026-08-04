<?php

/**
 * Doble de conexion mysqli para los tests.
 *
 * Permite probar servicios que hablan con la base de datos sin levantar MySQL
 * ni el stack de Docker: apunta las consultas que se le piden y devuelve
 * resultados guionizados.
 *
 * Solo implementa lo que usan los servicios probados (prepare/bind_param/
 * execute/get_result/bind_result/fetch/close/query). No pretende ser un mysqli
 * completo.
 *
 * @see MagicLinkServiceTest
 */
class ConexionDoble
{
    /** @var array<string,array> guion: fragmento de SQL => resultado */
    private $guion;

    /** @var array<int,string> SQL de cada prepare(), en orden */
    private $statements = [];

    /** @var array<int,mixed> todos los valores pasados por bind_param */
    private $parametros = [];

    public function __construct(array $guion = [])
    {
        $this->guion = $guion;
    }

    /** Conexion que acepta un INSERT en la tabla indicada. */
    public static function conInsert($tabla)
    {
        return new self(['INSERT INTO ' . $tabla => ['afectadas' => 1]]);
    }

    /** Conexion con un guion explicito de fragmento SQL => resultado. */
    public static function conGuion(array $guion)
    {
        return new self($guion);
    }

    /** Conexion que devuelve una fila de `usuarios` con los campos indicados. */
    public static function conUsuario(array $campos)
    {
        return new self([
            'SELECT usuarios.usuario_id' => [
                'fila' => array_merge([
                    'usuario_id' => 5,
                    'email'      => 'x@y.z',
                    'clase'      => 'usuario',
                    'nombre'     => 'X',
                    'apellido'   => '',
                    'movil'      => '',
                    'imagen'     => null,
                    'activo'     => 1,
                    'eliminado'  => 0,
                ], $campos),
            ],
        ]);
    }

    public function prepare($sql)
    {
        $this->statements[] = $sql;

        $config = ['afectadas' => 0, 'fila' => null];
        foreach ($this->guion as $fragmento => $resultado) {
            if (stripos($sql, $fragmento) !== false) {
                $config = array_merge($config, $resultado);
                break;
            }
        }

        return new StatementDoble($config, $this->parametros);
    }

    public function query($sql)
    {
        $this->statements[] = $sql;
        return true;
    }

    /* ── Aserciones ─────────────────────────────────────────────────────── */

    /** SQL del primer prepare(). Para comprobar que el canje empieza por UPDATE. */
    public function primerStatement()
    {
        return $this->statements[0] ?? '';
    }

    public function numeroDeConsultas()
    {
        return count($this->statements);
    }

    /** Todos los valores enviados por bind_param, concatenados. */
    public function parametrosEnviados()
    {
        return implode('|', array_map('strval', $this->parametros));
    }
}

class StatementDoble
{
    public $affected_rows;

    private $fila;
    private $parametros;
    private $salida;

    public function __construct(array $config, array &$parametros)
    {
        $this->affected_rows = $config['afectadas'] ?? 0;
        $this->fila = $config['fila'] ?? null;
        $this->parametros = &$parametros;
    }

    public function bind_param(...$argumentos)
    {
        // El primer argumento son los tipos ("isis"), el resto los valores.
        array_shift($argumentos);
        foreach ($argumentos as $valor) {
            $this->parametros[] = $valor;
        }
        return true;
    }

    public function execute() { return true; }

    public function bind_result(&$salida)
    {
        $this->salida = &$salida;
        return true;
    }

    public function fetch()
    {
        if ($this->fila === null) {
            return false;
        }
        $this->salida = $this->fila;
        return true;
    }

    public function get_result()
    {
        return new ResultadoDoble(is_array($this->fila) ? $this->fila : null);
    }

    public function close() { return true; }
}

class ResultadoDoble
{
    private $fila;

    public function __construct($fila) { $this->fila = $fila; }

    public function fetch_assoc() { return $this->fila; }
}
