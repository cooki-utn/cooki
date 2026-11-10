<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

use Cooki\Db;
use Cooki\Repositorios\UsuarioRepositorio;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Plan semanal (incremento 3 · HU-35): la página, guardar, cambiar y quitar, lo que no se acepta y la nota "Hoy"
 * de Mi cocina. Usa una cuenta nueva.
 */
final class PlanTest extends TestCase
{
    private int $usuario = 0;
    private string $email = '';
    private Navegador $web;

    protected function setUp(): void
    {
        try {
            $db = Db::conexion();
        } catch (PDOException $e) {
            $this->markTestSkipped('Sin base de datos disponible: ' . $e->getMessage());
        }
        $this->email = 'plan-' . uniqid() . '@cooki.test';
        $this->usuario = (new UsuarioRepositorio($db))->crear('Planificadora', $this->email, 'Usuario1234');
        $this->web = new Navegador();
        $this->web->ingresar($this->email, 'Usuario1234');
    }

    protected function tearDown(): void
    {
        if ($this->usuario) {
            Db::conexion()->prepare('DELETE FROM usuario WHERE id = ?')->execute([$this->usuario]);
        }
    }

    /** @return array<string, array<string, mixed>> lo guardado, por "fecha|momento" */
    private function plan(): array
    {
        $consulta = Db::conexion()->prepare(
            "SELECT CONCAT(fecha, '|', momento) AS lugar, receta_id, receta_ia_id, porciones
             FROM plan_comida WHERE usuario_id = ?"
        );
        $consulta->execute([$this->usuario]);
        return $consulta->fetchAll(\PDO::FETCH_UNIQUE);
    }

    /** @return array{id: int, titulo: string, porciones_base: int} una receta cualquiera del recetario */
    private function receta(): array
    {
        $fila = Db::conexion()->query('SELECT id, titulo, porciones_base FROM receta ORDER BY id LIMIT 1')->fetch();
        return [
            'id' => (int) $fila['id'], 'titulo' => (string) $fila['titulo'],
            'porciones_base' => (int) $fila['porciones_base'],
        ];
    }

