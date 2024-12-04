
<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12 p-4">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
            <?php echo translate('usuarios_get.titulo'); ?>
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('usuarios_get.descripcion'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.parametros_consulta'); ?>
        </h2>
        <ul class="list-disc list-inside <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-700'; ?> mb-4">
            <li>
                <strong><?php echo translate('usuarios_get.page'); ?></strong> 
                <?php echo translate('usuarios_get.page_descripcion'); ?>
            </li>
            <li>
                <strong><?php echo translate('usuarios_get.limit'); ?></strong> 
                <?php echo translate('usuarios_get.limit_descripcion'); ?>
            </li>
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
      <span class="text-blue-500">"usuario_id"</span>: <span class="text-yellow-500">1</span>,
      <span class="text-blue-500">"email"</span>: <span class="text-green-500">"ejemplo1@galagaagency.com"</span>,
      <span class="text-blue-500">"password_hash"</span>: <span class="text-green-500">"token_hasheado"</span>,
      <span class="text-blue-500">"clase_id"</span>: <span class="text-yellow-500">1</span>,
      <span class="text-blue-500">"nombre"</span>: <span class="text-green-500">"ejemplo"</span>,
      <span class="text-blue-500">"apellido"</span>: <span class="text-green-500">"1"</span>,
      <span class="text-blue-500">"imagen"</span>: <span class="text-green-500">"https://example.com/ejemplo1.jpg"</span>,
      <span class="text-blue-500">"movil"</span>: <span class="text-green-500">"123456789"</span>,
      <span class="text-blue-500">"activo"</span>: <span class="text-yellow-500">1</span>,
      <span class="text-blue-500">"eliminado"</span>: <span class="text-yellow-500">0</span>
    },
    {
      <span class="text-blue-500">"usuario_id"</span>: <span class="text-yellow-500">1</span>,
      <span class="text-blue-500">"email"</span>: <span class="text-green-500">"ejemplo2@galagaagency.com"</span>,
      <span class="text-blue-500">"password_hash"</span>: <span class="text-green-500">"token_hasheado"</span>,
      <span class="text-blue-500">"clase_id"</span>: <span class="text-yellow-500">1</span>,
      <span class="text-blue-500">"nombre"</span>: <span class="text-green-500">"ejemplo"</span>,
      <span class="text-blue-500">"apellido"</span>: <span class="text-green-500">"1"</span>,
      <span class="text-blue-500">"imagen"</span>: <span class="text-green-500">"https://example.com/ejemplo2.jpg"</span>,
      <span class="text-blue-500">"movil"</span>: <span class="text-green-500">"123456789"</span>,
      <span class="text-blue-500">"activo"</span>: <span class="text-yellow-500">1</span>,
      <span class="text-blue-500">"eliminado"</span>: <span class="text-yellow-500">0</span>
    }
  ],
  <span class="text-blue-500">"page"</span>: <span class="text-yellow-500">1</span>,
  <span class="text-blue-500">"limit"</span>: <span class="text-yellow-500">200</span>
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
curl -X GET "https://app-energiasolarcanarias-backend.com/usuarios?page=1&limit=200" \
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
        const codigo = `curl -X GET "https://app-energiasolarcanarias-backend.com/usuarios?page=1&limit=200" \\
-H "Authorization: Bearer tu_token_de_acceso"`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>