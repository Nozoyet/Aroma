<?php
declare(strict_types=1);

/**
 * Prueba del motor de reglas SIN base de datos (datos simulados en este archivo).
 * Ejecutar:  php pruebas/probar_motor.php
 * Verifica los casos del informe: descartar, comprar, DFS (meta no alcanzada) y A*.
 */
require_once __DIR__ . '/../src/autoload.php';

// FC de R1..R22 tal como están en la tabla `regla`
$fc = [
    'R1' => 1.00, 'R2' => 1.00, 'R3' => 1.00, 'R4' => 1.00, 'R5' => 1.00, 'R6' => 0.90, 'R7' => 0.95,
    'R8' => 0.90, 'R9' => 0.90, 'R10' => 0.80, 'R11' => 0.70, 'R12' => 0.95, 'R13' => 0.80, 'R14' => 0.80,
    'R15' => 0.90, 'R16' => 0.80, 'R17' => 0.80, 'R18' => 0.90, 'R19' => 1.00, 'R20' => 0.85,
    'R21' => 0.70, 'R22' => 0.90,
];
$reglasBd   = array_map(static fn(float $v): array => ['fc_regla' => $v, 'activa' => true], $fc);
$parametros = ['umbral_fc_calidad' => 0.60, 'umbral_fc_emergencia' => 0.50];
$motor      = new MotorReglas($reglasBd, $parametros);

$fallos = 0;
function verificar(string $descripcion, bool $ok): void
{
    global $fallos;
    echo ($ok ? '  [OK]    ' : '  [FALLA] ') . $descripcion . "\n";
    if (!$ok) {
        $fallos++;
    }
}
function casi(float $a, float $b): bool
{
    return abs($a - $b) < 0.0005;
}
function lote(string $insumo, string $cat, int $dias, string $visual = 'bueno', string $frio = 'cumple'): array
{
    return ['lote_id' => 0, 'insumo' => $insumo, 'categoria_riesgo' => $cat, 'dias_vida' => $dias,
            'estado_visual' => $visual, 'cadena_frio' => $frio];
}
function mostrar(array $r): void
{
    foreach ($r['traza'] as $t) {
        $v = is_array($t['valor']) ? '[' . implode(',', $t['valor']) . ']' : (string)$t['valor'];
        printf("      %-4s %-20s = %-18s FC %.4f\n", $t['regla'], $t['conclusion'], $v, $t['fc']);
    }
    foreach ($r['alertas'] as $a) {
        echo "      ALERTA: {$a}\n";
    }
}

$viernes = ['laboral', 'fin_de_semana'];   // hoy viernes, mañana sábado

// ---------------- Caso 3: solo reglas ----------------
echo "\nCaso 3a - Calamar vence hoy, cadena de frio NO cumple\n";
$r = $motor->inferir(lote('Calamar', 'alto', 0, 'bueno', 'no_cumple'),
    ['cobertura_dias' => 1.5, 'tipo_dia_proximos' => $viernes, 'platos' => []]);
mostrar($r);
verificar('accion principal = descartar', $r['accion_principal'] === 'descartar');
verificar('FC descartar = 0.95', casi($r['acciones']['descartar'] ?? 0, 0.95));
verificar('NO se dispara menu_emergencia (R18)', !isset($r['acciones']['menu_emergencia']));

echo "\nCaso 3b - Pollo fresco, cobertura 0.16 dias, manana es fin de semana\n";
$r = $motor->inferir(lote('Pollo', 'alto', 3), ['cobertura_dias' => 0.16, 'tipo_dia_proximos' => $viernes, 'platos' => []]);
mostrar($r);
verificar('accion principal = comprar', $r['accion_principal'] === 'comprar');
verificar('FC comprar = 0.85 + 0.70 x 0.15 = 0.955', casi($r['acciones']['comprar'] ?? 0, 0.955));
verificar('R22 (mantener) no se dispara', !isset($r['acciones']['mantener']));

