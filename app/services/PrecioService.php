<?php
require_once __DIR__ . "/../services/ZohoService.php";
class PrecioService
{
    private $zohoService;

    public function __construct(){
        $this->zohoService = new ZohoService;
    }
    // En un entorno real, esto se obtendría de la base de datos
    public function getPreciosPorFechas()
    {
        return [
            [
                'precio' => 0.93,
                'precio_ahorro' => 0.73,
                'fecha_inicio' => '2025-03-08',
                'fecha_final' => '2025-03-15',
                'planta_id' => '3041138',
                'proveedor' => 'solaredge',
                'moneda' => 'EUR'
            ],
            [
                'precio' => 0.14,
                'fecha_inicio' => '2025-03-16',
                'fecha_final' => '2025-03-31',
                'planta_id' => '3041138',
                'proveedor' => 'solaredge',
                'moneda' => 'EUR'
            ],
            [
                'precio' => 7.15,
                'fecha_inicio' => '2025-04-01',
                'fecha_final' => '',
                'planta_id' => '3041138',
                'proveedor' => 'solaredge',
                'moneda' => 'EUR'
            ]
        ];
    }

    // En un entorno real, esto se obtendría de la base de datos
    public function getPreciosPersonalizadosPorPlanta($plantId, $proveedor)
    {
        $precios = $this->zohoService->obtenerListadoDePrecios($plantId,$proveedor);
        return $precios;
    }
}

?>