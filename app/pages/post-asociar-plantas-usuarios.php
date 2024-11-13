<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12">
      <h1 class="text-3xl font-bold text-blue-600 mb-4">POST /usuarios/relacionar</h1>
      <p class="text-gray-700 mb-4">
      <?php echo translate('asociar_plantas_usuarios.descripcion')?>
      </p>
      <h2 class="text-xl font-semibold text-gray-800 mb-2"><?php echo translate('usuarios_get.parametros_consulta') ?></h2>
        <ul class="list-disc list-inside text-gray-700 mb-4">
            <li><strong>idplanta</strong> <?php echo translate('asociar_plantas_usuarios.idplanta')?></li>
            <li><strong>idusuario</strong> <?php echo translate('asociar_plantas_usuarios.idusuario')?></li>
            <li><strong>proveedor</strong> <?php echo translate('asociar_plantas_usuarios.proveedor')?></li>
        </ul>
      <h2 class="text-xl font-semibold text-gray-800 mb-2">Respuesta de Ejemplo</h2>
      <pre class="bg-gray-100 rounded-lg p-4 text-sm text-gray-900 overflow-auto">
{
    "status": true,
    "code": 200,
    "message": "200 - Solicitud exitosa",
    "data": true
}
      </pre>
    </div>

    <!-- Componente de Código Copiable -->
    <div class="w-full md:w-4/12 mt-6 md:mt-0">
      <div class="bg-gray-800 text-white rounded-lg p-4 relative">
        <h2 class="text-lg font-semibold mb-2"><?php echo translate('usuarios_post.respuesta_ejemplo')?> </h2>
        <pre class="text-sm overflow-auto mb-4">
curl -X GET "https://app-energiasolarcanarias-backend.com/usuarios/relacionar?idplanta=1851069&idusuario=20&proveedor=GoodWe" \\
-H "Authorization: Bearer tu_token_de_acceso" \\
        </pre>
        <button
          class="absolute top-2 right-2 bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition"
          onclick="copiarCodigo()"
        >
          Copiar
        </button>
      </div>
    </div>
  </div>

<script>
  function copiarCodigo() {
    const codigo = `curl -X GET "https://app-energiasolarcanarias-backend.com/usuarios/relacionar?idplanta=1851069&idusuario=20&proveedor=GoodWe" \\
-H "Authorization: Bearer tu_token_de_acceso" \\`;
    navigator.clipboard.writeText(codigo).then(() => {
      alert('Código copiado al portapapeles');
    }).catch(err => {
      alert('Error al copiar el código');
    });
  }
</script>
