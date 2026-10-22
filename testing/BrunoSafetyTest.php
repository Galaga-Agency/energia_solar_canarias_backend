<?php

use PHPUnit\Framework\TestCase;

final class BrunoSafetyTest extends TestCase
{
    /** @return array<string, array{method: string, url: string, contents: string}> */
    private function requests(string $folder): array
    {
        $files = glob(__DIR__ . '/../bruno/' . $folder . '/*.bru') ?: [];
        $requests = [];
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, 'No se pudo leer ' . basename($file));
            if (!preg_match('/(?m)^(get|post|put|delete|patch) \{\s*\R\s*url:\s*([^\r\n]+)/', $contents, $matches)) {
                continue;
            }
            $requests[basename($file)] = [
                'method' => strtoupper($matches[1]),
                'url' => trim($matches[2]),
                'contents' => $contents,
            ];
        }

        return $requests;
    }

    public function testEveryBackendRequestIsRestrictedToLocalhost(): void
    {
        $requests = $this->requests('ESC Backend');
        self::assertCount(67, $requests, 'Revisa el inventario de peticiones Bruno del backend.');

        foreach ($requests as $name => $request) {
            self::assertStringContainsString('script:pre-request', $request['contents'], $name);
            self::assertStringContainsString("bru.getEnvVar('base_url_esc')", $request['contents'], $name);
            self::assertStringContainsString('localhost|127\\.0\\.0\\.1|\\[::1\\]', $request['contents'], $name);
            self::assertStringContainsString('if (!isLocal)', $request['contents'], $name);
        }
    }

    public function testZohoAndUserMutationRequestsRequireZohoToBeDisabled(): void
    {
        $rutasProtegidas = [
            'POST /usuarios',
            'PUT /usuario',
            'PUT /usuarios',
            'PUT /usuarios/{{esc_user_id}}',
            'DELETE /usuario',
            'DELETE /usuarios/{{esc_user_id}}',
            'DELETE /usuarios?idApp={{esc_zoho_id_app}}',
            'GET /zoho/actualizarDatosPlantas',
            'POST /zoho/crearCliente',
            'PUT /zoho/actualizarCliente',
            'DELETE /zoho/eliminarCliente/{{esc_zoho_customer_id}}',
        ];
        $requests = $this->requests('ESC Backend');
        $rutasEncontradas = [];

        foreach ($requests as $name => $request) {
            $ruta = $request['method'] . ' ' . substr($request['url'], strlen('{{base_url_esc}}'));
            if (!in_array($ruta, $rutasProtegidas, true)) {
                continue;
            }
            $rutasEncontradas[] = $ruta;
            self::assertStringContainsString('esc_zoho_desactivado_confirmado', $request['contents'], $name);
            self::assertStringContainsString('ZOHO_DESACTIVADO=1', $request['contents'], $name);
        }

        self::assertEqualsCanonicalizing($rutasProtegidas, $rutasEncontradas);
    }

    public function testDirectZohoWritesRequireAnExplicitOptIn(): void
    {
        $requests = $this->requests('Zoho CRM');
        $writes = [];
        foreach ($requests as $name => $request) {
            if (!in_array($request['method'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                continue;
            }
            if (!str_contains($request['url'], '{{zoho_api_domain}}')) {
                continue;
            }
            $writes[] = $name;
            if (str_contains($request['contents'], 'zoho_permitir_escritura')) {
                continue;
            }
            self::fail($name . ' modifica Zoho sin una confirmación explícita.');
        }

        self::assertNotEmpty($writes, 'Mantén una cobertura de las escrituras directas a Zoho.');
    }
}
