
<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12 p-4">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
        GET /plant/power/realtime/{id}
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('realtime.descripcion'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.parametros_consulta'); ?>
        </h2>
        <ul class="list-disc list-inside <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-700'; ?> mb-4">
            <li>
                <strong>id</strong> 
                <?php echo translate('realtime.id_descripcion'); ?>
            </li>
            <li>
                <strong>proveedor</strong> 
                <?php echo translate('get_detalles_planta.proveedor'); ?>
            </li>
        </ul>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.respuesta_ejemplo'); ?>
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
{
    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">true</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">200</span>,
    <span class="text-blue-500">"message"</span>: <span class="text-green-500">"200 - Solicitud exitosa"</span>,
    <span class="text-blue-500">"data"</span>: {
        <span class="text-blue-500">"siteCurrentPowerFlow"</span>: {
            <span class="text-blue-500">"updateRefreshRate"</span>: <span class="text-yellow-500">3</span>,
            <span class="text-blue-500">"unit"</span>: <span class="text-green-500">"kW"</span>,
            <span class="text-blue-500">"connections"</span>: [
                {
                    <span class="text-blue-500">"from"</span>: <span class="text-green-500">"GRID"</span>,
                    <span class="text-blue-500">"to"</span>: <span class="text-green-500">"Load"</span>
                },
                {
                    <span class="text-blue-500">"from"</span>: <span class="text-green-500">"PV"</span>,
                    <span class="text-blue-500">"to"</span>: <span class="text-green-500">"Load"</span>
                },
                {
                    <span class="text-blue-500">"from"</span>: <span class="text-green-500">"PV"</span>,
                    <span class="text-blue-500">"to"</span>: <span class="text-green-500">"Storage"</span>
                }
            ],
            <span class="text-blue-500">"GRID"</span>: {
                <span class="text-blue-500">"status"</span>: <span class="text-green-500">"Active"</span>,
                <span class="text-blue-500">"currentPower"</span>: <span class="text-yellow-500">0.02</span>
            },
            <span class="text-blue-500">"LOAD"</span>: {
                <span class="text-blue-500">"status"</span>: <span class="text-green-500">"Active"</span>,
                <span class="text-blue-500">"currentPower"</span>: <span class="text-yellow-500">6.37</span>
            },
            <span class="text-blue-500">"PV"</span>: {
                <span class="text-blue-500">"status"</span>: <span class="text-green-500">"Active"</span>,
                <span class="text-blue-500">"currentPower"</span>: <span class="text-yellow-500">8.15</span>
            },
            <span class="text-blue-500">"STORAGE"</span>: {
                <span class="text-blue-500">"status"</span>: <span class="text-green-500">"Charging"</span>,
                <span class="text-blue-500">"currentPower"</span>: <span class="text-yellow-500">1.8</span>,
                <span class="text-blue-500">"chargeLevel"</span>: <span class="text-yellow-500">15</span>,
                <span class="text-blue-500">"critical"</span>: <span class="text-yellow-500">false</span>
            }
        }
    }
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
curl -X GET "https://app-energiasolarcanarias-backend.com/plant/power/realtime/3546753?proveedor=solaredge" \
-H "Authorization: Bearer tu_token_de_acceso"
            </pre>
            <button
                class="absolute top-2 right-2 <?php echo $theme === 'dark' ? 'bg-blue-400 hover:bg-blue-500' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white px-3 py-1 rounded transition"
                onclick="copiarCodigo()"
            >
            <?php echo translate('copiar'); ?>
            </button>
        </div>
    </div>
</div>

<script>
    function copiarCodigo() {
        const codigo = `curl -X GET "https://app-energiasolarcanarias-backend.com/plant/power/realtime/3546753?proveedor=solaredge" \
-H "Authorization: Bearer tu_token_de_acceso"`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>