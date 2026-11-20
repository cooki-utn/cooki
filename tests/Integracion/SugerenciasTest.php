<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

use Cooki\Db;
use Cooki\Repositorios\ContactoRepositorio;
use Cooki\Repositorios\IngredienteRepositorio;
use Cooki\Repositorios\UsuarioRepositorio;
use PDOException;
use PHPUnit\Framework\TestCase;

/** PR D, parte 3: "¿Falta algún tipo?" en Recetas y el aviso de ingredientes nuevos en Administración. */
final class SugerenciasTest extends TestCase
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
        $this->email = 'sugerencias-' . uniqid() . '@cooki.test';
        $this->usuario = (new UsuarioRepositorio($db))->crear('Sugerencias', $this->email, 'Usuario1234');
    }

    protected function tearDown(): void
    {
        if ($this->usuario) {
            $db = Db::conexion();
            $db->prepare('DELETE FROM contacto WHERE usuario_id = ?')->execute([$this->usuario]);
            (new UsuarioRepositorio($db))->borrar($this->usuario);
        }
    }

    public function testFaltaUnTipoLlegaComoConsultaConTopePorDia(): void
    {
        $web = new Navegador();
        $web->ingresar($this->email, 'Usuario1234');
        [, $html] = $web->get('app/recetas.php');
        $this->assertStringContainsString('¿Falta algún tipo?', $html);

        $sugerir = ['accion' => 'sugerir_tipo', 'tipo' => '  sin   azúcar '];
        [$estado, , $destino] = $web->post('app/recetas.php', $sugerir);
        $this->assertSame(302, $estado);
        $this->assertStringEndsWith('app/recetas.php?sugerido=1#falta-tipo', $destino);
        [, $html] = $web->get('app/recetas.php?sugerido=1');
        $this->assertStringContainsString('¡Gracias! Se lo pasamos al administrador.', $html);

        $consulta = Db::conexion()->prepare('SELECT nombre, email, asunto, mensaje FROM contacto WHERE usuario_id = ?');
        $consulta->execute([$this->usuario]);
        $this->assertSame([
            'nombre' => 'Sugerencias',
            'email' => $this->email,
            'asunto' => ContactoRepositorio::ASUNTO_TIPO,
            'mensaje' => 'Me gustaría que haya recetas del tipo: sin azúcar',
        ], $consulta->fetch());

        // Muy corto: no se guarda y se avisa en la nota
        [$estado, $html] = $web->post('app/recetas.php', ['accion' => 'sugerir_tipo', 'tipo' => 'x']);
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('de 3 a 60 letras', $html);

        // Tope por día
        for ($i = 2; $i <= ContactoRepositorio::TIPOS_POR_DIA; $i++) {
            $web->post('app/recetas.php', ['accion' => 'sugerir_tipo', 'tipo' => 'tipo ' . $i]);
            $web->get('app/recetas.php');
        }
        [, $html] = $web->post('app/recetas.php', ['accion' => 'sugerir_tipo', 'tipo' => 'uno más']);
        $this->assertStringContainsString('Ya mandaste', $html);
        $this->assertSame(
            ContactoRepositorio::TIPOS_POR_DIA,
            (new ContactoRepositorio(Db::conexion()))->contarDeHoy($this->usuario, ContactoRepositorio::ASUNTO_TIPO)
        );
    }

    public function testElPanelAvisaLosIngredientesNuevosParaRevisar(): void
    {
        $db = Db::conexion();
        (new IngredienteRepositorio($db))->crearPropio($this->usuario, 'Kale de prueba ' . uniqid(), 6, 'u');
        $propios = (int) $db->query('SELECT COUNT(*) FROM ingrediente WHERE usuario_id IS NOT NULL')->fetchColumn();

        $admin = new Navegador();
        $admin->ingresar('admin@cooki.test', 'Admin1234');
        [, $html] = $admin->get('admin/index.php');
        $texto = $propios === 1 ? 'Hay 1 ingrediente nuevo' : 'Hay ' . $propios . ' ingredientes nuevos';
        $this->assertStringContainsString($texto . ' para revisar', $html);
        $this->assertStringContainsString('admin/ingredientes.php?propios', $html);
        $this->assertStringContainsString(
            $propios === 1 ? '1 agregado por un usuario, para revisar.' : $propios . ' agregados por los usuarios',
            $html
        );
    }
}
