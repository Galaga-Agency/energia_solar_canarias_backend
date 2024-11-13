<div class="flex flex-col md:flex-row justify-between">
    <!-- Descripción del Endpoint -->
    <div class="w-full md:w-7/12 p-4">
        <h1 class="text-3xl font-bold text-blue-600 mb-4">GET /plants/details/{id}</h1>
        <p class="text-gray-700 mb-4">
        <?php echo translate('get_detalles_planta.descripcion')?>
        </p>
        <h2 class="text-xl font-semibold text-gray-800 mb-2"><?php echo translate('usuarios_get.respuesta_ejemplo') ?></h2>
        <pre class="bg-gray-100 rounded-lg p-4 text-sm text-gray-900 overflow-auto">
        {
    "status": true,
    "code": 200,
    "message": "200 - Solicitud exitosa",
    "data": [
        {
            "organization": "Whatever",
            "id": "identificador",
            "name": "Whatever",
            "accountId": "",
            "status": "working",
            "peakPower": 0,
            "lastUpdateTime": "0000-00-00",
            "installationDate": "0000-00-00",
            "ptoDate": "",
            "notes": "",
            "type": "",
            "location": "Whatever place, Madrid, Spain",
            "batteryCapacity": null,
            "orgCode": null,
            "kpi": {
                "monthGeneration": null,
                "pac": null,
                "power": null,
                "totalPower": null,
                "dayIncome": null,
                "yieldRate": null,
                "currency": null
            },
            "alertQuantity": "",
            "highestImpact": "",
            "primaryModule": {
                "manufacturerName": "name",
                "modelName": "things",
                "maximumPower": 0,
                "temperatureCoef": 0
            },
            "isEvcharge": null,
            "isTigo": null,
            "isPowerflow": null,
            "isSec": null,
            "isGenset": null,
            "isMicroInverter": null,
            "hasLayout": null,
            "layout_id": null,
            "isMeter": null,
            "isEnvironmental": null,
            "powercontrol_status": null,
            "chartsTypesByPlant": null
        }
    ]
}
        </pre>
    </div>

    <!-- Componente de Código Copiable -->
    <div class="w-full md:w-4/12 mt-6 md:mt-0 p-4">
        <div class="bg-gray-800 text-white rounded-lg p-4 relative">
            <h2 class="text-lg font-semibold mb-2"> <?php echo translate('usuarios_get.ejemplo_uso') ?></h2>
            <pre class="text-sm overflow-auto mb-4">
curl -X GET "https://app-energiasolarcanarias-backend.com/plants/details/{id}" \
-H "Authorization: Bearer tu_token_de_acceso"
            </pre>
            <button
                class="absolute top-2 right-2 bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition"
                onclick="copiarCodigo()"
            >
                Copiar
            </button>
        </div>
    </div>
</div>

<script>
    function copiarCodigo() {
        const codigo = `curl -X GET "https://app-energiasolarcanarias-backend.com/plants/details/{id}" \\
-H "Authorization: Bearer tu_token_de_acceso"`;
        navigator.clipboard.writeText(codigo).then(() => {
            alert('Código copiado al portapapeles');
        }).catch(err => {
            alert('Error al copiar el código');
        });
    }
</script>
