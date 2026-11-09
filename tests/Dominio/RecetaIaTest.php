<?php

declare(strict_types=1);

namespace Cooki\Tests\Dominio;

use Cooki\Dominio\RecetaIa;
use PHPUnit\Framework\TestCase;

/** Lo que devuelve la IA: validación, cruce con el catálogo, restricciones, adaptaciones y clave de la caché. */
final class RecetaIaTest extends TestCase
{
    private RecetaIa $ia;

    protected function setUp(): void
    {
        $this->ia = new RecetaIa([
            ['id' => 5, 'nombre' => 'Zapallito', 'categoria_id' => 1],
            ['id' => 15, 'nombre' => 'Huevo', 'categoria_id' => 5],
            ['id' => 12, 'nombre' => 'Queso cremoso', 'categoria_id' => 3],
            ['id' => 24, 'nombre' => 'Leche', 'categoria_id' => 3],
            ['id' => 96, 'nombre' => 'Dulce de leche', 'categoria_id' => 6],
            ['id' => 14, 'nombre' => 'Pollo', 'categoria_id' => 4],
            ['id' => 16, 'nombre' => 'Harina 0000', 'categoria_id' => 6],
            ['id' => 2, 'nombre' => 'Cebolla', 'categoria_id' => 1],
            ['id' => 30, 'nombre' => 'Sal', 'categoria_id' => 7],
            ['id' => 40, 'nombre' => 'Limón', 'categoria_id' => 2],
        ], ['calabacín' => 5, 'zucchini' => 5]);
    }

    /** @return array<string, mixed> una receta válida, como la devolvería la IA */
    private function receta(array $cambios = []): array
    {
        return $cambios + [
            'titulo' => 'Revuelto de zapallitos',
            'descripcion' => 'Rápido y suave.',
            'porciones' => 2,
            'minutos_prep' => 10,
            'minutos_coccion' => 15,
            'dificultad' => 1,
            'etiquetas' => ['vegetariana', 'inventada'],
            'ingredientes' => [
                ['nombre' => 'Zapallitos', 'cantidad' => 2, 'unidad' => 'u', 'peso' => 3],
                ['nombre' => 'huevos', 'cantidad' => 3, 'unidad' => 'u', 'peso' => 2],
                ['nombre' => 'Sal', 'cantidad' => null, 'unidad' => 'a gusto', 'peso' => 1],
                ['nombre' => 'Orégano fresco', 'cantidad' => 1, 'unidad' => 'pizca', 'peso' => 1],
            ],
            'pasos' => [
                ['texto' => 'Cortá {ing:zapallito} en cubos.', 'minutos' => null],
                ['texto' => 'Saltealos.', 'minutos' => 10],
                ['texto' => 'Agregá los huevos batidos y revolvé.', 'minutos' => 3],
            ],
            'por_que' => 'Usa el zapallito que vence en 2 días.',
        ];
    }

    public function testValidaYCruzaConElCatalogoPorNombrePluralYSinonimo(): void
    {
        $r = $this->ia->validar($this->receta());
        $this->assertNull($r['error']);
        $ing = $r['receta']['ingredientes'];
        $this->assertSame([5, 15, 30, null], array_column($ing, 'id'));
        $this->assertSame(['Zapallito', 'Huevo', 'Sal', 'Orégano fresco'], array_column($ing, 'nombre'));
        $this->assertSame(['cantidad' => null, 'unidad' => ''], ['cantidad' => $ing[2]['cantidad'],
            'unidad' => $ing[2]['unidad']]);
        $this->assertSame(2.0, $ing[0]['cantidad']);
        $this->assertSame(['vegetariana'], $r['receta']['etiquetas']);
        $this->assertSame('Cortá zapallito en cubos.', $r['receta']['pasos'][0]['texto']);
        $this->assertSame(10, $r['receta']['pasos'][1]['minutos']);

        $conSinonimo = $this->receta();
        $conSinonimo['ingredientes'][0]['nombre'] = 'Calabacín';
        $this->assertSame(5, $this->ia->validar($conSinonimo)['receta']['ingredientes'][0]['id']);
        $this->assertSame(40, $this->ia->buscar('limones')['id']);
    }

    public function testRechazaUnidadesYCantidadesInvalidas(): void
    {
        $mal = $this->receta();
        $mal['ingredientes'][0]['unidad'] = 'puñado';
        $this->assertStringContainsString('unidad', (string) $this->ia->validar($mal)['error']);

        $mal = $this->receta();
        $mal['ingredientes'][0]['cantidad'] = -2;
        $this->assertStringContainsString('cantidad', (string) $this->ia->validar($mal)['error']);
    }

