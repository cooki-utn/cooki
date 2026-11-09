<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

use Cooki\Ia\ClienteGemini;
use Cooki\Ia\ErrorIa;
use Cooki\Ia\Instrucciones;
use PHPUnit\Framework\TestCase;

/**
 * Cliente de Gemini contra el Gemini de mentira (servidor-ia-falso.php): qué manda, cómo lee la respuesta y
 * cómo informa cada error. No usa la IA real ni la clave.
 */
final class ClienteGeminiTest extends TestCase
{
    private string $registro = '';

    protected function setUp(): void
    {
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('Falta la extensión cURL.');
        }
        $this->registro = ServidorIaFalso::nuevoRegistro();
    }

    private function cliente(string $modo, int $timeout = 5): ClienteGemini
    {
        return new ClienteGemini('clave-de-prueba', 'modelo-x', ServidorIaFalso::url($modo, $this->registro), $timeout);
    }

    private function pedir(string $modo, int $timeout = 5): \Cooki\Ia\RespuestaIa
    {
        return $this->cliente($modo, $timeout)
            ->generarJson(Instrucciones::receta(), Instrucciones::pedido(['a' => 1]), Instrucciones::esquemaReceta());
    }

    private function error(string $modo, int $timeout = 5): ErrorIa
    {
        try {
            $this->pedir($modo, $timeout);
        } catch (ErrorIa $e) {
            return $e;
        }
        $this->fail('Tenía que fallar con el modo ' . $modo);
    }

    public function testDevuelveElJsonConTokensYMandaLoPedido(): void
    {
        $respuesta = $this->pedir('ok');
        $this->assertSame('Revuelto de zapallitos', $respuesta->datos['titulo']);
        $this->assertSame(321, $respuesta->tokensEntrada);
        $this->assertSame(210, $respuesta->tokensSalida);

        $pedidos = ServidorIaFalso::pedidos($this->registro);
        $this->assertCount(1, $pedidos);
        $this->assertSame('models/modelo-x:generateContent', $pedidos[0]['ruta']);
        $this->assertSame('clave-de-prueba', $pedidos[0]['clave'], 'La clave va en el encabezado x-goog-api-key');
        $config = $pedidos[0]['pedido']['generationConfig'];
        $this->assertSame('application/json', $config['responseMimeType']);
        $this->assertSame(['thinkingLevel' => 'minimal'], $config['thinkingConfig']);
        $this->assertArrayNotHasKey('temperature', $config);
        $this->assertStringContainsString('Cooki', $pedidos[0]['pedido']['systemInstruction']['parts'][0]['text']);
    }

    public function testSiElModeloNoAceptaElPensamientoMinimoRepiteSinEl(): void
    {
        $this->assertSame('Revuelto de zapallitos', $this->pedir('pensamiento')->datos['titulo']);
        $pedidos = ServidorIaFalso::pedidos($this->registro);
        $this->assertCount(2, $pedidos);
        $this->assertArrayNotHasKey('thinkingConfig', $pedidos[1]['pedido']['generationConfig']);
    }

    public function testInformaCadaErrorConSuMotivo(): void
    {
        $this->assertSame(ErrorIa::CUPO, $this->error('429')->motivo);
        $modelo = $this->error('404');
        $this->assertSame(ErrorIa::MODELO, $modelo->motivo);
        $this->assertStringContainsString('cambiá IA_MODELO', $modelo->getMessage());
        $clave = $this->error('clave');
        $this->assertSame(ErrorIa::CLAVE, $clave->motivo);
        $this->assertStringNotContainsString('clave-de-prueba', $clave->getMessage());
        $this->assertSame(ErrorIa::FORMATO, $this->error('basura')->motivo);
        $this->assertSame('La IA no está disponible ahora. El resto del sitio anda igual.', $clave->paraUsuario());
    }

    public function testCortaCuandoTardaDemasiado(): void
    {
        $inicio = microtime(true);
        $error = $this->error('lento', 1);
        $this->assertSame(ErrorIa::TIEMPO, $error->motivo);
        $this->assertLessThan(2.5, microtime(true) - $inicio);
        $this->assertStringContainsString('tardó demasiado', $error->paraUsuario());
    }

    public function testSinServidorEsUnErrorDeConexion(): void
    {
        $cliente = new ClienteGemini('x', 'modelo-x', 'http://127.0.0.1:1', 2);
        try {
            $cliente->generarJson('a', 'b', Instrucciones::esquemaReemplazo());
            $this->fail('Tenía que fallar');
        } catch (ErrorIa $e) {
            $this->assertSame(ErrorIa::CONEXION, $e->motivo);
        }
    }
}
