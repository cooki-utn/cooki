<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

use Cooki\Db;
use Cooki\Repositorios\IaRepositorio;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * La flecha de volver (enlaceVolver): vuelve a la página de la que se vino, la recuerda aunque la página
 * se recargue y, si no se sabe de dónde vino, va a la página de siempre. También: Mi cocina separa lo vencido.
 */
final class NavegacionTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            Db::conexion();
        } catch (PDOException $e) {
            $this->markTestSkipped('Sin base de datos disponible: ' . $e->getMessage());
        }
    }

    private static function barra(string $html): string
    {
        preg_match('#<nav class="volver-barra".*?</nav>#s', $html, $m);
        return $m[0] ?? '';
    }

    public function testSinSaberDeDondeVinoVuelveALasRecetas(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/receta.php?id=1');
        $barra = self::barra($html);
        $this->assertStringContainsString('Volver a Recetas', $barra);
        $this->assertStringNotContainsString('class="atajo"', $barra, 'el atajo a Recetas no se repite');
    }

    public function testVuelveALaPaginaDeLaQueVinoYLaRecuerda(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->desde('app/index.php')->get('app/receta.php?id=1');
        $barra = self::barra($html);
        $this->assertStringContainsString('Volver a Mi cocina', $barra);
        $this->assertMatchesRegularExpression('#href="[^"]*app/index\.php\?atras=1"#', $barra);
        $this->assertStringContainsString('class="atajo"', $barra, 'y un atajo a Recetas');

        // Cambiar las porciones recarga la misma página: sigue volviendo a Mi cocina
        [, $html] = $web->desde('app/receta.php?id=1')->get('app/receta.php?id=1&porciones=6');
        $this->assertStringContainsString('Volver a Mi cocina', self::barra($html));

        // Con los filtros del listado: vuelve a la misma búsqueda
        [, $html] = $web->desde('app/recetas.php?q=papa&pagina=2')->get('app/receta.php?id=2');
        $this->assertStringContainsString('app/recetas.php?q=papa&amp;pagina=2&amp;atras=1', self::barra($html));
    }

    public function testNoVuelveAOtroSitio(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->desde('https://otro-sitio.example/app/index.php')->get('app/receta.php?id=1');
        $this->assertStringContainsString('Volver a Recetas', self::barra($html));
        $this->assertStringNotContainsString('otro-sitio', $html);
    }

    public function testLasRecetasDeIaVuelvenAMisRecetasDeIaYAMiCocina(): void
    {
        $db = Db::conexion();
        $maria = (int) $db->query("SELECT id FROM usuario WHERE email = 'maria@cooki.test'")->fetchColumn();
        $repo = new IaRepositorio($db);
        $id = $repo->crearReceta($maria, 'nueva', null, ['titulo' => 'Prueba de volver', 'porciones' => 2,
            'minutos_prep' => 5, 'minutos_coccion' => 0, 'dificultad' => 1, 'etiquetas' => [], 'descripcion' => '',
            'ingredientes' => [['id' => 5, 'nombre' => 'Zapallito', 'categoria_id' => 1, 'cantidad' => 1.0,
                'unidad' => 'u', 'peso' => 3]],
            'pasos' => [['texto' => 'Cortar.', 'minutos' => null]], 'por_que' => '', 'cambios' => []]);
        $repo->marcarGuardada($maria, $id, true);
        try {
            $web = new Navegador();
            $web->ingresar('maria@cooki.test', 'Usuario1234');
            [, $html] = $web->get('app/receta-ia.php?id=' . $id);
            $barra = self::barra($html);
            $this->assertStringContainsString('Volver a Mis recetas de IA', $barra, 'Sin saber de dónde vino');
            $this->assertStringNotContainsString('>Mis recetas de IA</a>', $barra, 'El atajo no repite la flecha');
            [, $html] = $web->desde('app/index.php')->get('app/mis-recetas-ia.php');
            $this->assertStringContainsString('Volver a Mi cocina', self::barra($html));
            $this->assertStringContainsString('Prueba de volver', $html);
            [, $html] = $web->desde('app/mis-recetas-ia.php')->get('app/receta-ia.php?id=' . $id);
            $this->assertStringContainsString('Volver a Mis recetas de IA', self::barra($html));
            [, $html] = $web->desde('app/index.php')->get('app/receta-ia.php?id=' . $id);
            $this->assertStringContainsString('Volver a Mi cocina', self::barra($html));
            $this->assertStringContainsString('class="atajo"', self::barra($html), 'y un atajo a Mis recetas de IA');
        } finally {
            $repo->borrarReceta($maria, $id);
        }
    }

    public function testDeLaAdministracionDeLaIaSeVuelveALaIa(): void
    {
        $web = new Navegador();
        $web->ingresar('admin@cooki.test', 'Admin1234');
        [, $html] = $web->desde('admin/ia.php')->get('app/receta.php?id=1');
        $this->assertStringContainsString('Volver a la IA', self::barra($html));
    }

    public function testMiCocinaNoPoneLoVencidoEnUsaloPronto(): void
    {
        $vencidos = (int) Db::conexion()->query(
            'SELECT COUNT(*) FROM despensa_item WHERE usuario_id = 3 AND nivel > 0 AND fecha_vencimiento < CURDATE()'
        )->fetchColumn();
        $this->assertGreaterThan(0, $vencidos, 'la despensa de María tiene algo vencido (seed.sql)');

        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/index.php');
        $this->assertStringContainsString('class="aviso-vencidos"', $html);
        $this->assertStringContainsString($vencidos . ' vencido', $html);
        $this->assertStringContainsString('app/despensa.php?estado=vencidos', $html);
        preg_match('#<ul class="lista-vencimientos">.*?</ul>#s', $html, $lista);
        $this->assertStringNotContainsString('Vencido', $lista[0] ?? '', 'solo lo que todavía se puede usar');
    }

    public function testUnIngredienteSinCantidadQuedaAGusto(): void
    {
        $admin = new Navegador();
        $admin->ingresar('admin@cooki.test', 'Admin1234');
        $admin->get('admin/receta-editar.php');
        $campos = [
            'titulo' => 'Prueba a gusto ' . uniqid(), 'descripcion' => '', 'porciones_base' => 2,
            'minutos_prep' => 5, 'minutos_coccion' => 0, 'dificultad' => 1, 'pasos' => 'Mezclar.',
            'ingredientes' => [['id' => 1, 'cantidad' => '1', 'unidad' => 'u', 'peso' => 2],
                ['id' => 2, 'cantidad' => '', 'unidad' => 'g', 'peso' => 1]],
        ];
        [$estado, $html] = $admin->post('admin/receta-editar.php', $campos);
        $this->assertSame(200, $estado, 'con unidad y sin cantidad no se guarda');
        $this->assertStringContainsString('unidad pero sin cantidad', $html);

        $campos['ingredientes'][1]['unidad'] = '';
        [$estado] = $admin->post('admin/receta-editar.php', $campos);
        $this->assertSame(302, $estado);
        $id = (int) Db::conexion()->query(
            'SELECT id FROM receta WHERE titulo = ' . Db::conexion()->quote($campos['titulo'])
        )->fetchColumn();
        try {
            [, $html] = $admin->get('app/receta.php?id=' . $id);
            $this->assertStringContainsString('a gusto', $html);
        } finally {
            Db::conexion()->exec('DELETE FROM receta WHERE id = ' . $id);
        }
    }
}