    public function testRechazaRecetasIncompletasODemasiadoLargas(): void
    {
        $this->assertNotNull($this->ia->validar('no es json')['error']);
        $this->assertNotNull($this->ia->validar($this->receta(['titulo' => '']))['error']);
        $this->assertNotNull($this->ia->validar($this->receta(['porciones' => 0]))['error']);
        $this->assertNotNull($this->ia->validar($this->receta(['pasos' => []]))['error']);
        $muchos = array_fill(0, 16, ['nombre' => 'x', 'cantidad' => 1, 'unidad' => 'u']);
        $error = $this->ia->validar($this->receta(['ingredientes' => $muchos]))['error'];
        $this->assertStringContainsString('15', (string) $error);
        $pasos = array_fill(0, 13, ['texto' => 'Revolver.']);
        $error = $this->ia->validar($this->receta(['pasos' => $pasos]))['error'];
        $this->assertStringContainsString('12', (string) $error);
    }

    public function testDevuelveElErrorQueMandoLaIa(): void
    {
        $r = $this->ia->validar(['error' => 'Con eso solo no alcanza.']);
        $this->assertNull($r['receta']);
        $this->assertStringContainsString('Con eso solo no alcanza', (string) $r['error']);
    }

    public function testRechazaLaRecetaSiUsaUnaRestriccionTambienPorSinonimoONombre(): void
    {
        $this->assertStringContainsString('Huevo', (string) $this->ia->validar($this->receta(), [15])['error']);

        // La restricción es el zapallito y la IA lo llamó "zucchini"
        $receta = $this->receta();
        $receta['ingredientes'][0]['nombre'] = 'Zucchini';
        $this->assertNotNull($this->ia->validar($receta, [5])['error']);

        // La restricción es Leche y la IA propuso dulce de leche (está en el catálogo) o "leche de vaca" (no está)
        foreach (['Dulce de leche', 'Leche de vaca'] as $nombre) {
            $receta = $this->receta();
            $receta['ingredientes'][] = ['nombre' => $nombre, 'cantidad' => 100, 'unidad' => 'g', 'peso' => 2];
            $this->assertNotNull($this->ia->validar($receta, [24])['error'], $nombre);
        }
        // "Sal" no es parte de "Salsa"
        $receta = $this->receta();
        $receta['ingredientes'][] = ['nombre' => 'Salsa de tomate', 'cantidad' => 200, 'unidad' => 'g', 'peso' => 2];
        $receta['ingredientes'] = array_values(
            array_filter($receta['ingredientes'], fn ($i) => $i['nombre'] !== 'Sal')
        );
        $this->assertNull($this->ia->validar($receta, [30])['error']);
    }

    public function testVerificaLaAdaptacionEnElServidor(): void
    {
        $this->assertNotNull($this->ia->validar($this->receta(), [], 'sin_huevo')['error']);

        $sinHuevo = $this->receta();
        $sinHuevo['ingredientes'][1] = ['nombre' => 'Fécula de maíz', 'cantidad' => 2, 'unidad' => 'cda', 'peso' => 2];
        $this->assertNull($this->ia->validar($sinHuevo, [], 'sin_huevo')['error']);

        $conHarina = $this->receta();
        $conHarina['ingredientes'][] = ['nombre' => 'Harina 0000', 'cantidad' => 50, 'unidad' => 'g', 'peso' => 2];
        $this->assertNotNull($this->ia->validar($conHarina, [], 'sin_tacc')['error']);
        $this->assertNull($this->ia->validar($this->receta(), [], 'sin_tacc')['error']);

        $conQueso = $this->receta();
        $conQueso['ingredientes'][] = ['nombre' => 'Queso cremoso', 'cantidad' => 100, 'unidad' => 'g', 'peso' => 2];
        $this->assertNotNull($this->ia->validar($conQueso, [], 'sin_lactosa')['error']);
        $conAlmendras = $this->receta();
        $conAlmendras['ingredientes'][] = ['nombre' => 'Leche de almendras', 'cantidad' => 100, 'unidad' => 'ml'];
        $this->assertNull($this->ia->validar($conAlmendras, [], 'sin_lactosa')['error']);

        $conPollo = $this->receta();
        $conPollo['ingredientes'][] = ['nombre' => 'Pollo', 'cantidad' => 1, 'unidad' => 'u', 'peso' => 3];
        $this->assertNotNull($this->ia->validar($conPollo, [], 'vegetariana')['error']);
        $conJamon = $this->receta();
        $conJamon['ingredientes'][] = ['nombre' => 'Jamón cocido', 'cantidad' => 100, 'unidad' => 'g'];
        $this->assertNotNull($this->ia->validar($conJamon, [], 'vegetariana')['error']);
    }