// ---------------- Caso 4: lengua que vence hoy (DFS) ----------------
echo "\nCaso 4 - Lengua vence hoy, estado bueno\n";
$platos = [
    1 => ['nombre' => 'Lengua de Res Picante', 'demanda_total' => 0.0, 'fc_demanda' => 0.7, 'porciones_posibles' => 12],
    2 => ['nombre' => 'Lengua con Pure', 'demanda_total' => 0.0, 'fc_demanda' => 0.7, 'porciones_posibles' => 12],
];
$r = $motor->inferir(lote('Lengua de res', 'alto', 0), ['cobertura_dias' => 1.0, 'tipo_dia_proximos' => ['laboral', 'laboral'], 'platos' => $platos]);
mostrar($r);
verificar('accion principal = menu_emergencia', $r['accion_principal'] === 'menu_emergencia');
verificar('FC menu_emergencia = 0.9 x 0.5 = 0.45', casi($r['acciones']['menu_emergencia'] ?? 0, 0.45));
verificar('fc_calidad (0.5) < umbral de promocion (0.6): no es candidato a promocion', !isset($r['acciones']['promocionar']));

// ---------------- Caso A*: lengua que vence en 2 dias ----------------
echo "\nCaso A* - Lengua vence en 2 dias, cobertura 3.4 dias\n";
$platos = [
    1 => ['nombre' => 'Lengua de Res Picante', 'demanda_total' => 36.0, 'fc_demanda' => 0.8, 'porciones_posibles' => 60],
    2 => ['nombre' => 'Lengua con Pure', 'demanda_total' => 16.0, 'fc_demanda' => 0.8, 'porciones_posibles' => 60],
];
$r = $motor->inferir(lote('Lengua de res', 'alto', 2), ['cobertura_dias' => 3.4, 'tipo_dia_proximos' => ['laboral', 'laboral'], 'platos' => $platos]);
mostrar($r);
verificar('accion principal = promocionar', $r['accion_principal'] === 'promocionar');
verificar('cadena de FC: R13 0.8 -> R16 0.56 -> R17 0.448', casi($r['acciones']['promocionar'] ?? 0, 0.448));
verificar('platos a promocionar = P1 y P2', $r['platos_promocion'] === [1, 2]);

// ---------------- Casos de borde ----------------
echo "\nBorde - Lechuga por vencer con estado visual REGULAR (ninguna regla de calidad aplica)\n";
$r = $motor->inferir(lote('Lechuga', 'medio', 2, 'regular'), ['cobertura_dias' => 5.0, 'tipo_dia_proximos' => ['laboral', 'laboral'], 'platos' => [1 => $platos[1]]]);
mostrar($r);
verificar('sin accion principal y con alerta para el chef', $r['accion_principal'] === null && count($r['alertas']) === 1);

echo "\nBorde - Papa vencida (riesgo bajo): ninguna regla de descarte\n";
$r = $motor->inferir(lote('Papa', 'bajo', -1), ['cobertura_dias' => 5.0, 'tipo_dia_proximos' => ['laboral', 'laboral'], 'platos' => []]);
mostrar($r);
verificar('alerta de lote vencido sin regla', count($r['alertas']) === 1);

echo "\nBorde - Regla desactivada (R19) en la BD\n";
$bd2 = $reglasBd;
$bd2['R19']['activa'] = false;
$r = (new MotorReglas($bd2, $parametros))->inferir(lote('Calamar', 'alto', 0, 'bueno', 'no_cumple'),
    ['cobertura_dias' => 1.5, 'tipo_dia_proximos' => $viernes, 'platos' => []]);
verificar('sin R19 ya no se recomienda descartar', !isset($r['acciones']['descartar']));

echo "\n" . ($fallos === 0 ? "Todas las pruebas pasaron.\n" : "{$fallos} prueba(s) fallaron.\n");
exit($fallos === 0 ? 0 : 1);
