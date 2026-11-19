<?php
declare(strict_types=1);

/**
 * Módulo de explicación: convierte la traza del motor y los resultados de DFS/A* en texto
 * legible ("¿por qué se recomienda esto?"). No decide nada, solo narra lo que ocurrió.
 * Cada método devuelve una lista de líneas de texto plano.
 */
final class Explicador
{
    /** @param array<string, array{condicion: string, conclusion: string}> $reglasBd tabla `regla` */
    public function __construct(private array $reglasBd, private Grafo $grafo)
    {
    }

    /** Datos del lote, reglas disparadas (con su condición en la BD) y acciones candidatas. */
    public function lote(array $lote, array $inf): array
    {
        $dias = $lote['dias_vida'];
        $vida = $dias < 0 ? 'vencido hace ' . (-$dias) . ' día(s)' : ($dias === 0 ? 'vence hoy' : "vence en {$dias} día(s)");

        $l = [sprintf('Lote %d de %s: %s g, %s, riesgo %s, estado visual %s, cadena de frío %s.',
            $lote['lote_id'], $lote['insumo'], number_format($lote['stock_actual'], 0, '.', ''), $vida,
            $lote['categoria_riesgo'], $lote['estado_visual'], str_replace('_', ' ', $lote['cadena_frio']))];

        if ($inf['traza'] !== []) {
            $l[] = 'Reglas disparadas (en orden):';
            foreach ($inf['traza'] as $t) {
                $r = $this->reglasBd[$t['regla']] ?? ['condicion' => '', 'conclusion' => ''];
                $l[] = sprintf('  %s: SI %s ENTONCES %s  [FC %.2f]', $t['regla'], $r['condicion'], $r['conclusion'], $t['fc']);
            }
        }
        if ($inf['acciones'] !== []) {
            arsort($inf['acciones']);
            $txt = [];
            foreach ($inf['acciones'] as $a => $fc) {
                $txt[] = sprintf('%s (FC %.2f)', $a, $fc);
            }
            $l[] = 'Acciones candidatas: ' . implode(', ', $txt)
                . '. Se elige "' . $inf['accion_principal'] . '" por prioridad (la inocuidad va primero).';
        }
        foreach ($inf['alertas'] as $a) {
            $l[] = 'ALERTA: ' . $a;
        }
        return $l;
    }

    public function dfs(array $r): array
    {
        $l = [sprintf('Búsqueda DFS en el grafo: %d nodos explorados, %d hojas evaluadas.', $r['nodos'], $r['hojas']),
              sprintf('Meta: rescatar al menos %.0f %% del lote (%.2f de %.2f Bs).', $r['umbral'] * 100, $r['meta'], $r['valor_lote'])];
        foreach ($r['menu'] as $platoId => $k) {
            $l[] = sprintf('  - %s: %d porciones', $this->grafo->nombrePlato($platoId), $k);
        }
        $l[] = sprintf('Se rescatan %.2f Bs (%.1f %%) usando %.0f g; sobran %.0f g (%.2f Bs de merma).',
            $r['valor_rescatado'], $r['porcentaje'] * 100, $r['gramos_usados'], $r['gramos_sobrante'], $r['valor_merma']);
        if ($r['advertencia'] !== null) {
            $l[] = 'ADVERTENCIA: ' . $r['advertencia'];
        }
        return $l;
    }

    public function aEstrella(array $r): array
    {
        $l = [sprintf('Búsqueda A*: %d nodos expandidos, %d generados%s.', $r['expandidos'], $r['generados'],
                $r['heuristica'] ? '' : ' (sin heurística)'),
              sprintf('Sin intervenir se perderían %.2f Bs. Con el plan: %.2f Bs (descuentos %.2f + merma %.0f g). Ahorro: %.2f Bs.',
                $r['costo_sin_intervencion'], $r['costo_total'], $r['descuentos'], $r['merma_final_g'], $r['ahorro'])];
        foreach ($r['plan'] as $p) {
            $l[] = sprintf('  - Día %d: promocionar %d porciones de %s (descuento total %.2f Bs)',
                $p['dia'], $p['porciones'], $this->grafo->nombrePlato($p['plato_id']), $p['descuento']);
        }
        if ($r['plan'] === []) {
            $l[] = '  - No hace falta promocionar: la demanda esperada ya consume el lote.';
        }
        return $l;
    }

    public function compra(float $gramos, float $stock, float $consumoDiario, float $objetivoDias): array
    {
        return [sprintf('Stock %.0f g con consumo de %.0f g/día; para cubrir %.1f días conviene comprar %.0f g.',
            $stock, $consumoDiario, $objetivoDias, $gramos)];
    }
}