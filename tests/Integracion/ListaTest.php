<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

use Cooki\Db;
use Cooki\Repositorios\ListaRepositorio;
use Cooki\Repositorios\UsuarioRepositorio;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Lista de compras (incremento 3 · HU-26 a HU-29): agregar y sumar, agregar lo que falta de una receta,
 * comprar (pasa a la despensa) y deshacer. Usa una cuenta nueva.
 */
final class ListaTest extends TestCase
{
    private int $usuario = 0;
    private string $email = '';
    private ListaRepositorio $lista;

    protected function setUp(): void
    {
        try {
            $db = Db::conexion();
        } catch (PDOException $e) {
            $this->markTestSkipped('Sin base de datos disponible: ' . $e->getMessage());
        }
        $this->email = 'lista-' . uniqid() . '@cooki.test';
        $this->usuario = (new UsuarioRepositorio($db))->crear('Compradora', $this->email, 'Usuario1234');
        $this->lista = new ListaRepositorio($db);
    }

    protected function tearDown(): void
    {
        if ($this->usuario) {
            Db::conexion()->prepare('DELETE FROM usuario WHERE id = ?')->execute([$this->usuario]);
        }
    }

    /** @return array<int, array<string, mixed>> la despensa por ingrediente_id */
    private function despensa(): array
    {
        $consulta = Db::conexion()->prepare(
            'SELECT ingrediente_id, nivel, cantidad, unidad, fecha_vencimiento, ubicacion,
                    DATEDIFF(fecha_vencimiento, CURDATE()) AS dias
             FROM despensa_item WHERE usuario_id = ? ORDER BY ingrediente_id'
        );
        $consulta->execute([$this->usuario]);
        return $consulta->fetchAll(\PDO::FETCH_UNIQUE);
    }

    public function testSumaLaMismaUnidadYSeparaLasDistintas(): void
    {
        $primero = $this->lista->agregar($this->usuario, 1, null, 2.0, 'u');
        $segundo = $this->lista->agregar($this->usuario, 1, null, 3.0, 'u');
        $this->assertSame($primero['id'], $segundo['id']);
        $this->assertTrue($segundo['sumado']);
        $this->lista->agregar($this->usuario, 1, null, 300.0, 'g');   // en gramos: otro renglón
        $this->lista->agregar($this->usuario, 18, null, 1.0, 'kg');
        $this->lista->agregar($this->usuario, 18, null, 500.0, 'g');  // 1 kg + 500 g = 1500 g

        $items = $this->lista->listar($this->usuario);
        $this->assertCount(3, $items);
        $this->assertSame(['5.00', 'u'], [$items[0]['cantidad'], $items[0]['unidad']]);
        $this->assertSame(['1500.00', 'g'], [$items[2]['cantidad'], $items[2]['unidad']]);
        $this->assertSame(3, $this->lista->pendientes($this->usuario));
    }

    public function testTextoLibreYTextoQueEstaEnElCatalogo(): void
    {
        $libre = $this->lista->agregar($this->usuario, null, '  Papel   de cocina ');
        $this->assertNotNull($libre);
        $catalogo = $this->lista->agregar($this->usuario, null, 'papa');   // está en el catálogo
        $items = array_column($this->lista->listar($this->usuario), null, 'id');
        $this->assertSame('Papel de cocina', $items[$libre['id']]['nombre']);
        $this->assertNull($items[$libre['id']]['ingrediente_id']);
        $this->assertSame(1, (int) $items[$catalogo['id']]['ingrediente_id']);

        $this->assertNull($this->lista->agregar($this->usuario, null, 'x'), 'Texto muy corto');
        $this->assertNull($this->lista->agregar($this->usuario, 999999, null), 'Ingrediente que no existe');
        $this->assertNull($this->lista->agregar($this->usuario, 1, null, -2.0, 'u'), 'Cantidad negativa');
    }

