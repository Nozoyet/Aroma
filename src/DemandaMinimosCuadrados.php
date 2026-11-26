<?php
declare(strict_types=1);

/**
 * Demanda v2: regresión lineal por mínimos cuadrados, un modelo por plato.
 *
 *   porciones(t) = b0 + b1·t + g1·[martes] + g2·[miércoles] + ... + g6·[domingo]
 *
 *   t  = días transcurridos desde el primer día del historial (captura la tendencia de crecimiento)
 *   gk = efecto de cada día de la semana respecto del lunes (captura la estacionalidad semanal)
 *
 * Los coeficientes salen de resolver las ecuaciones normales (XᵀX)·b = Xᵀy por eliminación de Gauss
 * con pivoteo parcial. Los feriados no entran al ajuste (igual que en la versión 1).
 *
 * Certeza (FC): 1 − s/porciones, con s = error típico de los residuos, acotada a [0.3, 0.9].
 * Si un plato tiene muy pocos datos (< MIN_DIAS) se usa el modelo de respaldo (promedio por día de la semana).
 */
final class DemandaMinimosCuadrados implements IDemanda
{
    public const  MIN_DIAS = 28;       // 4 semanas: al menos 4 observaciones por día de la semana
    private const FC_MIN   = 0.3;
    private const FC_MAX   = 0.9;
    private const P        = 8;        // coeficientes: intercepto + tendencia + 6 días

    /** @var array<int, array{b: float[], s: float, r2: float, n: int, t0: int}|null> */
    private array $modelos = [];

    /** @param array<int, array<int, array{0: string, 1: float}>> $series plato_id => [[fecha, porciones], ...] */
    public function __construct(private array $series, private IDemanda $respaldo)
    {
    }

    public function estimar(int $platoId, string $fecha): array
    {
        $m = $this->modelo($platoId);
        if ($m === null) {
            return $this->respaldo->estimar($platoId, $fecha);
        }
        $y = max(0.0, $this->predecir($m, $fecha));
        $fc = $y > 0.0 ? 1.0 - $m['s'] / max($y, 1.0) : self::FC_MIN;
        return ['porciones' => $y, 'fc' => max(self::FC_MIN, min(self::FC_MAX, $fc))];
    }

    /** Cuántos platos se estiman con regresión (para informarlo en pantalla). */
    public function platosConRegresion(): int
    {
        $n = 0;
        foreach (array_keys($this->series) as $platoId) {
            $n += $this->modelo((int)$platoId) !== null ? 1 : 0;
        }
        return $n;
    }

    /**
     * Qué aprendió la regresión de un plato: tendencia (porciones/día), ajuste (R²) y días usados.
     * @return array{pendiente: float, r2: float, n: int}|null  null si el plato usa el modelo de respaldo
     */
    public function resumen(int $platoId): ?array
    {
        $m = $this->modelo($platoId);
        return $m === null ? null : ['pendiente' => $m['b'][1], 'r2' => $m['r2'], 'n' => $m['n']];
    }

    /**
     * Serie para graficar la demanda de un plato: ventas reales, ajuste del modelo, tendencia y pronóstico.
     * La ventana va de ($hoy - $atras) a ($hoy + $adelante - 1); los días desde $hoy en adelante son pronóstico.
     *
     * - real:      porciones vendidas (null si ese día no tiene registro, por ejemplo un feriado)
     * - ajuste:    lo que predice el modelo con el efecto del día de la semana
     * - tendencia: recta sin el efecto semanal (nivel promedio de la semana), muestra si la demanda sube o baja
     *
     * @return array{dias: array<int, array{fecha: string, real: ?float, ajuste: float, tendencia: float, futuro: bool}>,
     *               s: float, pendiente: float, r2: float}|null  null si el plato usa el modelo de respaldo
     */
    public function proyeccion(int $platoId, string $hoy, int $atras = 28, int $adelante = 7): ?array
    {
        $m = $this->modelo($platoId);
        if ($m === null) {
            return null;
        }
        $reales = [];
        foreach ($this->series[$platoId] ?? [] as [$fecha, $y]) {
            $reales[$fecha] = (float)$y;
        }
        $efectoMedio = array_sum(array_slice($m['b'], 2)) / 7.0;   // el lunes vale 0
        $dias = [];
        $inicio = new DateTimeImmutable($hoy);
        for ($i = -$atras; $i < $adelante; $i++) {
            $fecha = $inicio->modify(($i >= 0 ? '+' : '') . "{$i} day")->format('Y-m-d');
            $t = (float)($this->dia($fecha) - $m['t0']);
            $dias[] = [
                'fecha'     => $fecha,
                'real'      => $reales[$fecha] ?? null,
                'ajuste'    => max(0.0, $this->predecir($m, $fecha)),
                'tendencia' => max(0.0, $m['b'][0] + $m['b'][1] * $t + $efectoMedio),
                'futuro'    => $i >= 0,
            ];
        }
        return ['dias' => $dias, 's' => $m['s'], 'pendiente' => $m['b'][1], 'r2' => $m['r2']];
    }

