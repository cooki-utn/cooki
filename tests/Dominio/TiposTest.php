<?php

declare(strict_types=1);

namespace Cooki\Tests\Dominio;

use Cooki\Dominio\Tipos;
use PHPUnit\Framework\TestCase;

/** Los tipos de receta: nombres para mostrar, orden, "Rápida" y las reglas de cada dieta. */
final class TiposTest extends TestCase
{
    public function testLosNombresVanConMayusculaYSinTaccBienEscrito(): void
    {
        $this->assertSame('Sin TACC', Tipos::nombre('sin_tacc'));
        $this->assertSame('Económica', Tipos::nombre('economica'));
        $this->assertSame('Merienda salada', Tipos::nombre('merienda_salada'));
        $this->assertSame('Sin azucar', Tipos::nombre('sin_azucar'), 'una clave que no conoce se muestra prolija');
        $this->assertSame('img/tipos/vegana.svg', Tipos::dibujo('vegana'));
        $this->assertNull(Tipos::dibujo('sin_azucar'));
    }

    public function testOrdenaComoEnLaListaYSacaRepetidosYVacios(): void
    {
        $this->assertSame(
            ['vegetariana', 'sin_tacc', 'dulce', 'otra'],
            Tipos::ordenar(['dulce', 'otra', '', 'sin_tacc', 'vegetariana', 'dulce'])
        );
    }

    public function testRapidaEsDeTreintaMinutosOMenos(): void
    {
        $this->assertTrue(Tipos::esRapida(10, 20));
        $this->assertFalse(Tipos::esRapida(10, 21));
    }

    public function testCadaDietaSabeQueNoPuedeLlevar(): void
    {
        $this->assertTrue(Tipos::rompe('vegetariana', 'atun-en-lata', 6));
        $this->assertTrue(Tipos::rompe('vegana', 'huevo', 5));
        $this->assertTrue(Tipos::rompe('vegana', 'miel', 6));
        $this->assertTrue(Tipos::rompe('vegana', 'tapas-de-empanada', 6), 'las tapas comunes llevan grasa');
        $this->assertTrue(Tipos::rompe('sin_lactosa', 'dulce-de-leche', 6));
        $this->assertTrue(Tipos::rompe('sin_huevo', 'mayonesa', 6));
        $this->assertTrue(Tipos::rompe('sin_tacc', 'avena', 6));
        $this->assertFalse(Tipos::rompe('sin_huevo', 'manteca', 3));
        $this->assertFalse(Tipos::rompe('rapida', 'pollo', 4), 'rapida no es una dieta');
    }

    public function testDiceQueDietasCumpleUnaReceta(): void
    {
        $ensalada = [['slug' => 'lenteja', 'categoria_id' => 8], ['slug' => 'tomate', 'categoria_id' => 1]];
        $this->assertSame(Tipos::DIETAS, Tipos::dietasQueCumple($ensalada));
        $conQueso = array_merge($ensalada, [['slug' => 'queso-rallado', 'categoria_id' => 3]]);
        $this->assertSame(['vegetariana', 'sin_tacc', 'sin_huevo'], Tipos::dietasQueCumple($conQueso));
    }
}
