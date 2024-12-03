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
            <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
{
  "email": "ejemplo@galagaagency.com",
  "password": "contraseña",
  "idiomaUsuario": "es"
}
            </pre>
            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
                <?php echo translate('login.respuesta_ejemplo'); ?>
            </h2>
            <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
{
  "status": "success",
  "code": 200,
  "message": "Successful login, the token to continue has been sent to your email with a validity of 5 minutes",
  "data": {
    "id": 2,
    "email": "ejemplo@galagaagency.com",
    "clase": "cliente",
    "movil": "645521246",
    "nombre": "ejemplo",
    "apellido": "1",
    "imagen": "ejemplo.jpg",
    "idiomaUsuario": "es"
  },
  "pagination": null
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
                    onclick="copiarCodigoLogin()"
                >
                    Copiar
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
            <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
{
  "id": 1,
  "token": "1234"
}
            </pre>
            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
                <?php echo translate('login.respuesta_ejemplo'); ?>
            </h2>
            <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
{
  "status": "success",
  "code": 200,
  "message": "El token aún es válido para el usuario enviado",
  "data": {
    "id": 1,
    "email": "ejemplo@galagaagency.com",
    "clase": "admin",
    "movil": "645521246",
    "nombre": "ejemplo",
    "apellido": "1",
    "imagen": "ejemplo.jpg",
    "tokenIdentificador": "token_identificativo"
  },
  "pagination": null
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
                    onclick="copiarCodigoToken()"
                >
                    Copiar
                </button>
            </div>
        </div>
    </div>
</div>
