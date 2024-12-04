<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
            POST /plants/graficas
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('asociar_plantas_usuarios.descripcion'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.parametros_consulta'); ?>
        </h2>
        <ul class="list-disc list-inside <?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <li><strong>idproveedor</strong> <?php echo translate('asociar_plantas_usuarios.proveedor'); ?></li>
        </ul>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.parametros_consulta'); ?>
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
/*{ GoodWe
    "id": "b5e7ad84-679f-4b99-a238-912631598450",
    "date": "2024-11-11", //fecha en la que sacas el grafico
    "range": "dia", // 2 dia 3 mes y 4 año
    "chartIndexId": "generacion de energia y ingresos" //Depende del grafico cambian los datos que se le pasan
}*/
/*
{Goodwe
    "id": "b5e7ad84-679f-4b99-a238-912631598450",
    "date": "2024-11-21", //fecha en la que sacas el grafico
    "chartIndexId": "potencia" //Depende del grafico cambian los datos que se le pasan
}
/*
/*SolarEdge
{
    "id": "1851069",
    "dia": "DAY", //dia mes o año que quieres que te saque
    "fechaFin":"2024-11-19", //parametro opcional si no se le manda se le pasara la fecha de hoy a las 23:59:59
    "fechaInicio":"2024-11-18"  //parametro opcional si no se envia se recogera en DAY principio del dia actual Month dia 1 del mes actual o YEAR primer dia del año actual
}
*/
/*Grafica de Victron Energy
{
    "id": "98081",
    "interval":"15mins", //15mins hours 2hours days weeks months years
    "type": "venus", //venus live_feed consumption solar_yield kwh generator generator-runtime custom forecast
    "fechaFin":"2024-11-25", //parametro opcional si no se le manda se le pasara la fecha de hoy a las 23:59:59
    "fechaInicio":"2024-11-24"  //parametro opcional si no se le manda se le pasara la fecha de hoy a las 00:00:00
}
Documentacion de VictronEnergy https://vrm-api-docs.victronenergy.com/#/operations/installations/idSite/stats
*/
        </pre>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            Respuesta de Ejemplo
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto">
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
        <div class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-800 text-white'; ?> rounded-lg p-4 relative">
            <h2 class="text-lg font-semibold mb-2">
                <?php echo translate('usuarios_post.respuesta_ejemplo'); ?>
            </h2>
            <pre class="text-sm overflow-auto mb-4">
curl -X GET "https://app-energiasolarcanarias-backend.com/usuarios/relacionar?idplanta=1851069&idusuario=20&proveedor=1" \
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
        const codigo = `curl -X GET "https://app-energiasolarcanarias-backend.com/usuarios/relacionar?idplanta=1851069&idusuario=20&proveedor=1" \\
-H "Authorization: Bearer tu_token_de_acceso"`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>
