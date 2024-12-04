
<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12 p-4">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
        GET /clima
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('clima.descripcion'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('clima.body'); ?>
        </h2>
        <ul class="list-disc list-inside <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-700'; ?> mb-4">
            <li>
                <strong>name</strong> 
                <?php echo translate('clima.name'); ?>
            </li>
        </ul>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.respuesta_ejemplo'); ?>
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
{
  <span class="text-blue-500">"latitude"</span>: <span class="text-yellow-500">28.1</span>,
  <span class="text-blue-500">"longitude"</span>: <span class="text-yellow-500">-15.5</span>,
  <span class="text-blue-500">"timezone"</span>: <span class="text-green-500">"Europe/Madrid"</span>,
  <span class="text-blue-500">"current"</span>: {
    <span class="text-blue-500">"temperature_2m"</span>: <span class="text-yellow-500">21.6</span>,
    <span class="text-blue-500">"humidity_2m"</span>: <span class="text-yellow-500">73</span>,
    <span class="text-blue-500">"cloud_cover"</span>: <span class="text-yellow-500">35</span>,
    <span class="text-blue-500">"wind_speed_10m"</span>: <span class="text-yellow-500">20.3</span>
  },
  <span class="text-blue-500">"daily"</span>: {
    <span class="text-blue-500">"temperature_max"</span>: [<span class="text-yellow-500">21.7</span>, <span class="text-yellow-500">21.8</span>, <span class="text-yellow-500">21.3</span>],
    <span class="text-blue-500">"temperature_min"</span>: [<span class="text-yellow-500">17.7</span>, <span class="text-yellow-500">17.3</span>, <span class="text-yellow-500">17.4</span>],
    <span class="text-blue-500">"uv_index_max"</span>: [<span class="text-yellow-500">4.65</span>, <span class="text-yellow-500">3.05</span>, <span class="text-yellow-500">4.20</span>]
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
curl -X GET "https://app-energiasolarcanarias-backend.com/clima" \
-H "Authorization: Bearer tu_token_de_acceso"
-d '{
    "name":"Alcorcón, Madrid"
}'
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
        const codigo = `curl -X GET "https://app-energiasolarcanarias-backend.com/clima" \
-H "Authorization: Bearer tu_token_de_acceso"
-d '{
    "name":"Alcorcón, Madrid"
}'`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>