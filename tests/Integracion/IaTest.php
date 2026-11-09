<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

use Cooki\Db;
use Cooki\Dominio\RecetaIa;
use Cooki\Ia\ClienteGemini;
use Cooki\Ia\ErrorIa;
use Cooki\Ia\Ia;
use Cooki\Repositorios\IaRepositorio;
use Cooki\Repositorios\IngredienteRepositorio;
use Cooki\Repositorios\ListaRepositorio;
use Cooki\Repositorios\PreferenciasRepositorio;
use Cooki\Repositorios\UsuarioRepositorio;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * La IA de punta a punta (incremento 3 · HU-30 y HU-33) contra el Gemini de mentira: receta nueva, caché,
 * límites, errores amables, restricciones y privacidad. Ninguna prueba usa la IA real ni la clave.
 */
final class IaTest extends TestCase
{
    private PDO $db;
    private int $usuario = 0;
    private string $email = '';
    private string $registro = '';
    /** Un ingrediente propio con nombre único: así la caché de otras corridas no interfiere. */
    private string $unico = '';

    protected function setUp(): void
    {
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('Falta la extensión cURL.');
        }
        try {
            $this->db = Db::conexion();
        } catch (PDOException $e) {
            $this->markTestSkipped('Sin base de datos disponible: ' . $e->getMessage());
        }
        $this->email = 'ia-' . uniqid() . '@cooki.test';
        $this->usuario = (new UsuarioRepositorio($this->db))->crear('Martina Inventora', $this->email, 'Usuario1234');
        $this->unico = 'Hongo ' . bin2hex(random_bytes(4));
        $hongo = (new IngredienteRepositorio($this->db))->crearPropio($this->usuario, $this->unico, 1, 'g');
        // Zapallito que vence en 2 días, huevos y cebolla (y el hongo propio)
        $alta = $this->db->prepare(
            'INSERT INTO despensa_item (usuario_id, ingrediente_id, nivel, fecha_vencimiento, ubicacion)
             VALUES (?, ?, ?, ?, ?)'
        );
        $alta->execute([$this->usuario, 5, 1, date('Y-m-d', strtotime('+2 days')), 'heladera']);
        $alta->execute([$this->usuario, 15, 2, date('Y-m-d', strtotime('+15 days')), 'heladera']);
        $alta->execute([$this->usuario, 2, 2, null, 'alacena']);
        $alta->execute([$this->usuario, $hongo, 2, null, 'heladera']);
        $this->registro = ServidorIaFalso::nuevoRegistro();
    }

    protected function tearDown(): void
    {
        putenv('IA_LIMITE_USUARIO_DIA');
        putenv('IA_LIMITE_GLOBAL_DIA');
        if ($this->usuario) {
            $this->db->prepare('DELETE FROM despensa_item WHERE usuario_id = ?')->execute([$this->usuario]);
            $this->db->prepare('DELETE FROM usuario WHERE id = ?')->execute([$this->usuario]);
        }
    }

    private function ia(string $modo = 'ok'): Ia
    {
        $cliente = new ClienteGemini('clave-de-prueba', 'modelo-x', ServidorIaFalso::url($modo, $this->registro), 5);
        return new Ia($this->db, $cliente, microtime(true));
    }

    /** @return array{motivo: string, mensaje: string} */
    private function falla(callable $pedido): array
    {
        try {
            $pedido();
        } catch (ErrorIa $e) {
            return ['motivo' => $e->motivo, 'mensaje' => $e->paraUsuario()];
        }
        $this->fail('Tenía que fallar');
    }

    /** @return list<array<string, mixed>> los pedidos de hoy de esta persona */
    private function usos(): array
    {
        $consulta = $this->db->prepare('SELECT * FROM uso_ia WHERE usuario_id = ? ORDER BY id');
        $consulta->execute([$this->usuario]);
        return $consulta->fetchAll();
    }

    public function testRecetaNuevaConLoQueTieneYSinDatosPersonales(): void
    {
        $ia = $this->ia();
        $id = $ia->recetaNueva($this->usuario, ['porciones' => 3, 'minutos' => 15, 'tipo' => 'rapida']);
        $receta = (new IaRepositorio($this->db))->receta($this->usuario, $id);
        $this->assertSame('Revuelto de zapallitos', $receta['titulo']);
        $this->assertSame('nueva', $receta['tipo']);
        $this->assertFalse($receta['guardada']);
        $this->assertSame(5, $receta['contenido']['ingredientes'][0]['id']);
        $opciones = $receta['contenido']['pedido']['opciones'];
        $this->assertSame(['porciones' => 3, 'minutos' => 15, 'tipo' => 'rapida'], $opciones);

        $pedidos = ServidorIaFalso::pedidos($this->registro);
        $this->assertCount(1, $pedidos);
        $texto = $pedidos[0]['pedido']['contents'][0]['parts'][0]['text'];
        $this->assertStringContainsString('Zapallito', $texto);
        $this->assertStringContainsString('"vence_en_dias": 2', $texto);
        $this->assertStringContainsString('"minutos_max": 15', $texto);
        $this->assertStringNotContainsString('Martina', $texto, 'Nunca se manda el nombre');
        $this->assertStringNotContainsString($this->email, $texto, 'Nunca se manda el correo');

        $usos = $this->usos();
        $this->assertCount(1, $usos);
        $this->assertSame([1, 0, 321, 210], [(int) $usos[0]['ok'], (int) $usos[0]['desde_cache'],
            (int) $usos[0]['tokens_entrada'], (int) $usos[0]['tokens_salida']]);
        $this->assertSame(Ia::limiteUsuario() - 1, $ia->restantesHoy($this->usuario));
    }

    public function testLaMismaConsultaSaleDeLaCacheSinLlamarNiDescontar(): void
    {
        $ia = $this->ia();
        $opciones = ['porciones' => 2, 'minutos' => 30, 'tipo' => 'almuerzo_cena'];
        $primera = $ia->recetaNueva($this->usuario, $opciones);
        $segunda = $ia->recetaNueva($this->usuario, $opciones);
        $this->assertNotSame($primera, $segunda, 'Cada pedido es una receta de IA propia');
        $this->assertCount(1, ServidorIaFalso::pedidos($this->registro), 'La segunda vez no llama a la IA');
        $this->assertSame([0, 1], array_map(fn ($u) => (int) $u['desde_cache'], $this->usos()));
        $this->assertSame(Ia::limiteUsuario() - 1, $ia->restantesHoy($this->usuario));
    }

    public function testPedirOtraNoRepiteYNoSaleDeLaCache(): void
    {
        $ia = $this->ia();
        $ia->recetaNueva($this->usuario, []);
        $ia->recetaNueva($this->usuario, [], ['Revuelto de zapallitos']);
        $pedidos = ServidorIaFalso::pedidos($this->registro);
        $this->assertCount(2, $pedidos);
        $texto = $pedidos[1]['pedido']['contents'][0]['parts'][0]['text'];
        $this->assertStringContainsString('"Revuelto de zapallitos"', $texto);
    }

    public function testLimiteDiarioDeLaPersonaYDelSitio(): void
    {
        putenv('IA_LIMITE_USUARIO_DIA=1');
        $ia = $this->ia();
        $ia->recetaNueva($this->usuario, ['tipo' => 'dulce']);
        $error = $this->falla(fn () => $ia->recetaNueva($this->usuario, ['tipo' => 'rapida']));
        $this->assertSame(ErrorIa::LIMITE, $error['motivo']);
        $this->assertSame('Por hoy ya usaste tus 1 pedidos a la IA. Mañana podés pedir más.', $error['mensaje']);
        $this->assertCount(1, ServidorIaFalso::pedidos($this->registro), 'Con el límite no se llama a la IA');

        putenv('IA_LIMITE_USUARIO_DIA=50');
        putenv('IA_LIMITE_GLOBAL_DIA=' . (new IaRepositorio($this->db))->llamadasHoy());
        $error = $this->falla(fn () => $ia->recetaNueva($this->usuario, ['tipo' => 'rapida']));
        $this->assertStringContainsString('Por hoy la IA ya respondió todo lo que podía', $error['mensaje']);
    }

    public function testLosErroresNoDescuentanYSeExplicanAmablemente(): void
    {
        $error = $this->falla(fn () => $this->ia('429')->recetaNueva($this->usuario, []));
        $this->assertSame(ErrorIa::CUPO, $error['motivo']);
        $this->assertStringContainsString('Probá de nuevo', $error['mensaje']);
        $usos = $this->usos();
        $this->assertSame(0, (int) $usos[0]['ok']);
        $this->assertStringContainsString('429', (string) $usos[0]['error']);
        $this->assertSame(Ia::limiteUsuario(), $this->ia()->restantesHoy($this->usuario), 'Lo que falla no descuenta');

        $error = $this->falla(fn () => $this->ia('error-ia')->recetaNueva($this->usuario, ['tipo' => 'dulce']));
        $this->assertSame(ErrorIa::FORMATO, $error['motivo']);
    }

    public function testRechazaLaRecetaQueUsaUnaRestriccionYNoLaMandaALaIa(): void
    {
        (new PreferenciasRepositorio($this->db))->agregar($this->usuario, 'restricciones', 15);   // Huevo
        $error = $this->falla(fn () => $this->ia()->recetaNueva($this->usuario, []));
        $this->assertSame(ErrorIa::FORMATO, $error['motivo']);
        $pedidos = ServidorIaFalso::pedidos($this->registro);
        $this->assertCount(2, $pedidos, 'Reintenta una vez porque había tiempo');
        $datos = json_decode(substr($pedidos[0]['pedido']['contents'][0]['parts'][0]['text'], 18), true);
        $this->assertSame(['Huevo'], $datos['no_usar']);
        $this->assertNotContains('Huevo', array_column($datos['despensa'], 'nombre'));
        $this->assertStringContainsString('Huevo', (string) $this->usos()[0]['error']);
        $this->assertSame(0, (int) $this->db->query(
            "SELECT COUNT(*) FROM receta_ia WHERE usuario_id = {$this->usuario}"
        )->fetchColumn());
    }

    public function testSinDespensaNoSeLlama(): void
    {
        $this->db->prepare('DELETE FROM despensa_item WHERE usuario_id = ?')->execute([$this->usuario]);
        $error = $this->falla(fn () => $this->ia()->recetaNueva($this->usuario, []));
        $this->assertSame(ErrorIa::DATOS, $error['motivo']);
        $this->assertSame([], ServidorIaFalso::pedidos($this->registro));
    }

    public function testLoQueFaltaVaALaListaConLaRecetaDeIa(): void
    {
        $id = $this->ia()->recetaNueva($this->usuario, []);
        $repo = new IaRepositorio($this->db);
        $contenido = $repo->receta($this->usuario, $id)['contenido'];
        $faltan = RecetaIa::faltantes(RecetaIa::marcar($contenido, $repo->idsQueTengo($this->usuario)));
        $this->assertSame(['Queso rallado'], array_column($faltan, 'nombre'), 'Sal y aceite son básicos');
        $lista = new ListaRepositorio($this->db);
        $this->assertSame(1, $lista->agregarFaltantes($this->usuario, $faltan, null, $id));
        $this->assertSame(0, $lista->agregarFaltantes($this->usuario, $faltan, null, $id), 'No duplica');
        $items = $lista->listar($this->usuario);
        $this->assertSame(['30.00', 'g'], [$items[0]['cantidad'], $items[0]['unidad']]);
    }

    public function testPaginasYApiConElGeminiDeMentira(): void
    {
        $base = ClienteApi::servidorCon([
            'IA_CLAVE' => 'clave-de-prueba',
            'IA_URL' => ServidorIaFalso::url('ok', $this->registro),
            'IA_LIMITE_USUARIO_DIA' => '5',
        ]);
        $web = new Navegador($base);
        $web->ingresar($this->email, 'Usuario1234');
        [, $html] = $web->get('app/index.php');
        $this->assertStringContainsString('¿Y si inventamos algo?', $html);
        $this->assertStringContainsString('solo tus ingredientes y restricciones, nunca tu nombre', $html);
        $this->assertStringContainsString('Te quedan 5 pedidos hoy.', $html);

        // Sin JavaScript: el formulario común lleva a la receta
        [$estado, , $destino] = $web->post('app/receta-ia.php', ['accion' => 'pedir', 'porciones' => '2',
            'minutos' => '30', 'tipo' => 'almuerzo_cena']);
        $this->assertSame(302, $estado);
        $this->assertMatchesRegularExpression('#app/receta-ia\.php\?id=(\d+)$#', $destino);
        $id = (int) substr($destino, strrpos($destino, '=') + 1);
        [$estado, $html] = $web->get('app/receta-ia.php?id=' . $id);
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('revisala antes de cocinar', $html);
        $this->assertStringContainsString('Por qué esta receta', $html);
        $this->assertStringContainsString('Te falta:', $html);
        $this->assertStringContainsString('name="receta_ia" value="' . $id . '"', $html);

        // Guardarla: aparece en Mis recetas de IA
        $web->post('app/receta-ia.php', ['accion' => 'guardar', 'id' => (string) $id]);
        [, $html] = $web->get('app/mis-recetas-ia.php');
        $this->assertStringContainsString('Revuelto de zapallitos', $html);

        // Con JavaScript: por la API (y "Pedir otra")
        [$estado, $json] = $web->json('POST', 'api/ia/receta', ['otra_de' => $id]);
        $this->assertSame(201, $estado);
        $this->assertStringContainsString('app/receta-ia.php?id=' . $json['id'], $json['url']);
        [$estado, $json] = $web->json('GET', 'api/ia');
        $this->assertSame(['disponible' => true, 'restantes' => 3, 'limite' => 5], $json);

        // Otra persona no la puede ver
        $otro = new Navegador($base);
        $otro->ingresar('maria@cooki.test', 'Usuario1234');
        [$estado, , $destino] = $otro->get('app/receta-ia.php?id=' . $id);
        $this->assertSame(302, $estado);
        $this->assertStringContainsString('error.php', $destino);
    }

    public function testSinIaNoAparecenLosBotonesYLaApiLoDice(): void
    {
        $web = new Navegador(ClienteApi::servidorCon(['IA_PROVEEDOR' => 'ninguno']));
        $web->ingresar($this->email, 'Usuario1234');
        [, $html] = $web->get('app/index.php');
        $this->assertStringNotContainsString('¿Y si inventamos algo?', $html);
        [$estado, $json] = $web->json('POST', 'api/ia/receta', []);
        $this->assertSame(503, $estado);
        $this->assertSame('La IA no está disponible ahora. El resto del sitio anda igual.', $json['error']);
        [$estado] = (new Navegador())->json('POST', 'api/ia/receta', []);
        $this->assertSame(401, $estado);
    }
}
