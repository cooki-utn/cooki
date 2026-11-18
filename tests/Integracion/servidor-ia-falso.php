<?php

/**
 * Gemini de mentira para las pruebas: ninguna prueba llama a la IA real ni necesita la clave.
 * Es el router de un `php -S`. Las pruebas ponen IA_URL=http://127.0.0.1:<puerto>/<modo>/<registro>, así que llega:
 *   POST /<modo>/<registro>/models/<modelo>:generateContent
 *
 * Modos: ok · lento (tarda 3 s, para el tiempo agotado) · 429 (cupo) · 404 (modelo retirado) · basura (no es JSON)
 *        · pensamiento (400 si mandan thinkingConfig) · clave (400 "API key not valid") · error-ia (la IA dice que no).
 * Cada pedido se agrega como una línea JSON en <temp>/ia-falso-<registro>.log (para contar llamadas y revisar qué
 * se mandó). La respuesta sale de tests/Integracion/datos/ según el esquema: reemplazo, adaptada o receta.
 */

declare(strict_types=1);

$partes = explode('/', trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/'));
$modo = $partes[0] ?? 'ok';
$registro = preg_replace('/[^a-z0-9-]/', '', $partes[1] ?? 'x');
$cuerpo = (string) file_get_contents('php://input');
$pedido = json_decode($cuerpo, true) ?: [];

file_put_contents(
    sys_get_temp_dir() . "/ia-falso-{$registro}.log",
    json_encode([
        'modo' => $modo,
        'ruta' => implode('/', array_slice($partes, 2)),
        'clave' => $_SERVER['HTTP_X_GOOG_API_KEY'] ?? '',
        'pedido' => $pedido,
    ], JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND
);

header('Content-Type: application/json');
$error = function (int $codigo, string $mensaje): never {
    http_response_code($codigo);
    echo json_encode(['error' => ['code' => $codigo, 'message' => $mensaje]]);
    exit;
};

$propiedades = $pedido['generationConfig']['responseSchema']['properties'] ?? [];
$archivo = match (true) {
    isset($propiedades['reemplazo']) => 'gemini-reemplazo.json',
    isset($propiedades['cambios']) => 'gemini-adaptada.json',
    default => 'gemini-receta.json',
};
$texto = (string) file_get_contents(__DIR__ . '/datos/' . $archivo);

switch ($modo) {
    case 'lento':
        sleep(3);
        break;
    case '429':
        $error(429, 'Resource has been exhausted (e.g. check quota).');
        // no break: $error termina
    case '404':
        $error(404, 'models/x is not found for API version v1beta.');
        // no break
    case 'clave':
        $error(400, 'API key not valid. Please pass a valid API key.');
        // no break
    case 'pensamiento':
        if (isset($pedido['generationConfig']['thinkingConfig'])) {
            $error(400, 'Thinking level is not supported for this model.');
        }
        break;
    case 'basura':
        $texto = 'Acá tenés una receta riquísima: {titulo: ...';
        break;
    case 'error-ia':
        $texto = '{"error": "Con esos ingredientes no se puede armar una receta."}';
        break;
}

echo json_encode([
    'candidates' => [[
        'content' => ['role' => 'model', 'parts' => [['text' => $texto]]],
        'finishReason' => 'STOP',
    ]],
    'usageMetadata' => ['promptTokenCount' => 321, 'candidatesTokenCount' => 210, 'totalTokenCount' => 531],
], JSON_UNESCAPED_UNICODE);
