<?php
declare(strict_types=1);

/**
 * Grafo bipartito insumo → plato. Las aristas son las filas de `receta`, con peso =
 * gramos del insumo por porción. Se usa como índice: "¿qué platos consumen este insumo?"
 * y "¿cuántas porciones de este plato permite el stock?".
 */
final class Grafo
{
    /** Tope de porciones para evitar bucles si una receta no limitara por ningún insumo. */
    private const TOPE_PORCIONES = 1000;

    /** @var array<int, array{nombre: string, precio: float, receta: array<int, float>}> */
    private array $platos = [];
    /** @var array<int, array{nombre: string, costo: float, platos: array<int, float>}> */
    private array $insumos = [];

    /** @param array $recetas filas de Repositorio::recetas() */
    public function __construct(array $recetas)
    {
        foreach ($recetas as $f) {
            $p = (int)$f['plato_id'];
            $i = (int)$f['insumo_id'];
            $g = (float)$f['cantidad_por_porcion'];

            $this->platos[$p] ??= ['nombre' => (string)$f['plato'], 'precio' => (float)$f['precio_venta'], 'receta' => []];
            $this->platos[$p]['receta'][$i] = $g;

            $this->insumos[$i] ??= ['nombre' => (string)$f['insumo'], 'costo' => (float)$f['costo_unitario'], 'platos' => []];
            $this->insumos[$i]['platos'][$p] = $g;
        }
    }

    /** Todos los platos con receta: [plato_id => nombre]. */
    public function platos(): array
    {
        return array_map(static fn(array $p): string => $p['nombre'], $this->platos);
    }

    /** Platos que usan el insumo: [plato_id => gramos por porción]. */
    public function vecinos(int $insumoId): array
    {
        return $this->insumos[$insumoId]['platos'] ?? [];
    }

    /** Receta de un plato: [insumo_id => gramos por porción]. */
    public function receta(int $platoId): array
    {
        return $this->platos[$platoId]['receta'] ?? [];
    }

    public function gramos(int $platoId, int $insumoId): float
    {
        return $this->platos[$platoId]['receta'][$insumoId] ?? 0.0;
    }

    public function nombrePlato(int $platoId): string
    {
        return $this->platos[$platoId]['nombre'] ?? "Plato {$platoId}";
    }

    public function precioPlato(int $platoId): float
    {
        return $this->platos[$platoId]['precio'] ?? 0.0;
    }

    public function nombreInsumo(int $insumoId): string
    {
        return $this->insumos[$insumoId]['nombre'] ?? "Insumo {$insumoId}";
    }

    /**
     * Porciones máximas de un plato con el stock dado: MIN(floor(stock_i / peso_i)) sobre sus insumos.
     * @param array<int,float> $stock       gramos disponibles por insumo_id
     * @param int|null         $ignorar     insumo que no limita (el lote en riesgo se controla aparte)
     */
    public function kmax(int $platoId, array $stock, ?int $ignorar = null): int
    {
        $k = self::TOPE_PORCIONES;
        foreach ($this->receta($platoId) as $insumoId => $peso) {
            if ($insumoId === $ignorar) {
                continue;
            }
            $k = min($k, (int)floor(($stock[$insumoId] ?? 0.0) / $peso + 1e-9));
        }
        return max(0, $k);
    }
}
