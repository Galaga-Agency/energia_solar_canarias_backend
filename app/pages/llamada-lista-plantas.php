
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
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
{
  <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">true</span>,
  <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">200</span>,
  <span class="text-blue-500">"message"</span>: <span class="text-green-500">"200 - Solicitud exitosa"</span>,
  <span class="text-blue-500">"data"</span>: [
    {
      <span class="text-blue-500">"id"</span>: <span class="text-green-500">"identificador"</span>,
      <span class="text-blue-500">"name"</span>: <span class="text-green-500">"Hola mundo"</span>,
      <span class="text-blue-500">"address"</span>: <span class="text-green-500">"street of example, 3, 00000 city, city, Country"</span>,
      <span class="text-blue-500">"capacity"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"status"</span>: <span class="text-green-500">"error"</span>,
      <span class="text-blue-500">"type"</span>: <span class="text-green-500">"Residential"</span>,
      <span class="text-blue-500">"latitude"</span>: <span class="text-green-500">"0"</span>,
      <span class="text-blue-500">"longitude"</span>: <span class="text-green-500">"0"</span>,
      <span class="text-blue-500">"organization"</span>: <span class="text-green-500">"Whatever"</span>,
      <span class="text-blue-500">"current_power"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"total_energy"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"daily_energy"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"monthly_energy"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"installation_date"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"pto_date"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"notes"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"alert_quantity"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"highest_impact"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"primary_module"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"public_settings"</span>: <span class="text-yellow-500">null</span>
    },
    {
      <span class="text-blue-500">"id"</span>: <span class="text-green-500">"identificador2"</span>,
      <span class="text-blue-500">"name"</span>: <span class="text-green-500">"Galaga"</span>,
      <span class="text-blue-500">"address"</span>: <span class="text-green-500">"street. of example, 3, 00000 city, city, Country"</span>,
      <span class="text-blue-500">"capacity"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"status"</span>: <span class="text-green-500">"working"</span>,
      <span class="text-blue-500">"type"</span>: <span class="text-green-500">"Residential"</span>,
      <span class="text-blue-500">"latitude"</span>: <span class="text-green-500">"0"</span>,
      <span class="text-blue-500">"longitude"</span>: <span class="text-green-500">"0"</span>,
      <span class="text-blue-500">"organization"</span>: <span class="text-green-500">"Whatever"</span>,
      <span class="text-blue-500">"current_power"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"total_energy"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"daily_energy"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"monthly_energy"</span>: <span class="text-yellow-500">0</span>,
      <span class="text-blue-500">"installation_date"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"pto_date"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"notes"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"alert_quantity"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"highest_impact"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"primary_module"</span>: <span class="text-yellow-500">null</span>,
      <span class="text-blue-500">"public_settings"</span>: <span class="text-yellow-500">null</span>
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
