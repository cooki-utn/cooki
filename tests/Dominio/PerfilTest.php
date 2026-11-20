<?php

declare(strict_types=1);

namespace Cooki\Tests\Dominio;

use Cooki\Dominio\Perfil;
use PHPUnit\Framework\TestCase;

/** Lo que se cuenta en la bienvenida y cómo cambia las recetas (sin base de datos). */
final class PerfilTest extends TestCase
{
    public function testDelFormularioQuedaSoloLoQueSeConoce(): void
    {
        $perfil = Perfil::desdeFormulario([
            'dieta' => ['todo', 'vegana', 'vegetariana', 'carnivora', 'sin_tacc', 'vegana'],
            'gustos' => ['dulce', 'picante'],
            'nivel' => '7',
            'comensales' => '3',
        ]);
        $this->assertSame(['vegana', 'sin_tacc'], $perfil['dietas'], 'vegana ya incluye vegetariana');
        $this->assertSame(['dulce'], $perfil['gustos']);
        $this->assertNull($perfil['nivel']);
        $this->assertSame(3, $perfil['comensales']);

        $vacio = Perfil::desdeFormulario(['dieta' => 'vegana', 'nivel' => ['1']]);
        $this->assertSame(['dietas' => [], 'gustos' => [], 'nivel' => null, 'comensales' => null], $vacio);
    }

    public function testLaRecetaTieneQueTenerTodosLosTiposDeLaDieta(): void
    {
        $this->assertTrue(Perfil::vaConLaDieta(['economica'], []), 'sin dieta, todo va');
        $this->assertTrue(Perfil::vaConLaDieta(['vegana', 'sin_tacc'], ['vegetariana', 'sin_tacc']));
        $this->assertFalse(Perfil::vaConLaDieta(['vegetariana'], ['vegetariana', 'sin_tacc']));
        $this->assertFalse(Perfil::vaConLaDieta(['vegetariana'], ['vegana']));
    }

    public function testLosGustosSubenYLoDificilBajaSegunElNivel(): void
    {
        $this->assertSame(10, Perfil::ajuste(['dulce', 'rapida', 'vegana'], 1, ['dulce', 'rapida'], 1));
        $this->assertSame(-12, Perfil::ajuste(['economica'], 3, ['dulce'], 1), 'difícil para quien recién empieza');
        $this->assertSame(0, Perfil::ajuste(['economica'], 3, [], null), 'sin nivel no cambia nada');
    }

    public function testArmaLasListasDeIngredientesDelFormulario(): void
    {
        $grupos = ['pescado' => [68, 36]];
        $this->assertSame(
            [15, 68, 36],
            Perfil::ingredientesElegidos(['i:15', 'g:pescado', 'g:inventado', 'x:3', 'i:abc', 'i:15'], $grupos)
        );
        $this->assertSame([], Perfil::ingredientesElegidos('i:15'));
        $this->assertSame(
            ['Maní tostado', 'Kale'],
            Perfil::nombresNuevos(['  maní   tostado ', 'Maní tostado', 'x', 'kale', str_repeat('a', 81)])
        );
        $this->assertSame(['Palmitos'], Perfil::nombresNuevos('palmitos'));
        $muchos = array_map(fn ($n) => 'cosa ' . $n, range(1, 20));
        $this->assertCount(Perfil::MAXIMO_NUEVOS, Perfil::nombresNuevos($muchos));
    }

    public function testElPlanUsaLasPorcionesDeLaPersona(): void
    {
        $this->assertSame(3, Perfil::porcionesDelPlan(3, 6));
        $this->assertSame(6, Perfil::porcionesDelPlan(null, 6));
    }
}
