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
}
