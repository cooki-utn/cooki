<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

use Cooki\Db;
use Cooki\Repositorios\RecetaRepositorio;
use Cooki\Repositorios\UsuarioRepositorio;
use PDOException;
use PHPUnit\Framework\TestCase;

/** Mis preferencias (incremento 2 · P1 + P3): siempre tengo, no puedo comer y favoritas. */
final class PreferenciasTest extends TestCase
{
    private int $usuario = 0;
    private string $email = '';

    protected function setUp(): void
    {
        try {
            $db = Db::conexion();
        } catch (PDOException $e) {
            $this->markTestSkipped('Sin base de datos disponible: ' . $e->getMessage());
        }
        $this->email = 'prefs-' . uniqid() . '@cooki.test';
        $this->usuario = (new UsuarioRepositorio($db))->crear('Preferencias', $this->email, 'Usuario1234');
    }

    protected function tearDown(): void
    {
        if ($this->usuario) {
            Db::conexion()->prepare('DELETE FROM usuario WHERE id = ?')->execute([$this->usuario]);
        }
    }

    private function tortilla(): array
    {
        $todas = (new RecetaRepositorio(Db::conexion()))->buscar(['q' => 'Tortilla de papas'], $this->usuario);
        return array_values(array_filter($todas, fn ($r) => (int) $r['id'] === 1))[0];
    }

    public function testSiempreTengoCuentaComoDisponible(): void
    {
        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        $antes = (int) $this->tortilla()['tengo'];

        $web->get('app/mis-preferencias.php');
        [$estado, , $destino] = $web->post('app/mis-preferencias.php', [
            'accion' => 'agregar', 'lista' => 'basicos', 'ingrediente' => 'Papa',
        ]);
        $this->assertSame(302, $estado);
        $this->assertStringEndsWith('#siempre-tengo', $destino);
        $this->assertSame($antes + 1, (int) $this->tortilla()['tengo']);
        [, $html] = $web->get('app/mis-preferencias.php');
        $this->assertStringContainsString('<span>Papa</span>', $html);

        // Un nombre que no está en la lista no se agrega y se avisa
        $web->post('app/mis-preferencias.php', [
            'accion' => 'agregar', 'lista' => 'basicos', 'ingrediente' => 'Ingrediente inventado',
        ]);
        [, $html] = $web->get('app/mis-preferencias.php');
        $this->assertStringContainsString('No encontramos', $html);

        $web->post('app/mis-preferencias.php', ['accion' => 'quitar', 'lista' => 'basicos', 'id' => 1]);
        $this->assertSame($antes, (int) $this->tortilla()['tengo']);
    }

    public function testNoPuedoComerSacaLasRecomendacionesYAvisaEnElCatalogo(): void
    {
        $db = Db::conexion();
        // Con papa, huevo y cebolla la tortilla se recomienda
        $db->prepare(
            "INSERT INTO despensa_item (usuario_id, ingrediente_id, nivel, ubicacion) VALUES
             (:u1, 1, 2, 'alacena'), (:u2, 15, 2, 'heladera'), (:u3, 2, 2, 'alacena')"
        )->execute(['u1' => $this->usuario, 'u2' => $this->usuario, 'u3' => $this->usuario]);
        $ids = fn () => array_map(
            fn ($r) => (int) $r['id'],
            (new RecetaRepositorio($db))->recomendadas($this->usuario, 100)
        );
        $this->assertContains(1, $ids());

        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        $web->get('app/mis-preferencias.php');
        $web->post('app/mis-preferencias.php', [
            'accion' => 'agregar', 'lista' => 'restricciones', 'ingrediente' => 'Huevo',
        ]);
        $this->assertNotContains(1, $ids());
        [, $html] = $web->get('app/recetas.php?q=tortilla');
        $this->assertStringContainsString('Tiene algo que no comés', $html);
        [, $html] = $web->get('app/receta.php?id=1');
        $this->assertStringContainsString('No lo comés', $html);
    }

    public function testFavoritas(): void
    {
        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        [, $html] = $web->get('app/receta.php?id=1');
        $this->assertStringContainsString('aria-pressed="false"', $html);
        $web->post('app/mis-preferencias.php', [
            'accion' => 'favorita', 'receta' => 1, 'volver' => '/app/receta.php?id=1',
        ]);
        [, $html] = $web->get('app/receta.php?id=1');
        $this->assertStringContainsString('En mis favoritas', $html);

        [, $html] = $web->get('app/recetas.php?favoritas=1');
        $this->assertStringContainsString('1 receta encontrada<', $html);
        $this->assertStringContainsString('Tortilla de papas', $html);
        [, $html] = $web->get('app/mis-preferencias.php');
        $this->assertStringContainsString('Tortilla de papas', $html);

        // Un "volver" a otro sitio no se sigue
        [, , $destino] = $web->post('app/mis-preferencias.php', [
            'accion' => 'favorita', 'receta' => 1, 'volver' => '//otro-sitio.com/',
        ]);
        $this->assertStringEndsWith('app/mis-preferencias.php#favoritas', $destino);
        [, $html] = $web->get('app/recetas.php?favoritas=1');
        $this->assertStringContainsString('0 recetas encontradas', $html);
    }
}
