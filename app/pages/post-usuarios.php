<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
            <?php echo translate('usuarios_post.titulo'); ?>
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('usuarios_post.descripcion'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_post.cuerpo_solicitud'); ?>
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
{
  "email": "ejemplo@galagaagency.com",
  "password": "contraseña",
  "clase": "admin",
  "nombre": "ejemplo",
  "apellido": "1",
  "imagen": "https://cuv.upc.edu/es/shared/imatges/fotos-professorat-i-professionals/anonimo.jpg",
  "movil": "123456789",
  "activo": true,
  "eliminado": false
}
        </pre>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            Respuesta de Ejemplo
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
{
  "status": true,
  "code": 201,
  "message": "201 - Usuario creado con éxito",
  "data": {
    "usuario_id": 7,
    "email": "ejemplo@galagaagency.com",
    "clase": "admin",
    "nombre": "ejemplo",
    "apellido": "1",
    "imagen": "https://cuv.upc.edu/es/shared/imatges/fotos-professorat-i-professionals/anonimo.jpg",
    "movil": "123456789",
    "activo": true,
    "eliminado": false
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
curl -X POST "https://app-energiasolarcanarias-backend.com/usuarios" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer tu_token_de_acceso" \
-d '{
  "email": "ejemplo@galagaagency.com",
  "password": "contraseña",
  "clase": "admin",
  "nombre": "ejemplo",
  "apellido": "1",
  "imagen": "https://cuv.upc.edu/es/shared/imatges/fotos-professorat-i-professionals/anonimo.jpg",
  "movil": "123456789",
  "activo": true,
  "eliminado": false
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
        const codigo = `curl -X POST "https://app-energiasolarcanarias-backend.com/usuarios" \\
-H "Content-Type: application/json" \\
-H "Authorization: Bearer tu_token_de_acceso" \\
-d '{
  "email": "ejemplo@galagaagency.com",
  "password": "contraseña",
  "clase": "admin",
  "nombre": "ejemplo",
  "apellido": "1",
  "imagen": "https://cuv.upc.edu/es/shared/imatges/fotos-professorat-i-professionals/anonimo.jpg",
  "movil": "123456789",
  "activo": true,
  "eliminado": false
}'`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>
