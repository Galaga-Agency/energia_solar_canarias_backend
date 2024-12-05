<div>
    <h1 class="text-4xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
        <?php echo translate('inicio.titulo'); ?>
    </h1>
    <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-6">
        <?php echo translate('inicio.descripcion'); ?>
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Tarjeta de tipo 1 -->
        <div class="<?php echo $theme === 'dark' ? 'bg-blue-900' : 'bg-blue-100'; ?> p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-blue-300' : 'text-blue-700'; ?> mb-2">
                <?php echo translate('inicio.usuarios'); ?>
            </h2>
            <p class="<?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-600'; ?>">
                <?php echo translate('inicio.descripcionUsuarios'); ?>
            </p>
            <a href="?page=get-usuarios" class="inline-block mt-4 <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> font-semibold hover:underline">
                <?php echo translate('inicio.ver_mas'); ?> &rarr;
            </a>
        </div>

        <!-- Tarjeta de tipo 2 -->
        <div class="<?php echo $theme === 'dark' ? 'bg-green-900' : 'bg-green-100'; ?> p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-green-300' : 'text-green-700'; ?> mb-2">
                <?php echo translate('inicio.loginytokens'); ?>
            </h2>
            <p class="<?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-600'; ?>">
                <?php echo translate('inicio.login_descripcion'); ?>
            </p>
            <a href="?page=bearer-token" class="inline-block mt-4 <?php echo $theme === 'dark' ? 'text-green-400' : 'text-green-600'; ?> font-semibold hover:underline">
                <?php echo translate('inicio.ver_mas'); ?> &rarr;
            </a>
        </div>

        <!-- Tarjeta de tipo 3 -->
        <div class="<?php echo $theme === 'dark' ? 'bg-yellow-900' : 'bg-yellow-100'; ?> p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-yellow-300' : 'text-yellow-700'; ?> mb-2">
                <?php echo translate('inicio.datos_api'); ?>
            </h2>
            <p class="<?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-600'; ?>">
                <?php echo translate('inicio.datos_api_descripcion'); ?>
            </p>
            <a href="?page=get-lista-plantas" class="inline-block mt-4 <?php echo $theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'; ?> font-semibold hover:underline">
                <?php echo translate('inicio.ver_mas'); ?> &rarr;
            </a>
        </div>

        <!-- Tarjeta de tipo 4 -->
        <div class="<?php echo $theme === 'dark' ? 'bg-red-900' : 'bg-red-100'; ?> p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-red-300' : 'text-red-700'; ?> mb-2">
                <?php echo translate('inicio.logs'); ?>
            </h2>
            <p class="<?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-600'; ?>">
                <?php echo translate('inicio.logs_descriopcion'); ?>
            </p>
            <a href="?page=get-logs" class="inline-block mt-4 <?php echo $theme === 'dark' ? 'text-red-400' : 'text-red-600'; ?> font-semibold hover:underline">
                <?php echo translate('inicio.ver_mas'); ?> &rarr;
            </a>
        </div>

        <!-- Tarjeta de tipo 5 (nuevo) -->
        <div class="<?php echo $theme === 'dark' ? 'bg-indigo-900' : 'bg-indigo-100'; ?> p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-indigo-300' : 'text-indigo-700'; ?> mb-2">
                <?php echo translate('inicio.clases'); ?>
            </h2>
            <p class="<?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-600'; ?>">
                <?php echo translate('inicio.clases_descripcion'); ?>
            </p>
            <a href="?page=get-clases" class="inline-block mt-4 <?php echo $theme === 'dark' ? 'text-indigo-400' : 'text-indigo-600'; ?> font-semibold hover:underline">
                <?php echo translate('inicio.ver_mas'); ?> &rarr;
            </a>
        </div>

        <!-- Tarjeta de tipo 6 (nuevo) -->
        <div class="<?php echo $theme === 'dark' ? 'bg-purple-900' : 'bg-purple-100'; ?> p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-purple-300' : 'text-purple-700'; ?> mb-2">
                <?php echo translate('inicio.proveedores'); ?>
            </h2>
            <p class="<?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-600'; ?>">
                <?php echo translate('inicio.proveedores_descripcion'); ?>
            </p>
            <a href="?page=get-proveedores" class="inline-block mt-4 <?php echo $theme === 'dark' ? 'text-purple-400' : 'text-purple-600'; ?> font-semibold hover:underline">
                <?php echo translate('inicio.ver_mas'); ?> &rarr;
            </a>
        </div>
    </div>
</div>
