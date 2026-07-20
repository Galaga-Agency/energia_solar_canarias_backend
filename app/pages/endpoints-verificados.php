<?php
// Referencia de todos los endpoints de la API, verificados end-to-end.
// Se genera como pagina de la documentacion (index.php ?page=endpoints-verificados).
// Texto en espanol literal a proposito: es una tabla de referencia, no contenido
// traducible. Respeta el tema claro/oscuro como el resto de paginas.
$titulo   = $theme === 'dark' ? 'text-blue-400' : 'text-blue-600';
$sub      = $theme === 'dark' ? 'text-gray-300' : 'text-gray-800';
$texto    = $theme === 'dark' ? 'text-gray-300' : 'text-gray-700';
$tablaBg  = $theme === 'dark' ? 'bg-gray-900' : 'bg-gray-100';
$thBg     = $theme === 'dark' ? 'bg-gray-800 text-gray-200' : 'bg-blue-100 text-gray-800';
$borde    = $theme === 'dark' ? 'border-gray-700' : 'border-gray-300';
$ok       = 'text-green-500';
$no       = $theme === 'dark' ? 'text-gray-600' : 'text-gray-400';
?>
<div class="w-full p-4">
    <h1 class="text-3xl font-bold <?php echo $titulo; ?> mb-2">Endpoints verificados</h1>
    <p class="<?php echo $texto; ?> mb-6">
        Todos los endpoints de la API probados de extremo a extremo con tres identidades:
        administrador (Bearer), usuario normal (Bearer) y clave de API (esquema
        <code>Token</code>). Cada fila indica el metodo, la ruta, la autorizacion que exige
        y el resultado observado.
    </p>

    <h2 class="text-xl font-semibold <?php echo $sub; ?> mb-2">Autenticacion</h2>
    <ul class="list-disc list-inside <?php echo $texto; ?> mb-6">
        <li><strong>Bearer (JWT):</strong> cabecera <code>Authorization: Bearer &lt;jwt&gt;</code>.
            Se obtiene con <code>/login</code> + <code>/token</code>. Dura 180 dias.</li>
        <li><strong>Clave de API:</strong> cabecera <code>Authorization: Token &lt;uuid&gt;</code>.
            La crea el propio usuario con su Bearer en <code>/usuario/bearerToken</code>.</li>
        <li>La cabecera <code>Authorization</code> se lee sin distinguir mayusculas
            (RFC 7230), asi que funciona por HTTP/1.1 y HTTP/2.</li>
    </ul>

    <h2 class="text-xl font-semibold <?php echo $sub; ?> mb-2">Control de acceso</h2>
    <ul class="list-disc list-inside <?php echo $texto; ?> mb-6">
        <li><strong>Admin</strong> (clase 1): ve y opera sobre todas las plantas y usuarios.</li>
        <li><strong>Usuario</strong> (clase 2): solo sus plantas asociadas. Pedir una planta
            ajena devuelve <strong>403</strong>, exista o no (no se confirma su existencia).</li>
        <li>El mismo control aplica tanto por Bearer como por clave de API.</li>
    </ul>

    <h2 class="text-xl font-semibold <?php echo $sub; ?> mb-3">Tabla de endpoints</h2>
    <div class="overflow-x-auto mb-8">
    <table class="w-full text-sm border <?php echo $borde; ?> <?php echo $tablaBg; ?>">
        <thead class="<?php echo $thBg; ?>">
            <tr>
                <th class="text-left p-2 border <?php echo $borde; ?>">Metodo</th>
                <th class="text-left p-2 border <?php echo $borde; ?>">Ruta</th>
                <th class="text-left p-2 border <?php echo $borde; ?>">Autorizacion</th>
                <th class="text-left p-2 border <?php echo $borde; ?>">Notas</th>
            </tr>
        </thead>
        <tbody class="<?php echo $texto; ?>">
        <?php
        $filas = [
            ['POST',  '/login',                              'usuario+apikey', 'Envia un codigo por email.'],
            ['POST',  '/token',                              'usuario+apikey', 'Valida el codigo y devuelve el JWT.'],
            ['GET',   '/usuario',                            'Bearer/Token',   'Datos del usuario autenticado.'],
            ['GET',   '/usuario/bearerToken',                'Bearer',         'Crea/devuelve la clave de API del usuario.'],
            ['GET',   '/usuarios',                           'admin',          'Lista paginada. Usuario normal: 403.'],
            ['POST',  '/usuarios',                           'admin',          'Alta atomica: si Zoho no acepta, no se crea.'],
            ['PUT',   '/usuarios/{id}',                      'admin',          'Sincroniza con Zoho antes de tocar local.'],
            ['DELETE','/usuarios/{id}',                      'admin',          'Baja logica atomica con Zoho.'],
            ['POST',  '/usuarios/relacionar',                'admin',          'Asocia una planta a un usuario.'],
            ['DELETE','/usuarios/relacionar',                'admin',          'Quita la asociacion.'],
            ['GET',   '/proveedores',                        'Bearer/Token',   'Lista de proveedores disponibles.'],
            ['GET',   '/clases',                             'Bearer/Token',   'Clases de usuario.'],
            ['GET',   '/logs',                               'admin',          'Registro de la aplicacion.'],
            ['POST',  '/clima',                              'Bearer/Token',   'Cuerpo JSON: {name} o {lat,long}.'],
            ['GET',   '/plants',                             'Bearer/Token',   'Agregado (admin) o solo las suyas (usuario).'],
            ['GET',   '/plants?proveedor={p}',               'admin',          'Plantas de un proveedor.'],
            ['GET',   '/plants/details/{id}',                'propietario',    'Detalle. Planta ajena: 403.'],
            ['GET',   '/plant/power/realtime/{id}',          'propietario',    'Tiempo real. Planta ajena: 403.'],
            ['GET',   '/plant/inventario/{id}',              'propietario',    'Equipos de la planta.'],
            ['GET',   '/plant/overview/{id}',                'propietario',    'Solo SolarEdge; el resto 404.'],
            ['GET',   '/plant/benefits/{id}',                'propietario',    'SolarEdge y Sungrow; el resto 404.'],
            ['GET',   '/plant/alert?proveedor={p}',          'propietario*',   'GoodWe va por todo el parque: solo admin.'],
            ['GET',   '/plants/graficas',                    'propietario',    'Historicos por planta.'],
            ['GET',   '/plants/energy/{ids}',                'propietario',    'SolarEdge: varias plantas.'],
            ['GET',   '/plant/grafica/bateria/{id}',         'propietario',    'SolarEdge.'],
            ['GET',   '/plant/grafica/comparacion/{id}',     'propietario',    'SolarEdge.'],
        ];
        foreach ($filas as $f) {
            $colorMet = [
                'GET' => 'text-green-500', 'POST' => 'text-yellow-500',
                'PUT' => 'text-blue-500', 'DELETE' => 'text-red-500',
            ][$f[0]] ?? '';
            echo '<tr>';
            echo '<td class="p-2 border ' . $borde . ' font-mono ' . $colorMet . '">' . $f[0] . '</td>';
            echo '<td class="p-2 border ' . $borde . ' font-mono">' . htmlspecialchars($f[1]) . '</td>';
            echo '<td class="p-2 border ' . $borde . '">' . htmlspecialchars($f[2]) . '</td>';
            echo '<td class="p-2 border ' . $borde . '">' . htmlspecialchars($f[3]) . '</td>';
            echo '</tr>';
        }
        ?>
        </tbody>
    </table>
    <p class="<?php echo $no; ?> text-xs mt-1">* propietario = admin, o usuario con esa planta asociada.</p>
    </div>

    <h2 class="text-xl font-semibold <?php echo $sub; ?> mb-3">Que ofrece cada proveedor</h2>
    <p class="<?php echo $texto; ?> mb-3">
        No todos los proveedores exponen todos los datos. Lo que un proveedor no ofrece
        responde <strong>404</strong> (no es un fallo, lo declara su adaptador).
    </p>
    <div class="overflow-x-auto mb-8">
    <table class="w-full text-sm border <?php echo $borde; ?> <?php echo $tablaBg; ?>">
        <thead class="<?php echo $thBg; ?>">
            <tr>
                <th class="text-left p-2 border <?php echo $borde; ?>">Proveedor</th>
                <?php foreach (['plants','details','realtime','graficas','inventario','alertas','benefits','overview'] as $c) {
                    echo '<th class="p-2 border ' . $borde . ' text-center">' . $c . '</th>';
                } ?>
            </tr>
        </thead>
        <tbody class="<?php echo $texto; ?>">
        <?php
        // true = lo ofrece, false = 404. Coincide con lo que declaran los adaptadores.
        $matriz = [
            'GoodWe'        => [1,1,1,1,1,0,0,0],
            'SolarEdge'     => [1,1,1,1,1,0,1,1],
            'VictronEnergy' => [1,1,1,1,1,1,0,0],
            'Sungrow'       => [1,1,1,1,1,1,1,0],
            'Sigenergy'     => [1,1,1,1,1,1,0,0],
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

    <p class="<?php echo $no; ?> text-xs">
        Verificado el 20/07/2026: ~70 comprobaciones de endpoints y 90 de las suites de
        integracion, en verde. Ver <code>testing/integracion/</code>.
    </p>
</div>
