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
use Cooki\Repositorios\RecetaRepositorio;
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
    private int $hongo = 0;

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
        $this->hongo = $hongo;
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
        // La base de prueba junta las llamadas de todas las corridas del día: el límite del sitio se prueba aparte
        putenv('IA_LIMITE_GLOBAL_DIA=1000000');
    }

    protected function tearDown(): void
    {
        putenv('IA_LIMITE_USUARIO_DIA');
        putenv('IA_LIMITE_GLOBAL_DIA');
        if ($this->usuario) {
            $this->db->prepare('DELETE FROM despensa_item WHERE usuario_id = ?')->execute([$this->usuario]);
            $this->db->prepare('DELETE FROM restriccion_usuario WHERE usuario_id = ?')->execute([$this->usuario]);
            // La sugerencia que dejan las pruebas del reemplazo (el Gemini de mentira siempre dice leche)
            $this->db->exec("DELETE FROM sugerencia_reemplazo WHERE ingrediente_id = 1 AND reemplazo_texto = 'Leche'");
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

    public function testAdaptarUnaRecetaSinTocarLaOriginal(): void
    {
        // El hongo único como restricción: así la caché de otras corridas no interfiere
        (new PreferenciasRepositorio($this->db))->agregar($this->usuario, 'restricciones', $this->hongo);
        $recetas = new RecetaRepositorio($this->db);
        $tortilla = $recetas->obtener(1, $this->usuario);   // Tortilla de papas: lleva huevo y le falta la papa
        $ia = $this->ia();
        $id = $ia->adaptar($this->usuario, $tortilla, 'sin_huevo', 2);
        $adaptada = (new IaRepositorio($this->db))->receta($this->usuario, $id);
        $this->assertSame('adaptada', $adaptada['tipo']);
        $this->assertSame(1, (int) $adaptada['receta_base']);
        $this->assertSame('Tortilla de papas', $adaptada['titulo_base']);
        $this->assertSame(['Huevo → 2 cda de fécula de maíz con agua'], $adaptada['contenido']['cambios']);
        $this->assertSame(['adaptacion' => 'sin_huevo', 'porciones' => 2], $adaptada['contenido']['pedido']);
        $this->assertEquals($tortilla['ingredientes'], $recetas->obtener(1, $this->usuario)['ingredientes']);

        $datos = $this->datosDelPedido(0);
        $this->assertSame('sin_huevo', $datos['adaptacion']);
        $this->assertSame(2, $datos['receta']['porciones']);
        $original = array_column($tortilla['ingredientes'], 'cantidad', 'nombre');
        $enviada = array_column($datos['receta']['ingredientes'], 'cantidad', 'nombre');
        $this->assertEqualsWithDelta(
            (float) $original['Huevo'] / 2,
            $enviada['Huevo'],
            0.01,
            'Las cantidades van con las porciones elegidas (4 → 2)'
        );
        $this->assertArrayNotHasKey('despensa', $datos, 'Sin huevo no necesita la despensa (la caché sirve a todos)');

        // "Con lo que tengo" sí manda la despensa y lo que falta
        $ia->adaptar($this->usuario, $tortilla, 'con_lo_que_tengo', 4);
        $datos = $this->datosDelPedido(1);
        $this->assertContains('Papa', $datos['falta']);
        $this->assertContains('Zapallito', $datos['despensa']);

        // La tortilla ya es vegetariana: no se le pide nada a la IA
        $error = $this->falla(fn () => $ia->adaptar($this->usuario, $tortilla, 'vegetariana', 4));
        $this->assertSame(ErrorIa::DATOS, $error['motivo']);
        $this->assertCount(2, ServidorIaFalso::pedidos($this->registro));
    }

    public function testAdaptarRechazaLoQueNoCumple(): void
    {
        // La adaptada del Gemini de mentira lleva cebolla: si la persona no come cebolla, se rechaza
        (new PreferenciasRepositorio($this->db))->agregar($this->usuario, 'restricciones', 2);   // Cebolla
        $tortilla = (new RecetaRepositorio($this->db))->obtener(1, $this->usuario);
        $error = $this->falla(fn () => $this->ia()->adaptar($this->usuario, $tortilla, 'sin_huevo', 4));
        $this->assertSame(ErrorIa::FORMATO, $error['motivo']);
        $this->assertSame(0, (int) $this->db->query(
            "SELECT COUNT(*) FROM receta_ia WHERE usuario_id = {$this->usuario}"
        )->fetchColumn());
    }

    public function testConQueLoReemplazoYQuedaAnotadoParaLaAdministracion(): void
    {
        $tortilla = (new RecetaRepositorio($this->db))->obtener(1, $this->usuario);   // le falta la papa (id 1)
        $veces = fn () => (int) $this->db->query(
            "SELECT COALESCE(SUM(veces), 0) FROM sugerencia_reemplazo
             WHERE ingrediente_id = 1 AND reemplazo_texto = 'Leche'"
        )->fetchColumn();
        $antes = $veces();
        $ia = $this->ia();
        $r = $ia->reemplazo($this->usuario, $tortilla, 1, 2);
        $this->assertSame('Leche', $r['texto']);
        $this->assertSame(24, $r['ingrediente_id']);
        $this->assertSame('Usá leche: la misma cantidad (300 g).', $r['frase'], '600 g para 4 → 300 g para 2');
        $this->assertFalse($r['tengo']);
        $this->assertStringContainsString('liviana', $r['nota']);
        $this->assertSame($antes + 1, $veces());

        $datos = $this->datosDelPedido(0);
        $this->assertEquals(['nombre' => 'Papa', 'cantidad' => 300, 'unidad' => 'g'], $datos['falta']);
        $this->assertContains('Zapallito', $datos['despensa']);
        $this->assertSame('Tortilla de papas', $datos['receta']['titulo']);

        // La misma pregunta sale de la caché: no llama ni se anota otra vez
        $ia->reemplazo($this->usuario, $tortilla, 1, 2);
        $this->assertCount(1, ServidorIaFalso::pedidos($this->registro));
        $this->assertSame($antes + 1, $veces());

        // Lo que ya tiene no se reemplaza
        $error = $this->falla(fn () => $ia->reemplazo($this->usuario, $tortilla, 15, 2));
        $this->assertSame(ErrorIa::DATOS, $error['motivo']);
        $this->assertStringContainsString('Ya tenés Huevo', $error['mensaje']);

        // Si no toma leche, la respuesta (leche) se rechaza
        (new PreferenciasRepositorio($this->db))->agregar($this->usuario, 'restricciones', 24);
        $error = $this->falla(fn () => $this->ia()->reemplazo($this->usuario, $tortilla, 1, 2));
        $this->assertSame(ErrorIa::FORMATO, $error['motivo']);
        $this->assertSame($antes + 1, $veces());
    }

    public function testLosReemplazosGastanUnPedidoCadaCinco(): void
    {
        putenv('IA_LIMITE_USUARIO_DIA=1');
        $tortilla = (new RecetaRepositorio($this->db))->obtener(1, $this->usuario);
        $ia = $this->ia();
        $this->assertSame(0, $ia->reemplazosSinGastar($this->usuario));
        $this->assertStringContainsString('el próximo gasta 1', Ia::textoReemplazos(1, 0));

        // Con otras porciones es otra pregunta (no sale de la caché): el primero gasta el único pedido del día...
        $ia->reemplazo($this->usuario, $tortilla, 1, 1);
        $this->assertSame(0, $ia->restantesHoy($this->usuario));
        $this->assertSame(4, $ia->reemplazosSinGastar($this->usuario));
        $this->assertSame(
            'Te quedan 4 reemplazos sin gastar pedidos (cada 5 reemplazos se gasta 1 pedido).',
            Ia::textoReemplazos(0, 4)
        );
        // ...y los 4 siguientes no gastan, aunque ya no le queden pedidos
        foreach ([2, 3, 4, 5] as $porciones) {
            $ia->reemplazo($this->usuario, $tortilla, 1, $porciones);
        }
        $this->assertSame(0, $ia->reemplazosSinGastar($this->usuario));
        $error = $this->falla(fn () => $ia->reemplazo($this->usuario, $tortilla, 1, 6));
        $this->assertSame(ErrorIa::LIMITE, $error['motivo']);
        $this->assertStringContainsString('Los ya consultados', Ia::textoReemplazos(0, 0));
        // Lo que ya consultó se puede ver igual
        $this->assertSame('Leche', $ia->reemplazo($this->usuario, $tortilla, 1, 3)['texto']);
        $this->assertCount(5, ServidorIaFalso::pedidos($this->registro));
    }

    public function testSabeQueYaSeConsultoAntesDePedir(): void
    {
        (new PreferenciasRepositorio($this->db))->agregar($this->usuario, 'restricciones', $this->hongo);
        $tortilla = (new RecetaRepositorio($this->db))->obtener(1, $this->usuario);
        $ia = $this->ia();
        $this->assertSame([], $ia->reemplazosConsultados($this->usuario, $tortilla, 2));
        $this->assertSame([], $ia->adaptacionesConsultadas($this->usuario, $tortilla, 2));

        $ia->reemplazo($this->usuario, $tortilla, 1, 2);
        $ia->adaptar($this->usuario, $tortilla, 'sin_huevo', 2);
        $this->assertSame([1], $ia->reemplazosConsultados($this->usuario, $tortilla, 2));
        $this->assertSame(['sin_huevo'], $ia->adaptacionesConsultadas($this->usuario, $tortilla, 2));
        $otras = $ia->reemplazosConsultados($this->usuario, $tortilla, 4);
        $this->assertSame([], $otras, 'Otras porciones, otra pregunta');
        $this->assertSame([], $ia->adaptacionesConsultadas($this->usuario, $tortilla, 4));
    }

    /** @return array<string, mixed> los datos que se le mandaron a la IA en el pedido número $n */
    private function datosDelPedido(int $n): array
    {
        $texto = ServidorIaFalso::pedidos($this->registro)[$n]['pedido']['contents'][0]['parts'][0]['text'];
        return json_decode(substr($texto, strlen("Datos del pedido:\n")), true);
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

        // Adaptar con IA (HU-31): sin JavaScript y por la API
        [, $html] = $web->get('app/receta.php?id=1&porciones=2');
        $this->assertStringContainsString('Adaptar con IA', $html);
        $this->assertStringContainsString('value="sin_huevo"', $html);
        $this->assertStringNotContainsString('value="vegetariana"', $html, 'La tortilla ya es vegetariana');
        [$estado, , $destino] = $web->post('app/receta-ia.php', ['accion' => 'adaptar', 'receta' => '1',
            'adaptacion' => 'sin_huevo', 'porciones' => '2']);
        $this->assertSame(302, $estado);
        [, $html] = $web->get(substr($destino, (int) strpos($destino, 'app/')));
        $this->assertStringContainsString('Qué cambió', $html);
        $this->assertStringContainsString('Adaptada de', $html);
        [$estado] = $web->json('POST', 'api/ia/adaptar/1', ['adaptacion' => 'sin_huevo', 'porciones' => 2]);
        $this->assertSame(201, $estado, 'La segunda vez sale de la caché');
        [$estado] = $web->json('POST', 'api/ia/adaptar/1', ['adaptacion' => 'vegetariana']);
        $this->assertSame(422, $estado);
        [$estado] = $web->json('POST', 'api/ia/adaptar/999999', ['adaptacion' => 'sin_huevo']);
        $this->assertSame(404, $estado);

        // ¿Con qué lo reemplazo? (HU-32): sin JavaScript, la respuesta aparece una vez en el ingrediente
        [, $html] = $web->get('app/receta.php?id=1');
        $this->assertStringContainsString('¿Con qué lo reemplazo? (IA)', $html);
        [$estado, , $destino] = $web->post('app/receta-ia.php', ['accion' => 'reemplazo', 'receta' => '1',
            'ingrediente' => '1', 'porciones' => '4']);
        $this->assertSame(302, $estado);
        $this->assertStringEndsWith('app/receta.php?id=1&porciones=4#ing-1', $destino);
        [, $html] = $web->get('app/receta.php?id=1&porciones=4');
        $this->assertStringContainsString('Usá leche: la misma cantidad (600 g).', $html);
        [, $html] = $web->get('app/receta.php?id=1&porciones=4');
        $this->assertStringNotContainsString('Usá leche', $html, 'Se muestra una sola vez');
        $this->assertStringContainsString('ya consultado · no gasta', $html);
        [$estado, $json] = $web->json('POST', 'api/ia/reemplazo', ['receta' => 1, 'ingrediente' => 1,
            'porciones' => 4]);
        $this->assertSame(200, $estado);
        $this->assertSame('Usá leche: la misma cantidad (600 g).', $json['frase']);
        [$estado] = $web->json('POST', 'api/ia/reemplazo', ['receta' => 1, 'ingrediente' => 15]);
        $this->assertSame(422, $estado, 'El huevo ya lo tiene');

        // Otra persona no la puede ver
        $otro = new Navegador($base);
        $otro->ingresar('maria@cooki.test', 'Usuario1234');
        [$estado, , $destino] = $otro->get('app/receta-ia.php?id=' . $id);
        $this->assertSame(302, $estado);
        $this->assertStringContainsString('error.php', $destino);
    }

    public function testLaAdministracionVeElUsoPasaRecetasAlRecetarioYReemplazosASustituciones(): void
    {
        $repo = new IaRepositorio($this->db);
        $repo->registrarUso($this->usuario, 'receta', false, false, 'Gemini no respondió en 12 s.');
        $idIa = $repo->crearReceta($this->usuario, 'nueva', null, [
            'titulo' => 'Tarta de prueba ' . $this->unico, 'descripcion' => 'De prueba.', 'porciones' => 2,
            'minutos_prep' => 10, 'minutos_coccion' => 20, 'dificultad' => 1, 'etiquetas' => ['vegetariana'],
            'ingredientes' => [
                ['id' => 5, 'nombre' => 'Zapallito', 'categoria_id' => 1, 'cantidad' => 2.0, 'unidad' => 'u',
                    'peso' => 3],
                ['id' => $this->hongo, 'nombre' => $this->unico, 'categoria_id' => 1, 'cantidad' => 100.0,
                    'unidad' => 'g', 'peso' => 2],
                ['id' => null, 'nombre' => 'Polvo mágico', 'categoria_id' => null, 'cantidad' => null, 'unidad' => '',
                    'peso' => 1],
            ],
            'pasos' => [['texto' => 'Cortá todo.', 'minutos' => null], ['texto' => 'Horneá.', 'minutos' => 20]],
            'por_que' => '', 'cambios' => [],
        ]);
        $repo->marcarGuardada($this->usuario, $idIa, true);
        $raro = 'Polvo ' . $this->unico;
        $repo->sumarSugerencia(2, ['texto' => 'Zapallito', 'ingrediente_id' => 5, 'ratio' => 1.0,
            'nota' => 'Más suave.']);
        $repo->sumarSugerencia(2, ['texto' => $raro, 'ingrediente_id' => null, 'ratio' => 0.5, 'nota' => '']);
        $sustituciones = fn (): int => (int) $this->db
            ->query('SELECT COUNT(*) FROM sustitucion WHERE ingrediente_id = 2 AND reemplazo_id = 5')->fetchColumn();
        $habia = $sustituciones();

        $web = new Navegador(ClienteApi::servidorCon(['IA_CLAVE' => 'clave-de-prueba']));
        $web->ingresar('admin@cooki.test', 'Admin1234');
        [, $html] = $web->get('admin/index.php');
        $this->assertStringContainsString('admin/ia.php', $html, 'El panel tiene la nota de la IA');
        $this->assertMatchesRegularExpression('/\d+ pedidos? hoy · \d+ con error/', $html);
        [$estado, $html] = $web->get('admin/ia.php');
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('Prendida', $html);
        $this->assertStringContainsString('Últimos 7 días', $html);
        $this->assertStringContainsString('Gemini no respondió en 12 s.', $html);
        $this->assertStringContainsString('Tarta de prueba ' . $this->unico, $html);
        $this->assertStringNotContainsString('Martina Inventora', $html, 'No dice quién la guardó');
        $this->assertStringContainsString('admin/receta-editar.php?desde_ia=' . $idIa, $html);

        // "Pasar al recetario": el editor viene lleno, sin lo que no está en el catálogo general
        [$estado, $html] = $web->get('admin/receta-editar.php?desde_ia=' . $idIa);
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('<h1>Pasar al recetario</h1>', $html);
        $this->assertStringContainsString('value="Tarta de prueba ' . $this->unico . '"', $html);
        $afuera = 'quedaron afuera: <strong>' . $this->unico . ', Polvo mágico</strong>';
        $this->assertStringContainsString($afuera, $html);
        $this->assertStringContainsString('Horneá. [⏱ 20 min]', $html);
        $this->assertStringContainsString('name="desde_ia" value="' . $idIa . '"', $html);
        $web->post('admin/receta-editar.php', ['desde_ia' => (string) $idIa, 'id' => '0',
            'titulo' => 'Tarta de prueba ' . $this->unico, 'descripcion' => 'Revisada.', 'porciones_base' => '2',
            'minutos_prep' => '10', 'minutos_coccion' => '20', 'dificultad' => '1',
            'ingredientes' => [['id' => '5', 'cantidad' => '2', 'unidad' => 'u', 'peso' => '3']],
            'pasos' => "Cortá todo.\nHorneá. [⏱ 20 min]"]);
        $pasada = (int) $this->db->query('SELECT pasada_a FROM receta_ia WHERE id = ' . $idIa)->fetchColumn();
        $this->assertGreaterThan(0, $pasada, 'Quedó anotada en qué receta se convirtió');
        try {
            $guardada = (new RecetaRepositorio($this->db))->obtener($pasada);
            $this->assertSame('Tarta de prueba ' . $this->unico, $guardada['titulo']);
            [, $html] = $web->get('admin/ia.php');
            $this->assertStringContainsString('app/receta.php?id=' . $pasada, $html);
            [, , $destino] = $web->get('admin/receta-editar.php?desde_ia=' . $idIa);
            $this->assertStringContainsString('admin/ia.php', $destino, 'No se pasa dos veces');
        } finally {
            (new RecetaRepositorio($this->db))->borrar($pasada);
        }

        // Reemplazos: el que está en el catálogo se pasa; el que no, solo se descarta
        $pendientesPorTexto = fn (): array => array_column($repo->sugerenciasPendientes(), null, 'reemplazo_texto');
        $pendientes = $pendientesPorTexto();
        $this->assertArrayHasKey($raro, $pendientes);
        $web->get('admin/ia.php');
        [, , $destino] = $web->post('admin/ia.php', ['accion' => 'pasar', 'id' => (string) $pendientes[$raro]['id']]);
        $this->assertStringEndsWith('admin/ia.php#reemplazos', $destino);
        [, $html] = $web->get('admin/ia.php');
        $this->assertStringContainsString('no está en el catálogo: cargalo primero en Ingredientes', $html);
        $web->post('admin/ia.php', ['accion' => 'pasar', 'id' => (string) $pendientes['Zapallito']['id']]);
        $web->get('admin/ia.php');
        $web->post('admin/ia.php', ['accion' => 'descartar', 'id' => (string) $pendientes[$raro]['id']]);
        try {
            $this->assertSame(max(1, $habia), $sustituciones());
            $this->assertArrayNotHasKey('Zapallito', $pendientesPorTexto());
            $this->assertArrayNotHasKey($raro, $pendientesPorTexto());
            $repo->sumarSugerencia(2, ['texto' => $raro, 'ingrediente_id' => null, 'ratio' => 0.5, 'nota' => '']);
            $this->assertArrayNotHasKey(
                $raro,
                $pendientesPorTexto(),
                'Lo descartado no vuelve aunque la IA lo sugiera otra vez'
            );
        } finally {
            if ($habia === 0) {
                $this->db->exec('DELETE FROM sustitucion WHERE ingrediente_id = 2 AND reemplazo_id = 5');
            }
            $borrar = $this->db->prepare(
                'DELETE FROM sugerencia_reemplazo WHERE ingrediente_id = 2 AND reemplazo_texto IN (?, ?)'
            );
            $borrar->execute(['Zapallito', $raro]);
        }

        // Una persona sin rol de administración no entra
        $otro = new Navegador();
        $otro->ingresar($this->email, 'Usuario1234');
        [, , $destino] = $otro->get('admin/ia.php');
        $this->assertStringContainsString('error.php', $destino);
    }

    public function testSinIaNoAparecenLosBotonesYLaApiLoDice(): void
    {
        $web = new Navegador(ClienteApi::servidorCon(['IA_PROVEEDOR' => 'ninguno']));
        $web->ingresar($this->email, 'Usuario1234');
        [, $html] = $web->get('app/index.php');
        $this->assertStringNotContainsString('¿Y si inventamos algo?', $html);
        [, $html] = $web->get('app/receta.php?id=1');
        $this->assertStringNotContainsString('Adaptar con IA', $html);
        [$estado, $json] = $web->json('POST', 'api/ia/receta', []);
        $this->assertSame(503, $estado);
        $this->assertSame('La IA no está disponible ahora. El resto del sitio anda igual.', $json['error']);
        [$estado] = (new Navegador())->json('POST', 'api/ia/receta', []);
        $this->assertSame(401, $estado);
    }
}
