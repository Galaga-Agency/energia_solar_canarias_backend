<?php
// Referencia de todos los endpoints de la API, verificados end-to-end.
// Se genera como pagina de la documentacion (index.php ?page=endpoints-verificados).
// El texto sale de idiomas.json (seccion "endpoints") via translate(), igual que el
// resto de la documentacion. Las rutas y metodos no se traducen (son literales de la
// API). Respeta el tema claro/oscuro como el resto de paginas.

/** @var string $theme Tema activo ('dark'|'light'); lo define index.php, que incluye esta pagina. */
$titulo   = $theme === 'dark' ? 'text-blue-400' : 'text-blue-600';
$sub      = $theme === 'dark' ? 'text-gray-300' : 'text-gray-800';
$texto    = $theme === 'dark' ? 'text-gray-300' : 'text-gray-700';
$tablaBg  = $theme === 'dark' ? 'bg-gray-900' : 'bg-gray-100';
$thBg     = $theme === 'dark' ? 'bg-gray-800 text-gray-200' : 'bg-blue-100 text-gray-800';
$borde    = $theme === 'dark' ? 'border-gray-700' : 'border-gray-300';
$ok       = 'text-green-500';
$no       = $theme === 'dark' ? 'text-gray-600' : 'text-gray-400';

// Texto plano desde idiomas.json; se escapa porque algunos valores llevan < > ({<jwt>}).
$t = fn($clave) => htmlspecialchars(translate('endpoints.' . $clave));
?>
<div class="w-full p-4">
    <h1 class="text-3xl font-bold <?php echo $titulo; ?> mb-2"><?php echo $t('titulo'); ?></h1>
    <p class="<?php echo $texto; ?> mb-6"><?php echo $t('intro'); ?></p>

    <h2 class="text-xl font-semibold <?php echo $sub; ?> mb-2"><?php echo $t('auth_titulo'); ?></h2>
    <ul class="list-disc list-inside <?php echo $texto; ?> mb-6">
        <li><?php echo $t('auth_bearer'); ?></li>
        <li><?php echo $t('auth_apikey'); ?></li>
        <li><?php echo $t('auth_case'); ?></li>
    </ul>

    <h2 class="text-xl font-semibold <?php echo $sub; ?> mb-2"><?php echo $t('acceso_titulo'); ?></h2>
    <ul class="list-disc list-inside <?php echo $texto; ?> mb-6">
        <li><?php echo $t('acceso_admin'); ?></li>
        <li><?php echo $t('acceso_usuario'); ?></li>
        <li><?php echo $t('acceso_ambos'); ?></li>
    </ul>

    <h2 class="text-xl font-semibold <?php echo $sub; ?> mb-3"><?php echo $t('tabla_titulo'); ?></h2>
    <div class="overflow-x-auto mb-8">
        <table class="w-full text-sm border <?php echo $borde; ?> <?php echo $tablaBg; ?>">
            <thead class="<?php echo $thBg; ?>">
                <tr>
                    <th class="text-left p-2 border <?php echo $borde; ?>"><?php echo $t('col_metodo'); ?></th>
                    <th class="text-left p-2 border <?php echo $borde; ?>"><?php echo $t('col_ruta'); ?></th>
                    <th class="text-left p-2 border <?php echo $borde; ?>"><?php echo $t('col_auth'); ?></th>
                    <th class="text-left p-2 border <?php echo $borde; ?>"><?php echo $t('col_notas'); ?></th>
                </tr>
            </thead>
            <tbody class="<?php echo $texto; ?>">
                <?php
                // Cada fila: metodo, ruta, autorizacion, clave de nota (endpoints.n.<clave>).
                $filas = [
                    ['POST',  '/login',                              'usuario+apikey', 'login'],
                    ['POST',  '/token',                              'usuario+apikey', 'token'],
                    ['GET',   '/usuario',                            'Bearer/Token',   'usuario'],
                    ['GET',   '/usuario/bearerToken',                'Bearer',         'bearertoken'],
                    ['GET',   '/usuarios',                           'admin',          'usuarios_get'],
                    ['POST',  '/usuarios',                           'admin',          'usuarios_post'],
                    ['PUT',   '/usuarios/{id}',                      'admin',          'usuarios_put'],
                    ['DELETE', '/usuarios/{id}',                      'admin',          'usuarios_delete'],
                    ['POST',  '/usuarios/relacionar',                'admin',          'relacionar_post'],
                    ['DELETE', '/usuarios/relacionar',                'admin',          'relacionar_delete'],
                    ['GET',   '/proveedores',                        'Bearer/Token',   'proveedores'],
                    ['GET',   '/clases',                             'Bearer/Token',   'clases'],
                    ['GET',   '/logs',                               'admin',          'logs'],
                    ['POST',  '/clima',                              'Bearer/Token',   'clima'],
                    ['GET',   '/plants',                             'Bearer/Token',   'plants'],
                    ['GET',   '/plants?proveedor={p}',               'admin',          'plants_prov'],
                    ['GET',   '/plants/details/{id}',                'propietario',    'details'],
                    ['GET',   '/plant/power/realtime/{id}',          'propietario',    'realtime'],
                    ['GET',   '/plant/inventario/{id}',              'propietario',    'inventario'],
                    ['GET',   '/plant/overview/{id}',                'propietario',    'overview'],
                    ['GET',   '/plant/benefits/{id}',                'propietario',    'benefits'],
                    ['GET',   '/plant/alert?proveedor={p}',          'propietario*',   'alert'],
                    ['GET',   '/plants/graficas',                    'propietario',    'graficas'],
                    ['GET',   '/plants/energy/{ids}',                'propietario',    'energy'],
                    ['GET',   '/plant/grafica/bateria/{id}',         'propietario',    'bateria'],
                    ['GET',   '/plant/grafica/comparacion/{id}',     'propietario',    'comparacion'],
                ];
                foreach ($filas as $f) {
                    $colorMet = [
                        'GET' => 'text-green-500',
                        'POST' => 'text-yellow-500',
                        'PUT' => 'text-blue-500',
                        'DELETE' => 'text-red-500',
                    ][$f[0]] ?? '';
                    echo '<tr>';
                    echo '<td class="p-2 border ' . $borde . ' font-mono ' . $colorMet . '">' . $f[0] . '</td>';
                    echo '<td class="p-2 border ' . $borde . ' font-mono">' . htmlspecialchars($f[1]) . '</td>';
                    echo '<td class="p-2 border ' . $borde . '">' . htmlspecialchars($f[2]) . '</td>';
                    echo '<td class="p-2 border ' . $borde . '">' . $t('n.' . $f[3]) . '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
        <p class="<?php echo $no; ?> text-xs mt-1"><?php echo $t('nota_propietario'); ?></p>
    </div>

    <h2 class="text-xl font-semibold <?php echo $sub; ?> mb-3"><?php echo $t('prov_titulo'); ?></h2>
    <p class="<?php echo $texto; ?> mb-3"><?php echo $t('prov_intro'); ?></p>
    <div class="overflow-x-auto mb-8">
        <table class="w-full text-sm border <?php echo $borde; ?> <?php echo $tablaBg; ?>">
            <thead class="<?php echo $thBg; ?>">
                <tr>
                    <th class="text-left p-2 border <?php echo $borde; ?>"><?php echo $t('col_proveedor'); ?></th>
                    <?php foreach (['plants', 'details', 'realtime', 'graficas', 'inventario', 'alertas', 'benefits', 'overview'] as $c) {
                        echo '<th class="p-2 border ' . $borde . ' text-center">' . $c . '</th>';
                    } ?>
                </tr>
            </thead>
            <tbody class="<?php echo $texto; ?>">
                <?php
                // true = lo ofrece, false = 404. Coincide con lo que declaran los adaptadores.
                $matriz = [
                    'GoodWe'        => [1, 1, 1, 1, 1, 0, 0, 0],
                    'SolarEdge'     => [1, 1, 1, 1, 1, 0, 1, 1],
                    'VictronEnergy' => [1, 1, 1, 1, 1, 1, 0, 0],
                    'Sungrow'       => [1, 1, 1, 1, 1, 1, 1, 0],
                    'Sigenergy'     => [1, 1, 1, 1, 1, 1, 0, 0],
                ];
                foreach ($matriz as $prov => $caps) {
                    echo '<tr><td class="p-2 border ' . $borde . ' font-semibold">' . $prov . '</td>';
                    foreach ($caps as $c) {
                        echo '<td class="p-2 border ' . $borde . ' text-center ' . ($c ? $ok : $no) . '">' . ($c ? '&#10003;' : '&#8212;') . '</td>';
                    }
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <p class="<?php echo $no; ?> text-xs"><?php echo $t('pie'); ?></p>
</div>