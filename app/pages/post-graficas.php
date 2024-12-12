<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
            POST /plants/graficas
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('post_graficas.endpoint_description'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.parametros_consulta'); ?>
        </h2>
        <ul class="list-disc list-inside <?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <li><strong>proveedor</strong> <?php echo translate('asociar_plantas_usuarios.proveedor'); ?></li>
        </ul>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('post_graficas.parameters_title'); ?>
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
<span class="text-green-500">/* GoodWe */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"b5e7ad84-679f-4b99-a238-912631598450"</span>,
    <span class="text-blue-500">"date"</span>: <span class="text-yellow-500">"2024-11-11"</span>, <span class="text-green-500">// fecha en la que sacas el gráfico</span>
    <span class="text-blue-500">"range"</span>: <span class="text-yellow-500">"dia"</span>, <span class="text-green-500">// 2 dia 3 mes y 4 año</span>
    <span class="text-blue-500">"chartIndexId"</span>: <span class="text-yellow-500">"generación de energía y ingresos"</span> <span class="text-green-500">// Depende del gráfico cambian los datos que se le pasan</span>
}
<span class="text-green-500">/* GoodWe */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"b5e7ad84-679f-4b99-a238-912631598450"</span>,
    <span class="text-blue-500">"date"</span>: <span class="text-yellow-500">"2024-11-21"</span>, <span class="text-green-500">// fecha en la que sacas el gráfico</span>
    <span class="text-blue-500">"chartIndexId"</span>: <span class="text-yellow-500">"potencia"</span> <span class="text-green-500">// Depende del gráfico cambian los datos que se le pasan</span>
}
<span class="text-green-500">/* SolarEdge */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"1851069"</span>,
    <span class="text-blue-500">"dia"</span>: <span class="text-yellow-500">"DAY"</span>, <span class="text-green-500">// dia mes o año que quieres que te saque</span>
    <span class="text-blue-500">"fechaFin"</span>: <span class="text-yellow-500">"2024-11-19"</span>, <span class="text-green-500">// parametro opcional si no se le manda se le pasara la fecha de hoy a las 23:59:59</span>
    <span class="text-blue-500">"fechaInicio"</span>: <span class="text-yellow-500">"2024-11-18"</span> <span class="text-green-500">// parametro opcional si no se envia se recogera en DAY principio del dia actual Month dia 1 del mes actual o YEAR primer dia del año actual</span>
}
<span class="text-green-500">/* Grafica de Victron Energy */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"98081"</span>,
    <span class="text-blue-500">"interval"</span>: <span class="text-yellow-500">"15mins"</span>, <span class="text-green-500">// 15mins hours 2hours days weeks months years</span>
    <span class="text-blue-500">"type"</span>: <span class="text-yellow-500">"venus"</span>, <span class="text-green-500">// venus live_feed consumption solar_yield kwh generator generator-runtime custom forecast</span>
    <span class="text-blue-500">"fechaFin"</span>: <span class="text-yellow-500">"2024-11-25"</span>, <span class="text-green-500">// parametro opcional si no se le manda se le pasara la fecha de hoy a las 23:59:59</span>
    <span class="text-blue-500">"fechaInicio"</span>: <span class="text-yellow-500">"2024-11-24"</span> <span class="text-green-500">// parametro opcional si no se le manda se le pasara la fecha de hoy a las 00:00:00</span>
}
</pre>

        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            Respuesta de Ejemplo
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
{
    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">true</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">200</span>,
    <span class="text-blue-500">"message"</span>: <span class="text-green-500">"200 - Solicitud exitosa"</span>,
    <span class="text-blue-500">"data"</span>: {
        <span class="text-blue-500">"consumption"</span>: [
            {
                <span class="text-blue-500">"date"</span>: <span class="text-green-500">"2024-11-18 00:00:00"</span>,
                <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">117869</span>
            },
            {
                <span class="text-blue-500">"date"</span>: <span class="text-green-500">"2024-11-19 00:00:00"</span>,
                <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">127128</span>
            }
        ],
        <span class="text-blue-500">"totalConsumption"</span>: <span class="text-yellow-500">244997</span>,
        <span class="text-blue-500">"solarProduction"</span>: [
            {
                <span class="text-blue-500">"date"</span>: <span class="text-green-500">"2024-11-18 00:00:00"</span>,
                <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">60023</span>
            },
            {
                <span class="text-blue-500">"date"</span>: <span class="text-green-500">"2024-11-19 00:00:00"</span>,
                <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">64201</span>
            }
        ],
        <span class="text-blue-500">"totalProduction"</span>: <span class="text-yellow-500">124224</span>,
        <span class="text-blue-500">"storagePower"</span>: [
            {
                <span class="text-blue-500">"nameplate"</span>: <span class="text-yellow-500">9800</span>,
                <span class="text-blue-500">"serialNumber"</span>: <span class="text-green-500">"7E043EDB"</span>,
                <span class="text-blue-500">"modelNumber"</span>: <span class="text-green-500">"LGC RESU 10"</span>,
                <span class="text-blue-500">"telemetryCount"</span>: <span class="text-yellow-500">1149</span>,
                <span class="text-blue-500">"telemetries"</span>: [
                    {
                        <span class="text-blue-500">"timeStamp"</span>: <span class="text-green-500">"2024-11-18 00:00:51"</span>,
                        <span class="text-blue-500">"power"</span>: <span class="text-yellow-500">0</span>,
                        <span class="text-blue-500">"batteryState"</span>: <span class="text-yellow-500">10</span>,
                        <span class="text-blue-500">"lifeTimeEnergyDischarged"</span>: <span class="text-yellow-500">13491068</span>,
                        <span class="text-blue-500">"lifeTimeEnergyCharged"</span>: <span class="text-yellow-500">9357134</span>,
                        <span class="text-blue-500">"batteryPercentageState"</span>: <span class="text-yellow-500">11</span>,
                        <span class="text-blue-500">"fullPackEnergyAvailable"</span>: <span class="text-yellow-500">7920</span>,
                        <span class="text-blue-500">"internalTemp"</span>: <span class="text-yellow-500">27.4</span>,
                        <span class="text-blue-500">"ACGridCharging"</span>: <span class="text-yellow-500">0</span>
                    }
                ]
            }
        ],
        <span class="text-blue-500">"totalExport"</span>: <span class="text-yellow-500">16259</span>,
        <span class="text-blue-500">"porcentajeExport"</span>: <span class="text-yellow-500">13.088453116950024</span>,
        <span class="text-blue-500">"overview"</span>: {
            <span class="text-blue-500">"lastUpdateTime"</span>: <span class="text-green-500">"2024-12-04 10:21:38"</span>,
            <span class="text-blue-500">"lifeTimeData"</span>: {
                <span class="text-blue-500">"energy"</span>: <span class="text-yellow-500">94705520</span>
            },
            <span class="text-blue-500">"lastYearData"</span>: {
                <span class="text-blue-500">"energy"</span>: <span class="text-yellow-500">27781930</span>
            },
            <span class="text-blue-500">"lastDayData"</span>: {
                <span class="text-blue-500">"energy"</span>: <span class="text-yellow-500">16459</span>
            }
        }
    }
}
</pre>

    </div>

    <!-- Componente de Código Copiable -->
    <div class="w-full md:w-4/12 mt-6 md:mt-0">
        <div class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-800 text-white'; ?> rounded-lg p-4 relative">
            <h2 class="text-lg font-semibold mb-2">
                <?php echo translate('usuarios_post.respuesta_ejemplo'); ?>
            </h2>
            <pre class="text-sm overflow-auto mb-4">