    public function testGuardaLosCambiosDeUnaAdaptacionYSiempreHayUnPrincipal(): void
    {
        $receta = $this->receta(['cambios' => ['Huevo → fécula', '', 'Menos sal']]);
        foreach ($receta['ingredientes'] as $n => $ing) {
            $receta['ingredientes'][$n]['peso'] = 1;
        }
        $r = $this->ia->validar($receta)['receta'];
        $this->assertSame(['Huevo → fécula', 'Menos sal'], $r['cambios']);
        $this->assertSame(3, $r['ingredientes'][0]['peso']);
    }

    public function testMarcaLoQueTengoYLoQueFalta(): void
    {
        $receta = RecetaIa::marcar($this->ia->validar($this->receta())['receta'], [5, 30]);
        $this->assertSame([true, false, true, false], array_column($receta['ingredientes'], 'tengo'));
    }

    public function testValidaUnReemplazo(): void
    {
        $r = $this->ia->validarReemplazo(['reemplazo' => 'leche', 'ratio' => 0.5, 'nota' => 'Queda más liviano.']);
        $this->assertSame(
            ['texto' => 'Leche', 'ingrediente_id' => 24, 'ratio' => 0.5, 'nota' => 'Queda más liviano.'],
            $r['reemplazo']
        );
        $r = $this->ia->validarReemplazo(['reemplazo' => 'Yogur natural', 'ratio' => 1, 'nota' => '']);
        $this->assertNull($r['reemplazo']['ingrediente_id']);
        $this->assertNotNull($this->ia->validarReemplazo(['reemplazo' => 'Leche', 'ratio' => 1], [24])['error']);
        $this->assertNotNull($this->ia->validarReemplazo(['reemplazo' => 'Leche', 'ratio' => 0])['error']);
    }

    public function testSoloOfreceLasAdaptacionesQueHacenFalta(): void
    {
        $tortilla = [
            ['nombre' => 'Huevo', 'categoria_id' => '5'],
            ['nombre' => 'Papa', 'categoria_id' => '1'],
            ['nombre' => 'Cebolla', 'categoria_id' => '1'],
        ];
        $this->assertSame(['sin_huevo'], array_keys(RecetaIa::adaptacionesPara($tortilla, false)));
        $this->assertSame(
            ['sin_huevo', 'con_lo_que_tengo'],
            array_keys(RecetaIa::adaptacionesPara($tortilla, true)),
            '"Con lo que tengo" solo si falta algo'
        );
        $milanesas = [
            ['nombre' => 'Pollo', 'categoria_id' => 4],
            ['nombre' => 'Pan rallado', 'categoria_id' => null],
            ['nombre' => 'Queso rallado', 'categoria_id' => 3],
        ];
        $this->assertSame(
            ['sin_tacc', 'sin_lactosa', 'vegetariana'],
            array_keys(RecetaIa::adaptacionesPara($milanesas, false))
        );
        $this->assertSame([], RecetaIa::adaptacionesPara([['nombre' => 'Zapallito', 'categoria_id' => 1]], false));
    }

    public function testLaClaveDeLaCacheNoDependeDelOrden(): void
    {
        $a = RecetaIa::clave('receta', [
            'despensa' => [['nombre' => 'Huevo', 'nivel' => 'normal'], ['nombre' => 'Zapallito', 'nivel' => 'poco']],
            'opciones' => ['porciones' => 2, 'tipo' => 'rapida'],
        ]);
        $b = RecetaIa::clave('receta', [
            'opciones' => ['tipo' => 'rapida', 'porciones' => 2],
            'despensa' => [['nivel' => 'poco', 'nombre' => 'zapallito'], ['nivel' => 'normal', 'nombre' => 'Huevo']],
        ]);
        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
        $this->assertNotSame($a, RecetaIa::clave('adaptar', ['opciones' => ['tipo' => 'rapida', 'porciones' => 2]]));
        $this->assertNotSame($a, RecetaIa::clave('receta', ['opciones' => ['tipo' => 'dulce', 'porciones' => 2]]));
    }
}
