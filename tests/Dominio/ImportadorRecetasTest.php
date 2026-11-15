<?php

declare(strict_types=1);

namespace Cooki\Tests\Dominio;

use Cooki\Dominio\ImportadorRecetas;
use PHPUnit\Framework\TestCase;

/** Validación del formato JSON de recetas, con un catálogo de prueba (sin base de datos). */
final class ImportadorRecetasTest extends TestCase
{
    private function importador(): ImportadorRecetas
    {
        return new ImportadorRecetas(
            [
                ['id' => 1, 'nombre' => 'Papa', 'categoria_id' => 1],
                ['id' => 15, 'nombre' => 'Huevo', 'categoria_id' => 5],
                ['id' => 16, 'nombre' => 'Harina 0000', 'categoria_id' => 6],
                ['id' => 13, 'nombre' => 'Carne picada', 'categoria_id' => 4],
            ],
            ['patata' => 1],
            ['vegetariana' => 1, 'sin_tacc' => 2]
        );
    }

    /** @return array<string, mixed> */
    private function receta(array $cambios = []): array
    {
        return $cambios + [
            'titulo' => 'Papas con huevo',
            'porciones' => 2,
            'minutos_prep' => 10,
            'minutos_coccion' => 15,
            'dificultad' => 1,
            'etiquetas' => ['vegetariana', 'sin_tacc'],
            'ingredientes' => [
                ['nombre' => 'patata', 'cantidad' => 500, 'unidad' => 'g', 'peso' => 3],
                ['nombre' => 'Huevo', 'cantidad' => 2, 'unidad' => 'u', 'nota' => 'batidos'],
            ],
            'pasos' => ['Hervir {ing:papa}.', ['texto' => 'Sumar el huevo y cocinar.', 'minutos' => 3]],
        ];
    }

    public function testUnaRecetaCorrectaQuedaListaParaGuardar(): void
    {
        $resultado = $this->importador()->validar($this->receta());
        $this->assertSame([], $resultado['errores']);
        $receta = $resultado['receta'];
        $this->assertSame('papas-con-huevo', $receta['datos']['slug']);
        $this->assertSame(1, $receta['ingredientes'][0]['id'], 'el sinónimo "patata" es Papa');
        $this->assertSame(2, $receta['ingredientes'][1]['peso'], 'sin peso, queda secundario');
        $this->assertSame('batidos', $receta['ingredientes'][1]['nota']);
        $this->assertSame(180, $receta['pasos'][1]['segundos']);
        $this->assertSame([1, 2], $receta['etiquetas']);
    }

    public function testDetectaIngredientesDesconocidosYRepetidos(): void
    {
        $errores = $this->importador()->validar($this->receta(['ingredientes' => [
            ['nombre' => 'Papa'], ['nombre' => 'patata'], ['nombre' => 'Unicornio'],
        ]]))['errores'];
        $this->assertCount(2, $errores);
        $this->assertStringContainsString('repetido', $errores[0]);
        $this->assertStringContainsString('Unicornio', $errores[1]);
    }

    public function testRevisaQueLasEtiquetasSeanCiertas(): void
    {
        $errores = $this->importador()->validar($this->receta(['ingredientes' => [
            ['nombre' => 'Carne picada'], ['nombre' => 'Harina 0000'],
        ], 'pasos' => ['Mezclar.']]))['errores'];
        $this->assertSame(
            ['Dice vegetariana pero lleva Carne picada.', 'Dice sin_tacc pero lleva Harina 0000.'],
            $errores
        );
    }

    public function testRevisaLosTiposNuevosYCalculaRapida(): void
    {
        $importador = new ImportadorRecetas(
            [
                ['id' => 1, 'nombre' => 'Papa', 'categoria_id' => 1],
                ['id' => 15, 'nombre' => 'Huevo', 'categoria_id' => 5],
                ['id' => 10, 'nombre' => 'Manteca', 'categoria_id' => 3],
                ['id' => 73, 'nombre' => 'Miel', 'categoria_id' => 6],
            ],
            [],
            ['vegana' => 5, 'sin_lactosa' => 6, 'sin_huevo' => 7, 'rapida' => 8]
        );
        $errores = $importador->validar($this->receta([
            'etiquetas' => ['vegana', 'sin_lactosa', 'sin_huevo'],
            'ingredientes' => [['nombre' => 'Papa'], ['nombre' => 'Manteca'], ['nombre' => 'Miel']],
            'pasos' => ['Mezclar.'],
        ]))['errores'];
        $this->assertSame([
            'Dice vegana pero lleva Manteca.',
            'Dice vegana pero lleva Miel.',
            'Dice sin_lactosa pero lleva Manteca.',
        ], $errores);

        // 10 + 15 minutos: "rapida" se agrega sola; si se escribe en el JSON, es un error
        $bien = $importador->validar($this->receta([
            'etiquetas' => ['sin_lactosa'], 'ingredientes' => [['nombre' => 'Papa']], 'pasos' => ['Hervir.'],
        ]));
        $this->assertSame([6, 8], $bien['receta']['etiquetas']);
        $lenta = $importador->validar($this->receta([
            'etiquetas' => [], 'minutos_coccion' => 40,
            'ingredientes' => [['nombre' => 'Papa']], 'pasos' => ['Hervir.'],
        ]));
        $this->assertSame([], $lenta['receta']['etiquetas']);
        $escrita = $importador->validar($this->receta([
            'etiquetas' => ['rapida'], 'ingredientes' => [['nombre' => 'Papa']], 'pasos' => ['Hervir.'],
        ]));
        $this->assertStringContainsString('se calcula sola', $escrita['errores'][0]);
    }

    public function testRevisaNumerosUnidadesYMarcadores(): void
    {
        $errores = $this->importador()->validar($this->receta([
            'porciones' => 0,
            'dificultad' => 5,
            'ingredientes' => [['nombre' => 'Papa', 'cantidad' => -1, 'unidad' => 'balde', 'peso' => 7]],
            'pasos' => ['Agregar {ing:cebolla}.', ['texto' => 'Esperar.', 'minutos' => 999]],
        ]))['errores'];
        $this->assertCount(7, $errores);
        $this->assertStringContainsString('{ing:cebolla}', implode(' ', $errores));
    }

    public function testFaltanDatosObligatorios(): void
    {
        $this->assertNotSame([], $this->importador()->validar(['titulo' => 'Nada'])['errores']);
        $this->assertSame(['No es un objeto JSON válido.'], $this->importador()->validar(null)['errores']);
    }

    public function testElSqlEscapaComillasYUsaLosIds(): void
    {
        $receta = $this->importador()->validar($this->receta(['descripcion' => "La de l'abuela"]))['receta'];
        $sql = ImportadorRecetas::aSql($receta);
        $this->assertStringContainsString("'La de l''abuela'", $sql);
        $this->assertStringContainsString("(@receta, 1, 500, 'g', 3, FALSE, NULL)", $sql);
        $this->assertStringContainsString("(@receta, 2, 'Sumar el huevo y cocinar.', 180)", $sql);
    }
}
