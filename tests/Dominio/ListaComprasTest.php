<?php

declare(strict_types=1);

namespace Cooki\Tests\Dominio;

use Cooki\Dominio\ListaCompras;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Reglas de la lista de compras: sumar, qué falta de una receta y cómo queda la despensa al comprar. */
final class ListaComprasTest extends TestCase
{
    public function testSumaLaMismaUnidadYConvierteKilosYLitros(): void
    {
        $this->assertSame(['cantidad' => 5.0, 'unidad' => 'u'], ListaCompras::sumar(2.0, 'u', 3.0, 'u'));
        $this->assertSame(['cantidad' => 1500.0, 'unidad' => 'g'], ListaCompras::sumar(1.0, 'kg', 500.0, 'g'));
        $this->assertSame(['cantidad' => 750.0, 'unidad' => 'ml'], ListaCompras::sumar(250.0, 'ml', 0.5, 'l'));
    }

    public function testConUnidadesDistintasNoSeSuma(): void
    {
        $this->assertNull(ListaCompras::sumar(1.0, 'taza', 200.0, 'g'));
        $this->assertNull(ListaCompras::sumar(2.0, 'u', 300.0, 'g'));
    }

    public function testSinCantidadMasUnaCantidadQuedaLaCantidad(): void
    {
        $this->assertSame(['cantidad' => 2.0, 'unidad' => 'u'], ListaCompras::sumar(null, null, 2.0, 'u'));
        $this->assertSame(['cantidad' => 2.0, 'unidad' => 'u'], ListaCompras::sumar(2.0, 'u', null, null));
        $this->assertSame(['cantidad' => null, 'unidad' => null], ListaCompras::sumar(null, null, null, null));
    }

    public function testFaltantesEscalaYDejaAfueraLoQueTieneONoCome(): void
    {
        $base = ['en_despensa' => 0, 'es_basico' => 0, 'restringido' => 0, 'es_opcional' => 0];
        $ing = fn (int $id, string $nombre, ?string $cantidad, ?string $unidad, array $otros = []) => $otros
            + ['ingrediente_id' => $id, 'nombre' => $nombre, 'cantidad' => $cantidad, 'unidad' => $unidad] + $base;
        $ingredientes = [
            $ing(1, 'Papa', '4.00', 'u'),
            $ing(15, 'Huevo', '6.00', 'u', ['en_despensa' => 1]),
            $ing(20, 'Sal', null, 'pizca', ['es_basico' => 1]),
            $ing(10, 'Manteca', '50', 'g', ['restringido' => 1]),
            $ing(3, 'Morrón', '1', 'u', ['es_opcional' => 1]),
            $ing(30, 'Perejil', null, null),
        ];
        $this->assertSame([
            ['ingrediente_id' => 1, 'nombre' => 'Papa', 'cantidad' => 2.0, 'unidad' => 'u'],
            ['ingrediente_id' => 30, 'nombre' => 'Perejil', 'cantidad' => null, 'unidad' => null],
        ], ListaCompras::faltantes($ingredientes, 0.5));
    }

    public function testLaPizcaVaSinCantidad(): void
    {
        $this->assertSame(['cantidad' => null, 'unidad' => null], ListaCompras::escalar('1', 'pizca', 2.0));
        $this->assertSame(['cantidad' => 300.0, 'unidad' => 'g'], ListaCompras::escalar('150.00', 'g', 2.0));
    }

    public function testVencimientoEstimado(): void
    {
        $hoy = new DateTimeImmutable('2026-11-09');
        $this->assertSame('2026-11-16', ListaCompras::vencimiento(7, $hoy));
        $this->assertNull(ListaCompras::vencimiento(null, $hoy));
    }

    public function testUbicacionSugerida(): void
    {
        $this->assertSame('heladera', ListaCompras::ubicacion('Lácteos', true, 30));
        $this->assertSame('heladera', ListaCompras::ubicacion('Verduras', true, 7));    // tomate
        $this->assertSame('alacena', ListaCompras::ubicacion('Verduras', true, 21));    // papa
        $this->assertSame('alacena', ListaCompras::ubicacion('Almacén', false, 730));   // fideos
        $this->assertSame('alacena', ListaCompras::ubicacion('Lácteos', false, null));  // leche larga vida
    }

