<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12 p-4">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
            GET /plant/overview/{id}
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('overview.descripcion'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('login.cuerpo_solicitud'); ?>
        </h2>
        <ul class="list-disc list-inside <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-700'; ?> mb-4">
            <li>
                <strong>id</strong>
                <?php echo translate('get_detalles_planta.id'); ?>
            </li>
            <li>
                <strong>proveedor</strong>
                <?php echo translate('asociar_plantas_usuarios.proveedor'); ?>
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
        <span class="text-blue-500">"overview"</span>: {
            <span class="text-blue-500">"lastUpdateTime"</span>: <span class="text-yellow-500">"2024-12-04 12:10:14"</span>,
            <span class="text-blue-500">"lifeTimeData"</span>: {
                <span class="text-blue-500">"energy"</span>: <span class="text-yellow-500">15771751</span>,
                <span class="text-blue-500">"revenue"</span>: <span class="text-yellow-500">672.9907</span>
            },
            <span class="text-blue-500">"lastYearData"</span>: {
                <span class="text-blue-500">"energy"</span>: <span class="text-yellow-500">4329293</span>
            },
            <span class="text-blue-500">"lastMonthData"</span>: {
                <span class="text-blue-500">"energy"</span>: <span class="text-yellow-500">55849</span>
            },
            <span class="text-blue-500">"lastDayData"</span>: {
                <span class="text-blue-500">"energy"</span>: <span class="text-yellow-500">5310</span>
            },
            <span class="text-blue-500">"currentPower"</span>: {
                <span class="text-blue-500">"power"</span>: <span class="text-yellow-500">3647.821</span>
            },
            <span class="text-blue-500">"measuredBy"</span>: <span class="text-green-500">"INVERTER"</span>
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
curl -X GET "https://app-energiasolarcanarias-backend.com/plant/overview/34324?proveedor=solaredge" \
-H "Authorization: Bearer tu_token_de_acceso"
            </pre>
            <button
                class="absolute top-2 right-2 <?php echo $theme === 'dark' ? 'bg-blue-400 hover:bg-blue-500' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white px-3 py-1 rounded transition"
                onclick="copiarCodigo()">
                <?php echo translate('copiar'); ?>
            </button>
        </div>
    </div>
</div>

<script>
    function copiarCodigo() {
        const codigo = `curl -X GET "https://app-energiasolarcanarias-backend.com/plant/overview/34324?proveedor=solaredge" \
-H "Authorization: Bearer tu_token_de_acceso"`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>