    /** Días de historial que usa el modelo con más datos. */
    public function diasUsados(): int
    {
        $max = 0;
        foreach (array_keys($this->series) as $platoId) {
            $max = max($max, $this->modelo((int)$platoId)['n'] ?? 0);
        }
        return $max;
    }

    // ------------------------------------------------------------------------------------------

    private function modelo(int $platoId): ?array
    {
        if (!array_key_exists($platoId, $this->modelos)) {
            $this->modelos[$platoId] = $this->ajustar($this->series[$platoId] ?? []);
        }
        return $this->modelos[$platoId];
    }

    private function ajustar(array $serie): ?array
    {
        $n = count($serie);
        if ($n < self::MIN_DIAS) {
            return null;
        }
        $t0 = $this->dia($serie[0][0]);

        // Ecuaciones normales: A = XᵀX (P×P), c = Xᵀy
        $A = array_fill(0, self::P, array_fill(0, self::P, 0.0));
        $c = array_fill(0, self::P, 0.0);
        $sumY = 0.0;
        foreach ($serie as [$fecha, $y]) {
            $x = $this->fila($fecha, $t0);
            for ($i = 0; $i < self::P; $i++) {
                $c[$i] += $x[$i] * $y;
                for ($j = 0; $j < self::P; $j++) {
                    $A[$i][$j] += $x[$i] * $x[$j];
                }
            }
            $sumY += $y;
        }
        for ($i = 0; $i < self::P; $i++) {      // regularización mínima: evita matriz singular si falta un día de la semana
            $A[$i][$i] += 1e-9;
        }
        $b = $this->resolver($A, $c);
        if ($b === null) {
            return null;
        }

        // Calidad del ajuste
        $media = $sumY / $n;
        $sse = $sst = 0.0;
        foreach ($serie as [$fecha, $y]) {
            $e = $y - $this->producto($b, $this->fila($fecha, $t0));
            $sse += $e * $e;
            $sst += ($y - $media) ** 2;
        }
        return [
            'b' => $b, 't0' => $t0, 'n' => $n,
            's' => sqrt($sse / max(1, $n - self::P)),
            'r2' => $sst > 0.0 ? 1.0 - $sse / $sst : 0.0,
        ];
    }

    /** Fila de la matriz de diseño: [1, t, lun..dom sin el lunes]. */
    private function fila(string $fecha, int $t0): array
    {
        $dow = ((int)(new DateTimeImmutable($fecha))->format('N')) - 1;   // 0 = lunes
        $x = array_fill(0, self::P, 0.0);
        $x[0] = 1.0;
        $x[1] = (float)($this->dia($fecha) - $t0);
        if ($dow > 0) {
            $x[1 + $dow] = 1.0;
        }
        return $x;
    }

    private function predecir(array $m, string $fecha): float
    {
        return $this->producto($m['b'], $this->fila($fecha, $m['t0']));
    }

    private function producto(array $b, array $x): float
    {
        $s = 0.0;
        foreach ($b as $i => $bi) {
            $s += $bi * $x[$i];
        }
        return $s;
    }

    private function dia(string $fecha): int
    {
        return intdiv((new DateTimeImmutable($fecha, new DateTimeZone('UTC')))->getTimestamp(), 86400);
    }

    /** Resuelve A·b = c por eliminación de Gauss con pivoteo parcial. */
    private function resolver(array $A, array $c): ?array
    {
        $n = count($c);
        for ($i = 0; $i < $n; $i++) {
            $A[$i][] = $c[$i];
        }
        for ($col = 0; $col < $n; $col++) {
            $piv = $col;
            for ($r = $col + 1; $r < $n; $r++) {
                if (abs($A[$r][$col]) > abs($A[$piv][$col])) {
                    $piv = $r;
                }
            }
            if (abs($A[$piv][$col]) < 1e-12) {
                return null;
            }
            [$A[$col], $A[$piv]] = [$A[$piv], $A[$col]];
            for ($r = $col + 1; $r < $n; $r++) {
                $f = $A[$r][$col] / $A[$col][$col];
                for ($k = $col; $k <= $n; $k++) {
                    $A[$r][$k] -= $f * $A[$col][$k];
                }
            }
        }
        $b = array_fill(0, $n, 0.0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $s = $A[$i][$n];
            for ($j = $i + 1; $j < $n; $j++) {
                $s -= $A[$i][$j] * $b[$j];
            }
            $b[$i] = $s / $A[$i][$i];
        }
        return $b;
    }
}