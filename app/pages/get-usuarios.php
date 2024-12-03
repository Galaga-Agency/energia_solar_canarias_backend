
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
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
{
  "status": true,
  "code": 200,
  "message": "200 - Solicitud exitosa",
  "data": [
    {
      "usuario_id": 1,
      "email": "ejemplo1@galagaagency.com",
      "password_hash": "token_hasheado",
      "clase_id": 1,
      "nombre": "ejemplo",
      "apellido": "1",
      "imagen": "https://example.com/ejemplo1.jpg",
      "movil": "123456789",
      "activo": 1,
      "eliminado": 0
    },
    {
      "usuario_id": 1,
      "email": "ejemplo2@galagaagency.com",
      "password_hash": "token_hasheado",
      "clase_id": 1,
      "nombre": "ejemplo",
      "apellido": "1",
      "imagen": "https://example.com/ejemplo2.jpg",
      "movil": "123456789",
      "activo": 1,
      "eliminado": 0
    }
  ],
  "page": 1,
  "limit": 200
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