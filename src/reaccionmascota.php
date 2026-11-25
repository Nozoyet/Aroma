<?php
declare(strict_types=1);

/**
 * Elige la pose del onigiri. Regla de oro: la mascota nunca contradice al sistema experto.
 *  - Las poses críticas (cry, angry, alert, sad, ok, happy) solo las pide PHP, a partir de datos del sistema.
 *  - El modelo (o la heurística de la fase 1) solo puede sugerir poses de TONO (TONO).
 *  - neutral / think / sleepy también las maneja el JavaScript (reposo, espera, inactividad).
 */
final class ReaccionMascota
{
    public const TODAS = ['neutral', 'think', 'alert', 'ok', 'happy', 'sad', 'cry', 'angry',
                          'shocked', 'sleepy', 'confused', 'shy', 'love'];

    /** Prioridad de las poses ligadas a decisiones del sistema (la primera que aparezca gana). */
    public const ORDEN_PHP = ['cry', 'angry', 'alert', 'sad', 'ok', 'happy', 'think'];

    /** Lo único que puede sugerir el modelo de lenguaje. */
    public const TONO = ['love', 'shocked', 'confused', 'shy'];

    public static function valida(string $pose): bool
    {
        return in_array($pose, self::TODAS, true);
    }

    /**
     * @param string[]    $candidatasPhp poses que PHP pide según el sistema experto
     * @param string|null $sugerida      pose sugerida por el modelo (no confiable)
     */
    public static function elegir(array $candidatasPhp, ?string $sugerida = null): string
    {
        foreach (self::ORDEN_PHP as $pose) {
            if (in_array($pose, $candidatasPhp, true)) {
                return $pose;
            }
        }
        $s = $sugerida === null ? '' : strtolower(trim($sugerida));
        return in_array($s, self::TONO, true) ? $s : 'neutral';
    }
}