curl -X POST "https://app-energiasolarcanarias-backend.com/plants/graficas?proveedor=solaredge" \
-H "Authorization: Bearer tu_token_de_acceso" \
-H "Content-Type: application/json" \
-d '{
    "id": "1851069",
    "proveedor": "SolarEdge",
    "dia": "DAY",
    "fechaInicio": "2024-11-18",
    "fechaFin": "2024-11-19"
}'
            </pre>
            <button
                class="absolute top-2 right-2 <?php echo $theme === 'dark' ? 'bg-blue-400 hover:bg-blue-500' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white px-3 py-1 rounded transition"
                onclick="copiarCodigo()">
                <?php echo translate('copiar'); ?>
            </button>
        </div>
        </pre>
        <h3 class="text-md font-semibold mt-4">
            <?php echo translate('post_graficas.parameters_title'); ?>
        </h3>
        <ul class="list-disc pl-5 text-sm <?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-900'; ?>">
            <li><strong><?php echo translate('post_graficas.parameters_list_id_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_id_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_proveedor_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_proveedor_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_dia_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_dia_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_fechaInicio_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_fechaInicio_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_fechaFin_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_fechaFin_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_chartIndexId_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_chartIndexId_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_range_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_range_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_interval_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_interval_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_type_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_type_description'); ?></li>
        </ul>

    </div>
</div>
</div>
</div>

<script>
    function copiarCodigo() {
        const codigo = `curl -X POST "https://app-energiasolarcanarias-backend.com/plants/graficas?proveedor=solaredge" \
-H "Authorization: Bearer tu_token_de_acceso" \
-H "Content-Type: application/json" \
-d '{
    "id": "1851069",
    "dia": "DAY",
    "fechaInicio": "2024-11-18",
    "fechaFin": "2024-11-19"
}'`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>