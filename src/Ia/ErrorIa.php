<?php

declare(strict_types=1);

namespace Cooki\Ia;

use RuntimeException;

/**
 * Un pedido a la IA que no salió. El mensaje es para la administración (se guarda en uso_ia y nunca lleva la clave);
 * paraUsuario() es el texto amable que ve la persona.
 */
final class ErrorIa extends RuntimeException
{
    public const TIEMPO = 'tiempo';       // tardó más que IA_TIMEOUT
    public const CUPO = 'cupo';           // 429: se terminó el cupo gratuito (por minuto o por día)
    public const MODELO = 'modelo';       // 404: Google retiró el modelo
    public const CLAVE = 'clave';         // 401/403: la clave está mal o la desactivaron
    public const FORMATO = 'formato';     // no devolvió el JSON pedido, o la receta no pasó la validación
    public const SERVIDOR = 'servidor';   // otro error de Google (5xx, 400)
    public const CONEXION = 'conexion';   // no se pudo conectar
    public const LIMITE = 'limite';       // se llegó al límite diario del sitio o de la persona
    public const APAGADA = 'apagada';     // sin IA_CLAVE
    public const DATOS = 'datos';         // falta algo de la persona (por ejemplo, la despensa está vacía)

    public function __construct(public readonly string $motivo, string $mensaje)
    {
        parent::__construct($mensaje);
    }

    public function paraUsuario(): string
    {
        return match ($this->motivo) {
            self::TIEMPO => 'La IA tardó demasiado en responder. Probá de nuevo en un rato.',
            self::CUPO => 'La IA está muy pedida en este momento. Probá de nuevo en unos minutos.',
            self::FORMATO => 'La IA respondió algo que no pudimos usar. Probá de nuevo o cambiá las opciones.',
            self::LIMITE, self::APAGADA, self::DATOS => $this->getMessage(),
            default => 'La IA no está disponible ahora. El resto del sitio anda igual.',
        };
    }
}
