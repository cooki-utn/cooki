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

    /** @return array<string, mixed> un ingrediente de receta */
    private static function ing(int $id, string $nombre, ?float $cantidad, ?string $unidad, array $mas = []): array
    {
        return $mas + ['ingrediente_id' => $id, 'nombre' => $nombre, 'cantidad' => $cantidad, 'unidad' => $unidad,
            'opcional' => false, 'basico' => false, 'restringido' => false, 'gramos_por_unidad' => null, 'peso' => 3];
    }

    /** @return array<string, mixed> una comida del plan */
    private static function comida(string $clave, string $titulo, array $ingredientes, int $porciones = 2): array
    {
        return ['clave' => $clave, 'fecha' => substr($clave, 0, 10), 'titulo' => $titulo, 'porciones' => $porciones,
            'porciones_base' => 2, 'ingredientes' => $ingredientes];
    }

    public function testLoQueUsaUnaComidaYaNoEstaParaLaSiguiente(): void
    {
        $despensa = [15 => [['nivel' => 2, 'cantidad' => 6.0, 'unidad' => 'u', 'vence' => null]]];
        $tortilla = self::comida('2026-11-09|almuerzo', 'Tortilla de papas', [self::ing(15, 'Huevo', 3, 'u')], 4);
        $revuelto = self::comida('2026-11-09|cena', 'Revuelto', [self::ing(15, 'Huevo', 2, 'u')]);
        $resultado = PlanSemanal::simular([$tortilla, $revuelto], $despensa);

        $this->assertTrue($resultado['2026-11-09|almuerzo']['tiene_todo'], '6 huevos para 4 porciones: alcanza justo');
        $cena = $resultado['2026-11-09|cena'];
        $this->assertFalse($cena['tiene_todo']);
        $this->assertSame('usado', $cena['ingredientes'][0]['estado']);
        $this->assertSame('Tortilla de papas', $cena['ingredientes'][0]['lo_usa']);
        $this->assertSame([['ingrediente_id' => 15, 'texto' => null, 'nombre' => 'Huevo', 'cantidad' => 2.0,
            'unidad' => 'u']], $cena['faltantes']);
    }

    public function testSinCantidadSeGastaDeAUnNivel(): void
    {
        $despensa = [5 => [['nivel' => 2, 'cantidad' => null, 'unidad' => null, 'vence' => null]]];
        $comidas = [];
        foreach (['2026-11-09', '2026-11-10', '2026-11-11'] as $fecha) {
            $comidas[] = self::comida($fecha . '|cena', 'Zapallitos ' . $fecha, [self::ing(5, 'Zapallito', 2, 'u')]);
        }
        $estados = array_map(fn ($c) => $c['ingredientes'][0]['estado'], PlanSemanal::simular($comidas, $despensa));
        $this->assertSame(['tengo', 'tengo', 'usado'], array_values($estados), 'Normal → Poco → Agotado');
    }

    public function testLoQueVenceAntesDeEseDiaNoSirveYSeUsaOtroLote(): void
    {
        $despensa = [7 => [
            ['nivel' => 2, 'cantidad' => 1000.0, 'unidad' => 'ml', 'vence' => '2026-11-10'],
            ['nivel' => 2, 'cantidad' => 500.0, 'unidad' => 'ml', 'vence' => '2026-11-20'],
        ], 8 => [['nivel' => 3, 'cantidad' => null, 'unidad' => null, 'vence' => '2026-11-10']]];
        $resultado = PlanSemanal::simular([self::comida('2026-11-12|cena', 'Flan', [
            self::ing(7, 'Leche', 500, 'ml'), self::ing(8, 'Crema', 200, 'ml'),
        ])], $despensa)['2026-11-12|cena'];
        $this->assertSame(['tengo', 'vence'], array_column($resultado['ingredientes'], 'estado'));
    }

    public function testBasicosOpcionalesRestringidosYLoQueNoEstaEnElCatalogo(): void
    {
        $resultado = PlanSemanal::simular([self::comida('2026-11-09|cena', 'De la IA', [
            self::ing(1, 'Sal', null, 'pizca', ['basico' => true]),
            self::ing(2, 'Perejil', 10, 'g', ['opcional' => true]),
            self::ing(3, 'Queso', 100, 'g', ['restringido' => true]),
            ['ingrediente_id' => null, 'nombre' => 'Salsa sriracha', 'cantidad' => 1, 'unidad' => 'cda'],
            self::ing(4, 'Pollo', 300, 'g'),
        ], 4)], [])['2026-11-09|cena'];

        $this->assertSame(['Sal', 'Salsa sriracha', 'Pollo'], array_column($resultado['ingredientes'], 'nombre'));
        $this->assertSame(['tengo', 'falta', 'falta'], array_column($resultado['ingredientes'], 'estado'));
        $this->assertSame([
            ['ingrediente_id' => null, 'texto' => 'Salsa sriracha', 'nombre' => 'Salsa sriracha', 'cantidad' => 2.0,
                'unidad' => 'cda'],
            ['ingrediente_id' => 4, 'texto' => null, 'nombre' => 'Pollo', 'cantidad' => 600.0, 'unidad' => 'g'],
        ], $resultado['faltantes'], 'Escalado a 4 porciones');
    }

    public function testArmarLaSemanaUsaPrimeroLoQueVenceNoRepiteYCompletaTodo(): void
    {
        $despensa = [
            5 => [['nivel' => 1, 'cantidad' => null, 'unidad' => null, 'vence' => '2026-11-10']],   // zapallito
            15 => [['nivel' => 3, 'cantidad' => 12.0, 'unidad' => 'u', 'vence' => null]],             // huevo
        ];
        $receta = fn (string $id, string $titulo, array $ings, int $minutos = 30): array => ['id' => $id,
            'titulo' => $titulo, 'minutos' => $minutos, 'dificultad' => 1, 'porciones_base' => 2, 'reciente' => false,
            'ingredientes' => $ings];
        $candidatas = [
            $receta('r:1', 'Huevos revueltos', [self::ing(15, 'Huevo', 2, 'u')], 10),
            $receta('r:2', 'Revuelto de zapallitos', [
                self::ing(15, 'Huevo', 2, 'u'), self::ing(5, 'Zapallito', 2, 'u'),
            ]),
            $receta('r:3', 'Tarta de zapallitos', [self::ing(5, 'Zapallito', 3, 'u'), self::ing(15, 'Huevo', 3, 'u')]),
            $receta('r:4', 'Milanesas', [self::ing(20, 'Carne', 500, 'g')]),
        ];
        $lugares = [
            ['clave' => '2026-11-09|almuerzo', 'fecha' => '2026-11-09', 'comida' => null],
            ['clave' => '2026-11-09|cena', 'fecha' => '2026-11-09', 'comida' => null],
            ['clave' => '2026-11-10|almuerzo', 'fecha' => '2026-11-10', 'comida' => null],
            ['clave' => '2026-11-10|cena', 'fecha' => '2026-11-10', 'comida' => null],
            ['clave' => '2026-11-11|almuerzo', 'fecha' => '2026-11-11', 'comida' => null],
        ];
        $elegidas = PlanSemanal::armar($lugares, $candidatas, $despensa);

        $this->assertSame('r:2', $elegidas['2026-11-09|almuerzo'], 'Primero la que usa el zapallito que vence');
        $this->assertSame('r:1', $elegidas['2026-11-09|cena'], 'El zapallito ya se usó: la tarta no se puede hacer');
        $this->assertSame('r:3', $elegidas['2026-11-10|almuerzo'], 'Después la de más puntaje aunque falte algo');
        $this->assertSame('r:4', $elegidas['2026-11-10|cena']);
        $this->assertArrayNotHasKey('2026-11-11|almuerzo', $elegidas, 'No hay más recetas: no se repite ninguna');
    }

    public function testElDesplegableDiceCuantoFaltaEnCadaLugarContandoLoDeAntes(): void
    {
        $huevo = self::ing(15, 'Huevo', 2, 'u');
        $despensa = [15 => [['nivel' => 2, 'cantidad' => 2.0, 'unidad' => 'u', 'vence' => null]]];
        $receta = fn (string $id, string $titulo, array $ings): array => ['id' => $id, 'titulo' => $titulo,
            'porciones_base' => 2, 'ingredientes' => $ings];
        $candidatas = [
            $receta('r:3', 'Tortilla', [$huevo, self::ing(9, 'Papa', 3, 'u'), self::ing(2, 'Cebolla', 1, 'u'),
                self::ing(4, 'Aceite', 2, 'cda')]),
            $receta('r:1', 'Huevo duro', [$huevo]),
            $receta('r:2', 'Arroz con huevo', [$huevo, self::ing(20, 'Arroz', 200, 'g')]),
        ];
        $lugares = [
            ['clave' => 'a', 'fecha' => '2026-11-09', 'comida' => self::comida('2026-11-09|a', 'Huevo duro', [$huevo])],
            ['clave' => 'b', 'fecha' => '2026-11-09', 'comida' => null],
        ];
        $opciones = PlanSemanal::opciones($lugares, $candidatas, $despensa);

        // En el primer lugar (aunque ya tenga receta) se mira la despensa entera: el huevo está
        $this->assertSame(['r:1', 'r:2', 'r:3'], array_column($opciones['a'], 'id'), 'De la que menos le falta');
        $this->assertSame([0, 1, 3], array_column($opciones['a'], 'faltan'));
        $this->assertSame(['Arroz'], $opciones['a'][1]['faltantes']);
        // En el segundo, el almuerzo ya usó los 2 huevos
        $this->assertSame([1, 2, 4], array_column($opciones['b'], 'faltan'));
        $this->assertSame(['Huevo', 'Arroz'], $opciones['b'][1]['faltantes']);
        $this->assertSame(3, PlanSemanal::grupoFaltan(4));
        $this->assertSame(0, PlanSemanal::grupoFaltan(0));
    }

    public function testArmarRespetaLoQueYaEstabaYNoLoRepite(): void
    {
        $huevo = self::ing(15, 'Huevo', 2, 'u');
        $candidatas = [
            ['id' => 'r:1', 'titulo' => 'A', 'minutos' => 10, 'dificultad' => 1, 'porciones_base' => 2,
                'reciente' => false, 'ingredientes' => [$huevo]],
            ['id' => 'r:2', 'titulo' => 'B', 'minutos' => 20, 'dificultad' => 1, 'porciones_base' => 2,
                'reciente' => false, 'ingredientes' => [$huevo]],
        ];
        $lugares = [
            ['clave' => '2026-11-09|almuerzo', 'fecha' => '2026-11-09', 'comida' => self::comida(
                '2026-11-09|almuerzo',
                'A',
                [$huevo]
            ) + ['id' => 'r:1']],
            ['clave' => '2026-11-09|cena', 'fecha' => '2026-11-09', 'comida' => null],
        ];
        $this->assertSame(['2026-11-09|cena' => 'r:2'], PlanSemanal::armar($lugares, $candidatas, []));
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
