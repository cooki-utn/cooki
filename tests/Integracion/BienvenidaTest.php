<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

use Cooki\Db;
use Cooki\Dominio\Tipos;
use Cooki\Repositorios\PreferenciasRepositorio;
use Cooki\Repositorios\RecetaRepositorio;
use Cooki\Repositorios\UsuarioRepositorio;
use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/** Bienvenida y tipos de receta (PR D): "Contanos cómo comés" y lo que cambia en las recetas. */
final class BienvenidaTest extends TestCase
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
        $this->email = 'bienvenida-' . uniqid() . '@cooki.test';
        $this->usuario = (new UsuarioRepositorio($db))->crear('Bienvenida', $this->email, 'Usuario1234');
    }

    protected function tearDown(): void
    {
        // Con UsuarioRepositorio::borrar(), que también sirve para quien tiene ingredientes propios en sus listas
        $db = Db::conexion();
        $nueva = $db->prepare('SELECT id FROM usuario WHERE email = ?');
        $nueva->execute(['nueva-' . $this->email]);
        foreach ([$this->usuario, (int) $nueva->fetchColumn()] as $usuario) {
            if ($usuario) {
                (new UsuarioRepositorio($db))->borrar($usuario);
            }
        }
    }

    public function testCrearCuentaLlevaALaBienvenida(): void
    {
        $web = new Navegador();
        $web->get('registro.php');
        [$estado, , $destino] = $web->post('registro.php', [
            'nombre' => 'Nueva', 'email' => 'nueva-' . $this->email,
            'contrasena' => 'Usuario1234', 'repetir' => 'Usuario1234',
        ]);
        $this->assertSame(302, $estado);
        $this->assertStringEndsWith('app/bienvenida.php?nueva=1', $destino);
        [$estado, $html] = $web->get('app/bienvenida.php?nueva=1');
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('¡Hola, Nueva! ¿Cómo comés?', $html);
        $this->assertStringContainsString('Lo hago después', $html);
        $this->assertStringNotContainsString('volver-barra', $html, 'la primera vez no hay flecha de volver');
    }

    public function testGuardaTodoYCambiaLasRecetas(): void
    {
        $db = Db::conexion();
        $arroz = (int) $db->query("SELECT id FROM ingrediente WHERE nombre = 'Arroz' AND usuario_id IS NULL")
            ->fetchColumn();
        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        $web->get('app/bienvenida.php');
        [$estado, , $destino] = $web->post('app/bienvenida.php', [
            'dieta' => ['vegana'],
            'nocomo' => ['g:pescado'],
            'nocomo_otro' => 'maní tostado',
            'gustos' => ['dulce', 'rapida'],
            'nivel' => '1',
            'comensales' => '3',
            'basicos' => ['i:' . $arroz, 'i:999999'],
        ]);
        $this->assertSame(302, $estado);
        $this->assertStringEndsWith('app/index.php', $destino);

        $preferencias = new PreferenciasRepositorio($db);
        $this->assertSame(
            ['dietas' => ['vegana'], 'gustos' => ['dulce', 'rapida'], 'nivel' => 1, 'comensales' => 3],
            $preferencias->perfil($this->usuario)
        );
        $noCome = array_column($preferencias->ingredientes($this->usuario, 'restricciones'), 'nombre');
        sort($noCome);
        $this->assertSame(['Atún en lata', 'Maní tostado', 'Merluza'], $noCome, 'el grupo Pescado y uno nuevo');
        $propio = $db->prepare("SELECT usuario_id FROM ingrediente WHERE nombre = 'Maní tostado' AND usuario_id = ?");
        $propio->execute([$this->usuario]);
        $this->assertSame($this->usuario, (int) $propio->fetchColumn(), 'lo nuevo queda como ingrediente propio');
        $this->assertSame(
            ['Arroz'],
            array_column($preferencias->ingredientes($this->usuario, 'basicos'), 'nombre'),
            'un id que no existe se ignora'
        );

        // Mi cocina solo recomienda recetas veganas
        $db->prepare(
            "INSERT INTO despensa_item (usuario_id, ingrediente_id, nivel, ubicacion)
             SELECT :usuario, id, 2, 'alacena' FROM ingrediente WHERE usuario_id IS NULL"
        )->execute(['usuario' => $this->usuario]);
        $recomendadas = (new RecetaRepositorio($db))->recomendadas($this->usuario, 100);
        $this->assertNotEmpty($recomendadas);
        foreach ($recomendadas as $receta) {
            $this->assertContains('vegana', explode(', ', (string) $receta['etiquetas']), $receta['titulo']);
        }

        // En Recetas el filtro viene puesto y se puede sacar
        [, $html] = $web->get('app/recetas.php');
        $this->assertMatchesRegularExpression('/name="dieta" value="1" checked>\s*Solo de mi dieta \(Vegana\)/', $html);
        $conDieta = $this->cantidad($html);
        [, $html] = $web->get('app/recetas.php?dieta_enviada=1');
        $this->assertGreaterThan($conDieta, $this->cantidad($html));
        $this->assertStringContainsString('No va con tu dieta', $html);

        // Al volver a abrirla, muestra lo que eligió
        [, $html] = $web->get('app/bienvenida.php');
        $this->assertMatchesRegularExpression('/value="g:pescado" checked/', $html);
        $this->assertMatchesRegularExpression('/value="vegana" checked/', $html);
        $this->assertStringContainsString('Maní tostado', $html);
        $this->assertStringContainsString('volver-barra', $html);
    }

    public function testElPlanAnotaParaCuantosCocina(): void
    {
        $db = Db::conexion();
        (new PreferenciasRepositorio($db))->guardarPerfil(
            $this->usuario,
            ['dietas' => [], 'gustos' => [], 'nivel' => null, 'comensales' => 5]
        );
        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        $web->get('app/plan.php');
        $hoy = (new DateTimeImmutable('today'))->format('Y-m-d');
        $web->post('app/plan.php', ['accion' => 'guardar', 'fecha' => $hoy, 'momento' => 'cena', 'receta' => 'r:1']);
        $consulta = $db->prepare('SELECT porciones FROM plan_comida WHERE usuario_id = ?');
        $consulta->execute([$this->usuario]);
        $this->assertSame(5, (int) $consulta->fetchColumn());
    }

    public function testLasRecetasLlevanSusTiposConDibujoYLaDificultadConSemaforo(): void
    {
        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        [, $html] = $web->get('app/recetas.php?q=bruschettas');
        $this->assertStringContainsString('img/tipos/merienda_salada.svg', $html);
        $this->assertStringContainsString('<span>Sin lactosa</span>', $html);
        $this->assertStringContainsString('<span>Rápida</span>', $html, '15 minutos: se calcula sola');
        $this->assertStringContainsString('dificultad--1', $html);
        [, $html] = $web->get('app/recetas.php');
        $this->assertStringContainsString(
            'img/tipos/sin_tacc.svg" alt="" width="30" height="30"><span>Sin TACC (apta celíacos)</span>',
            $html,
            'la lista de tipos lleva los stickers'
        );
        $this->assertStringContainsString("classList.add('js')", $html, 'desde el <head> se sabe que hay JavaScript');
        $tipos = Db::conexion()->query('SELECT nombre FROM etiqueta')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertEqualsCanonicalizing(array_keys(Tipos::TIPOS), $tipos, 'la base tiene los 9 tipos');
    }

    private function cantidad(string $html): int
    {
        preg_match('/(\d+) recetas?( encontradas)?</', $html, $m);
        return (int) ($m[1] ?? 0);
    }
}