    public function testComprarPasaALaDespensaYDeshacerLaDejaComoEstaba(): void
    {
        $db = Db::conexion();
        // Cebolla: había 1 unidad en la alacena. Tomate: no había.
        $db->prepare("INSERT INTO despensa_item (usuario_id, ingrediente_id, nivel, cantidad, unidad, ubicacion)
                      VALUES (?, 2, 1, 1, 'u', 'alacena')")->execute([$this->usuario]);
        $cebolla = $this->lista->agregar($this->usuario, 2, null, 2.0, 'u')['id'];
        $tomate = $this->lista->agregar($this->usuario, 4, null, 360.0, 'g')['id'];
        $detergente = $this->lista->agregar($this->usuario, null, 'Detergente')['id'];
        $antes = $this->despensa();

        $this->assertSame(3, $this->lista->comprar($this->usuario, [$cebolla, $tomate, $detergente]));
        $this->assertSame(0, $this->lista->comprar($this->usuario, [$cebolla]), 'No se compra dos veces');
        $despensa = $this->despensa();
        $this->assertSame(['3.00', 'u', 'alacena', 2], [
            $despensa[2]['cantidad'], $despensa[2]['unidad'], $despensa[2]['ubicacion'], (int) $despensa[2]['nivel'],
        ]);
        $this->assertSame(30, (int) $despensa[2]['dias'], 'Vence en la vida útil de la cebolla');
        // 360 g de tomate = 3 tomates de 120 g; dura poco: va a la heladera
        $fila = $despensa[4];
        $this->assertSame(['3.00', 'u', 'heladera'], [$fila['cantidad'], $fila['unidad'], $fila['ubicacion']]);
        $this->assertCount(2, $despensa, 'El detergente (texto libre) no pasa a la despensa');
        // Lo comprado hoy se sigue viendo
        $this->assertCount(3, $this->lista->listar($this->usuario));
        $this->assertSame(0, $this->lista->pendientes($this->usuario));

        $this->assertTrue($this->lista->deshacer($this->usuario, $cebolla));
        $this->assertTrue($this->lista->deshacer($this->usuario, $tomate));
        $this->assertFalse($this->lista->deshacer($this->usuario, $tomate), 'Ya se deshizo');
        $this->assertSame($antes, $this->despensa());
        $this->assertSame(2, $this->lista->pendientes($this->usuario));
    }

    public function testNadieTocaLaListaDeOtro(): void
    {
        $mio = $this->lista->agregar($this->usuario, 1, null, 2.0, 'u')['id'];
        $juan = (int) Db::conexion()->query("SELECT id FROM usuario WHERE email = 'juan@cooki.test'")->fetchColumn();
        $this->assertSame(0, $this->lista->comprar($juan, [$mio]));
        $this->assertFalse($this->lista->borrar($juan, $mio));
        $this->assertSame(1, $this->lista->pendientes($this->usuario));
    }

    public function testDesdeLaRecetaConPorcionesALaListaYDeAhiALaDespensa(): void
    {
        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        [, $html] = $web->get('app/receta.php?id=1&porciones=2');
        $this->assertStringContainsString('Agregar lo que falta a la lista', $html);
        $this->assertStringContainsString('+ lista', $html);

        $volver = '/app/receta.php?id=1&porciones=2';
        $pedido = ['accion' => 'receta', 'receta' => 1, 'porciones' => 2, 'volver' => $volver];
        [$estado, , $destino] = $web->post('app/lista.php', $pedido);
        $this->assertSame(302, $estado);
        $this->assertStringEndsWith('/app/receta.php?id=1&porciones=2', $destino);
        // Tocarlo dos veces no duplica las cantidades
        $web->get('app/receta.php?id=1&porciones=2');
        $web->post('app/lista.php', $pedido);
        $papa = array_values(array_filter($this->lista->listar($this->usuario), fn ($i) => $i['nombre'] === 'Papa'));
        $this->assertCount(1, $papa);
        $this->assertSame(['300.00', 'g'], [$papa[0]['cantidad'], $papa[0]['unidad']], 'La mitad de 600 g');
        $nombres = array_column($this->lista->listar($this->usuario), 'nombre');
        $this->assertNotContains('Sal', $nombres, 'La sal es un básico: no va a la lista');

        [, $html] = $web->get('app/receta.php?id=1&porciones=2');
        $this->assertStringContainsString('En tu lista', $html);
        [$estado, $html] = $web->get('app/lista.php');
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('para: Tortilla de papas', $html);
        $this->assertStringContainsString('https://wa.me/?text=', $html);
        $this->assertStringContainsString('Verduras', $html);

        [$estado, , $destino] = $web->post('app/lista.php', ['accion' => 'comprar', 'ids' => [$papa[0]['id']]]);
        $this->assertStringEndsWith('app/lista.php', $destino);
        $despensa = $this->despensa();
        $papaEnDespensa = [$despensa[1]['cantidad'], $despensa[1]['unidad']];
        $this->assertSame(['2.00', 'u'], $papaEnDespensa, '300 g = 2 papas de 150 g');
        [, $html] = $web->get('app/lista.php');
        $this->assertStringContainsString('Comprado hoy', $html);
        $this->assertStringContainsString('Ya está en tu despensa', $html);
    }

    public function testAgregarAManoQuitarYSinSesion(): void
    {
        [, , $destino] = (new Navegador())->get('app/lista.php');
        $this->assertStringEndsWith('error.php?motivo=sin-sesion', $destino);

        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        $web->get('app/lista.php');
        $web->post('app/lista.php', ['accion' => 'agregar', 'ingrediente_id' => '', 'texto' => 'Servilletas']);
        $web->post('app/lista.php', ['accion' => 'agregar', 'ingrediente_id' => 4, 'cantidad' => '2', 'unidad' => 'u']);
        [, $html] = $web->get('app/lista.php');
        $this->assertStringContainsString('Servilletas', $html);
        $this->assertStringContainsString('2 unidades', $html);
        $this->assertSame(2, $this->lista->pendientes($this->usuario));

        $items = $this->lista->listar($this->usuario);
        $tomate = array_values(array_filter($items, fn ($i) => $i['nombre'] === 'Tomate'))[0];
        $web->post('app/lista.php', ['quitar' => $tomate['id']]);
        $this->assertSame(1, $this->lista->pendientes($this->usuario));
    }

    public function testLaListaPorLaApi(): void
    {
        $api = new ClienteApi();
        ClienteApi::iniciar();
        $this->assertSame(401, $api->pedir('GET', '/lista')[0]);
        $api->pedir('POST', '/sesion', ['email' => $this->email, 'contrasena' => 'Usuario1234']);

        $this->assertSame(201, $api->pedir('POST', '/lista', ['texto' => 'Servilletas'])[0]);
        [$estado, $tomate] = $api->pedir('POST', '/lista', ['ingrediente_id' => 4, 'cantidad' => 2, 'unidad' => 'u']);
        $this->assertSame(201, $estado);
        [$estado, $datos] = $api->pedir('POST', '/lista', ['ingrediente_id' => 4, 'cantidad' => 2, 'unidad' => 'u']);
        $this->assertSame([200, true], [$estado, $datos['sumado']]);
        $enTazas = ['ingrediente_id' => 4, 'cantidad' => 2, 'unidad' => 'taza'];
        $this->assertSame(400, $api->pedir('POST', '/lista', $enTazas)[0]);
        $this->assertSame(400, $api->pedir('POST', '/lista', [])[0]);

        [$estado, $datos] = $api->pedir('GET', '/lista');
        $this->assertSame(200, $estado);
        $this->assertSame(['Verduras', 'Otros'], array_column($datos['pendientes'], 'categoria'));
        $this->assertStringContainsString('- Tomate · 4 unidades', $datos['texto']);

        $id = $tomate['id'];
        $this->assertSame(200, $api->pedir('POST', "/lista/{$id}/comprado")[0]);
        $this->assertSame(404, $api->pedir('POST', "/lista/{$id}/comprado")[0], 'Ya está comprado');
        $this->assertArrayHasKey(4, $this->despensa());
        $this->assertSame(200, $api->pedir('DELETE', "/lista/{$id}/comprado")[0]);
        $this->assertArrayNotHasKey(4, $this->despensa(), 'Deshacer saca de la despensa lo que era nuevo');
        $this->assertSame(200, $api->pedir('DELETE', "/lista/{$id}")[0]);
        $this->assertSame(404, $api->pedir('DELETE', "/lista/{$id}")[0]);
    }

    public function testOtraCantidadPasaALaDespensaYDeshacerVuelveALaPrevista(): void
    {
        $tomate = $this->lista->agregar($this->usuario, 4, null, 2.0, 'u')['id'];
        $harina = $this->lista->agregar($this->usuario, 16, null, 500.0, 'g')['id'];

        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        [, $html] = $web->get('app/lista.php');
        $this->assertStringContainsString('Otra cantidad', $html);
        $this->assertStringContainsString('Marcar todo como comprado', $html);
        $web->post('app/lista.php', [
            'comprar_uno' => $harina,
            'cantidad_comprada' => [$harina => '1', $tomate => ''],
            'unidad_comprada' => [$harina => 'kg', $tomate => 'u'],
        ]);
        $this->assertSame('1000.00', $this->despensa()[16]['cantidad'], 'Pasa lo que de verdad se compró: 1 kg');
        $renglon = array_column($this->lista->listar($this->usuario), null, 'id')[$harina];
        $this->assertSame(['1.00', 'kg'], [$renglon['cantidad'], $renglon['unidad']]);
        $this->assertSame(1, $this->lista->pendientes($this->usuario), 'El tomate sigue sin comprar');

        $this->assertTrue($this->lista->deshacer($this->usuario, $harina));
        $renglon = array_column($this->lista->listar($this->usuario), null, 'id')[$harina];
        $this->assertSame(['500.00', 'g'], [$renglon['cantidad'], $renglon['unidad']], 'Vuelve la cantidad prevista');
        $this->assertArrayNotHasKey(16, $this->despensa());

        // Sin escribir cuánto, "Lo compré" no marca nada
        $web->get('app/lista.php');
        $web->post('app/lista.php', ['comprar_uno' => $tomate, 'cantidad_comprada' => [$tomate => '']]);
        $this->assertSame(2, $this->lista->pendientes($this->usuario));
    }

    public function testMarcarTodoComoComprado(): void
    {
        $this->lista->agregar($this->usuario, 4, null, 2.0, 'u');
        $this->lista->agregar($this->usuario, null, 'Servilletas');
        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        $web->get('app/lista.php');
        $web->post('app/lista.php', ['accion' => 'comprar_todo']);
        $this->assertSame(0, $this->lista->pendientes($this->usuario));
        $this->assertArrayHasKey(4, $this->despensa());
    }

    public function testLaNotaDeLaListaEnMiCocinaYMiDespensa(): void
    {
        $this->lista->agregar($this->usuario, 4, null, 2.0, 'u');
        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        foreach (['app/index.php', 'app/despensa.php'] as $pagina) {
            [, $html] = $web->get($pagina);
            $this->assertStringContainsString('id="titulo-nota-lista"', $html, $pagina);
            $this->assertStringContainsString('data-estado="abierta"', $html, $pagina);
        }

        // Cerrar: queda el recuadro chico (la nota entera, oculta)
        $web->post('app/preferencias.php', ['nota_lista' => 'cerrada', 'volver' => '/app/index.php']);
        [, $html] = $web->get('app/index.php');
        $this->assertStringContainsString('data-estado="cerrada"', $html);
        $this->assertMatchesRegularExpression('/nota-lista__abierta"[^>]*hidden/', $html);

        // Apagada en Mis preferencias: no aparece en ningún lado
        [, $html] = $web->get('app/mis-preferencias.php');
        $this->assertStringContainsString('Mostrar mi lista de compras en Mi cocina y Mi despensa', $html);
        $apagar = ['nota_lista' => 'nunca', 'avisar' => '1', 'volver' => '/app/mis-preferencias.php'];
        $web->post('app/preferencias.php', $apagar);
        foreach (['app/index.php', 'app/despensa.php'] as $pagina) {
            [, $html] = $web->get($pagina);
            $this->assertStringNotContainsString('class="nota-lista"', $html, $pagina);
        }
        $guardado = Db::conexion()->prepare('SELECT nota_lista FROM usuario WHERE id = ?');
        $guardado->execute([$this->usuario]);
        $this->assertSame('nunca', $guardado->fetchColumn(), 'Queda guardado en la cuenta');
        $this->assertSame(302, $web->post('app/preferencias.php', ['nota_lista' => 'cualquiera', 'volver' => '/'])[0]);
        $guardado->execute([$this->usuario]);
        $this->assertSame('nunca', $guardado->fetchColumn(), 'Un valor inválido no se guarda');
    }
}
