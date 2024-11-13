<h1 class="text-4xl font-bold text-blue-600 mb-4"><?php echo translate('inicio.titulo'); ?></h1>
<p class="text-gray-700 mb-6">
    <?php
    echo translate('inicio.descripcion');
    ?>
</p>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <!-- Tarjeta de ejemplo 1 -->
    <div class="bg-blue-100 p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
        <h2 class="text-xl font-semibold text-blue-700 mb-2"><?php echo translate('inicio.usuarios'); ?></h2>
    <p class="text-gray-600">
        <?php echo translate('inicio.descripcionUsuarios'); ?>
    </p>
        <a href="?page=get-usuarios" class="inline-block mt-4 text-blue-600 font-semibold hover:underline"> <?php echo translate('inicio.ver_mas'); ?> &rarr;</a>
    </div>

      <!-- Tarjeta de ejemplo 2 -->
    <div class="bg-green-100 p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
        <h2 class="text-xl font-semibold text-green-700 mb-2"><?php echo translate('inicio.loginytokens'); ?></h2>
        <p class="text-gray-600">
        <?php echo translate('inicio.login_descripcion'); ?>
    </p>
        <a href="?page=bearer-token" class="inline-block mt-4 text-green-600 font-semibold hover:underline"><?php echo translate('inicio.ver_mas'); ?> &rarr;</a>
    </div>

    <!-- Tarjeta de ejemplo 3 -->
    <div class="bg-yellow-100 p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
        <h2 class="text-xl font-semibold text-yellow-700 mb-2"><?php echo translate('inicio.datos_api'); ?></h2>
        <p class="text-gray-600">
            <?php echo translate('inicio.datos_api_descripcion'); ?>
        </p>
        <a href="?page=get-lista-plantas" class="inline-block mt-4 text-yellow-600 font-semibold hover:underline"><?php echo translate('inicio.ver_mas'); ?> &rarr;</a>
    </div>

    <!-- Tarjeta de ejemplo 4 -->
    <div class="bg-red-100 p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
        <h2 class="text-xl font-semibold text-red-700 mb-2"><?php echo translate('inicio.datos_api'); ?></h2>
        <p class="text-gray-600">
            <?php echo translate('inicio.mas_funcionalidades'); ?>
        </p>
        <a href="#" class="inline-block mt-4 text-red-600 font-semibold hover:underline"><?php echo translate('inicio.ver_mas'); ?> &rarr;</a>
    </div>
</div>