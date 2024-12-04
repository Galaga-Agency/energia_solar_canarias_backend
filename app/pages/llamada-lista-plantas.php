
<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12 p-4">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">GET /plants</h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('get_lista_plantas.descripcion'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.parametros_consulta'); ?>
        </h2>
        <ul class="list-disc list-inside <?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <li>GET <strong>proveedor</strong> <?php echo translate('get_detalles_planta.proveedor'); ?></li>
            <li>GET <strong>page</strong> <?php echo translate('usuarios_get.page_descripcion'); ?></li>
            <li>GET <strong>pageSize</strong> <?php echo translate('usuarios_get.limit_descripcion'); ?></li>
        </ul>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.respuesta_ejemplo'); ?>
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
{
"status": true,
"code": 200,
"message": "200 - Solicitud exitosa",
    "data": [
        {
            "id": "identificador",
            "name": "Hola mundo",
            "address": "street of example, 3, 00000 city, city, Country",
            "capacity": 0,
            "status": "error",
            "type": "Residential",
            "latitude": "0",
            "longitude": "0",
            "organization": "Whatever",
            "current_power": 0,
            "total_energy": 0,
            "daily_energy": 0,
            "monthly_energy": 0,
            "installation_date": null,
            "pto_date": null,
            "notes": null,
            "alert_quantity": null,
            "highest_impact": null,
            "primary_module": null,
            "public_settings": null
        },
        {
            "id": "identificador2",
            "name": "Galaga",
            "address": "street. of example, 3, 00000 city, city, Country",
            "capacity": 0,
            "status": "working",
            "type": "Residential",
            "latitude": "0",
            "longitude": "0",
            "organization": "Whatever",
            "current_power": 0,
            "total_energy": 0,
            "daily_energy": 0,
            "monthly_energy": 0,
            "installation_date": null,
            "pto_date": null,
            "notes": null,
            "alert_quantity": null,
            "highest_impact": null,
            "primary_module": null,
            "public_settings": null
        }
    ]
}
        </pre>
    </div>

    <!-- Componente de Código Copiable -->
    <div class="w-full md:w-4/12 mt-6 md:mt-0 p-4">
        <div class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-800 text-white'; ?> rounded-lg p-4 relative">
            <h2 class="text-lg font-semibold mb-2">
                <?php echo translate('usuarios_get.ejemplo_uso'); ?>
            </h2>
            <pre class="text-sm overflow-auto mb-4">
curl -X GET "https://app-energiasolarcanarias-backend.com/plants" \
-H "Authorization: Bearer tu_token_de_acceso"
            </pre>
            <button
                class="absolute top-2 right-2 <?php echo $theme === 'dark' ? 'bg-blue-400 hover:bg-blue-500' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white px-3 py-1 rounded transition"
                onclick="copiarCodigo()"
            >
                Copiar
            </button>
        </div>
    </div>
</div>

<script>
    function copiarCodigo() {
        const codigo = `curl -X GET "https://app-energiasolarcanarias-backend.com/plants" \\
-H "Authorization: Bearer tu_token_de_acceso"`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>
