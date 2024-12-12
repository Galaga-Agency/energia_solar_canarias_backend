<div class="p-6 flex flex-col space-y-8">
    <!-- Descripción del Endpoint de Login -->
    <div class="flex flex-col md:flex-row justify-between">
        <div class="w-full md:w-7/12">
            <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
                <?php echo translate('login.titulo_login'); ?>
            </h1>
            <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
                <?php echo translate('login.descripcion_login'); ?>
            </p>
            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
                <?php echo translate('login.cuerpo_solicitud'); ?>
            </h2>
            <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
{
    <span class="text-blue-500">"email"</span>: <span class="text-green-500">"ejemplo@galagaagency.com"</span>,
    <span class="text-blue-500">"password"</span>: <span class="text-green-500">"contraseña"</span>,
    <span class="text-blue-500">"idiomaUsuario"</span>: <span class="text-green-500">"es"</span>
}
</pre>

            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
                <?php echo translate('login.respuesta_ejemplo'); ?>
            </h2>
            <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
{
  <span class="text-blue-500">"status"</span>: <span class="text-green-500">"success"</span>,
  <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">200</span>,
  <span class="text-blue-500">"message"</span>: <span class="text-green-500">"Successful login, the token to continue has been sent to your email with a validity of 5 minutes"</span>,
  <span class="text-blue-500">"data"</span>: {
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">2</span>,
    <span class="text-blue-500">"email"</span>: <span class="text-green-500">"ejemplo@galagaagency.com"</span>,
    <span class="text-blue-500">"clase"</span>: <span class="text-green-500">"cliente"</span>,
    <span class="text-blue-500">"movil"</span>: <span class="text-green-500">"645521246"</span>,
    <span class="text-blue-500">"nombre"</span>: <span class="text-green-500">"ejemplo"</span>,
    <span class="text-blue-500">"apellido"</span>: <span class="text-green-500">"1"</span>,
    <span class="text-blue-500">"imagen"</span>: <span class="text-green-500">"ejemplo.jpg"</span>,
    <span class="text-blue-500">"idiomaUsuario"</span>: <span class="text-green-500">"es"</span>
  },
  <span class="text-blue-500">"pagination"</span>: <span class="text-yellow-500">null</span>
}
</pre>

        </div>

        <!-- Componente de Código Copiable para el Login -->
        <div class="w-full md:w-4/12 mt-6 md:mt-0">
            <div class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-800 text-white'; ?> rounded-lg p-4 relative">
                <h2 class="text-lg font-semibold mb-2"><?php echo translate('login.ejemplo_uso_login'); ?></h2>
                <pre class="text-sm overflow-auto mb-4">
curl -X POST "https://app-energiasolarcanarias-backend.com/login" \
-H "Content-Type: application/json" \
-d '{
  "email": "ejemplo@galagaagency.com",
  "password": "contraseña",
  "idiomaUsuario": "es"
}'
                </pre>
                <button
                    class="absolute top-2 right-2 <?php echo $theme === 'dark' ? 'bg-blue-400 hover:bg-blue-500' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white px-3 py-1 rounded transition"
                    onclick="copiarCodigoLogin()">
                    <?php echo translate('copiar'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Descripción del Endpoint de Validación de Token -->
    <div class="flex flex-col md:flex-row justify-between">
        <div class="w-full md:w-7/12">
            <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
                <?php echo translate('login.titulo_token'); ?>
            </h1>
            <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
                <?php echo translate('login.descripcion_token'); ?>
            </p>
            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
                <?php echo translate('login.cuerpo_solicitud'); ?>
            </h2>
            <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">1</span>,
    <span class="text-blue-500">"token"</span>: <span class="text-green-500">"1234"</span>
}
            </pre>

            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
                <?php echo translate('login.respuesta_ejemplo'); ?>
            </h2>
            <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
{
    <span class="text-blue-500">"status"</span>: <span class="text-green-500">"success"</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">200</span>,
    <span class="text-blue-500">"message"</span>: <span class="text-green-500">"El token aún es válido para el usuario enviado"</span>,
    <span class="text-blue-500">"data"</span>: {
        <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">1</span>,
        <span class="text-blue-500">"email"</span>: <span class="text-green-500">"ejemplo@galagaagency.com"</span>,
        <span class="text-blue-500">"clase"</span>: <span class="text-green-500">"admin"</span>,
        <span class="text-blue-500">"movil"</span>: <span class="text-green-500">"645521246"</span>,
        <span class="text-blue-500">"nombre"</span>: <span class="text-green-500">"ejemplo"</span>,
        <span class="text-blue-500">"apellido"</span>: <span class="text-green-500">"1"</span>,
        <span class="text-blue-500">"imagen"</span>: <span class="text-green-500">"ejemplo.jpg"</span>,
        <span class="text-blue-500">"tokenIdentificador"</span>: <span class="text-green-500">"token_identificativo"</span>
    },
    <span class="text-blue-500">"pagination"</span>: <span class="text-yellow-500">null</span>
}
</pre>

        </div>

        <!-- Componente de Código Copiable para la Validación del Token -->
        <div class="w-full md:w-4/12 mt-6 md:mt-0">
            <div class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-800 text-white'; ?> rounded-lg p-4 relative">
                <h2 class="text-lg font-semibold mb-2"><?php echo translate('login.ejemplo_uso_token'); ?></h2>
                <pre class="text-sm overflow-auto mb-4">
curl -X POST "https://app-energiasolarcanarias-backend.com/token" \
-H "Content-Type: application/json" \
-d '{
  "id": 1,
  "token": "1234"
}'
                </pre>
                <button
                    class="absolute top-2 right-2 <?php echo $theme === 'dark' ? 'bg-blue-400 hover:bg-blue-500' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white px-3 py-1 rounded transition"
                    onclick="copiarCodigoToken()">
                    <?php echo translate('copiar'); ?>
                </button>
            </div>
        </div>
    </div>
</div>