    public function testAlComprarAlgoNuevo(): void
    {
        $tomate = ['categoria' => 'Verduras', 'perecedero' => 1, 'vida_util_dias' => 7,
            'unidad_default' => 'u', 'gramos_por_unidad' => '120'];
        $r = ListaCompras::alComprar(null, $tomate, 600.0, 'g', new DateTimeImmutable('2026-11-09'));
        $this->assertSame([
            'nivel' => 2, 'cantidad' => 5.0, 'unidad' => 'u',
            'fecha_vencimiento' => '2026-11-16', 'ubicacion' => 'heladera',
        ], $r);
    }

    public function testAlComprarSumaALoQueHabiaYMantieneLaUbicacion(): void
    {
        $harina = ['categoria' => 'Almacén', 'perecedero' => 0, 'vida_util_dias' => 365,
            'unidad_default' => 'g', 'gramos_por_unidad' => null];
        $habia = ['nivel' => 1, 'cantidad' => '200.00', 'unidad' => 'g', 'ubicacion' => 'freezer'];
        $r = ListaCompras::alComprar($habia, $harina, 1.0, 'kg', new DateTimeImmutable('2026-11-09'));
        $this->assertSame(2, $r['nivel']);
        $this->assertSame(1200.0, $r['cantidad']);
        $this->assertSame('freezer', $r['ubicacion']);
        $this->assertSame('2027-11-09', $r['fecha_vencimiento']);
    }

    public function testAlComprarSinSaberCuantoQuedaSinCantidad(): void
    {
        $leche = ['categoria' => 'Lácteos', 'perecedero' => 1, 'vida_util_dias' => 7,
            'unidad_default' => 'ml', 'gramos_por_unidad' => null];
        $hoy = new DateTimeImmutable('2026-11-09');
        // Había "Mucho" sin cantidad exacta: queda "Mucho" y sin cantidad
        $habia = ['nivel' => 3, 'cantidad' => null, 'unidad' => null, 'ubicacion' => 'heladera'];
        $r = ListaCompras::alComprar($habia, $leche, 1.0, 'l', $hoy);
        $this->assertSame([3, null, null], [$r['nivel'], $r['cantidad'], $r['unidad']]);
        // Se compró "1 taza": no se puede pasar a ml
        $r = ListaCompras::alComprar(null, $leche, 1.0, 'taza', $hoy);
        $this->assertSame([2, null], [$r['nivel'], $r['cantidad']]);
        // Estaba agotado: lo que había no cuenta
        $agotada = ['nivel' => 0, 'cantidad' => '0', 'unidad' => 'ml', 'ubicacion' => 'heladera'];
        $r = ListaCompras::alComprar($agotada, $leche, 1.0, 'l', $hoy);
        $this->assertSame([2, 1000.0], [$r['nivel'], $r['cantidad']]);
    }

    public function testAgrupaPorGondolaYTextoLibreAlFinal(): void
    {
        $items = [
            ['nombre' => 'Yerba', 'categoria' => null, 'orden' => null, 'cantidad' => null, 'unidad' => null],
            ['nombre' => 'Tomate', 'categoria' => 'Verduras', 'orden' => 1, 'cantidad' => '3', 'unidad' => 'u'],
            ['nombre' => 'Arroz', 'categoria' => 'Almacén', 'orden' => 6, 'cantidad' => '500', 'unidad' => 'g'],
            ['nombre' => 'Acelga', 'categoria' => 'Verduras', 'orden' => 1, 'cantidad' => null, 'unidad' => null],
        ];
        $grupos = ListaCompras::agrupar($items);
        $this->assertSame(['Verduras', 'Almacén', 'Otros'], array_column($grupos, 'categoria'));
        $this->assertSame(['Acelga', 'Tomate'], array_column($grupos[0]['items'], 'nombre'));
        $this->assertSame(
            "Lista de compras\n\n*Verduras*\n- Acelga\n- Tomate · 3 unidades\n\n"
                . "*Almacén*\n- Arroz · 500 g\n\n*Otros*\n- Yerba",
            ListaCompras::texto($grupos)
        );
    }
}
