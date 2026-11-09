<?php

declare(strict_types=1);

namespace Cooki\Ia;

/** Lo que devolvió la IA: el JSON ya decodificado, los tokens que usó y cuánto tardó. */
final class RespuestaIa
{
    /** @param array<string, mixed> $datos */
    public function __construct(
        public readonly array $datos,
        public readonly ?int $tokensEntrada = null,
        public readonly ?int $tokensSalida = null,
        public readonly int $milisegundos = 0,
    ) {
    }
}
