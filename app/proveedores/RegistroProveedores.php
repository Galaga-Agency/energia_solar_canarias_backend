<?php

require_once __DIR__ . '/Proveedor.php';

/**
 * Registro de proveedores: dado `?proveedor=sigenergy`, devuelve el adaptador.
 *
 * Sustituye a los switch de cinco ramas repartidos por el router y la fachada. Quien
 * llama ya no enumera proveedores: pide el que le han dicho y usa el contrato.
 *
 * Es PEREZOSO a proposito. ApiControladorService instanciaba los CINCO controladores
 * en su constructor, en toda peticion, aunque solo hiciera falta uno (y varios montan
 * conexion y servicios por el camino). Aqui solo se construye el que se pide, y una
 * sola vez por peticion.
 *
 * Para registrar un proveedor nuevo se añade una linea en porDefecto(). No hay que
 * tocar ni el router ni la fachada: ese es justamente el objetivo.
 */
class RegistroProveedores
{
    /** @var array<string,callable> clave => fabrica que devuelve un Proveedor */
    private $fabricas = [];

    /** @var array<string,Proveedor> ya construidos en esta peticion */
    private $instancias = [];

    /**
     * Registra un proveedor.
     *
     * Recibe una FABRICA (un callable), no una instancia, para no construir lo que no
     * se va a usar.
     */
    public function registrar(string $clave, callable $fabrica): void
    {
        $this->fabricas[strtolower($clave)] = $fabrica;
    }

    public function existe(string $clave): bool
    {
        return isset($this->fabricas[strtolower((string) $clave)]);
    }

    /** Claves registradas, para mensajes de error y para /proveedores. */
    public function claves(): array
    {
        return array_keys($this->fabricas);
    }

    /**
     * Devuelve el proveedor.
     *
     * @throws ProveedorDesconocido si la clave no esta registrada
     */
    public function obtener(string $clave): Proveedor
    {
        $k = strtolower((string) $clave);
        if (!isset($this->fabricas[$k])) {
            throw new ProveedorDesconocido($clave, $this->claves());
        }
        if (!isset($this->instancias[$k])) {
            $this->instancias[$k] = ($this->fabricas[$k])();
        }
        return $this->instancias[$k];
    }

    /**
     * Registro con los cinco proveedores.
     *
     * Los require van dentro de cada fabrica para que pedir uno no cargue los otros
     * cuatro: sin autoload (este proyecto no lo tiene para app/), cada require arrastra
     * su servicio, su modelo y su conexion.
     */
    public static function porDefecto(): self
    {
        $r = new self();

        $r->registrar('goodwe', function () {
            require_once __DIR__ . '/adaptadores/GoodWeAdaptador.php';
            return new GoodWeAdaptador();
        });
        $r->registrar('solaredge', function () {
            require_once __DIR__ . '/adaptadores/SolarEdgeAdaptador.php';
            return new SolarEdgeAdaptador();
        });
        $r->registrar('victronenergy', function () {
            require_once __DIR__ . '/adaptadores/VictronEnergyAdaptador.php';
            return new VictronEnergyAdaptador();
        });
        $r->registrar('sungrow', function () {
            require_once __DIR__ . '/adaptadores/SungrowAdaptador.php';
            return new SungrowAdaptador();
        });
        $r->registrar('sigenergy', function () {
            require_once __DIR__ . '/adaptadores/SigenergyAdaptador.php';
            return new SigenergyAdaptador();
        });

        return $r;
    }
}

/** La clave de `?proveedor=` no corresponde a ningun proveedor registrado. */
class ProveedorDesconocido extends RuntimeException
{
    private $clave;

    public function __construct(string $clave, array $conocidos = [])
    {
        $this->clave = $clave;
        parent::__construct("Proveedor '$clave' no valido. Disponibles: " . implode(', ', $conocidos));
    }

    public function clave(): string
    {
        return $this->clave;
    }
}
