
<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12 p-4">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
        GET /plant/alert
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('alertas.descripcion'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.parametros_consulta'); ?>
        </h2>
        <ul class="list-disc list-inside <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-700'; ?> mb-4">
            <li>
                <strong>(GoodWe) pageIndex</strong> 
                <?php echo translate('alertas.pageIndex_descripcion'); ?>
            </li>
            <li>
                <strong>(GoodWe) pageSize</strong> 
                <?php echo translate('alertas.pageSize_descripcion'); ?>
            </li>
            <li>
                <strong>(All) proveedor</strong> 
                <?php echo translate('alertas.proveedor'); ?>
            </li>
            <li>
                <strong>(VictronEnergy) siteId</strong> 
                ID
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
        <span class="text-blue-500">"hasError"</span>: <span class="text-yellow-500">false</span>,
        <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">0</span>,
        <span class="text-blue-500">"msg"</span>: <span class="text-green-500">"Successful"</span>,
        <span class="text-blue-500">"data"</span>: {
            <span class="text-blue-500">"record"</span>: <span class="text-yellow-500">51</span>,
            <span class="text-blue-500">"list"</span>: [
                {
                    <span class="text-blue-500">"adcode"</span>: <span class="text-green-500">"43243242"</span>,
                    <span class="text-blue-500">"devicesn"</span>: <span class="text-green-500">"432432432ERIN332131"</span>,
                    <span class="text-blue-500">"deviceName"</span>: <span class="text-green-500">"F5834-EM"</span>,
                    <span class="text-blue-500">"happentime"</span>: <span class="text-green-500">"12/23/2024 07:50:19"</span>,
                    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">1</span>,
                    <span class="text-blue-500">"stationId"</span>: <span class="text-green-500">"3213213-3213a-34dds-32432-432432432432"</span>,
                    <span class="text-blue-500">"stationname"</span>: <span class="text-green-500">"Ejemplo"</span>,
                    <span class="text-blue-500">"error_code"</span>: <span class="text-yellow-500">33344444</span>,
                    <span class="text-blue-500">"warningid"</span>: <span class="text-green-500">"4324324324456578687"</span>,
                    <span class="text-blue-500">"warninglevel"</span>: <span class="text-yellow-500">1</span>,
                    <span class="text-blue-500">"warningname"</span>: <span class="text-green-500">"Relay Check\r\nFailure"</span>,
                    <span class="text-blue-500">"is_show"</span>: <span class="text-yellow-500">true</span>,
                    <span class="text-blue-500">"attention"</span>: <span class="text-yellow-500">0</span>,
                    <span class="text-blue-500">"error_type"</span>: <span class="text-yellow-500">0</span>,
                    <span class="text-blue-500">"is_add_task"</span>: <span class="text-yellow-500">2</span>,
                    <span class="text-blue-500">"recoverytime"</span>: <span class="text-green-500">"12/23/2024 07:51:52"</span>,
                    <span class="text-blue-500">"error_code_key"</span>: <span class="text-green-500">"e2-34566"</span>,
                    <span class="text-blue-500">"warning_code"</span>: <span class="text-green-500">"e2-34567"</span>,
                    <span class="text-blue-500">"device_type"</span>: <span class="text-yellow-500">null</span>,
                    <span class="text-blue-500">"fault_classification"</span>: <span class="text-yellow-500">null</span>,
                    <span class="text-blue-500">"standard_level"</span>: <span class="text-yellow-500">null</span>,
                    <span class="text-blue-500">"pw_type"</span>: <span class="text-green-500">"battery storage"</span>
                }
            ]
        },
        <span class="text-blue-500">"components"</span>: {
            <span class="text-blue-500">"para"</span>: <span class="text-yellow-500">null</span>,
            <span class="text-blue-500">"langVer"</span>: <span class="text-yellow-500">179</span>,
            <span class="text-blue-500">"timeSpan"</span>: <span class="text-yellow-500">0</span>,
            <span class="text-blue-500">"api"</span>: <span class="text-green-500">"URL"</span>,
            <span class="text-blue-500">"msgSocketAdr"</span>: <span class="text-yellow-500">""</span>
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
curl -X GET "https://app-energiasolarcanarias-backend.com/plant/alert?proveedor=goodwe&pageIndex=1&pageSize=1" \
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
        const codigo = `curl -X GET "{{URL}}plant/alert?proveedor=goodwe&pageIndex=1&pageSize=1" \
-H "Authorization: Bearer tu_token_de_acceso"`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>