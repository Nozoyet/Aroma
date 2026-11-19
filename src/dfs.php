<?php
declare(strict_types=1);

/**
 * DFS con retroceso: menú de emergencia para un lote que vence HOY.
 *
 * Decide cuántas porciones preparar de cada plato vecino del insumo en el grafo.
 * Meta: rescatar al menos `umbral_rescate` (70 %) del valor del lote, usando como máximo
 * `max_platos_menu` platos distintos. Se detiene en la primera solución.
 * Poda: si ni usando todo el insumo restante se alcanza la meta ni se mejora la mejor
 * solución parcial, la rama se descarta. Si no hay solución, devuelve la mejor parcial
 * con una advertencia (nunca se queda sin respuesta).
 */
final class Dfs
{
    private const EPS = 1e-6;
    private const LIMITE_NODOS = 500000;

    private int $insumo = 0;
    private float $costoG = 0.0;
    private float $meta = 0.0;
    private int $maxPlatos = 3;
    /** @var array<int, array{id: int, w: float}> platos ordenados por gramos del insumo por porción (desc) */
    private array $platos = [];

    private int $nodos = 0;
    private int $hojas = 0;
    private bool $resuelto = false;
    private bool $truncado = false;
    private float $mejorValor = -1.0;
    /** @var array<int,int> */
    private array $mejorMenu = [];

    public function __construct(private Grafo $grafo)
    {
    }

    /**
     * @param array<int,float> $stockTotal  gramos totales por insumo_id (para los demás ingredientes)
     * @param array<string,float> $parametros  umbral_rescate, max_platos_menu
     */
    public function resolver(int $insumoId, float $stockLote, float $costoG, array $stockTotal, array $parametros): array
    {
        $umbral = (float)($parametros['umbral_rescate'] ?? 0.70);
        $valorLote = $stockLote * $costoG;

        $this->insumo    = $insumoId;
        $this->costoG    = $costoG;
        $this->meta      = $umbral * $valorLote;
        $this->maxPlatos = (int)($parametros['max_platos_menu'] ?? 3);
        $this->nodos = $this->hojas = 0;
        $this->resuelto = $this->truncado = false;
        $this->mejorValor = -1.0;
        $this->mejorMenu = [];

        $this->platos = [];
        foreach ($this->grafo->vecinos($insumoId) as $platoId => $w) {
            $this->platos[] = ['id' => $platoId, 'w' => $w];
        }
        usort($this->platos, static fn(array $a, array $b): int => [$b['w'], $a['id']] <=> [$a['w'], $b['id']]);

        // Del insumo en riesgo solo se dispone del lote; de los demás, del stock total
        $stock = $stockTotal;
        $stock[$insumoId] = $stockLote;

        $this->buscar(0, $stock, [], 0.0);

        $menu = $this->mejorMenu;
        ksort($menu);
        $valor     = max(0.0, $this->mejorValor);
        $gramos    = $costoG > 0 ? $valor / $costoG : 0.0;
        $sobrante  = max(0.0, $stockLote - $gramos);

        $advertencia = null;
        if (!$this->resuelto) {
            $advertencia = $menu === []
                ? 'Ningún plato se puede preparar con el stock actual: el lote no se puede rescatar.'
                : 'No se alcanza la meta de rescate con el stock de los demás ingredientes; se propone la mejor opción parcial.';
        }
        if ($this->truncado) {
            $advertencia = trim(($advertencia ?? '') . ' Búsqueda truncada por límite de nodos.');
        }

        return [
            'resuelto'        => $this->resuelto,
            'menu'            => $menu,                                   // plato_id => porciones
            'valor_lote'      => round($valorLote, 2),
            'meta'            => round($this->meta, 2),
            'umbral'          => $umbral,
            'valor_rescatado' => round($valor, 2),
            'porcentaje'      => $valorLote > 0 ? round($valor / $valorLote, 4) : 0.0,
            'gramos_usados'   => round($gramos, 1),
            'gramos_sobrante' => round($sobrante, 1),
            'valor_merma'     => round($sobrante * $costoG, 2),
            'nodos'           => $this->nodos,
            'hojas'           => $this->hojas,
            'advertencia'     => $advertencia,
        ];
    }

    /** @param array<int,float> $stock  @param array<int,int> $menu */
    private function buscar(int $i, array $stock, array $menu, float $valor): void
    {
        if ($this->resuelto) {
            return;
        }
        if (++$this->nodos > self::LIMITE_NODOS) {
            $this->truncado = true;
            return;
        }
        if ($i === count($this->platos)) {
            $this->hojas++;
            $this->evaluar($menu, $valor);
            return;
        }

        // Poda: lo máximo que aún se podría rescatar es todo el insumo restante
        $cota = $valor + max(0.0, $stock[$this->insumo]) * $this->costoG;
        if ($cota + self::EPS < $this->meta && $cota <= $this->mejorValor + self::EPS) {
            return;
        }

        $plato = $this->platos[$i];
        $tope  = count($menu) >= $this->maxPlatos ? 0 : $this->grafo->kmax($plato['id'], $stock);

        for ($k = $tope; $k >= 0; $k--) {
            $s2 = $stock;
            $m2 = $menu;
            $v2 = $valor;
            if ($k > 0) {
                foreach ($this->grafo->receta($plato['id']) as $insumoId => $peso) {
                    $s2[$insumoId] = ($s2[$insumoId] ?? 0.0) - $k * $peso;
                }
                $m2[$plato['id']] = $k;
                $v2 += $k * $plato['w'] * $this->costoG;
            }
            $this->buscar($i + 1, $s2, $m2, $v2);
            if ($this->resuelto) {
                return;
            }
        }
    }

    /** @param array<int,int> $menu */
    private function evaluar(array $menu, float $valor): void
    {
        if ($valor + self::EPS >= $this->meta) {
            $this->resuelto = true;
            $this->mejorValor = $valor;
            $this->mejorMenu = $menu;
            return;
        }
        $mejora = $valor > $this->mejorValor + self::EPS;
        $empate = abs($valor - $this->mejorValor) <= self::EPS && count($menu) < count($this->mejorMenu);
        if ($mejora || $empate) {
            $this->mejorValor = $valor;
            $this->mejorMenu = $menu;
        }
    }
}