    public function testLaPaginaPideSesionYMuestraLaSemanaConElImanPlan(): void
    {
        [$estado, , $destino] = (new Navegador())->get('app/plan.php');
        $this->assertSame(302, $estado);
        $this->assertStringEndsWith('error.php?motivo=sin-sesion', $destino);

        [$estado, $html] = $this->web->get('app/plan.php');
        $this->assertSame(200, $estado);
        $this->assertSame(7, substr_count($html, 'class="postit plan-dia'));
        $this->assertSame(14, substr_count($html, 'name="receta"'));
        $this->assertStringContainsString('id="dia-' . date('Y-m-d') . '"', $html);
        $this->assertStringContainsString('Todo el recetario', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function testGuardarCambiarYQuitarUnaComida(): void
    {
        $receta = $this->receta();
        $hoy = date('Y-m-d');
        $this->web->get('app/plan.php');
        [, , $destino] = $this->web->post('app/plan.php', [
            'accion' => 'guardar', 'fecha' => $hoy, 'momento' => 'cena', 'receta' => 'r:' . $receta['id'],
            'porciones' => '',
        ]);
        $this->assertStringEndsWith('app/plan.php#dia-' . $hoy, $destino);
        $plan = $this->plan();
        $porciones = (int) $plan[$hoy . '|cena']['porciones'];
        $this->assertSame($receta['porciones_base'], $porciones, 'Sin porciones, las de la receta');

        // Cambiar: misma comida, 3 porciones (no se duplica)
        $this->web->post('app/plan.php', [
            'accion' => 'guardar', 'fecha' => $hoy, 'momento' => 'cena', 'receta' => 'r:' . $receta['id'],
            'porciones' => '3',
        ]);
        $plan = $this->plan();
        $this->assertCount(1, $plan);
        $this->assertSame(3, (int) $plan[$hoy . '|cena']['porciones']);

        // Mi cocina lo muestra en la nota "Hoy", con "Cocinar"
        [, $html] = $this->web->get('app/index.php');
        $this->assertStringContainsString('id="titulo-hoy"', $html);
        $this->assertStringContainsString(e($receta['titulo']), $html);
        $this->assertStringContainsString('class="nota-hoy__cocinar"', $html);

        $this->web->get('app/plan.php');
        $this->web->post('app/plan.php', ['accion' => 'quitar', 'fecha' => $hoy, 'momento' => 'cena']);
        $this->assertSame([], $this->plan());
        [, $html] = $this->web->get('app/index.php');
        $this->assertStringContainsString('Todavía no elegiste qué cocinar hoy.', $html);
    }

    public function testNoSeAceptanOtrosDiasNiRecetasDeIaAjenas(): void
    {
        $receta = $this->receta();
        $this->web->get('app/plan.php');
        foreach ([date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+7 day'))] as $fecha) {
            $this->web->post('app/plan.php', [
                'accion' => 'guardar', 'fecha' => $fecha, 'momento' => 'almuerzo', 'receta' => 'r:' . $receta['id'],
            ]);
        }
        $this->web->post('app/plan.php', [
            'accion' => 'guardar', 'fecha' => date('Y-m-d'), 'momento' => 'merienda', 'receta' => 'r:' . $receta['id'],
        ]);
        // Una receta de IA de otra persona (o una que no se guardó) no entra
        $db = Db::conexion();
        $db->prepare(
            "INSERT INTO receta_ia (usuario_id, tipo, titulo, contenido, guardada)
             VALUES (?, 'nueva', 'Sin guardar', '{}', 1)"
        )->execute([$this->usuario]);
        $propiaSinGuardar = (int) $db->lastInsertId();
        $db->prepare('UPDATE receta_ia SET guardada = 0 WHERE id = ?')->execute([$propiaSinGuardar]);
        $db->prepare(
            "INSERT INTO receta_ia (usuario_id, tipo, titulo, contenido, guardada)
             VALUES (3, 'nueva', 'De María', '{}', 1)"
        )->execute();
        $ajena = (int) $db->lastInsertId();
        try {
            foreach (['ia:' . $propiaSinGuardar, 'ia:' . $ajena, 'r:99999999', 'algo'] as $valor) {
                [, , $destino] = $this->web->post('app/plan.php', [
                    'accion' => 'guardar', 'fecha' => date('Y-m-d'), 'momento' => 'cena', 'receta' => $valor,
                ]);
                $this->assertStringContainsString('app/plan.php', $destino, $valor);
            }
        } finally {
            $db->prepare('DELETE FROM receta_ia WHERE id = ?')->execute([$ajena]);
        }
        $this->assertSame([], $this->plan());
    }

    public function testAlGuardarSeBorraLoDeDiasQueYaPasaron(): void
    {
        $receta = $this->receta();
        Db::conexion()->prepare(
            "INSERT INTO plan_comida (usuario_id, fecha, momento, receta_id, porciones)
             VALUES (?, CURDATE() - INTERVAL 2 DAY, 'cena', ?, 2)"
        )->execute([$this->usuario, $receta['id']]);
        $this->web->get('app/plan.php');
        $manana = date('Y-m-d', strtotime('+1 day'));
        $this->web->post('app/plan.php', [
            'accion' => 'guardar', 'fecha' => $manana, 'momento' => 'almuerzo', 'receta' => 'r:' . $receta['id'],
        ]);
        $this->assertSame([$manana . '|almuerzo'], array_keys($this->plan()));
    }

    public function testConFetchRespondeJson(): void
    {
        $receta = $this->receta();
        [, $html] = $this->web->get('app/plan.php');
        preg_match('/name="csrf" value="([^"]+)"/', $html, $m);
        $respuesta = $this->web->postFetch('app/plan.php', [
            'csrf' => $m[1], 'accion' => 'guardar', 'fecha' => date('Y-m-d'), 'momento' => 'almuerzo',
            'receta' => 'r:' . $receta['id'], 'porciones' => '5',
        ]);
        $this->assertSame(200, $respuesta[0]);
        $datos = json_decode($respuesta[1], true);
        $this->assertTrue($datos['ok']);
        $this->assertSame(5, $datos['porciones']);
        $this->assertSame('Anotado para el almuerzo de hoy.', $datos['mensaje']);
    }

    public function testArmarLaSemanaCompletaTodoSinRepetirNiUsarLoQueNoCome(): void
    {
        $db = Db::conexion();
        $db->prepare('INSERT INTO restriccion_usuario (usuario_id, ingrediente_id) VALUES (?, 15)')   // huevo
            ->execute([$this->usuario]);
        $receta = $this->receta();
        $this->web->get('app/plan.php');
        $this->web->post('app/plan.php', [
            'accion' => 'guardar', 'fecha' => date('Y-m-d'), 'momento' => 'cena', 'receta' => 'r:' . $receta['id'],
            'porciones' => '3',
        ]);
        $this->web->get('app/plan.php');
        $this->web->post('app/plan.php', ['accion' => 'armar']);

        $plan = $this->plan();
        $this->assertCount(14, $plan, 'Se completan los 14 lugares');
        $this->assertSame(3, (int) $plan[date('Y-m-d') . '|cena']['porciones'], 'Lo que ya estaba no se toca');
        $recetas = array_map('intval', array_column($plan, 'receta_id'));
        $this->assertSame($recetas, array_values(array_unique($recetas)), 'No se repiten recetas');
        $conHuevo = $db->prepare(
            'SELECT COUNT(*) FROM plan_comida p JOIN receta_ingrediente ri ON ri.receta_id = p.receta_id
             WHERE p.usuario_id = ? AND ri.ingrediente_id = 15 AND ri.es_opcional = 0 AND p.receta_id <> ?'
        );
        $conHuevo->execute([$this->usuario, $receta['id']]);
        $this->assertSame(0, (int) $conHuevo->fetchColumn(), 'Armar no elige recetas con algo que no come');

        [, , $destino] = $this->web->post('app/plan.php', ['accion' => 'armar']);
        $this->assertStringEndsWith('app/plan.php', $destino);
        [, $html] = $this->web->get('app/plan.php');
        $this->assertStringContainsString('Ya tenés toda la semana planeada.', $html);
    }

    public function testLoQueFaltaParaLaSemanaVaALaListaUnaSolaVez(): void
    {
        $receta = $this->receta();
        $hoy = date('Y-m-d');
        $this->web->get('app/plan.php');
        $this->web->post('app/plan.php', [
            'accion' => 'guardar', 'fecha' => $hoy, 'momento' => 'almuerzo', 'receta' => 'r:' . $receta['id'],
        ]);
        [, $html] = $this->web->get('app/plan.php');
        $this->assertStringContainsString('Falta:', $html, 'La despensa está vacía: falta todo lo que no es básico');
        $this->assertStringContainsString('Agregar lo que falta para la semana a la lista', $html);

        $this->web->post('app/plan.php', ['accion' => 'faltantes']);
        $cuantos = Db::conexion()->prepare(
            'SELECT COUNT(*) FROM lista_compra_item WHERE usuario_id = ? AND receta_id = ? AND comprado_en IS NULL'
        );
        $cuantos->execute([$this->usuario, $receta['id']]);
        $agregados = (int) $cuantos->fetchColumn();
        $this->assertGreaterThan(0, $agregados);

        // Otra vez (toda la semana o solo esa comida): no se duplica, y la comida dice que ya está en la lista
        $this->web->get('app/plan.php');
        $this->web->post('app/plan.php', ['accion' => 'faltantes', 'fecha' => $hoy, 'momento' => 'almuerzo']);
        $cuantos->execute([$this->usuario, $receta['id']]);
        $this->assertSame($agregados, (int) $cuantos->fetchColumn());
        [, $html] = $this->web->get('app/plan.php');
        $this->assertStringContainsString('Ya está en tu lista', $html);
        $this->assertStringNotContainsString('Agregar lo que falta para la semana a la lista', $html);
    }
}
