<?php

declare(strict_types=1);

namespace Cooki\Tests\Dominio;

use Cooki\Dominio\PlanSemanal;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Plan semanal: los días, los nombres, los lugares válidos, el desplegable y las porciones. */
final class PlanSemanalTest extends TestCase
{
    private DateTimeImmutable $hoy;

    protected function setUp(): void
    {
        $this->hoy = new DateTimeImmutable('2026-11-09 21:30');   // un lunes a la noche
    }

    public function testLaSemanaSonSieteDiasDesdeHoyAunqueCambieElMes(): void
    {
        $dias = PlanSemanal::dias(new DateTimeImmutable('2026-11-27'));
        $this->assertCount(7, $dias);
        $this->assertSame('2026-11-27', $dias[0]);
        $this->assertSame('2026-12-03', $dias[6]);
    }

    public function testLosDiasSeLeenComoLosDiceUnaPersona(): void
    {
        $this->assertSame('Hoy', PlanSemanal::nombreDia('2026-11-09', $this->hoy));
        $this->assertSame('Mañana', PlanSemanal::nombreDia('2026-11-10', $this->hoy));
        $this->assertSame('Miércoles 11', PlanSemanal::nombreDia('2026-11-11', $this->hoy));
        $this->assertSame('Domingo 15', PlanSemanal::nombreDia('2026-11-15', $this->hoy));
    }

    public function testLosAvisosNombranElLugarComoCorresponde(): void
    {
        $this->assertSame('el almuerzo de hoy', PlanSemanal::lugar('2026-11-09', 'almuerzo', $this->hoy));
        $this->assertSame('la cena de mañana', PlanSemanal::lugar('2026-11-10', 'cena', $this->hoy));
        $this->assertSame('la cena del miércoles 11', PlanSemanal::lugar('2026-11-11', 'cena', $this->hoy));
    }

    public function testSoloValenLosLugaresDeEstaSemana(): void
    {
        $this->assertTrue(PlanSemanal::lugarValido('2026-11-09', 'almuerzo', $this->hoy), 'Hoy, aunque sea de noche');
        $this->assertTrue(PlanSemanal::lugarValido('2026-11-15', 'cena', $this->hoy));
        $this->assertFalse(PlanSemanal::lugarValido('2026-11-16', 'cena', $this->hoy), 'El octavo día no');
        $this->assertFalse(PlanSemanal::lugarValido('2026-11-08', 'cena', $this->hoy), 'Ayer no');
        $this->assertFalse(PlanSemanal::lugarValido('2026-11-10', 'merienda', $this->hoy));
        $this->assertFalse(PlanSemanal::lugarValido('10/11/2026', 'cena', $this->hoy));
    }

    public function testElDesplegableDistingueRecetasYRecetasDeIa(): void
    {
        $this->assertSame(['receta_id' => 12, 'receta_ia_id' => null], PlanSemanal::opcion('r:12'));
        $this->assertSame(['receta_id' => null, 'receta_ia_id' => 5], PlanSemanal::opcion('ia:5'));
        $this->assertNull(PlanSemanal::opcion(''));
        $this->assertNull(PlanSemanal::opcion('r:0'));
        $this->assertNull(PlanSemanal::opcion('x:3'));
        $this->assertNull(PlanSemanal::opcion('r:3; DROP'));
        $this->assertSame('r:12', PlanSemanal::valorOpcion(12, null));
        $this->assertSame('ia:5', PlanSemanal::valorOpcion(null, 5));
        $this->assertSame('', PlanSemanal::valorOpcion(null, null));
    }

    public function testSinPorcionesValidasSeUsanLasDeLaReceta(): void
    {
        $this->assertSame(3, PlanSemanal::porciones('3', 4));
        $this->assertSame(4, PlanSemanal::porciones('', 4));
        $this->assertSame(4, PlanSemanal::porciones('0', 4));
        $this->assertSame(4, PlanSemanal::porciones('99', 4));
        $this->assertSame(4, PlanSemanal::porciones('dos', 4));
        $this->assertSame(12, PlanSemanal::porciones(null, 20), 'La receta tampoco puede pasar el máximo');
    }
}
