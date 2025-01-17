<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
            <?php echo translate('subir_imagen.titulo'); ?>
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('subir_imagen.descripcion'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('subir_imagen.cuerpo_solicitud'); ?>
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
{
    <span class="text-blue-500">"imagen"</span>: <span class="text-green-500">"archivo.jpg"</span>
}
        </pre>

        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            Respuesta de Ejemplo
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
{
    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">true</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">200</span>,
    <span class="text-blue-500">"message"</span>: <span class="text-green-500">"Imagen subida con éxito"</span>,
    <span class="text-blue-500">"data"</span>: {
        <span class="text-blue-500">"imagen_url"</span>: <span class="text-green-500">"https://app-energiasolarcanarias-backend.com/uploads/archivo.jpg"</span>
    }
}
        </pre>
    </div>

    <!-- Componente de Código Copiable -->
    <div class="w-full md:w-4/12 mt-6 md:mt-0">
        <div class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-800 text-white'; ?> rounded-lg p-4 relative">
            <h2 class="text-lg font-semibold mb-2">
                <?php echo translate('subir_imagen.respuesta_ejemplo'); ?>
            </h2>
            <pre class="text-sm overflow-auto mb-4">
curl -X POST "https://app-energiasolarcanarias-backend.com/usuario/imagen" \
-H "Authorization: Bearer tu_token_de_acceso" \
-F "imagen=@archivo.jpg"
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
        const codigo = `curl -X POST "https://app-energiasolarcanarias-backend.com/usuario/imagen" \\
-H "Authorization: Bearer tu_token_de_acceso" \\
-F "imagen=@archivo.jpg"`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>