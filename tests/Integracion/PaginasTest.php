<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

use Cooki\Config;
use Cooki\Db;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas de punta a punta de las páginas del parcial: se piden como un navegador
 * y se comprueba qué muestran y a dónde redirigen. Usan los usuarios de seed.sql.
 */
final class PaginasTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            Db::conexion();
        } catch (PDOException $e) {
            $this->markTestSkipped('Sin base de datos disponible: ' . $e->getMessage());
        }
    }

    public function testPaginaPrincipalTieneNombreMenuRegistroEIngreso(): void
    {
        [$estado, $html] = (new Navegador())->get('index.php');
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('¿Qué puedo cocinar?', $html);
        $this->assertStringContainsString('Cooki es la puerta de tu heladera:', $html);
        $this->assertStringContainsString('fideos.svg', $html, 'El logo y la pestaña usan el dibujo de los fideos');
        $this->assertStringContainsString('aria-label="Principal"', $html);
        $this->assertStringContainsString('registro.php', $html);
        $this->assertStringContainsString('login.php', $html);
    }

    public function testLasPaginasPublicasResponden(): void
    {
        $web = new Navegador();
        foreach (['index.php', 'login.php', 'registro.php', 'contacto.php', 'nosotros.php'] as $pagina) {
            $this->assertSame(200, $web->get($pagina)[0], "{$pagina} tiene que responder 200");
        }
    }

    public function testQuienesSomosMuestraAlEquipoYSoloEstaEnElMenuSinCuenta(): void
    {
        $web = new Navegador();
        [$estado, $html] = $web->get('nosotros.php');
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('Quiénes somos', $html);
        $equipo = require dirname(__DIR__, 2) . '/src/equipo.php';
        $this->assertCount(4, $equipo);
        foreach ($equipo as $persona) {
            $this->assertStringContainsString($persona['area'], $html);
            $this->assertStringContainsString('img/equipo/' . $persona['imagen'] . '.svg', $html);
            if ($persona['nombre'] !== '') {
                $this->assertStringContainsString($persona['nombre'], $html);
            }
            if ($persona['github'] !== '') {
                $this->assertStringContainsString('href="' . $persona['github'] . '"', $html);
            }
        }
        $this->assertStringContainsString('contacto.php', $html);
        // El mate y el café, a los costados de la nota de contacto
        $contacto = '#<img class="nosotros__adorno"[^>]*yerba\.svg.*nosotros__contacto'
            . '.*<img class="nosotros__adorno[^"]*"[^>]*cafe\.svg#s';
        $this->assertMatchesRegularExpression($contacto, $html);

        [, $inicio] = $web->get('index.php');
        $this->assertStringContainsString('nosotros.php', $inicio, 'El inicio tiene el enlace');
        $this->assertStringContainsString('<span class="solo-lectores">', $html);
        $this->assertDoesNotMatchRegularExpression('/\bP[1-5]\b/', strip_tags($html), 'El rol interno no se muestra');
        $imanNosotros = '#class="menu__invitado".*href="[^"]*nosotros\.php"#s';
        $this->assertMatchesRegularExpression($imanNosotros, $inicio, 'Sin cuenta, el menú tiene el imán Nosotros');
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $cocina] = $web->get('app/index.php');
        $this->assertStringNotContainsString('nosotros.php', $cocina, 'Con cuenta no está en el menú');
    }

    public function testLasTipografiasNoDependenDeGoogle(): void
    {
        [, $html] = (new Navegador())->get('index.php');
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertFileExists(dirname(__DIR__, 2) . '/public/fonts/figtree-latin-400-normal.woff2');
    }

    public function testTodasLasPaginasDeAdministracionResponden(): void
    {
        $web = new Navegador();
        $this->assertStringEndsWith('admin/index.php', $web->ingresar('admin@cooki.test', 'Admin1234'));
        $paginas = ['admin/index.php', 'admin/recetas.php', 'admin/receta-editar.php', 'admin/ingredientes.php',
            'admin/ingredientes.php?nuevo', 'admin/usuarios.php', 'admin/usuarios.php?nuevo', 'admin/consultas.php',
            'admin/correo.php'];
        foreach ($paginas as $pagina) {
            $this->assertSame(200, $web->get($pagina)[0], "{$pagina} tiene que responder 200 (sin composer install)");
        }
        [, $html] = $web->get('admin/consultas.php');
        $this->assertStringContainsString('css/estilos.css', $html, 'Consultas usa el encabezado común');
    }

    public function testProbarElCorreoMuestraLaConfiguracionSinLaContrasenaYAvisaElResultado(): void
    {
        $web = new Navegador();
        $web->ingresar('admin@cooki.test', 'Admin1234');
        [, $html] = $web->get('admin/index.php');
        $this->assertStringContainsString('admin/correo.php', $html, 'El panel tiene la nota del correo');
        [$estado, $html] = $web->get('admin/correo.php');
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('Configuración actual', $html);
        $this->assertStringContainsString('admin@cooki.test', $html, 'La prueba va a la cuenta de quien la pide');
        $contrasena = Config::get('MAIL_CONTRASENA');
        if ($contrasena !== '') {
            $this->assertStringNotContainsString($contrasena, $html, 'La contraseña del correo nunca se muestra');
        }
        [$estado, , $destino] = $web->post('admin/correo.php', []);
        $this->assertSame(302, $estado);
        $this->assertStringEndsWith('admin/correo.php', $destino);
        [, $html] = $web->get('admin/correo.php');
        $resultado = '/se mandó el correo de prueba|No se pudo mandar/';
        $this->assertMatchesRegularExpression($resultado, $html);
    }

    public function testProbarElCorreoConLaConfiguracionDeGmailDelHosting(): void
    {
        // Como en el hosting: con MAIL_USUARIO cargado (el encabezado común usa $usuario y lo pisaba)
        $web = new Navegador(ClienteApi::servidorCon([
            'APP_ENV' => 'produccion',
            'MAIL_HOST' => 'smtp.gmail.com',
            'MAIL_PORT' => '587',
            'MAIL_SEGURIDAD' => 'tls',
            'MAIL_USUARIO' => 'cuenta.del.proyecto@gmail.com',
            'MAIL_CONTRASENA' => 'clavedeaplicacion',
            'MAIL_REMITENTE' => 'otra@cooki.test',
        ]));
        $web->ingresar('admin@cooki.test', 'Admin1234');
        [$estado, $html] = $web->get('admin/correo.php');
        $this->assertSame(200, $estado);
        $this->assertStringNotContainsString('error inesperado', $html);
        $this->assertStringContainsString('<dd>cuenta.del.proyecto@gmail.com</dd>', $html);
        $this->assertStringContainsString('cargada (no se muestra)', $html);
        $this->assertStringNotContainsString('clavedeaplicacion', $html, 'La contraseña nunca se muestra');
        $this->assertStringContainsString('Poné esa misma dirección en MAIL_REMITENTE', $html);
        $this->assertStringContainsString('Mandarme un correo de prueba', $html, 'La página llega hasta el final');
    }

    public function testUsuarioGeneralNoPuedeProbarElCorreo(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, , $destino] = $web->get('admin/correo.php');
        $this->assertStringEndsWith('error.php?motivo=sin-permiso', $destino);
    }

    public function testPaginaPrivadaSinSesionRedirigeAlError(): void
    {
        [$estado, , $destino] = (new Navegador())->get('app/despensa.php');
        $this->assertSame(302, $estado);
        $this->assertStringEndsWith('error.php?motivo=sin-sesion', $destino);
    }

    public function testIngresoIncorrectoRedirigeALaPaginaDeError(): void
    {
        $destino = (new Navegador())->ingresar('maria@cooki.test', 'otra-cosa');
        $this->assertStringEndsWith('error.php?motivo=login', $destino);
    }

    public function testUsuarioGeneralVaASuCocinaYNoPuedeEntrarAAdministracion(): void
    {
        $web = new Navegador();
        $this->assertStringEndsWith('app/index.php', $web->ingresar('maria@cooki.test', 'Usuario1234'));
        [$estado, $html] = $web->get('app/index.php');
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('Hola, María', $html);
        [, , $destino] = $web->get('admin/index.php');
        $this->assertStringEndsWith('error.php?motivo=sin-permiso', $destino);
    }

    public function testAdministradorVaAlPanel(): void
    {
        $web = new Navegador();
        $this->assertStringEndsWith('admin/index.php', $web->ingresar('admin@cooki.test', 'Admin1234'));
        [$estado, $html] = $web->get('admin/index.php');
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('admin/recetas.php', $html);
    }

    public function testFormularioSinTokenSeRechaza(): void
    {
        $campos = ['csrf' => 'falso', 'email' => 'a@b.c', 'contrasena' => 'x'];
        [, , $destino] = (new Navegador())->post('login.php', $campos);
        $this->assertStringEndsWith('error.php?motivo=formulario-vencido', $destino);
    }

    public function testRegistroCreaUnUsuarioGeneralConContrasenaEncriptada(): void
    {
        $email = 'prueba-' . uniqid() . '@cooki.test';
        $web = new Navegador();
        $web->get('registro.php');
        [, , $destino] = $web->post('registro.php', [
            'nombre' => 'Prueba', 'email' => $email, 'contrasena' => 'Clave1234', 'repetir' => 'Clave1234',
        ]);
        $this->assertStringEndsWith('app/index.php', $destino);

        $consulta = Db::conexion()->prepare('SELECT rol, hash_contrasena FROM usuario WHERE email = ?');
        $consulta->execute([$email]);
        $usuario = $consulta->fetch();
        Db::conexion()->prepare('DELETE FROM usuario WHERE email = ?')->execute([$email]);

        $this->assertSame('general', $usuario['rol']);
        $this->assertTrue(password_verify('Clave1234', $usuario['hash_contrasena']));
    }

    public function testRegistroConContrasenaDebilMuestraElError(): void
    {
        $web = new Navegador();
        $web->get('registro.php');
        [$estado, $html] = $web->post('registro.php', [
            'nombre' => 'Prueba', 'email' => 'debil@cooki.test', 'contrasena' => '123', 'repetir' => '123',
        ]);
        $this->assertSame(200, $estado);
        $this->assertStringContainsString('al menos 8 caracteres', $html);
    }

    public function testContactoGuardaLaConsulta(): void
    {
        $asunto = 'Prueba automática ' . uniqid();
        $web = new Navegador();
        $web->get('contacto.php');
        [, , $destino] = $web->post('contacto.php', [
            'nombre' => 'Tester', 'email' => 'tester@cooki.test', 'asunto' => $asunto,
            'mensaje' => 'Mensaje de prueba de más de diez caracteres.',
        ]);
        $this->assertStringEndsWith('contacto.php', $destino);

        $consulta = Db::conexion()->prepare('SELECT COUNT(*) FROM contacto WHERE asunto = ?');
        $consulta->execute([$asunto]);
        $this->assertSame(1, (int) $consulta->fetchColumn());
        Db::conexion()->prepare('DELETE FROM contacto WHERE asunto = ?')->execute([$asunto]);
    }

    public function testBuscadorDeRecetasEncuentraPorSinonimoDeIngrediente(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/recetas.php?q=pimiento');
        $this->assertStringContainsString('Arroz con pollo', $html);
        $this->assertStringNotContainsString('Panqueques', $html);
    }

    public function testNadiePuedeEditarLaDespensaDeOtro(): void
    {
        $sql = 'SELECT id FROM despensa_item WHERE usuario_id = 4 LIMIT 1';
        $idDeJuan = (int) Db::conexion()->query($sql)->fetchColumn();
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, , $destino] = $web->get('app/despensa.php?editar=' . $idDeJuan);
        $this->assertStringEndsWith('error.php?motivo=no-encontrado', $destino);
    }

    public function testAbmDeIngredientesAltaModificacionYBaja(): void
    {
        $nombre = 'Ingrediente ' . uniqid();
        $web = new Navegador();
        $web->ingresar('admin@cooki.test', 'Admin1234');
        $web->get('admin/ingredientes.php?nuevo');
        $campos = ['accion' => 'guardar', 'id' => 0, 'nombre' => $nombre, 'categoria_id' => 6,
            'unidad_default' => 'g', 'perecedero' => '1', 'vida_util_dias' => '30'];
        $web->post('admin/ingredientes.php', $campos);

        $consulta = Db::conexion()->prepare('SELECT id FROM ingrediente WHERE nombre = ?');
        $consulta->execute([$nombre]);
        $id = (int) $consulta->fetchColumn();
        $this->assertGreaterThan(0, $id, 'No se creó el ingrediente');

        $web->get('admin/ingredientes.php?editar=' . $id);
        $web->post('admin/ingredientes.php', ['id' => $id, 'nombre' => $nombre . ' editado',
            'sinonimos' => 'otro nombre (ar), Otro Nombre, segundo'] + $campos);
        $consulta->execute([$nombre . ' editado']);
        $this->assertSame($id, (int) $consulta->fetchColumn());
        // Incremento 2: los otros nombres se cargan en el mismo formulario (sin repetidos, región en mayúscula)
        $sinonimos = Db::conexion()->prepare(
            'SELECT texto, region FROM sinonimo WHERE ingrediente_id = ? ORDER BY texto'
        );
        $sinonimos->execute([$id]);
        $this->assertSame(
            [['texto' => 'otro nombre', 'region' => 'AR'], ['texto' => 'segundo', 'region' => null]],
            $sinonimos->fetchAll(\PDO::FETCH_ASSOC)
        );
        [, $html] = $web->get('admin/ingredientes.php?editar=' . $id);
        $this->assertStringContainsString('value="otro nombre (AR), segundo"', $html);

        $web->get('admin/ingredientes.php');
        $web->post('admin/ingredientes.php', ['accion' => 'borrar', 'id' => $id]);
        $consulta->execute([$nombre . ' editado']);
        $this->assertFalse($consulta->fetchColumn());
    }

    public function testElColorYLaDisposicionSeGuardanEnLaCuenta(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        $web->get('app/index.php');
        [, , $destino] = $web->post('app/preferencias.php', ['color' => 'noche', 'volver' => '/app/recetas.php']);
        $this->assertStringEndsWith('/app/recetas.php', $destino);
        $web->post('app/preferencias.php', ['disposicion' => 'panoramica', 'volver' => '/app/index.php']);
        [, $html] = $web->get('app/index.php');
        $this->assertStringContainsString('data-color="noche"', $html);
        $this->assertStringContainsString('data-disposicion="panoramica"', $html);

        [, , $destino] = $web->post('app/preferencias.php', ['color' => 'fucsia', 'volver' => '//otro-sitio.com']);
        $this->assertStringEndsWith('app/index.php', $destino, 'No redirige a otro sitio');
        $web->post('app/preferencias.php', ['disposicion' => 'gigante', 'volver' => '/app/index.php']);
        $fila = Db::conexion()->query('SELECT color, disposicion FROM usuario WHERE id = 3')->fetch();
        Db::conexion()->exec("UPDATE usuario SET color = 'verde', disposicion = 'normal' WHERE id = 3");
        $this->assertSame('noche', $fila['color'], 'Un color que no está en la lista no se guarda');
        $this->assertSame('panoramica', $fila['disposicion'], 'Una disposición que no está en la lista no se guarda');
    }

    public function testElVolumenSeGuardaEnLaCuenta(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/index.php');
        $this->assertStringContainsString('data-volumen="50"', $html, 'Arranca en 50');
        $web->post('app/preferencias.php', ['volumen' => '80', 'volver' => '/app/index.php']);
        $web->post('app/preferencias.php', ['volumen' => '150', 'volver' => '/app/index.php']);
        [, $html] = $web->get('app/index.php');
        $volumen = Db::conexion()->query('SELECT volumen FROM usuario WHERE id = 3')->fetchColumn();
        Db::conexion()->exec('UPDATE usuario SET volumen = 50 WHERE id = 3');
        $this->assertSame(80, (int) $volumen, 'Un volumen fuera de 0 a 100 no se guarda');
        $this->assertStringContainsString('data-volumen="80"', $html);
    }

    public function testElPanelDeAdministracionTieneNotasConDibujos(): void
    {
        $web = new Navegador();
        $web->ingresar('admin@cooki.test', 'Admin1234');
        [, $html] = $web->get('admin/index.php');
        $notas = substr_count($html, 'class="postit postit--');
        $this->assertSame(5, $notas, 'Recetas, ingredientes, consultas, usuarios y correo');
        [, $html] = $web->get('admin/ingredientes.php?q=manzana');
        $this->assertStringContainsString('img/ingredientes/manzana.svg', $html);
        [, $html] = $web->get('admin/ingredientes.php?editar=1');
        $this->assertStringContainsString('class="vista-dibujo"', $html);
    }

    public function testLaDespensaGuardaLaCantidadExactaConSuUnidad(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        $web->get('app/despensa.php');
        $campos = ['accion' => 'guardar', 'id' => 0, 'ingrediente_id' => 38, 'nivel' => 0,
            'cantidad' => '3', 'ubicacion' => 'heladera', 'fecha_vencimiento' => ''];
        $web->post('app/despensa.php', $campos);

        $consulta = Db::conexion()->prepare(
            'SELECT id, nivel, cantidad, unidad FROM despensa_item WHERE usuario_id = 3 AND ingrediente_id = 38'
        );
        $consulta->execute();
        $item = $consulta->fetch();
        [, $html] = $web->get('app/despensa.php');
        $web->post('app/despensa.php', ['accion' => 'guardar', 'id' => $item['id'], 'cantidad' => '0'] + $campos);
        $consulta->execute();
        $agotado = $consulta->fetch();
        Db::conexion()->exec('DELETE FROM despensa_item WHERE usuario_id = 3 AND ingrediente_id = 38');

        $this->assertSame(3.0, (float) $item['cantidad']);
        $this->assertSame('u', $item['unidad']);
        $this->assertSame(2, (int) $item['nivel'], 'Con cantidad mayor a 0 no puede quedar "agotado"');
        $this->assertStringContainsString('3 unidades', $html);
        $this->assertSame(0, (int) $agotado['nivel'], 'Cantidad 0 = agotado');
    }

    public function testElSelectorDeMiCocinaBuscaRecetasConLosIngredientesElegidos(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        // Huevo (15) y Papa (1): la tortilla los usa a los dos; los panqueques, solo el huevo
        [, $html] = $web->get('app/index.php?ing[]=15&ing[]=1');
        $resultado = $this->seccionSelector($html);
        $this->assertStringContainsString('Tortilla de papas', $resultado);
        $this->assertStringNotContainsString('Panqueques', $resultado);

        [, $html] = $web->get('app/index.php?ing[]=15&ing[]=1&modo=alguno');
        $this->assertStringContainsString('Panqueques', $this->seccionSelector($html));

        // Carne picada (13) no está en la despensa de María: se ignora
        [, $html] = $web->get('app/index.php?ing[]=13');
        $this->assertStringContainsString('Todavía no elegiste nada', $this->seccionSelector($html));
    }

    public function testLasTarjetasDeRecetasMuestranLoQueFaltaTachado(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/recetas.php?q=zapallitos');
        $this->assertStringContainsString('alt="Tapa de tarta (te falta)"', $html);
        $this->assertStringContainsString('alt="Huevo"', $html);
    }

    public function testLaDespensaMuestraElContadorYLosVencidosAlFinal(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/despensa.php');
        // María tiene el tomate vencido (ayer): cuenta como vencido, va gris y al final, con "Cambiar fecha"
        $this->assertMatchesRegularExpression('/contador__item--vencidos"[^>]*>\s*<strong>1<\/strong>/', $html);
        $this->assertStringContainsString('class="cambiar-fecha"', $html);
        $filas = preg_match_all('/<tr(?: class="vencido")? data-nombre="([^"]+)"/', $html, $m);
        $this->assertGreaterThan(1, $filas);
        $this->assertSame('tomate', end($m[1]), 'Lo vencido va al final de la lista');
        $this->assertStringContainsString('<tr class="vencido" data-nombre="tomate"', $html);
        $this->assertStringContainsString('Agregar producto', $html);
    }

    public function testLosNumerosDelContadorFiltranLaDespensa(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        $filas = function (string $html): array {
            preg_match_all('/<tr(?: class="vencido")? data-nombre="([^"]+)"/', $html, $m);
            return $m[1];
        };

        [, $html] = $web->get('app/despensa.php?estado=vencidos');
        $this->assertSame(['tomate'], $filas($html));
        $this->assertStringContainsString('aria-current="true"', $html);

        [, $html] = $web->get('app/despensa.php?estado=por_vencer');
        $this->assertEqualsCanonicalizing(['queso cremoso', 'leche', 'zapallito'], $filas($html));

        // Con ubicación y estado a la vez: en el freezer no hay nada vencido
        [, $html] = $web->get('app/despensa.php?ubicacion=freezer&estado=vencidos');
        $this->assertSame([], $filas($html));
        $this->assertStringContainsString('No hay productos vencidos en esta ubicación', $html);
    }

    public function testElSelectorPonePrimeroLoQueVenceConElGloboYa(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/index.php');
        $patron = '/<label class="selector__sticker([^"]*)">\s*<input[^>]+>\s*<img[^>]+>\s*<span>([^<]+)/';
        preg_match_all($patron, $html, $m);
        // El queso cremoso vence mañana: va primero y con "¡ya!". El tomate venció: no aparece.
        $this->assertSame('Queso cremoso', $m[2][0]);
        $this->assertSame(' selector__sticker--ya', $m[1][0]);
        $this->assertNotContains('Tomate', $m[2]);
    }

    public function testElDetalleTienePasosParaTildar(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/receta.php?id=1');
        $this->assertSame(
            (int) Db::conexion()->query('SELECT COUNT(*) FROM paso WHERE receta_id = 1')->fetchColumn(),
            substr_count($html, 'class="paso__check"')
        );
    }

    /** El HTML del resultado del selector (sin el resto de la página, que también tiene recetas). */
    public function testLaBarraSinCuentaEsTodaDeImanesConAjustes(): void
    {
        [, $html] = (new Navegador())->get('index.php');
        $inicio = (int) strpos($html, '<ul>');
        $menu = substr($html, $inicio, (int) strpos($html, '</ul>') - $inicio);
        $this->assertStringContainsString('login.php">Ingresar</a></li>', $menu, 'Ingresar es un imán más del menú');
        $this->assertStringContainsString('registro.php">Crear cuenta</a></li>', $menu);
        $this->assertStringContainsString('cuenta--ajustes', $html);
        $this->assertStringContainsString('class="marca__letra"', $html, 'Letras separadas para la ola');
        $this->assertMatchesRegularExpression('#js/sitio\.js\?v=\d+#', $html, 'El JS lleva versión');
        [, $login] = (new Navegador())->get('login.php');
        $this->assertSame(15, substr_count($login, 'stickers-deco__dibujo'), 'Ingresar: 10 a los costados y 5 abajo');
    }

    public function testSinCuentaElColorSeGuardaEnUnaCookieYPasaALaCuentaNueva(): void
    {
        $web = new Navegador();
        $web->agregarCookie('cooki_color', 'rosa');
        [, $html] = $web->get('index.php');
        $this->assertStringContainsString('data-color="rosa"', $html);

        $trucho = new Navegador();
        $trucho->agregarCookie('cooki_color', '"><script>');
        [, $html] = $trucho->get('index.php');
        $this->assertStringContainsString('data-color="verde"', $html, 'Un valor inválido no se usa');

        [, , $destino] = $web->post('app/preferencias.php', ['color' => 'celeste', 'volver' => '/contacto.php']);
        $this->assertSame('/contacto.php', $destino, 'Sin cuenta también se puede elegir el color');

        $email = 'color' . uniqid() . '@cooki.test';
        $web->get('registro.php');
        $web->post('registro.php', [
            'nombre' => 'Con Color', 'email' => $email, 'contrasena' => 'Clave1234', 'repetir' => 'Clave1234',
        ]);
        $consulta = Db::conexion()->prepare('SELECT color FROM usuario WHERE email = ?');
        $consulta->execute([$email]);
        $color = $consulta->fetchColumn();
        Db::conexion()->prepare('DELETE FROM usuario WHERE email = ?')->execute([$email]);
        $this->assertSame('rosa', $color, 'El color elegido sin cuenta pasa a la cuenta nueva');
    }

    public function testAlIngresarNoSeRepiteElSaludo(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/index.php');
        $this->assertSame(1, substr_count($html, 'Hola, María'), 'Solo el título saluda');
    }

    public function testSalirPreguntaYSoloCierraLaSesionPorPost(): void
    {
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/index.php');
        $this->assertStringContainsString('¿Seguro que querés salir?', $html);
        [, , $destino] = $web->get('salir.php');
        $this->assertStringEndsWith('app/index.php', $destino, 'Un enlace a salir.php no cierra la sesión');
        $this->assertSame(200, $web->get('app/despensa.php')[0]);
        $web->get('app/index.php');
        [, , $destino] = $web->post('salir.php', []);
        $this->assertStringEndsWith('index.php', $destino);
        $this->assertSame(302, $web->get('app/despensa.php')[0], 'Después de salir ya no hay sesión');
    }

    public function testUnIngredienteQueNoEstaSeAgregaComoPropioConElFrasco(): void
    {
        $db = Db::conexion();
        $nombre = 'Quinoa ' . substr(uniqid(), -5);
        $web = new Navegador();
        $web->ingresar('maria@cooki.test', 'Usuario1234');
        [, $html] = $web->get('app/despensa.php');
        $this->assertStringContainsString('¿No está en la lista? Agregalo', $html);
        $this->assertStringContainsString('“Agotado”', $html, 'Explica qué es "Agotado"');
        $campos = ['accion' => 'guardar', 'id' => 0, 'ingrediente_id' => 0, 'nombre_nuevo' => $nombre,
            'categoria_nueva' => 6, 'unidad_nueva' => 'g', 'nivel' => 2, 'cantidad' => '500',
            'ubicacion' => 'alacena', 'fecha_vencimiento' => ''];
        $web->post('app/despensa.php', $campos);
        $consulta = $db->prepare('SELECT id, usuario_id, unidad_default FROM ingrediente WHERE nombre = ?');
        $consulta->execute([$nombre]);
        $nuevo = $consulta->fetch();
        [, $html] = $web->get('app/despensa.php');

        // Otro usuario no lo ve; si escribe un sinónimo de la lista, se usa ese y no se crea nada
        $otro = new Navegador();
        $otro->ingresar('juan@cooki.test', 'Usuario1234');
        [, $htmlOtro] = $otro->get('app/despensa.php');
        $otro->post('app/despensa.php', ['nombre_nuevo' => 'piña', 'cantidad' => ''] + $campos);
        $piña = (int) $db->query("SELECT COUNT(*) FROM ingrediente WHERE nombre = 'piña'")->fetchColumn();

        // El administrador lo pasa a la lista general
        $admin = new Navegador();
        $admin->ingresar('admin@cooki.test', 'Admin1234');
        [, $htmlAdmin] = $admin->get('admin/ingredientes.php?propios');
        $admin->post('admin/ingredientes.php', ['accion' => 'general', 'id' => $nuevo['id']]);
        $general = $db->query('SELECT usuario_id FROM ingrediente WHERE id = ' . (int) $nuevo['id'])->fetchColumn();

        $db->exec('DELETE FROM despensa_item WHERE ingrediente_id = ' . (int) $nuevo['id']);
        $db->exec('DELETE FROM despensa_item WHERE usuario_id = 4
                   AND ingrediente_id = 50 AND cantidad IS NULL AND nivel = 2 AND ubicacion = \'alacena\'');
        $db->exec('DELETE FROM ingrediente WHERE id = ' . (int) $nuevo['id']);

        $this->assertNotFalse($nuevo, 'Se creó el ingrediente');
        $this->assertSame(3, (int) $nuevo['usuario_id'], 'Es propio de María');
        $this->assertSame('g', $nuevo['unidad_default']);
        $fila = (int) strpos($html, 'data-nombre="' . mb_strtolower($nombre) . '"');
        $this->assertGreaterThan(0, $fila);
        $this->assertStringContainsString('frasco.svg', substr($html, $fila, 600), 'En la despensa lleva el frasco');
        $this->assertStringNotContainsString($nombre, $htmlOtro, 'Los demás no lo ven');
        $this->assertSame(0, $piña, '"piña" es sinónimo de Ananá: no se crea un ingrediente nuevo');
        $this->assertStringContainsString('propio de María', $htmlAdmin);
        $this->assertNull($general, 'Quedó en la lista general');
    }

    public function testElAdministradorRespondeYElUsuarioVeLaRespuesta(): void
    {
        $asunto = 'Pregunta ' . uniqid();
        $maria = new Navegador();
        $maria->ingresar('maria@cooki.test', 'Usuario1234');
        $maria->get('contacto.php');
        $maria->post('contacto.php', [
            'nombre' => 'María López', 'email' => 'maria@cooki.test', 'asunto' => $asunto,
            'mensaje' => '¿Se pueden agregar ingredientes que no están?',
        ]);
        $consulta = Db::conexion()->prepare('SELECT id FROM contacto WHERE asunto = ?');
        $consulta->execute([$asunto]);
        $id = (int) $consulta->fetchColumn();

        $admin = new Navegador();
        $admin->ingresar('admin@cooki.test', 'Admin1234');
        [, $html] = $admin->get('admin/consultas.php');
        $this->assertStringContainsString('Volver al panel', $html);
        $this->assertStringContainsString('name="respuesta"', $html);
        $admin->post('admin/consultas.php', [
            'accion' => 'responder', 'id' => $id, 'respuesta' => 'Sí, desde tu despensa.',
        ]);

        $fila = Db::conexion()
            ->query('SELECT estado, respuesta, respondida_por FROM contacto WHERE id = ' . $id)->fetch();
        [, $htmlMaria] = $maria->get('contacto.php');
        Db::conexion()->exec('DELETE FROM contacto WHERE id = ' . $id);

        $this->assertSame('respondida', $fila['estado']);
        $this->assertSame('Sí, desde tu despensa.', $fila['respuesta']);
        $this->assertNotNull($fila['respondida_por']);
        $this->assertStringContainsString('Mis consultas', $htmlMaria);
        $this->assertStringContainsString('Sí, desde tu despensa.', $htmlMaria);
    }

    private function seccionSelector(string $html): string
    {
        $inicio = (int) strpos($html, 'id="resultado-selector"');
        return substr($html, $inicio, (int) strpos($html, 'Podés cocinar', $inicio) - $inicio);
    }
}
