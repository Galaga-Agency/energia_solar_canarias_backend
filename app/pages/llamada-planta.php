<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12 p-4">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
            GET /plants/details/{id}
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('get_detalles_planta.descripcion'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.parametros_consulta'); ?>
        </h2>
        <ul class="list-disc list-inside <?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <li><strong>id</strong> <?php echo translate('get_detalles_planta.id'); ?></li>
            <li><strong>proveedor</strong> <?php echo translate('get_detalles_planta.proveedor'); ?></li>
        </ul>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.respuesta_ejemplo'); ?>
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
{
  <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">true</span>,
  <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">200</span>,
  <span class="text-blue-500">"message"</span>: <span class="text-green-500">"200 - Solicitud exitosa"</span>,
  <span class="text-blue-500">"data"</span>: [
    {
      <span class="text-blue-500">"organization"</span>: <span class="text-green-500">"Whatever"</span>,
      <span class="text-blue-500">"id"</span>: <span class="text-green-500">"identificador"</span>,
      <span class="text-blue-500">"name"</span>: <span class="text-green-500">"Whatever"</span>,
      <span class="text-blue-500">"accountId"</span>: <span class="text-yellow-500">""</span>,
      <span class="text-blue-500">"status"</span>: <span class="text-green-500">"working"</span>,
      <span class="text-blue-500">"peakPower"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"lastUpdateTime"</span>: <span class="text-green-500">"0000-00-00"</span>,
      <span class="text-blue-500">"installationDate"</span>: <span class="text-green-500">"0000-00-00"</span>,
      <span class="text-blue-500">"ptoDate"</span>: <span class="text-yellow-500">""</span>,
      <span class="text-blue-500">"notes"</span>: <span class="text-yellow-500">""</span>,
      <span class="text-blue-500">"type"</span>: <span class="text-yellow-500">""</span>,
      <span class="text-blue-500">"location"</span>: <span class="text-green-500">"Whatever place, Madrid, Spain"</span>,
      <span class="text-blue-500">"batteryCapacity"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"orgCode"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"kpi"</span>: {
        <span class="text-blue-500">"monthGeneration"</span>: <span class="text-yellow-500">null</span>,
        <span class="text-blue-500">"pac"</span>: <span class="text-yellow-500">null</span>,
        <span class="text-blue-500">"power"</span>: <span class="text-yellow-500">null</span>,
        <span class="text-blue-500">"totalPower"</span>: <span class="text-yellow-500">null</span>,
        <span class="text-blue-500">"dayIncome"</span>: <span class="text-yellow-500">null</span>,
        <span class="text-blue-500">"yieldRate"</span>: <span class="text-yellow-500">null</span>,
        <span class="text-blue-500">"currency"</span>: <span class="text-yellow-500">null</span>
      },
      <span class="text-blue-500">"alertQuantity"</span>: <span class="text-yellow-500">""</span>,
      <span class="text-blue-500">"highestImpact"</span>: <span class="text-yellow-500">""</span>,
      <span class="text-blue-500">"primaryModule"</span>: {
        <span class="text-blue-500">"manufacturerName"</span>: <span class="text-green-500">"name"</span>,
        <span class="text-blue-500">"modelName"</span>: <span class="text-green-500">"things"</span>,
        <span class="text-blue-500">"maximumPower"</span>: <span class="text-yellow-500">0</span>,
        <span class="text-blue-500">"temperatureCoef"</span>: <span class="text-yellow-500">0</span>
      },
      <span class="text-blue-500">"isEvcharge"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"isTigo"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"isPowerflow"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"isSec"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"isGenset"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"isMicroInverter"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"hasLayout"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"layout_id"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"isMeter"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"isEnvironmental"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"powercontrol_status"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"chartsTypesByPlant"</span>: <span class="text-yellow-500">null</span>
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
curl -X GET "https://app-energiasolarcanarias-backend.com/plants/details/{id}" \
-H "Authorization: Bearer tu_token_de_acceso"
            </pre>
            <button
                class="absolute top-2 right-2 <?php echo $theme === 'dark' ? 'bg-blue-400 hover:bg-blue-500' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white px-3 py-1 rounded transition"
                onclick="copiarCodigo()">
                Copiar
            </button>
        </div>
    </div>
</div>

<script>
    function copiarCodigo() {
        const codigo = `curl -X GET "https://app-energiasolarcanarias-backend.com/plants/details/{id}" \\
-H "Authorization: Bearer tu_token_de_acceso"`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>