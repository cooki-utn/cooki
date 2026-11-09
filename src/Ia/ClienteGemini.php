<?php

declare(strict_types=1);

namespace Cooki\Ia;

use Cooki\Config;

/**
 * Cliente de la API de Gemini (Google AI Studio, plan gratuito) con cURL.
 *
 * - POST a {IA_URL}/models/{IA_MODELO}:generateContent con la clave en el encabezado x-goog-api-key
 *   (nunca en la dirección, para que no quede en ningún log).
 * - Pide la respuesta como JSON con un esquema (responseSchema) y el pensamiento al mínimo
 *   (thinkingLevel minimal), que en la prueba del hosting respondió en poco más de 1 s.
 *   Si el modelo no acepta ese campo (400), repite una vez sin él.
 * - No manda temperature, topP ni topK: Google recomienda los valores por defecto en los modelos 3.x.
 * - Sin reintentos si tardó: el hosting corta la página a los 20 s (riesgo R-13).
 */
final class ClienteGemini implements ProveedorIa
{
    private const MAX_TOKENS = 1500;

    public function __construct(
        private string $clave,
        private string $modelo,
        private string $url,
        private int $timeout = 12,
    ) {
    }

    public static function desdeConfig(): self
    {
        return new self(
            Config::get('IA_CLAVE'),
            Config::get('IA_MODELO', 'gemini-3.5-flash-lite'),
            Config::get('IA_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            max(1, (int) Config::get('IA_TIMEOUT', '12'))
        );
    }

    public function generarJson(string $instrucciones, string $pedido, array $esquema): RespuestaIa
    {
        if (!function_exists('curl_init')) {
            throw new ErrorIa(ErrorIa::CONEXION, 'Falta la extensión cURL de PHP.');
        }
        $inicio = microtime(true);
        [$codigo, $cuerpo] = $this->llamar($instrucciones, $pedido, $esquema, true);
        if ($codigo === 400 && stripos($cuerpo, 'thinking') !== false) {
            [$codigo, $cuerpo] = $this->llamar($instrucciones, $pedido, $esquema, false);
        }
        $milisegundos = (int) round((microtime(true) - $inicio) * 1000);

        if ($codigo !== 200) {
            throw $this->errorHttp($codigo, $cuerpo);
        }
        $datos = json_decode($cuerpo, true);
        if (!is_array($datos)) {
            throw new ErrorIa(ErrorIa::FORMATO, 'Gemini respondió algo que no es JSON.');
        }
        if (isset($datos['promptFeedback']['blockReason'])) {
            throw new ErrorIa(ErrorIa::FORMATO, 'Gemini bloqueó el pedido: ' . $datos['promptFeedback']['blockReason']);
        }
        $candidato = $datos['candidates'][0] ?? [];
        $texto = '';
        foreach ((array) ($candidato['content']['parts'] ?? []) as $parte) {
            if (is_array($parte) && empty($parte['thought']) && isset($parte['text'])) {
                $texto .= (string) $parte['text'];
            }
        }
        $fin = (string) ($candidato['finishReason'] ?? '');
        if ($fin === 'MAX_TOKENS') {
            throw new ErrorIa(ErrorIa::FORMATO, 'La respuesta de Gemini quedó cortada (MAX_TOKENS).');
        }
        $json = json_decode($texto, true);
        if (!is_array($json)) {
            $detalle = $fin !== '' && $fin !== 'STOP' ? " (finishReason {$fin})" : '';
            throw new ErrorIa(ErrorIa::FORMATO, 'El texto de Gemini no es el JSON pedido' . $detalle . '.');
        }
        $uso = $datos['usageMetadata'] ?? [];
        return new RespuestaIa(
            $json,
            isset($uso['promptTokenCount']) ? (int) $uso['promptTokenCount'] : null,
            isset($uso['candidatesTokenCount']) ? (int) $uso['candidatesTokenCount'] : null,
            $milisegundos
        );
    }

    /**
     * @param array<string, mixed> $esquema
     * @return array{0: int, 1: string} código HTTP y cuerpo
     */
    private function llamar(string $instrucciones, string $pedido, array $esquema, bool $pensamientoMinimo): array
    {
        $config = [
            'responseMimeType' => 'application/json',
            'responseSchema' => $esquema,
            'maxOutputTokens' => self::MAX_TOKENS,
        ];
        if ($pensamientoMinimo) {
            $config['thinkingConfig'] = ['thinkingLevel' => 'minimal'];
        }
        $cuerpo = [
            'systemInstruction' => ['parts' => [['text' => $instrucciones]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $pedido]]]],
            'generationConfig' => $config,
        ];
        $direccion = rtrim($this->url, '/') . '/models/' . rawurlencode($this->modelo) . ':generateContent';
        $ch = curl_init($direccion);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $this->clave],
            CURLOPT_POSTFIELDS => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        $respuesta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $numeroError = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($numeroError === CURLE_OPERATION_TIMEDOUT) {
            throw new ErrorIa(ErrorIa::TIEMPO, "Gemini no respondió en {$this->timeout} s.");
        }
        if ($respuesta === false || $numeroError !== 0) {
            throw new ErrorIa(ErrorIa::CONEXION, 'No se pudo conectar con Gemini: ' . mb_substr($error, 0, 150));
        }
        return [$codigo, (string) $respuesta];
    }

    private function errorHttp(int $codigo, string $cuerpo): ErrorIa
    {
        $datos = json_decode($cuerpo, true);
        $mensaje = is_array($datos) ? (string) ($datos['error']['message'] ?? '') : '';
        $mensaje = mb_substr(str_replace($this->clave, '***', $mensaje), 0, 150);
        return match (true) {
            $codigo === 429 => new ErrorIa(ErrorIa::CUPO, 'Gemini: cupo agotado (429). ' . $mensaje),
            $codigo === 404 => new ErrorIa(
                ErrorIa::MODELO,
                'El modelo configurado ya no existe: cambiá IA_MODELO en el .env (' . $this->modelo . ').'
            ),
            // Una clave inválida a veces vuelve como 400 "API key not valid"
            $codigo === 401, $codigo === 403, stripos($mensaje, 'API key') !== false => new ErrorIa(
                ErrorIa::CLAVE,
                "Gemini rechazó la clave ({$codigo}): revisá IA_CLAVE en el .env."
            ),
            default => new ErrorIa(ErrorIa::SERVIDOR, "Gemini respondió {$codigo}. {$mensaje}"),
        };
    }
}
