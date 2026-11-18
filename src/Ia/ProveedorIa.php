<?php

declare(strict_types=1);

namespace Cooki\Ia;

/**
 * Lo único que el sitio le pide a un proveedor de IA: un JSON con la forma del esquema.
 * Hoy lo cumple ClienteGemini; para cambiar de proveedor alcanza con otra clase que implemente esto (riesgo R-14).
 */
interface ProveedorIa
{
    /**
     * @param string $instrucciones quién es el ayudante y qué reglas sigue (src/Ia/Instrucciones.php)
     * @param string $pedido        los datos de este pedido (ingredientes, opciones), sin datos personales
     * @param array<string, mixed> $esquema la forma del JSON que tiene que devolver
     * @throws ErrorIa si no respondió, tardó demasiado o devolvió algo que no es JSON
     */
    public function generarJson(string $instrucciones, string $pedido, array $esquema): RespuestaIa;
}
