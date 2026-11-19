<?php
declare(strict_types=1);

/**
 * Vista: formulario de consulta + resultados. Toda la lógica está en src/Sistema.php.
 * Ejecutar en desarrollo:  php -S localhost:8000 -t public
 */
require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../config/conexion.php';

session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function opciones(array $valores, string $actual): string
{
    $h = '';
    foreach ($valores as $valor => $texto) {
        $h .= '<option value="' . e($valor) . '"' . ($valor === $actual ? ' selected' : '') . '>' . e($texto) . '</option>';
    }
    return $h;
}

const TXT_TIPO_DIA = ['laboral' => 'Laboral', 'fin_de_semana' => 'Fin de semana', 'feriado' => 'Feriado'];
const TXT_VISUAL   = ['bueno' => 'Bueno', 'regular' => 'Regular', 'malo' => 'Malo'];
const TXT_FRIO     = ['cumple' => 'Cumple', 'no_cumple' => 'No cumple'];
const DIAS_ES      = [1 => 'lun', 2 => 'mar', 3 => 'mié', 4 => 'jue', 5 => 'vie', 6 => 'sáb', 7 => 'dom'];

$hoy = date('Y-m-d');
$error = null;
$resultado = null;

try {
    $repo = new Repositorio();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new InvalidArgumentException('La sesión expiró. Recarga la página e inténtalo de nuevo.');
        }
        $entrada = [
            'tipo'          => (string)($_POST['tipo'] ?? 'plan_3_dias'),
            'observaciones' => trim((string)($_POST['observaciones'] ?? '')),
            'tipo_dia'      => is_array($_POST['tipo_dia'] ?? null) ? $_POST['tipo_dia'] : [],
            'lotes'         => is_array($_POST['lote'] ?? null) ? $_POST['lote'] : [],
            'nuevo_lote'    => is_array($_POST['nuevo'] ?? null) ? $_POST['nuevo'] : [],
        ];
        try {
            $resultado = (new Sistema($repo))->ejecutar($entrada, $hoy);
        } catch (PDOException $ex) {
            error_log('[Aroma] ' . $ex->getMessage());
            throw new InvalidArgumentException(
                'No se pudieron guardar los datos. Revisa que el stock de cada lote no supere su cantidad inicial.');
        }
    }

    // Se muestra siempre el estado actual de la BD (ya incluye lo que se acaba de guardar)
    $lotes   = $repo->lotes();
    $tipos   = $repo->tiposDia($hoy, 3);
    $insumos = $repo->insumos();
} catch (InvalidArgumentException $ex) {
    $error = $ex->getMessage();
} catch (Throwable $ex) {
    error_log('[Aroma] ' . $ex);
    $error = 'Error del sistema. Verifica que MySQL esté activo y que la base aroma_se esté cargada.';
}
$lotes   ??= [];
$tipos   ??= [];
$insumos ??= [];
$vistaPost = $_SERVER['REQUEST_METHOD'] === 'POST';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Aroma — Sistema experto contra la merma</title>
<style>
  :root { --borde:#d9d4cb; --fondo:#faf8f4; --tinta:#2b2a27; --verde:#2f6b4f; --rojo:#a33a2c; --ambar:#a8741a; --azul:#2c5a8a; }
  * { box-sizing: border-box; }
  body { font: 15px/1.5 system-ui, sans-serif; margin: 0; background: var(--fondo); color: var(--tinta); }
  main { max-width: 1000px; margin: 0 auto; padding: 24px 16px 64px; }
  h1 { font-size: 1.5rem; margin: 0 0 4px; } h2 { font-size: 1.15rem; margin: 28px 0 10px; }
  .sub { color: #6b6860; margin: 0 0 20px; }
  fieldset { border: 1px solid var(--borde); border-radius: 8px; background: #fff; margin: 0 0 16px; padding: 12px 16px; }
  legend { font-weight: 600; padding: 0 6px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #eee9df; vertical-align: middle; }
  th { font-size: .8rem; color: #6b6860; font-weight: 600; }
  input, select, textarea { font: inherit; padding: 5px 8px; border: 1px solid var(--borde); border-radius: 6px; background: #fff; }
  input[type=number] { width: 110px; } textarea { width: 100%; }
  .fila { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; }
  .dia { display: flex; flex-direction: column; gap: 4px; font-size: .85rem; }
  button { font: inherit; background: var(--verde); color: #fff; border: 0; border-radius: 8px; padding: 10px 20px; cursor: pointer; }
  .error { background: #fbeae7; border: 1px solid #e3b5ad; color: #7a2a1f; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; }
  .tarjeta { background: #fff; border: 1px solid var(--borde); border-left-width: 5px; border-radius: 8px; padding: 12px 16px; margin: 0 0 12px; }
  .a-descartar { border-left-color: var(--rojo); } .a-menu_emergencia { border-left-color: var(--ambar); }
  .a-promocionar { border-left-color: var(--azul); } .a-comprar { border-left-color: #6a4c93; }
  .a-mantener { border-left-color: var(--verde); } .a-ninguna { border-left-color: #888; }
  .etq { display: inline-block; font-size: .75rem; font-weight: 700; padding: 2px 8px; border-radius: 99px; background: #eee9df; margin-right: 8px; text-transform: uppercase; }
  .fc { float: right; font-size: .85rem; color: #6b6860; }
  .alerta { color: #7a2a1f; font-size: .9rem; margin: 6px 0 0; }
  ul.acc { margin: 8px 0 0; padding-left: 20px; }
  details { margin-top: 8px; } summary { cursor: pointer; color: #6b6860; font-size: .85rem; }
  pre { white-space: pre-wrap; font: .8rem/1.5 ui-monospace, monospace; background: #f3efe7; padding: 10px; border-radius: 6px; }
  .resumen { background: #eaf3ee; border: 1px solid #bcd9c8; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; }
</style>
</head>
<body>
<main>
  <h1>Aroma — Sistema experto contra la merma</h1>
  <p class="sub">Hoy: <?= e(date('d/m/Y')) ?>. Los datos del formulario vienen de la base de datos; corrige lo que haya cambiado y consulta.</p>

  <?php if ($error !== null): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<?php if ($resultado !== null): ?>
  <h2>Recomendaciones (consulta #<?= e($resultado['consulta_id']) ?>)</h2>
  <div class="resumen">
    <?= count($resultado['resultados']) ?> lote(s) evaluados.
    Ahorro estimado frente a no intervenir: <strong><?= e(number_format($resultado['ahorro_total'], 2, '.', '')) ?> Bs</strong>.
  </div>
  <?php foreach ($resultado['resultados'] as $r):
      $clase = $r['accion'] ?? 'ninguna';
      $l = $r['lote']; ?>
    <div class="tarjeta a-<?= e($clase) ?>">
      <span class="fc">Certeza (FC): <?= e(number_format($r['fc'] * 100, 1, '.', '')) ?> %</span>
      <span class="etq"><?= e(str_replace('_', ' ', $r['accion'] ?? 'sin acción')) ?></span>
      <strong><?= e($l['insumo']) ?></strong> — lote <?= e($l['lote_id']) ?>,
      <?= e(number_format($l['stock_actual'], 0, '.', '')) ?> g,
      <?= $l['dias_vida'] < 0 ? 'vencido' : ($l['dias_vida'] === 0 ? 'vence hoy' : 'vence en ' . e($l['dias_vida']) . ' día(s)') ?>
      <?php if ($r['filas'] !== []): ?>
        <ul class="acc"><?php foreach ($r['filas'] as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php foreach ($r['alertas'] as $a): ?><p class="alerta">⚠ <?= e($a) ?></p><?php endforeach; ?>
      <details><summary>¿Por qué?</summary><pre><?= e(implode("\n", $r['explicacion'])) ?></pre></details>
    </div>
  <?php endforeach; ?>
  <?php if ($resultado['resultados'] === []): ?><p>No hay lotes que evaluar para este tipo de consulta.</p><?php endif; ?>
  <h2>Nueva consulta</h2>
<?php endif; ?>

<?php if ($lotes !== [] || !$vistaPost): ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

    <fieldset>
      <legend>1. Tipo de consulta</legend>
      <div class="fila">
        <label><input type="radio" name="tipo" value="plan_3_dias" checked> Plan de 3 días (todos los lotes)</label>
        <label><input type="radio" name="tipo" value="emergencia"> Emergencia (solo lotes que vencen hoy o vencidos)</label>
      </div>
    </fieldset>

    <fieldset>
      <legend>2. Tipo de día (hoy y los próximos)</legend>
      <div class="fila">
        <?php foreach ($tipos as $t):
            $d = new DateTimeImmutable($t['fecha']); ?>
          <label class="dia"><?= e(DIAS_ES[(int)$d->format('N')] . ' ' . $d->format('d/m')) ?>
            <select name="tipo_dia[<?= e($t['fecha']) ?>]"><?= opciones(TXT_TIPO_DIA, $t['tipo_dia']) ?></select>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset>
      <legend>3. Estado de los lotes</legend>
      <table>
        <tr><th>Lote</th><th>Insumo</th><th>Riesgo</th><th>Vida (días)</th><th>Stock (g)</th><th>Estado visual</th><th>Cadena de frío</th></tr>
        <?php foreach ($lotes as $l): $id = $l['lote_id']; ?>
          <tr>
            <td><?= e($id) ?></td>
            <td><?= e($l['insumo']) ?></td>
            <td><?= e($l['categoria_riesgo']) ?></td>
            <td><?= e($l['dias_vida']) ?></td>
            <td><input type="number" name="lote[<?= e($id) ?>][stock]" min="0" step="0.01" value="<?= e($l['stock_actual']) ?>" required></td>
            <td><select name="lote[<?= e($id) ?>][visual]"><?= opciones(TXT_VISUAL, $l['estado_visual']) ?></select></td>
            <td><select name="lote[<?= e($id) ?>][frio]"><?= opciones(TXT_FRIO, $l['cadena_frio']) ?></select></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </fieldset>

    <fieldset>
      <legend>4. Registrar un lote nuevo (opcional)</legend>
      <div class="fila">
        <label>Insumo
          <select name="nuevo[insumo_id]">
            <option value="">— ninguno —</option>
            <?php foreach ($insumos as $id => $i): ?><option value="<?= e($id) ?>"><?= e($i['nombre']) ?></option><?php endforeach; ?>
          </select></label>
        <label>Vence <input type="date" name="nuevo[fecha_vencimiento]" min="<?= e($hoy) ?>"></label>
        <label>Cantidad (g) <input type="number" name="nuevo[cantidad]" min="0" step="0.01"></label>
        <label>Estado <select name="nuevo[visual]"><?= opciones(TXT_VISUAL, 'bueno') ?></select></label>
        <label>Frío <select name="nuevo[frio]"><?= opciones(TXT_FRIO, 'cumple') ?></select></label>
      </div>
    </fieldset>

    <fieldset>
      <legend>5. Observaciones (opcional)</legend>
      <textarea name="observaciones" rows="2" maxlength="300"></textarea>
    </fieldset>

    <button type="submit">Consultar al sistema</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>