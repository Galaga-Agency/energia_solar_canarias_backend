<?php

/** @var string $theme Tema activo ('dark'|'light'); lo define index.php, que incluye esta pagina. */
?>
<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12">
        <h1 class="text-3xl font-bold <?php echo $theme === 'dark' ? 'text-blue-400' : 'text-blue-600'; ?> mb-4">
            POST /plants/graficas
        </h1>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <?php echo translate('post_graficas.endpoint_description'); ?>
        </p>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('usuarios_get.parametros_consulta'); ?>
        </h2>
        <ul class="list-disc list-inside <?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-4">
            <li><strong>proveedor</strong> <?php echo translate('asociar_plantas_usuarios.proveedor'); ?></li>
        </ul>
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            <?php echo translate('post_graficas.parameters_title'); ?>
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
<span class="text-green-500">/* GoodWe */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"b5e7ad84-679f-4b99-a238-912631598450"</span>,
    <span class="text-blue-500">"date"</span>: <span class="text-yellow-500">"2024-11-11"</span>, <span class="text-green-500">// fecha en la que sacas el gráfico</span>
    <span class="text-blue-500">"range"</span>: <span class="text-yellow-500">"dia"</span>, <span class="text-green-500">// 2 dia 3 mes y 4 año</span>
    <span class="text-blue-500">"chartIndexId"</span>: <span class="text-yellow-500">"generación de energía y ingresos"</span> <span class="text-green-500">// Depende del gráfico cambian los datos que se le pasan</span>
}
<span class="text-green-500">/* GoodWe */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"b5e7ad84-679f-4b99-a238-912631598450"</span>,
    <span class="text-blue-500">"date"</span>: <span class="text-yellow-500">"2024-11-21"</span>, <span class="text-green-500">// fecha en la que sacas el gráfico</span>
    <span class="text-blue-500">"chartIndexId"</span>: <span class="text-yellow-500">"potencia"</span> <span class="text-green-500">// Depende del gráfico cambian los datos que se le pasan</span>
}
<span class="text-green-500">/* SolarEdge */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"1851069"</span>,
    <span class="text-blue-500">"dia"</span>: <span class="text-yellow-500">"DAY"</span>, <span class="text-green-500">// dia mes o año que quieres que te saque</span>
    <span class="text-blue-500">"fechaFin"</span>: <span class="text-yellow-500">"2024-11-19"</span>, <span class="text-green-500">// parametro opcional si no se le manda se le pasara la fecha de hoy a las 23:59:59</span>
    <span class="text-blue-500">"fechaInicio"</span>: <span class="text-yellow-500">"2024-11-18"</span> <span class="text-green-500">// parametro opcional si no se envia se recogera en DAY principio del dia actual Month dia 1 del mes actual o YEAR primer dia del año actual</span>
}
<span class="text-green-500">/* Grafica de Victron Energy */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"98081"</span>,
    <span class="text-blue-500">"interval"</span>: <span class="text-yellow-500">"15mins"</span>, <span class="text-green-500">// 15mins hours 2hours days weeks months years</span>
    <span class="text-blue-500">"type"</span>: <span class="text-yellow-500">"venus"</span>, <span class="text-green-500">// venus live_feed consumption solar_yield kwh generator generator-runtime custom forecast</span>
    <span class="text-blue-500">"fechaFin"</span>: <span class="text-yellow-500">"2024-11-25"</span>, <span class="text-green-500">// parametro opcional si no se le manda se le pasara la fecha de hoy a las 23:59:59</span>
    <span class="text-blue-500">"fechaInicio"</span>: <span class="text-yellow-500">"2024-11-24"</span> <span class="text-green-500">// parametro opcional si no se le manda se le pasara la fecha de hoy a las 00:00:00</span>
}
<span class="text-green-500">/* Grafica de Victron Energy overallstats */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"98081"</span>,
    <span class="text-blue-500">"type"</span>: <span class="text-yellow-500">"venus"</span>, <span class="text-green-500">// venus live_feed consumption solar_yield kwh generator generator-runtime custom forecast</span>
    <span class="text-blue-500">"overallstats"</span>: <span class="text-yellow-500">true</span>, <span class="text-green-500">// true or false</span>
}
<span class="text-green-500">/* Sungrow Day/Custom (intradia por minuto) --- id = ps_id NUMERICO */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"1234567"</span>, <span class="text-green-500">// ps_id NUMERICO (no el systemId VSSKC.../EUEEH..., eso es Sigenergy)</span>
    <span class="text-blue-500">"level"</span>: <span class="text-yellow-500">"Day"</span>, <span class="text-green-500">// Day | Custom | Week | Month | Year. Por defecto Day</span>
    <span class="text-blue-500">"points"</span>: <span class="text-yellow-500">"p24,p18,p21"</span>, <span class="text-green-500">// una o varias medidas (csv o array). Por defecto p24</span>
    <span class="text-blue-500">"interval"</span>: <span class="text-yellow-500">5</span>, <span class="text-green-500">// solo intradia. 5 | 15 | 30 | 60 min. Por defecto 5</span>
    <span class="text-blue-500">"fechaInicio"</span>: <span class="text-yellow-500">"20260722000000"</span>, <span class="text-green-500">// solo Custom, formato YmdHis. La ventana se capa a 24h (bloques de 2h)</span>
    <span class="text-blue-500">"fechaFin"</span>: <span class="text-yellow-500">"20260722235959"</span> <span class="text-green-500">// solo Custom, formato YmdHis. Por defecto ahora</span>
}
<span class="text-green-500">/* Sungrow Week/Month/Year (agregado dia/mes) */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"1234567"</span>,
    <span class="text-blue-500">"level"</span>: <span class="text-yellow-500">"Week"</span>, <span class="text-green-500">// Week/Month -> un punto por dia; Year -> un punto por mes</span>
    <span class="text-blue-500">"points"</span>: <span class="text-yellow-500">"p24,p14"</span>,
    <span class="text-blue-500">"date"</span>: <span class="text-yellow-500">"2026-07-22"</span> <span class="text-green-500">// opcional, fecha de referencia (yyyy-mm-dd). Por defecto hoy</span>
}
<span class="text-green-500">/* Puntos (points) mas usados: p24=potencia activa(W) [confirmado], p14=potencia DC(W),</span>
<span class="text-green-500">   p18/p19/p20=voltaje fase A/B/C(V), p21/p22/p23=corriente fase A/B/C(A), p44=voltaje MPPT(V).</span>
<span class="text-green-500">   OJO: los inversores HIBRIDOS (con bateria) devuelven serie VACIA por el plan de API de</span>
<span class="text-green-500">   Sungrow; hoy solo dan datos los inversores normales (tipo 1). */</span>
<span class="text-green-500">/* Sigenergy --- el id es el systemId (VSSKC.../EUEEH...), NO numerico */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"VSSKC1768221900"</span>, <span class="text-green-500">// systemId de la planta Sigenergy (empieza por VSSKC.../EUEEH...)</span>
    <span class="text-blue-500">"level"</span>: <span class="text-yellow-500">"Lifetime"</span> <span class="text-green-500">// opcional. Day | Week | Month | Year | Lifetime. Por defecto Day. Lifetime ignora la fecha y casi siempre responde</span>
}
<span class="text-green-500">/* Sigenergy con fecha (Day/Week/Month/Year) */</span>
{
    <span class="text-blue-500">"id"</span>: <span class="text-yellow-500">"VSSKC1768221900"</span>,
    <span class="text-blue-500">"level"</span>: <span class="text-yellow-500">"Day"</span>, <span class="text-green-500">// Day | Week | Month | Year | Lifetime</span>
    <span class="text-blue-500">"date"</span>: <span class="text-yellow-500">"2026-07-22"</span> <span class="text-green-500">// opcional, yyyy-MM-dd. Por defecto hoy. LIMITE: 1 consulta por estacion cada 5 min</span>
}
</pre>

        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2">
            Respuesta de Ejemplo
        </h2>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
{
    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">true</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">200</span>,
    <span class="text-blue-500">"message"</span>: <span class="text-green-500">"200 - Solicitud exitosa"</span>,
    <span class="text-blue-500">"data"</span>: {
        <span class="text-blue-500">"consumption"</span>: [
            {
                <span class="text-blue-500">"date"</span>: <span class="text-green-500">"2024-11-18 00:00:00"</span>,
                <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">117869</span>
            },
            {
                <span class="text-blue-500">"date"</span>: <span class="text-green-500">"2024-11-19 00:00:00"</span>,
                <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">127128</span>
            }
        ],
        <span class="text-blue-500">"totalConsumption"</span>: <span class="text-yellow-500">244997</span>,
        <span class="text-blue-500">"solarProduction"</span>: [
            {
                <span class="text-blue-500">"date"</span>: <span class="text-green-500">"2024-11-18 00:00:00"</span>,
                <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">60023</span>
            },
            {
                <span class="text-blue-500">"date"</span>: <span class="text-green-500">"2024-11-19 00:00:00"</span>,
                <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">64201</span>
            }
        ],
        <span class="text-blue-500">"totalProduction"</span>: <span class="text-yellow-500">124224</span>,
        <span class="text-blue-500">"storagePower"</span>: [
            {
                <span class="text-blue-500">"nameplate"</span>: <span class="text-yellow-500">9800</span>,
                <span class="text-blue-500">"serialNumber"</span>: <span class="text-green-500">"7E043EDB"</span>,
                <span class="text-blue-500">"modelNumber"</span>: <span class="text-green-500">"LGC RESU 10"</span>,
                <span class="text-blue-500">"telemetryCount"</span>: <span class="text-yellow-500">1149</span>,
                <span class="text-blue-500">"telemetries"</span>: [
                    {
                        <span class="text-blue-500">"timeStamp"</span>: <span class="text-green-500">"2024-11-18 00:00:51"</span>,
                        <span class="text-blue-500">"power"</span>: <span class="text-yellow-500">0</span>,
                        <span class="text-blue-500">"batteryState"</span>: <span class="text-yellow-500">10</span>,
                        <span class="text-blue-500">"lifeTimeEnergyDischarged"</span>: <span class="text-yellow-500">13491068</span>,
                        <span class="text-blue-500">"lifeTimeEnergyCharged"</span>: <span class="text-yellow-500">9357134</span>,
                        <span class="text-blue-500">"batteryPercentageState"</span>: <span class="text-yellow-500">11</span>,
                        <span class="text-blue-500">"fullPackEnergyAvailable"</span>: <span class="text-yellow-500">7920</span>,
                        <span class="text-blue-500">"internalTemp"</span>: <span class="text-yellow-500">27.4</span>,
                        <span class="text-blue-500">"ACGridCharging"</span>: <span class="text-yellow-500">0</span>
                    }
                ]
            }
        ],
        <span class="text-blue-500">"totalExport"</span>: <span class="text-yellow-500">16259</span>,
        <span class="text-blue-500">"porcentajeExport"</span>: <span class="text-yellow-500">13.088453116950024</span>,
        <span class="text-blue-500">"overview"</span>: {
            <span class="text-blue-500">"lastUpdateTime"</span>: <span class="text-green-500">"2024-12-04 10:21:38"</span>,
            <span class="text-blue-500">"lifeTimeData"</span>: {
                <span class="text-blue-500">"energy"</span>: <span class="text-yellow-500">94705520</span>
            },
            <span class="text-blue-500">"lastYearData"</span>: {
                <span class="text-blue-500">"energy"</span>: <span class="text-yellow-500">27781930</span>
            },
            <span class="text-blue-500">"lastDayData"</span>: {
                <span class="text-blue-500">"energy"</span>: <span class="text-yellow-500">16459</span>
            }
        }
    }
}
</pre>

        <!-- Forma de la respuesta por proveedor -->
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2 mt-6">
            <?php echo translate('post_graficas.respuestas_proveedor_title'); ?>
        </h2>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-2">
            <?php echo translate('post_graficas.respuestas_proveedor_desc'); ?>
        </p>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
