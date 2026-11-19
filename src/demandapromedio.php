<?php
declare(strict_types=1);

/**
 * Demanda v1: promedio histórico de ventas del mismo día de la semana.
 *   porciones = promedio de ese plato en ese día de la semana
 *   fc        = 1 − desviación/promedio, acotado a [0.3, 0.9]  (más variable = menos certeza)
 * Con menos de 3 observaciones, la certeza es la mínima.
 */
final class DemandaPromedio implements IDemanda
{
    private const FC_MIN = 0.3;
    private const FC_MAX = 0.9;
    private const MIN_OBSERVACIONES = 3;

    /** @param array<int, array<int, array{prom: float, desv: float, n: int}>> $stats de Repositorio::estadisticasDemanda() */
    public function __construct(private array $stats)
    {
    }

    public function estimar(int $platoId, string $fecha): array
    {
        $dow = ((int)(new DateTimeImmutable($fecha))->format('N')) - 1;   // 0 = lunes
        $e   = $this->stats[$platoId][$dow] ?? null;

        if ($e === null) {
            return ['porciones' => 0.0, 'fc' => self::FC_MIN];
        }
        if ($e['n'] < self::MIN_OBSERVACIONES || $e['prom'] <= 0.0) {
            return ['porciones' => (float)$e['prom'], 'fc' => self::FC_MIN];
        }
        $fc = 1.0 - $e['desv'] / $e['prom'];
        return ['porciones' => (float)$e['prom'], 'fc' => max(self::FC_MIN, min(self::FC_MAX, $fc))];
    }
}