<span class="text-green-500">/* <?php echo translate('post_graficas.respuesta_sungrow_label'); ?> */</span>
{
    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">true</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">200</span>,
    <span class="text-blue-500">"data"</span>: {
        <span class="text-blue-500">"ps_id"</span>: <span class="text-yellow-500">"1234567"</span>,
        <span class="text-blue-500">"ps_key"</span>: <span class="text-green-500">"1234567_14_1_1"</span>, <span class="text-green-500">// inversor localizado (tipo 1 o 14)</span>
        <span class="text-blue-500">"level"</span>: <span class="text-yellow-500">"day"</span>,
        <span class="text-blue-500">"series"</span>: { <span class="text-green-500">// UNA serie por punto pedido (multipunto)</span>
            <span class="text-blue-500">"p24"</span>: [
                { <span class="text-blue-500">"time"</span>: <span class="text-green-500">"20260722080000"</span>, <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">1523.0</span> },
                { <span class="text-blue-500">"time"</span>: <span class="text-green-500">"20260722080500"</span>, <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">1710.5</span> }
            ],
            <span class="text-blue-500">"p18"</span>: [ { <span class="text-blue-500">"time"</span>: <span class="text-green-500">"20260722080000"</span>, <span class="text-blue-500">"value"</span>: <span class="text-yellow-500">225.6</span> } ]
        }
    }
}
<span class="text-green-500">// Compat: si pides un solo "point" (en vez de "points"), data trae {point, series:[...]} plano.</span>
<span class="text-green-500">/* <?php echo translate('post_graficas.respuesta_sigenergy_label'); ?> */</span>
{
    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">true</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">200</span>,
    <span class="text-blue-500">"data"</span>: {
        <span class="text-blue-500">"powerGeneration"</span>: <span class="text-yellow-500">42.7</span>, <span class="text-green-500">// totales del periodo (kWh)</span>
        <span class="text-blue-500">"powerToGrid"</span>: <span class="text-yellow-500">18.3</span>,
        <span class="text-blue-500">"powerSelfConsumption"</span>: <span class="text-yellow-500">24.4</span>,
        <span class="text-blue-500">"itemList"</span>: [ <span class="text-green-500">// cada punto (Day = 288 de 5 min)</span>
            {
                <span class="text-blue-500">"dataTime"</span>: <span class="text-green-500">"2026-07-22 08:00:00"</span>,
                <span class="text-blue-500">"pvTotalPower"</span>: <span class="text-yellow-500">1523.0</span>,
                <span class="text-blue-500">"loadPower"</span>: <span class="text-yellow-500">640.0</span>,
                <span class="text-blue-500">"toGridPower"</span>: <span class="text-yellow-500">880.0</span>,
                <span class="text-blue-500">"batSoc"</span>: <span class="text-yellow-500">73</span>
            }
        ],
        <span class="text-blue-500">"_cache"</span>: { <span class="text-blue-500">"hit"</span>: <span class="text-yellow-500">false</span>, <span class="text-blue-500">"esperar_seg"</span>: <span class="text-yellow-500">0</span> } <span class="text-green-500">// límite 1/5min por estación</span>
    }
}
</pre>

        <!-- Respuestas de error -->
        <h2 class="text-xl font-semibold <?php echo $theme === 'dark' ? 'text-gray-400' : 'text-gray-800'; ?> mb-2 mt-6">
            <?php echo translate('post_graficas.errores_title'); ?>
        </h2>
        <p class="<?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-700'; ?> mb-2">
            <?php echo translate('post_graficas.errores_desc'); ?>
        </p>
        <pre class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-100 text-gray-900'; ?> rounded-lg p-4 text-sm overflow-auto font-mono">
<span class="text-green-500">/* Sungrow: id cruzado o planta sin inversor (HTTP 400) */</span>
{
    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">false</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">400</span>,
    <span class="text-blue-500">"message"</span>: <span class="text-green-500">"No se han encontrado graficas de Sungrow"</span>,
    <span class="text-blue-500">"data"</span>: { <span class="text-blue-500">"error"</span>: <span class="text-green-500">"no_inversor"</span>, <span class="text-blue-500">"proveedor"</span>: <span class="text-green-500">"Sungrow"</span>, <span class="text-blue-500">"ps_id"</span>: <span class="text-green-500">"EUEEH1768404593"</span> }
}
<span class="text-green-500">/* Sigenergy: planta sin datos servibles, code 13001 (HTTP 404) */</span>
{
    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">false</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">404</span>,
    <span class="text-blue-500">"message"</span>: <span class="text-green-500">"La planta no tiene datos disponibles"</span>,
    <span class="text-blue-500">"data"</span>: {
        <span class="text-blue-500">"proveedor"</span>: <span class="text-green-500">"Sigenergy"</span>,
        <span class="text-blue-500">"codigo_sigenergy"</span>: <span class="text-yellow-500">13001</span>,
        <span class="text-blue-500">"reintentable"</span>: <span class="text-yellow-500">false</span>, <span class="text-green-500">// se cachea: no gasta el cupo de 5 min</span>
        <span class="text-blue-500">"documentado"</span>: <span class="text-yellow-500">false</span>
    }
}
<span class="text-green-500">/* Sigenergy: llamaste antes de los 5 min, code 1201 (HTTP 429) */</span>
{
    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">false</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">429</span>,
    <span class="text-blue-500">"message"</span>: <span class="text-green-500">"Demasiado pronto: espera unos minutos"</span>,
    <span class="text-blue-500">"data"</span>: {
        <span class="text-blue-500">"proveedor"</span>: <span class="text-green-500">"Sigenergy"</span>,
        <span class="text-blue-500">"codigo_sigenergy"</span>: <span class="text-yellow-500">1201</span>,
        <span class="text-blue-500">"reintentable"</span>: <span class="text-yellow-500">true</span>,
        <span class="text-blue-500">"_cache"</span>: { <span class="text-blue-500">"esperar_seg"</span>: <span class="text-yellow-500">315</span> } <span class="text-green-500">// cuánto falta para reintentar</span>
    }
}
<span class="text-green-500">/* Sigenergy: id inexistente o de otra cuenta, code 1111 (HTTP 404) */</span>
{
    <span class="text-blue-500">"status"</span>: <span class="text-yellow-500">false</span>,
    <span class="text-blue-500">"code"</span>: <span class="text-yellow-500">404</span>,
    <span class="text-blue-500">"message"</span>: <span class="text-green-500">"Planta no encontrada o sin acceso"</span>,
    <span class="text-blue-500">"data"</span>: { <span class="text-blue-500">"proveedor"</span>: <span class="text-green-500">"Sigenergy"</span>, <span class="text-blue-500">"codigo_sigenergy"</span>: <span class="text-yellow-500">1111</span> }
}
</pre>

        <!-- Acceso y limites -->
        <div class="<?php echo $theme === 'dark' ? 'bg-yellow-900/30 border-yellow-600 text-yellow-200' : 'bg-yellow-50 border-yellow-500 text-yellow-800'; ?> border-l-4 rounded p-4 mt-6">
            <p class="font-semibold mb-1">⚠️ <?php echo translate('post_graficas.acceso_title'); ?></p>
            <p class="text-sm"><?php echo translate('post_graficas.acceso_desc'); ?></p>
        </div>

    </div>

    <!-- Componente de Código Copiable -->
    <div class="w-full md:w-4/12 mt-6 md:mt-0">
        <div class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-gray-300' : 'bg-gray-800 text-white'; ?> rounded-lg p-4 relative">
            <h2 class="text-lg font-semibold mb-2">
                <?php echo translate('usuarios_post.respuesta_ejemplo'); ?>
            </h2>
            <pre class="text-sm overflow-auto mb-4">
curl -X POST "https://app-backend.energiasolarcanarias.com/plants/graficas?proveedor=solaredge" \
-H "Authorization: Bearer tu_token_de_acceso" \
-H "Content-Type: application/json" \
-d '{
    "id": "1851069",
    "proveedor": "SolarEdge",
    "dia": "DAY",
    "fechaInicio": "2024-11-18",
    "fechaFin": "2024-11-19"
}'
            </pre>
            <button
                class="absolute top-2 right-2 <?php echo $theme === 'dark' ? 'bg-blue-400 hover:bg-blue-500' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white px-3 py-1 rounded transition"
                onclick="copiarCodigo()">
                <?php echo translate('copiar'); ?>
            </button>
        </div>
        </pre>
        <h3 class="text-md font-semibold mt-4">
            <?php echo translate('post_graficas.parameters_title'); ?>
        </h3>
        <ul class="list-disc pl-5 text-sm <?php echo $theme === 'dark' ? 'text-gray-300' : 'text-gray-900'; ?>">
            <li><strong><?php echo translate('post_graficas.parameters_list_id_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_id_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_proveedor_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_proveedor_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_dia_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_dia_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_fechaInicio_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_fechaInicio_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_fechaFin_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_fechaFin_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_chartIndexId_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_chartIndexId_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_range_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_range_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_interval_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_interval_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_type_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_type_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_overallstats_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_overallstats_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_point_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_point_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_points_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_points_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_level_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_level_description'); ?></li>
            <li><strong><?php echo translate('post_graficas.parameters_list_date_name'); ?>:</strong> <?php echo translate('post_graficas.parameters_list_date_description'); ?></li>
            <li class="mt-2 <?php echo $theme === 'dark' ? 'text-yellow-400' : 'text-yellow-700'; ?>"><strong>⚠️ <?php echo translate('post_graficas.nota_ids_name'); ?>:</strong> <?php echo translate('post_graficas.nota_ids_description'); ?></li>
        </ul>

    </div>
</div>
</div>
</div>

<script>
    function copiarCodigo() {
        const codigo = `curl -X POST "https://app-backend.energiasolarcanarias.com/plants/graficas?proveedor=solaredge" \
-H "Authorization: Bearer tu_token_de_acceso" \
-H "Content-Type: application/json" \
-d '{
    "id": "1851069",
    "dia": "DAY",
    "fechaInicio": "2024-11-18",
    "fechaFin": "2024-11-19"
}'`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>