<?php
declare(strict_types=1);

/**
 * Vista. Dos pantallas separadas:
 *   index.php                 -> formulario de consulta
 *   index.php?ver=resultado   -> recomendaciones (se llega tras enviar el formulario)
 * Al enviar el formulario se guarda el resultado en la sesión y se redirige (patrón POST-Redirect-GET):
 * así el botón "atrás" del navegador vuelve al formulario y recargar no repite la consulta.
 * Toda la lógica está en src/Sistema.php. Ejecutar en desarrollo:  php -S localhost:8000 -t public
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
        $h .= '<option value="' . e($valor) . '"' . ((string)$valor === $actual ? ' selected' : '') . '>' . e($texto) . '</option>';
    }
    return $h;
}
function vida(int $dias): string
{
    return $dias < 0 ? 'vencido' : ($dias === 0 ? 'vence hoy' : ($dias === 1 ? 'vence mañana' : "vence en {$dias} días"));
}

const TXT_TIPO_DIA = ['laboral' => 'Laboral', 'fin_de_semana' => 'Fin de semana', 'feriado' => 'Feriado'];
const TXT_VISUAL   = ['bueno' => 'Bueno', 'regular' => 'Regular', 'malo' => 'Malo'];
const TXT_FRIO     = ['cumple' => 'Cumple', 'no_cumple' => 'No cumple'];
const TXT_TIPO     = ['plan_3_dias' => 'Plan de 3 días', 'emergencia' => 'Emergencia'];
const DIAS_ES      = [1 => 'lun', 2 => 'mar', 3 => 'mié', 4 => 'jue', 5 => 'vie', 6 => 'sáb', 7 => 'dom'];
const ETIQUETA     = ['menu_emergencia' => 'Menú de emergencia', 'descartar' => 'Descartar', 'promocionar' => 'Promocionar',
                      'comprar' => 'Comprar', 'mantener' => 'Mantener', 'ninguna' => 'Revisar a mano'];
const CONFIANZA    = ['alta' => 'Confianza alta', 'media' => 'Confianza media', 'baja' => 'Confianza baja: confirme antes de actuar'];

$ruta = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$hoy  = date('Y-m-d');

// ---------------------------------------------------------------- envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new InvalidArgumentException('La sesión expiró. Vuelve a intentarlo.');
        }
        $entrada = [
            'tipo'          => (string)($_POST['tipo'] ?? 'plan_3_dias'),
            'observaciones' => trim((string)($_POST['observaciones'] ?? '')),
            'tipo_dia'      => is_array($_POST['tipo_dia'] ?? null) ? $_POST['tipo_dia'] : [],
            'lotes'         => is_array($_POST['lote'] ?? null) ? $_POST['lote'] : [],
            'nuevos_lotes'  => is_array($_POST['nuevo'] ?? null) ? array_values($_POST['nuevo']) : [],
            'insumos'       => is_array($_POST['insumos'] ?? null) ? array_values($_POST['insumos']) : [],
        ];
        $_SESSION['resultado'] = (new Sistema(new Repositorio()))->ejecutar($entrada, $hoy);
        $_SESSION['resultado_hora'] = date('H:i');
        header('Location: ' . $ruta . '?ver=resultado');
        exit;
    } catch (InvalidArgumentException $ex) {
        $_SESSION['error'] = $ex->getMessage();
    } catch (PDOException $ex) {
        error_log('[Aroma] ' . $ex->getMessage());
        $_SESSION['error'] = 'No se pudieron guardar los datos. Revisa que el stock de cada lote no supere su cantidad inicial.';
    } catch (Throwable $ex) {
        error_log('[Aroma] ' . $ex);
        $_SESSION['error'] = 'Error del sistema. Verifica que MySQL esté activo y que la base aroma_se esté cargada.';
    }
    header('Location: ' . $ruta);
    exit;
}

// ---------------------------------------------------------------- pantallas
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
$pantalla = ($_GET['ver'] ?? '') === 'resultado' ? 'resultado' : 'formulario';
$lotes = $tipos = $insumos = [];
$resultado = null;

if ($pantalla === 'resultado') {
    $resultado = $_SESSION['resultado'] ?? null;
    if ($resultado === null) {            // se abrió sin haber consultado
        header('Location: ' . $ruta);
        exit;
    }
} else {
    try {
        $repo    = new Repositorio();
        $lotes   = $repo->lotes();
        $tipos   = $repo->tiposDia($hoy, 3);
        $insumos = $repo->insumos();
    } catch (Throwable $ex) {
        error_log('[Aroma] ' . $ex);
        $error ??= 'Error del sistema. Verifica que MySQL esté activo y que la base aroma_se esté cargada.';
    }
}

// ---------------------------------------------------------------- piezas de la vista de resultados
/** Tarjeta de un lote. $grande = destacada (menú de emergencia). */
function tarjeta(array $r, bool $grande = false): void
{
    $l = $r['lote'];
    $acc = $r['accion'] ?? 'ninguna'; ?>
    <article class="tarjeta a-<?= e($acc) ?><?= $grande ? ' grande' : '' ?>">
      <header>
        <span class="etq"><?= e(ETIQUETA[$acc]) ?></span>
        <h3><?= e($l['insumo']) ?></h3>
        <p class="sec">Lote <?= e($l['lote_id']) ?> · <?= e(Explicador::peso($l['stock_actual'])) ?> · <?= e(vida($l['dias_vida'])) ?></p>
      </header>

      <?php if ($r['items'] !== []): ?>
        <ul class="items">
          <?php foreach ($r['items'] as $i): ?>
            <li><span class="cant"><?= e($i['cantidad']) ?></span>
                <span class="nom"><?= e($i['nombre']) ?></span>
                <?php if ($i['nota'] !== ''): ?><span class="sec nota"><?= e($i['nota']) ?></span><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($r['metricas'] !== []): ?>
        <dl class="metricas">
          <?php foreach ($r['metricas'] as $m): ?>
            <div><dt><?= e($m['etiqueta']) ?></dt><dd><?= e($m['valor']) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      <?php endif; ?>

      <div class="porque">
        <h4>¿Por qué?</h4>
        <?php foreach ($r['motivo'] as $frase): ?><p><?= e($frase) ?></p><?php endforeach; ?>
        <?php foreach ($r['alertas'] as $a): ?><p class="aviso">⚠ <?= e($a) ?></p><?php endforeach; ?>
      </div>

      <footer>
        <?php if ($r['confianza'] !== null): ?><span class="sec"><?= e(CONFIANZA[$r['confianza']]) ?></span><?php endif; ?>
        <details class="tec"><summary>Detalle técnico</summary><pre><?= e(implode("\n", $r['tecnico'])) ?></pre></details>
      </footer>
    </article>
<?php }

/** Todas las compras del día en una sola tarjeta. */
function tarjeta_compras(array $b): void
{ ?>
    <article class="tarjeta a-comprar">
      <header>
        <span class="etq">Comprar</span>
        <h3>Lista de compras</h3>
      </header>
      <ul class="items">
        <?php foreach ($b['items'] as $i): ?>
          <li><span class="cant"><?= e($i['cantidad']) ?></span>
              <span class="nom"><?= e($i['nombre']) ?></span>
              <span class="sec nota"><?= e($i['nota']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <div class="porque">
        <h4>¿Por qué?</h4>
        <p>Estos insumos tienen stock para menos de 2 días de ventas<?= $b['finde'] ? ', y hoy o mañana hay fin de semana o feriado, cuando se vende más' : '' ?>.
           Las cantidades alcanzan para cubrir unos 2 días.</p>
      </div>
    </article>
<?php }

/** Lista compacta para lo que no requiere acción. */
function lista_compacta(array $rs): void
{ ?>
    <ul class="compacta">
      <?php foreach ($rs as $r): $l = $r['lote']; ?>
        <li><strong><?= e($l['insumo']) ?></strong>
            <span class="sec">· lote <?= e($l['lote_id']) ?> · <?= e(Explicador::peso($l['stock_actual'])) ?> · <?= e(vida($l['dias_vida'])) ?></span>
            <p><?= e(implode(' ', $r['motivo'])) ?></p></li>
      <?php endforeach; ?>
    </ul>
<?php }

/** Gráfico semanal (promedio por día de cada semana): 8 semanas pasadas y 4 de pronóstico. */
function grafico_semanal(array $semanas, string $titulo): string
{
    $n = count($semanas);
    $W = 720; $H = 260; $ml = 52; $mr = 16; $mt = 22; $mb = 34;
    $x = static fn(int $i): float => $ml + $i * ($W - $ml - $mr) / ($n - 1);
    $max = 0.0;
    foreach ($semanas as $w) { $max = max($max, (float)($w['real'] ?? 0), $w['esperado'] + $w['sd']); }
    $paso = 1.0;
    foreach ([1, 2, 5, 10, 20, 25, 50] as $c) { $paso = (float)$c; if ($max / $paso <= 5) { break; } }
    $tope = max($paso, ceil($max * 1.08 / $paso) * $paso);
    $y = static fn(float $v): float => $mt + ($H - $mt - $mb) * (1 - $v / $tope);
    $hoyIdx = 0;
    foreach ($semanas as $i => $w) { if ($w['futuro']) { $hoyIdx = $i; break; } }
    $xl = $x($hoyIdx) - ($x(1) - $x(0)) / 2;   // el límite cae entre la última semana pasada y la primera futura

    $o = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e($titulo) . '" class="grafico">';
    for ($v = 0.0; $v <= $tope + 1e-9; $v += $paso) {
        $o .= '<line x1="' . $ml . '" x2="' . ($W - $mr) . '" y1="' . round($y($v), 1) . '" y2="' . round($y($v), 1) . '" class="g-rej"/>';
        $o .= '<text x="' . ($ml - 8) . '" y="' . round($y($v) + 4, 1) . '" class="g-eje" text-anchor="end">' . e(number_format($v, 0, ',', ' ')) . '</text>';
    }
    $o .= '<text x="' . $ml . '" y="12" class="g-eje">porciones por día (promedio de la semana)</text>';
    foreach ($semanas as $i => $w) {
        if ($i % 2 === ($hoyIdx % 2)) {
            $o .= '<text x="' . round($x($i), 1) . '" y="' . ($H - 10) . '" class="g-eje" text-anchor="middle">' . e((new DateTimeImmutable($w['desde']))->format('d/m')) . '</text>';
        }
    }
    $o .= '<rect x="' . round($xl, 1) . '" y="' . $mt . '" width="' . round($W - $mr - $xl, 1) . '" height="' . ($H - $mt - $mb) . '" class="g-fut"/>';
    $alto = $bajo = [];
    foreach ($semanas as $i => $w) {
        if ($w['futuro']) {
            $alto[] = round($x($i), 1) . ',' . round($y($w['esperado'] + $w['sd']), 1);
            $bajo[] = round($x($i), 1) . ',' . round($y(max(0.0, $w['esperado'] - $w['sd'])), 1);
        }
    }
    $o .= '<polygon points="' . implode(' ', array_merge($alto, array_reverse($bajo))) . '" class="g-banda"/>';
    $o .= '<line x1="' . round($xl, 1) . '" x2="' . round($xl, 1) . '" y1="' . $mt . '" y2="' . ($H - $mb) . '" class="g-hoy"/>';
    $o .= '<text x="' . round($xl - 7, 1) . '" y="' . ($mt + 12) . '" class="g-eje g-et" text-anchor="end">← semanas vendidas</text>';
    $o .= '<text x="' . round($xl + 7, 1) . '" y="' . ($mt + 12) . '" class="g-eje g-et">próximas 4 semanas →</text>';
    $pt = static fn(array $lista): string => implode(' ', array_map(static fn(int $i): string => round($x($i), 1) . ',' . round($y($lista[$i]['esperado']), 1), array_keys($lista)));
    $o .= '<polyline points="' . $pt(array_slice($semanas, 0, $hoyIdx, true)) . '" class="g-mod"/>';
    $o .= '<polyline points="' . $pt(array_slice($semanas, max(0, $hoyIdx - 1), null, true)) . '" class="g-pron"/>';
    $tramo = [];
    foreach ($semanas as $i => $w) {
        if ($w['real'] !== null) { $tramo[] = round($x($i), 1) . ',' . round($y((float)$w['real']), 1); }
    }
    if (count($tramo) > 1) { $o .= '<polyline points="' . implode(' ', $tramo) . '" class="g-real"/>'; }
    foreach ($semanas as $i => $w) {
        if ($w['real'] !== null) {
            $o .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y((float)$w['real']), 1) . '" r="4" class="g-punto"><title>Semana del '
                . e((new DateTimeImmutable($w['desde']))->format('d/m') . ': ' . number_format((float)$w['real'], 1, ',', ' ') . ' porciones por día') . '</title></circle>';
        }
    }
    return $o . '</svg>';
}

/** Veredicto en palabras del chef para un plato de la carta. @return array{0: string, 1: string, 2: string} etiqueta, clase, recomendación */
function veredicto_carta(array $f): array
{
    $alto = $f['nivel'] !== 'bajo';
    return match ($f['tendencia']) {
        'crece' => ['En crecimiento', 'v-crece', $alto
            ? 'Conviene mantenerlo en el menú y asegurar su stock: se espera que venda más.'
            : 'Todavía vende poco, pero va en aumento: conviene mantenerlo y seguir observándolo.'],
        'baja' => ['A la baja', 'v-baja', $alto
            ? 'Aún vende bien pero va cayendo: conviene vigilarlo y revisar su precio o presentación.'
            : 'Vende poco y sigue cayendo: conviene considerar reemplazarlo.'],
        default => ['Estable', 'v-estable', $alto
            ? 'Conviene mantenerlo en el menú: vende bien y de forma estable.'
            : 'Vende poco y sin crecer: conviene evaluar si aporta variedad o margen antes de seguir comprando para él.'],
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Aroma — Sistema experto contra la merma</title>
<style>
  :root { --fondo:#080808; --caja:#181716; --titulo:#DCD0BB; --sub:#C18A36; --linea:#A65F19; --sec:#A69A89; --alerta:#C0503F; }
  * { box-sizing: border-box; }
  html { color-scheme: dark; }
  body { margin: 0; background: var(--fondo); color: var(--titulo); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
  main { max-width: 960px; margin: 0 auto; padding: 36px 20px 80px; }
  h1, h2, h3 { font-family: Georgia, "Times New Roman", serif; font-weight: 600; margin: 0; }
  h1 { font-size: 2.1rem; letter-spacing: .01em; }
  h2 { color: var(--sub); font-family: system-ui, sans-serif; font-size: .8rem; letter-spacing: .14em; text-transform: uppercase;
       padding-bottom: 8px; border-bottom: 1px solid var(--linea); margin: 40px 0 16px; }
  h3 { font-size: 1.3rem; }
  .sec { color: var(--sec); }
  .sub { color: var(--sub); }
  .cabecera { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap;
              padding-bottom: 18px; border-bottom: 1px solid var(--linea); }
  .cabecera p { margin: 4px 0 0; }

  /* botones y enlaces */
  .btn { display: inline-block; font: inherit; font-weight: 600; background: var(--sub); color: var(--fondo); border: 0;
         border-radius: 8px; padding: 11px 24px; cursor: pointer; text-decoration: none; }
  .btn:hover { filter: brightness(1.1); }
  .btn-sec { background: transparent; color: var(--titulo); border: 1px solid var(--linea); }
  .btn-sec:hover { background: var(--caja); }

  /* formulario */
  .caja { background: var(--caja); border: 1px solid rgba(166,95,25,.45); border-radius: 12px; padding: 18px 22px; }
  .caja h2 { margin-top: 0; }
  input, select, textarea { font: inherit; color: var(--titulo); background: var(--fondo); border: 1px solid var(--linea);
                            border-radius: 6px; padding: 6px 10px; }
  input:focus, select:focus, textarea:focus { outline: 2px solid var(--sub); outline-offset: 1px; }
  input[type=number] { width: 120px; } textarea { width: 100%; resize: vertical; }
  .fila { display: flex; gap: 16px; flex-wrap: wrap; align-items: stretch; }
  .opcion { flex: 1 1 260px; border: 1px solid var(--linea); border-radius: 10px; padding: 12px 16px; cursor: pointer; display: block; }
  .opcion:has(input:checked) { border-color: var(--sub); background: rgba(193,138,54,.08); }
  .opcion small { display: block; color: var(--sec); }
  .dia { display: flex; flex-direction: column; gap: 4px; color: var(--sec); font-size: .9rem; }
  .tabla { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid rgba(166,95,25,.35); white-space: nowrap; }
  th { color: var(--sec); font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; }
  tr:last-child td { border-bottom: 0; }
  .urgente { color: var(--sub); font-weight: 600; border-color: var(--sub); }
  .ayuda { color: var(--sec); font-size: .85rem; margin: 10px 0 0; }
  .acciones { margin-top: 24px; }

  .error { border: 1px solid var(--alerta); color: var(--titulo); background: rgba(192,80,63,.12);
           padding: 12px 16px; border-radius: 10px; margin: 20px 0 0; }

  /* resultados */
  .resumen { display: flex; gap: 10px 28px; flex-wrap: wrap; margin-top: 22px; }
  .resumen div { color: var(--sec); }
  .resumen strong { color: var(--titulo); font-size: 1.15rem; margin-right: 4px; }
  .tarjeta { background: var(--caja); border: 1px solid rgba(166,95,25,.45); border-left: 4px solid var(--linea);
             border-radius: 12px; padding: 18px 22px; margin: 0 0 14px; }
  .tarjeta header { margin-bottom: 12px; }
  .tarjeta header .sec { margin: 2px 0 0; font-size: .9rem; }
  .etq { display: inline-block; font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
         color: var(--sub); border: 1px solid var(--sub); border-radius: 99px; padding: 1px 10px; margin-bottom: 6px; }
  .a-descartar { border-left-color: var(--alerta); }
  .a-descartar .etq { color: var(--alerta); border-color: var(--alerta); }
  .items { list-style: none; margin: 0 0 14px; padding: 0; }
  .items li { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 14px; padding: 8px 0; border-top: 1px solid rgba(166,95,25,.35); }
  .items li:first-child { border-top: 0; }
  .cant { color: var(--sub); font-weight: 700; min-width: 8.5em; }
  .nota { font-size: .9rem; }
  .metricas { display: flex; gap: 10px 32px; flex-wrap: wrap; margin: 0 0 14px; }
  .metricas dt { color: var(--sec); font-size: .8rem; }
  .metricas dd { margin: 0; font-size: 1.1rem; }
  .porque h4 { margin: 0 0 4px; color: var(--sub); font-size: .8rem; letter-spacing: .1em; text-transform: uppercase; }
  .porque p { margin: 0 0 6px; color: var(--titulo); }
  .porque .aviso { color: var(--sub); }
  .tarjeta footer { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;
                    margin-top: 10px; font-size: .85rem; }
  details summary { cursor: pointer; color: var(--sec); }
  .tec pre { white-space: pre-wrap; margin: 8px 0 0; padding: 10px 12px; background: var(--fondo); border-radius: 8px;
             color: var(--sec); font: .78rem/1.5 ui-monospace, Menlo, monospace; max-width: 100%; }

  /* menú de emergencia: la respuesta principal */
  .tarjeta.grande { border: 1px solid var(--sub); border-left-width: 6px; border-left-color: var(--sub); padding: 24px 28px; }
  .tarjeta.grande h3 { font-size: 1.7rem; color: var(--titulo); }
  .tarjeta.grande .items li { padding: 12px 0; }
  .tarjeta.grande .nom { font-family: Georgia, serif; font-size: 1.35rem; }
  .tarjeta.grande .cant { font-size: 1.35rem; }
  .tarjeta.grande .metricas dd { font-size: 1.3rem; color: var(--sub); }

  /* secciones opcionales */
  .compra-dia { margin: 14px 0 0; color: var(--sec); }
  .compra-dia strong { color: var(--sub); }
  .chk { display: inline-flex; align-items: center; gap: 6px; background: var(--fondo); border: 1px solid rgba(166,95,25,.5);
         border-radius: 999px; padding: 6px 14px; color: var(--titulo); cursor: pointer; }
  .chk input { accent-color: var(--sub); }
  .nuevo-fila { margin-bottom: 10px; }
  .aviso { background: var(--caja); border: 1px solid var(--sub); border-radius: 12px; padding: 16px 20px; margin: 0 0 16px; }
  .aviso p { margin: 0 0 12px; color: var(--titulo); }
  .aviso .btn { display: inline-block; }
  .menu-dia { margin-bottom: 16px; }
  .menu-dia h2 { margin-top: 0; }
  table.menu { width: 100%; border-collapse: collapse; margin-top: 8px; }
  table.menu th { text-align: left; color: var(--sub); font-size: .8rem; font-weight: 600; padding: 8px 8px; border-bottom: 1px solid var(--linea); }
  table.menu td { padding: 10px 8px; border-bottom: 1px solid rgba(166,95,25,.3); vertical-align: top; }
  table.menu .num { text-align: right; white-space: nowrap; }
  table.menu .prep { color: var(--sub); font-size: 1.25rem; font-weight: 700; }
  table.menu tr.tot td { border-bottom: 0; font-weight: 600; color: var(--titulo); }
  .etq-rojo { color: #C0503F; border-color: #C0503F; }
  .subdia { color: var(--sub); font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; margin: 20px 0 8px; }
  .opcional { background: var(--caja); border: 1px solid rgba(166,95,25,.45); border-radius: 12px; padding: 12px 20px; margin: 0 0 12px; }
  .opcional > summary { font-size: 1rem; }
  .opcional > summary strong { color: var(--titulo); }
  .compacta { list-style: none; margin: 12px 0 4px; padding: 0; }
  .compacta li { padding: 10px 0; border-top: 1px solid rgba(166,95,25,.35); }
  .compacta p { margin: 2px 0 0; color: var(--sec); }
  .vacio { color: var(--sec); }
  .vencen { margin: 0 0 14px; font-size: .95rem; } .vencen strong { color: var(--titulo); }
  @media (max-width: 600px) { main { padding: 24px 14px 60px; } h1 { font-size: 1.7rem; } }

  /* Aroma: restobar de fusión entre Asia y Bolivia */
  :root {
    --fondo: #101713;
    --caja: #1b241f;
    --titulo: #f5ecdf;
    --sub: #e5a45b;
    --linea: #b66d3d;
    --sec: #b9b7a8;
    --alerta: #ed745d;
    color-scheme: dark;
  }
  body {
    min-height: 100vh;
    background:
      radial-gradient(ellipse at 5% 0%, rgba(76, 112, 76, .3), transparent 34rem),
      radial-gradient(ellipse at 96% 30%, rgba(160, 77, 44, .15), transparent 30rem),
      linear-gradient(180deg, #131d17, var(--fondo) 52rem);
  }
  main { max-width: 1120px; padding-top: 42px; }
  .cabecera {
    position: relative;
    isolation: isolate;
    min-height: 152px;
    align-items: center;
    overflow: hidden;
    padding: 25px 32px;
    border: 1px solid rgba(229, 164, 91, .32);
    border-radius: 20px;
    background: linear-gradient(105deg, rgba(37, 58, 43, .98), rgba(32, 43, 35, .94) 62%, rgba(78, 48, 34, .88));
    box-shadow: 0 20px 50px rgba(0, 0, 0, .2);
    animation: aroma-entra .65s both;
  }
  .cabecera::after {
    position: absolute;
    z-index: -1;
    top: 50%; right: 34px;
    width: 190px; height: 190px;
    content: "";
    border: 1px solid rgba(239, 195, 130, .34);
    border-radius: 50%;
    background: radial-gradient(circle, rgba(208, 130, 64, .26) 0 16%, transparent 17% 30%, rgba(239, 195, 130, .08) 31% 32%, transparent 33% 47%, rgba(239, 195, 130, .12) 48% 49%, transparent 50%);
    box-shadow: 0 0 0 15px rgba(239, 195, 130, .025), 0 0 0 31px rgba(239, 195, 130, .025);
    transform: translateY(-50%);
    animation: aroma-pulso 9s ease-in-out infinite;
  }
  .cabecera > div, .cabecera > a { position: relative; z-index: 1; }
  .cabecera > div:first-child::before {
    display: block;
    margin-bottom: 7px;
    color: var(--sub);
    content: "AROMA  ·  RESTOBAR DE FUSIÓN";
    font-size: .67rem;
    font-weight: 700;
    letter-spacing: .2em;
  }
  h1 { color: #fff4e5; font-size: clamp(2.5rem, 5vw, 3.5rem); letter-spacing: -.035em; line-height: 1; }
  .cabecera p { margin-top: 10px; font-size: .95rem; }
  h2 { color: var(--sub); border-bottom-color: rgba(229, 164, 91, .3); margin-top: 40px; padding-bottom: 10px; }
  .caja {
    position: relative;
    overflow: hidden;
    background: linear-gradient(145deg, rgba(31, 43, 36, .98), rgba(24, 32, 27, .98));
    border: 1px solid rgba(229, 164, 91, .22);
    border-radius: 16px;
    padding: 22px 26px;
    box-shadow: 0 14px 34px rgba(0, 0, 0, .16);
    animation: aroma-entra .55s both;
  }
  .caja h2 { position: relative; color: #e7b36f; }
  .caja:has(.chk) {
    border-color: rgba(229, 164, 91, .38);
    background: radial-gradient(ellipse at 100% 0%, rgba(169, 92, 48, .12), transparent 25rem), linear-gradient(145deg, #24372c, #1b2821 70%);
  }
  .caja:has(.chk) h2 { color: #f4cf97; }
  .chk {
    min-height: 48px;
    gap: 9px;
    border-color: rgba(229, 164, 91, .36);
    background: rgba(12, 20, 15, .68);
    padding: 8px 15px;
    transition: transform .2s ease, background .2s ease, border-color .2s ease, box-shadow .2s ease;
  }
  .chk:hover { transform: translateY(-2px); border-color: var(--sub); background: rgba(70, 58, 37, .72); }
  .chk:has(input:checked) {
    color: #fff3e3;
    border-color: #e5a45b;
    background: linear-gradient(135deg, rgba(163, 92, 45, .52), rgba(89, 103, 57, .52));
    box-shadow: 0 4px 16px rgba(229, 164, 91, .12), inset 0 0 0 1px rgba(229, 164, 91, .15);
  }
  .chk input { width: 17px; height: 17px; accent-color: var(--sub); cursor: pointer; }
  .chk:focus-within { outline: 2px solid var(--sub); outline-offset: 3px; }
  .opcion { border-color: rgba(229, 164, 91, .3); background: rgba(255, 255, 255, .02); transition: transform .2s ease, border-color .2s ease, background .2s ease; }
  .opcion:hover { transform: translateY(-2px); border-color: var(--sub); }
  .opcion:has(input:checked) { border-color: var(--sub); background: rgba(229, 164, 91, .1); box-shadow: inset 0 0 0 1px rgba(229, 164, 91, .12); }
  .dia { color: var(--titulo); }
  input:not([type=checkbox]):not([type=radio]), select, textarea {
    min-height: 42px;
    border-color: rgba(229, 164, 91, .3);
    border-radius: 9px;
    background-color: #111a14;
    padding: 8px 11px;
  }
  input:focus, select:focus, textarea:focus { outline-color: var(--sub); }
  .tabla { border: 1px solid rgba(229, 164, 91, .18); border-radius: 11px; }
  th, td { padding: 11px 12px; border-bottom-color: rgba(229, 164, 91, .14); }
  th { background: rgba(229, 164, 91, .06); color: #dac6a8; }
  tr:hover { background: rgba(229, 164, 91, .035); }
  .btn { border-radius: 999px; padding: 12px 24px; background: linear-gradient(135deg, #edbd76, #cf8443); box-shadow: 0 8px 22px rgba(184, 106, 51, .2); transition: transform .2s ease, box-shadow .2s ease, filter .2s ease; }
  .btn:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(184, 106, 51, .28); }
  .btn-sec { color: var(--titulo); border: 1px solid rgba(229, 164, 91, .4); background: transparent; box-shadow: none; }
  .resumen { gap: 12px; margin: 24px 0 30px; }
  .resumen div { min-width: 138px; padding: 13px 16px; border: 1px solid rgba(229, 164, 91, .2); border-radius: 12px; background: rgba(255, 255, 255, .025); }
  .resumen strong { display: block; margin: 0 0 3px; color: var(--sub); font: 600 1.35rem/1.2 Georgia, serif; }
  .tarjeta, .opcional { background: linear-gradient(145deg, #202d25, #19221c); border-color: rgba(229, 164, 91, .22); border-radius: 15px; box-shadow: 0 12px 28px rgba(0, 0, 0, .14); }
  .tarjeta { padding: 22px 25px; }
  .tarjeta.grande { background: linear-gradient(130deg, rgba(119, 76, 42, .62), #202d25 78%); border-color: rgba(229, 164, 91, .65); }
  .items li, .compacta li { border-top-color: rgba(229, 164, 91, .16); }
  .porque { padding-top: 13px; border-top: 1px solid rgba(229, 164, 91, .16); }
  .opcional { padding: 15px 19px; }
  .error { border-radius: 12px; }
  .acciones { padding-top: 5px; }

  /* gráficos de demanda */
  .demanda { margin: 8px 0 22px; }
  h3.sub-g { font-size: 1.15rem; margin: 18px 0 4px; color: var(--titulo); }
  svg.grafico { width: 100%; height: auto; display: block; background: rgba(0,0,0,.18); border: 1px solid rgba(229,164,91,.18); border-radius: 12px; }
  .g-rej { stroke: rgba(229,164,91,.13); stroke-width: 1; }
  .g-eje { fill: var(--sec); font: 11px system-ui, sans-serif; }
  .g-fut { fill: rgba(229,164,91,.06); }
  .g-banda { fill: rgba(244,207,151,.2); stroke: none; }
  .g-hoy { stroke: var(--sub); stroke-width: 1; stroke-dasharray: 3 4; }
  .g-real { fill: none; stroke: rgba(229,164,91,.55); stroke-width: 1.2; }
  .g-punto { fill: #e5a45b; }
  .g-mod { fill: none; stroke: var(--sec); stroke-width: 1.4; }
  .g-tend { fill: none; stroke: #8fc08a; stroke-width: 2.6; stroke-linecap: round; }
  .g-pron { fill: none; stroke: #f4cf97; stroke-width: 2.4; stroke-dasharray: 7 5; }
  .leyenda { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px; margin: 12px 0 8px; }
  .leyenda > div { background: rgba(255,255,255,.03); border: 1px solid rgba(229,164,91,.16); border-radius: 10px; padding: 10px 12px; }
  .leyenda i { display: block; width: 44px; height: 0; border-top: 3px solid; margin-bottom: 8px; }
  .leyenda h4 { margin: 0 0 3px; font-size: .9rem; color: var(--titulo); }
  .leyenda p { margin: 0; font-size: .82rem; line-height: 1.45; color: var(--sec); }
  .leyenda i.l-real { border-color: #e5a45b; border-top-style: dotted !important; border-top-width: 5px !important; }
  .leyenda i.l-mod { border-color: var(--sec); border-top-width: 2px !important; }
  .leyenda i.l-tend { border-color: #8fc08a; border-top-width: 4px !important; }
  .leyenda i.l-pron { border-color: #f4cf97; border-top-style: dashed !important; }
  .uso { margin: 6px 0 8px; font-size: .9rem; color: var(--sec); }
  .uso strong { color: var(--sub); }
  .g-et { paint-order: stroke; stroke: #131d17; stroke-width: 4px; stroke-linejoin: round; }
  .g-et-tend { fill: #8fc08a; }
  .lectura { margin: 6px 0 8px; }
  .lectura strong { color: var(--sub); }
  .selector-pron { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 24px; margin: 6px 0 12px; }
  .selector-pron label { display: flex; align-items: center; gap: 10px; color: var(--sec); }
  .selector-pron select { min-width: 240px; }
  .horizonte { display: inline-flex; align-items: center; gap: 8px; font-size: .9rem; }
  .chip-h[hidden] { display: none; }
  .chip-h { font: inherit; font-size: .85rem; color: var(--titulo); background: transparent; border: 1px solid rgba(229,164,91,.4); border-radius: 999px; padding: 5px 16px; cursor: pointer; }
  .chip-h.on { background: linear-gradient(135deg, #edbd76, #cf8443); color: #101713; border-color: transparent; font-weight: 600; }
  .plato-carta { border-top: 1px solid rgba(229,164,91,.16); padding: 10px 0; }
  .plato-carta > summary { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; cursor: pointer; color: var(--titulo); }
  .pc-puesto { display: inline-flex; width: 28px; height: 28px; align-items: center; justify-content: center; border-radius: 50%; background: rgba(229,164,91,.15); color: var(--sub); font-weight: 700; font-size: .85rem; }
  .pc-nombre { font-family: Georgia, serif; font-size: 1.05rem; flex: 1 1 200px; }
  .pc-num { color: var(--sec); font-size: .9rem; }
  .pc-num strong { color: var(--titulo); }
  .vered { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; border-radius: 999px; padding: 2px 11px; border: 1px solid; }
  .v-crece { color: #9fd49a; border-color: #9fd49a; }
  .v-estable { color: var(--sub); border-color: var(--sub); }
  .v-baja { color: var(--alerta); border-color: var(--alerta); }
  .plato-carta svg.grafico { margin-top: 12px; }
  .reco { margin: 4px 0 6px; padding: 10px 14px; background: rgba(229,164,91,.08); border-radius: 10px; color: var(--titulo); }
  .reco strong { color: var(--sub); }
  @keyframes aroma-entra { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
  @keyframes aroma-pulso { 0%, 100% { transform: translateY(-50%) scale(1); opacity: .9; } 50% { transform: translateY(-50%) scale(1.035); opacity: .68; } }
  @media (max-width: 700px) {
    main { padding: 24px 14px 56px; }
    .cabecera { min-height: 132px; padding: 22px 20px; }
    .cabecera::after { right: -54px; opacity: .6; }
    .cabecera > div:first-child::before { max-width: 190px; font-size: .58rem; letter-spacing: .15em; }
    .caja { padding: 19px 17px; }
    .resumen div { min-width: calc(50% - 8px); flex: 1; }
    .tarjeta, .tarjeta.grande { padding: 19px 17px; }
    .acciones .btn { width: 100%; text-align: center; }
    .chk { min-height: 44px; }
  }
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; transition-duration: .01ms !important; }
  }
</style>
</head>
<body>
<main>

<?php if ($pantalla === 'formulario'): ?>
  <!-- ======================= PANTALLA 1: FORMULARIO ======================= -->
  <div class="cabecera">
    <div>
      <h1>Aroma</h1>
      <p class="sec">Sistema experto contra la merma · hoy es <?= e(DIAS_ES[(int)date('N')] . ' ' . date('d/m/Y')) ?></p>
    </div>
  </div>

  <?php if ($error !== null): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

    <section class="caja" style="margin-top:24px">
      <h2>1. ¿Qué quieres consultar?</h2>
      <div class="fila">
        <label class="opcion"><input type="radio" name="tipo" value="plan_3_dias" <?= ($_GET['tipo'] ?? '') !== 'emergencia' ? 'checked' : '' ?>>
          <strong>Plan de 3 días</strong><small>Revisa todos los lotes y propone qué hacer con cada uno.</small></label>
        <label class="opcion"><input type="radio" name="tipo" value="emergencia" <?= ($_GET['tipo'] ?? '') === 'emergencia' ? 'checked' : '' ?>>
          <strong>Emergencia</strong><small>Solo lotes que vencen hoy o ya vencidos.</small></label>
      </div>
    </section>

    <section class="caja" style="margin-top:16px">
      <h2>Insumos a priorizar (opcional)</h2>
      <p class="ayuda" style="margin:0 0 10px">Sin marcar nada se analizan todos los insumos. Si marcas uno (por ejemplo Lengua de res), el menú
        se arma solo con los platos que lo usan; los demás ingredientes se toman del stock sin que tengas que elegirlos.</p>
      <div class="fila">
        <?php $vistos = []; foreach ($lotes as $l): if (isset($vistos[$l['insumo_id']])) { continue; } $vistos[$l['insumo_id']] = true; ?>
          <label class="chk"><input type="checkbox" name="insumos[]" value="<?= e($l['insumo_id']) ?>" <?= in_array((string)$l['insumo_id'], array_map('strval', (array)($_GET['insumos'] ?? [])), true) ? 'checked' : '' ?>> <?= e($l['insumo']) ?></label>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="caja" style="margin-top:16px">
      <h2>2. Tipo de día</h2>
      <div class="fila">
        <?php foreach ($tipos as $t): $d = new DateTimeImmutable($t['fecha']); ?>
          <label class="dia"><?= e(DIAS_ES[(int)$d->format('N')] . ' ' . $d->format('d/m')) ?>
            <select name="tipo_dia[<?= e($t['fecha']) ?>]"><?= opciones(TXT_TIPO_DIA, $t['tipo_dia']) ?></select></label>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="caja" style="margin-top:16px">
      <h2>3. Estado de los lotes</h2>
      <div class="tabla">
        <table>
          <tr><th>Lote</th><th>Insumo</th><th>Riesgo</th><th>Vence</th><th>Stock (g)</th><th>Estado visual</th><th>Cadena de frío</th></tr>
          <?php foreach ($lotes as $l): $id = $l['lote_id']; ?>
            <tr>
              <td class="sec"><?= e($id) ?></td>
              <td><?= e($l['insumo']) ?></td>
              <td class="sec"><?= e($l['categoria_riesgo']) ?></td>
              <td><input type="date" class="<?= $l['dias_vida'] <= 0 ? 'urgente' : '' ?>" name="lote[<?= e($id) ?>][vence]"
                         value="<?= e(date('Y-m-d', strtotime($hoy . ' ' . ($l['dias_vida'] >= 0 ? '+' : '') . $l['dias_vida'] . ' day'))) ?>" required></td>
              <td><input type="number" name="lote[<?= e($id) ?>][stock]" min="0" step="0.01" value="<?= e($l['stock_actual']) ?>" required></td>
              <td><select name="lote[<?= e($id) ?>][visual]"><?= opciones(TXT_VISUAL, $l['estado_visual']) ?></select></td>
              <td><select name="lote[<?= e($id) ?>][frio]"><?= opciones(TXT_FRIO, $l['cadena_frio']) ?></select></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <p class="ayuda">Puedes corregir la fecha de vencimiento y el stock (por ejemplo, tras un recuento). Si el stock sube por encima de la cantidad inicial del lote, esa cantidad se actualiza.</p>
    </section>

    <section class="caja" style="margin-top:16px">
      <h2>4. ¿Llegó mercadería nueva? (opcional)</h2>
      <div id="nuevos">
        <div class="fila nuevo-fila">
          <label class="dia">Insumo
            <select name="nuevo[0][insumo_id]"><option value="">— ninguno —</option>
              <?php foreach ($insumos as $id => $i): ?><option value="<?= e($id) ?>"><?= e($i['nombre']) ?></option><?php endforeach; ?>
            </select></label>
          <label class="dia">Vence <input type="date" name="nuevo[0][fecha_vencimiento]" min="<?= e($hoy) ?>"></label>
          <label class="dia">Cantidad (g) <input type="number" name="nuevo[0][cantidad]" min="0" step="0.01"></label>
          <label class="dia">Estado <select name="nuevo[0][visual]"><?= opciones(TXT_VISUAL, 'bueno') ?></select></label>
          <label class="dia">Cadena de frío <select name="nuevo[0][frio]"><?= opciones(TXT_FRIO, 'cumple') ?></select></label>
        </div>
      </div>
      <p><button type="button" class="btn btn-sec" id="mas-lotes">+ Agregar otro lote</button></p>
      <script>
        (function () {
          var cont = document.getElementById('nuevos'), n = 1;
          document.getElementById('mas-lotes').addEventListener('click', function () {
            var fila = cont.firstElementChild.cloneNode(true);
            fila.querySelectorAll('[name]').forEach(function (c) {
              c.name = c.name.replace(/nuevo\[\d+\]/, 'nuevo[' + n + ']');
              if (c.tagName === 'INPUT') { c.value = ''; } else { c.selectedIndex = 0; }
            });
            n++; cont.appendChild(fila);
          });
        })();
      </script>
    </section>

   <!-- <section class="caja" style="margin-top:16px">
      <h2>5. Observaciones (opcional)</h2>
      <textarea name="observaciones" rows="2" maxlength="300"></textarea>
    </section>-->

    <div class="acciones"><button class="btn" type="submit">Consultar al sistema</button></div>
  </form> 

<?php else: ?>
  <!-- ======================= PANTALLA 2: RESULTADOS ======================= -->
  <?php
    $emergencia = $otras = $mantener = $revisar = [];
    foreach ($resultado['resultados'] as $r) {
        match (true) {
            $r['accion'] === 'menu_emergencia' => $emergencia[] = $r,
            in_array($r['accion'], ['descartar', 'promocionar', 'comprar'], true) => $otras[] = $r,
            $r['accion'] === 'mantener' => $mantener[] = $r,
            default => $revisar[] = $r,
        };
    }
    $orden = ['descartar' => 0, 'promocionar' => 1, 'comprar' => 2];
    usort($otras, static fn(array $a, array $b): int => $orden[$a['accion']] <=> $orden[$b['accion']]);
    $cuenta = static fn(array $rs, string $a): int => count(array_filter($rs, static fn(array $r): bool => $r['accion'] === $a));
  ?>
  <div class="cabecera">
    <div>
      <h1>Recomendaciones</h1>
      <p class="sec"><?= e(TXT_TIPO[$resultado['tipo']] ?? $resultado['tipo']) ?> · <?= e(date('d/m/Y', strtotime($resultado['fecha']))) ?>
        · consulta #<?= e($resultado['consulta_id']) ?><?= isset($_SESSION['resultado_hora']) ? ' · ' . e($_SESSION['resultado_hora']) : '' ?></p>
    </div>
    <a class="btn btn-sec" href="<?= e($ruta) ?>">← Nueva consulta</a>
  </div>

  <?php if ($error !== null): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <?php foreach ($resultado['avisos'] ?? [] as $av): ?>
    <div class="aviso">
      <p><?= e($av['texto']) ?></p>
      <?php if ($av['sugerir_plan']): ?>
        <a class="btn" href="<?= e($ruta) ?>?tipo=plan_3_dias&amp;insumos[]=<?= e($av['insumo_id']) ?>">Consultar plan de 3 días</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!empty($resultado['insumos_sel'])): ?>
    <p class="sec">Insumos analizados: <strong><?= e(implode(', ', $resultado['insumos_sel'])) ?></strong></p>
  <?php endif; ?>

  <?php if ($resultado['menu_dias'] !== []):
      $totalPlan = array_sum(array_column($resultado['menu_dias'], 'total_porciones'));
      $ingresoPlan = array_sum(array_column($resultado['menu_dias'], 'ingreso'));
      $nPromo = 0; $nCompra = 0;
      foreach ($resultado['menu_dias'] as $md) { $nCompra += count($md['comprar']); foreach ($md['platos'] as $pl) { $nPromo += $pl['promo'] > 0 ? 1 : 0; } } ?>
  <div class="resumen">
    <div><strong><?= e($totalPlan) ?></strong>porciones esperadas en 3 días</div>
    <div><strong><?= e($nPromo) ?></strong>promociones</div>
    <?php if ($nCompra > 0): ?><div><strong><?= e($nCompra) ?></strong>compras para cubrir el menú</div><?php endif; ?>
    <?php if ($resultado['ahorro_total'] > 0): ?>
      <div><strong><?= e(Explicador::bs((float)$resultado['ahorro_total'])) ?></strong>de ahorro estimado</div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="resumen">
    <div><strong><?= count($emergencia) ?></strong>menú de emergencia</div>
    <div><strong><?= $cuenta($otras, 'descartar') ?></strong>por descartar</div>
    <div><strong><?= $cuenta($otras, 'promocionar') ?></strong>por promocionar</div>
    <div><strong><?= $cuenta($otras, 'comprar') ?></strong>por comprar</div>
    <?php if ($resultado['ahorro_total'] > 0): ?>
      <div><strong><?= e(Explicador::bs((float)$resultado['ahorro_total'])) ?></strong>de ahorro estimado</div>
    <?php endif; ?>
  </div>

  <?php endif; ?>

  <?php if ($resultado['menu_dias'] !== []): ?>
    <?php foreach ($resultado['menu_dias'] as $i => $m):
        $f = new DateTimeImmutable($m['fecha']);
        $finde = in_array($m['tipo_dia'], ['fin_de_semana', 'feriado'], true);
        $pd = $resultado['plan_dias'][$i] ?? ['etiqueta' => 'Día ' . $m['dia'], 'vencen' => []]; ?>
      <section class="caja menu-dia">
        <h2>Día <?= e($m['dia']) ?> · <?= e($pd['etiqueta']) ?> · <?= e(DIAS_ES[(int)$f->format('N')] . ' ' . $f->format('d/m')) ?>
          · <?= e(strtolower(TXT_TIPO_DIA[$m['tipo_dia']] ?? $m['tipo_dia'])) ?><?= $finde ? ' (se vende más)' : '' ?></h2>
        <?php if ($pd['vencen'] !== []): ?>
          <p class="vencen sec">Vencen <?= $m['dia'] === 1 ? 'hoy' : e(strtolower($pd['etiqueta'])) ?>:
            <?php foreach ($pd['vencen'] as $k => $v): ?><?= $k > 0 ? ' · ' : '' ?><strong><?= e($v['insumo']) ?></strong> (<?= e(Explicador::peso($v['gramos'])) ?>)<?php endforeach; ?></p>
        <?php endif; ?>
        <table class="menu">
          <tr><th>Plato</th><th class="num">Se espera vender</th><th>Promoción</th></tr>
          <?php foreach ($m['platos'] as $pl): ?>
            <tr>
              <td><strong><?= e($pl['nombre']) ?></strong>
                <?php if ($pl['emergencia'] > 0): ?><br><span class="sec nota">Conviene usar hoy el lote que vence: hasta <?= e($pl['emergencia']) ?> porciones</span><?php endif; ?></td>
              <td class="num prep"><?= e($pl['esperadas']) ?></td>
              <td><?php if ($pl['promo'] > 0): ?><span class="etq">−<?= e(round($pl['descuento'] * 100)) ?> %</span> <?= e($pl['promo']) ?> porciones<?php else: ?><span class="sec">—</span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
        <?php if ($m['comprar'] !== []): ?>
          <p class="compra-dia"><strong>Comprar para este menú:</strong>
            <?php foreach ($m['comprar'] as $k => $c): ?><?= $k > 0 ? ' · ' : ' ' ?><?= e($c['insumo']) ?> ≈ <?= e(Explicador::peso($c['gramos'])) ?><?php endforeach; ?></p>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>


    <details class="opcional" open>
      <summary><strong>Avisos de inventario</strong> · descartes, compras y lotes por vencer</summary>
    <?php foreach ($resultado['plan_dias'] as $d):
        $f = new DateTimeImmutable($d['fecha']);
        $finde = in_array($d['tipo_dia'], ['fin_de_semana', 'feriado'], true); ?>
      <h3 class="subdia">Día <?= e($d['dia']) ?> · <?= e($d['etiqueta']) ?> · <?= e(DIAS_ES[(int)$f->format('N')] . ' ' . $f->format('d/m')) ?>
        · <?= e(strtolower(TXT_TIPO_DIA[$d['tipo_dia']] ?? $d['tipo_dia'])) ?><?= $finde ? ' (se vende más)' : '' ?></h3>

      <?php if ($d['vencen'] !== []): ?>
        <p class="vencen sec">Vencen <?= $d['dia'] === 1 ? 'hoy o ya vencidos' : e(strtolower($d['etiqueta'])) ?>:
          <?php foreach ($d['vencen'] as $k => $v): ?>
            <?= $k > 0 ? ' · ' : '' ?><strong><?= e($v['insumo']) ?></strong> (<?= e(Explicador::peso($v['gramos'])) ?><?= $v['vencido'] ? ', vencido' : '' ?>) → <?= e($v['estado']) ?>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>

      <?php foreach ($d['bloques'] as $b) { $b['lote'] === null ? tarjeta_compras($b) : tarjeta($b, $b['accion'] === 'menu_emergencia'); } ?>
      <?php if ($d['bloques'] === []): ?>
        <p class="vacio">Sin acciones nuevas este día: seguir con las ventas normales.</p>
      <?php endif; ?>
    <?php endforeach; ?>
    </details>

    <?php if (!empty($resultado['pronosticos']['series'])): $ps = $resultado['pronosticos']; ?>
    <details class="opcional" open>
      <summary><strong>Tendencia y pronóstico de la demanda</strong> · elija un insumo o un plato para ver cómo cambió su consumo y qué se espera</summary>
      <div class="selector-pron">
        <label>Ver pronóstico de
          <select id="pron-sel">
            <?php $grupo = null; foreach ($ps['series'] as $k => $sr):
                if ($sr['grupo'] !== $grupo): if ($grupo !== null) { echo '</optgroup>'; } $grupo = $sr['grupo']; echo '<optgroup label="' . e($grupo) . '">'; endif; ?>
              <option value="<?= e($k) ?>"<?= $k === $ps['defecto'] ? ' selected' : '' ?>><?= e($sr['nombre']) ?></option>
            <?php endforeach; if ($grupo !== null) { echo '</optgroup>'; } ?>
          </select></label>
        <span class="horizonte" role="group" aria-label="Horizonte del pronóstico">
          <span class="sec">Pronóstico a:</span>
          <button type="button" class="chip-h on" data-h="7">7 días</button>
          <button type="button" class="chip-h" data-h="30">30 días</button>
        </span>
      </div>
      <div id="pron-vista" aria-live="polite"></div>
      <div class="leyenda">
        <div><i class="l-real"></i><h4>Lo que se vendió</h4>
          <p>Cada punto dorado es un día real. Si falta un punto, ese día no tiene ventas registradas (feriado o sin datos).</p></div>
        <div><i class="l-mod"></i><h4>Lo normal para cada día</h4>
          <p>Línea gris: lo que el sistema espera según el día de la semana. Sube los viernes y sábados y baja entre semana. Un punto lejos de esta línea fue un día fuera de lo normal.</p></div>
        <div><i class="l-tend"></i><h4>Tendencia</h4>
          <p>Línea verde: el promedio de la semana sin los altibajos de cada día. Si sube, el consumo está creciendo; si baja, está cayendo.</p></div>
        <div><i class="l-pron"></i><h4>Pronóstico</h4>
          <p>Línea punteada, de hoy en adelante: lo que se espera vender. La banda sombreada es el margen en que probablemente caiga la venta real.</p></div>
      </div>
      <p class="uso"><strong>Cómo usarlo:</strong> para decidir cuánto preparar o comprar, guíese por la línea punteada. El borde de arriba de la banda sirve de margen de seguridad si no quiere quedarse corto.</p>
      <script type="application/json" id="pron-datos"><?= json_encode($ps['series'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PARTIAL_OUTPUT_ON_ERROR) ?></script>
    </details>
    <?php endif; ?>

    <?php if (!empty($resultado['carta'])): ?>
    <details class="opcional">
      <summary><strong>Perspectiva a un mes: ¿qué platos conviene mantener?</strong> · cómo viene la venta de cada plato y qué se espera las próximas 4 semanas</summary>
      <p class="sec">Se compara el promedio de porciones por día de las últimas semanas con lo que se espera el próximo mes. Los platos van de mayor a menor venta esperada.
        Es una guía basada en los últimos 6 meses de ventas: no incluye fiestas, temporadas, cambios de precio ni de carta, y el margen que muestra el gráfico es el del cálculo estadístico (a un mes la incertidumbre real es mayor).</p>
      <?php foreach ($resultado['carta'] as $f): [$etq, $clase, $reco] = veredicto_carta($f); ?>
        <details class="plato-carta">
          <summary>
            <span class="pc-puesto"><?= e($f['puesto']) ?></span>
            <strong class="pc-nombre"><?= e($f['plato']) ?></strong>
            <span class="vered <?= e($clase) ?>"><?= e($etq) ?></span>
            <span class="pc-num"><?= e(number_format($f['reciente'], 1, ',', ' ')) ?> → <strong><?= e(number_format($f['proximo'], 1, ',', ' ')) ?></strong> porc./día</span>
          </summary>
          <?= grafico_semanal($f['semanas'], 'Perspectiva semanal de ' . $f['plato']) ?>
          <div class="leyenda">
            <div><i class="l-real"></i><h4>Semanas ya vendidas</h4><p>Cada punto es el promedio de porciones por día de una semana real. Se muestran las últimas 8 semanas.</p></div>
            <div><i class="l-pron"></i><h4>Próximas 4 semanas</h4><p>Línea punteada y banda: lo que se espera vender por día y su margen estadístico.</p></div>
          </div>
          <p class="lectura">
            <strong><?= e($f['plato']) ?></strong> vende hoy unas <?= e(number_format($f['reciente'], 1, ',', ' ')) ?> porciones por día
            (promedio de las últimas 4 semanas) y se esperan <strong><?= e(number_format($f['proximo'], 1, ',', ' ')) ?></strong> el próximo mes<?php if ($f['cambio'] !== null): ?>,
            <?= abs($f['cambio']) < 1 ? 'casi igual' : e(number_format(abs($f['cambio']), 0)) . ' % ' . ($f['cambio'] > 0 ? 'más' : 'menos') ?><?php endif; ?>.
            Está en el puesto <?= e($f['puesto']) ?> de <?= e($f['de']) ?> en ventas esperadas<?= $f['nivel'] === 'alto' ? ' (de los más vendidos)' : ($f['nivel'] === 'bajo' ? ' (de los menos vendidos)' : '') ?>.
          </p>
          <p class="reco"><strong>Recomendación:</strong> <?= e($reco) ?></p>
        </details>
      <?php endforeach; ?>
    </details>
    <?php endif; ?>

    <details class="opcional">
      <summary><strong>Cómo se calcularon las ventas esperadas</strong> · mínimos cuadrados sobre <?= e($resultado['modelo_demanda']['dias']) ?> días</summary>
      <p class="sec">Para cada plato se ajusta una recta de tendencia más el efecto de cada día de la semana. «Se espera vender» es esa estimación; el chef decide cuánto preparar. Las promociones son porciones adicionales que el sistema propone para no perder lotes por vencer.</p>
      <table class="menu">
        <tr><th>Plato</th><th class="num">Tendencia</th><th class="num">Ajuste</th></tr>
        <?php foreach ($resultado['modelo_demanda']['detalle'] as $dt): ?>
          <tr><td><?= e($dt['plato']) ?></td>
            <?php if (isset($dt['pendiente'])): ?>
              <td class="num"><?= $dt['pendiente'] >= 0 ? '+' : '' ?><?= e(number_format($dt['pendiente'], 2)) ?> porciones por día</td>
              <td class="num"><?= e(round($dt['r2'] * 100)) ?> % de la variación explicada</td>
            <?php else: ?><td class="num sec" colspan="2">pocos datos: promedio por día de la semana</td><?php endif; ?></tr>
        <?php endforeach; ?>
      </table>
    </details>
  <?php else: ?>
  <?php if ($emergencia !== []): ?>
    <h2>Menú de emergencia · ingredientes que vencen hoy</h2>
    <?php foreach ($emergencia as $r) { tarjeta($r, true); } ?>
  <?php endif; ?>

  <?php if ($otras !== []): ?>
    <h2>Otras acciones</h2>
    <?php foreach ($otras as $r) { tarjeta($r); } ?>
  <?php endif; ?>

  <?php if ($emergencia === [] && $otras === [] && empty($resultado['avisos'])): ?>
    <h2>Resultado</h2>
    <p class="vacio">No hay nada urgente: ningún lote necesita menú de emergencia, promoción, compra o descarte.</p>
  <?php endif; ?>

  <?php endif; ?>

  <?php if ($mantener !== [] || $revisar !== []): ?>
    <h2>Otros lotes</h2>
    <?php if ($revisar !== []): ?>
      <details class="opcional">
        <summary><strong><?= count($revisar) ?> lote(s) para revisar a mano</strong> · el sistema no tiene una regla para decidir</summary>
        <?php lista_compacta($revisar); ?>
      </details>
    <?php endif; ?>
    <?php if ($mantener !== []): ?>
      <details class="opcional">
        <summary><strong><?= count($mantener) ?> lote(s) sin acción necesaria</strong> · se consumen antes de vencer</summary>
        <?php lista_compacta($mantener); ?>
      </details>
    <?php endif; ?>
  <?php endif; ?>

  <div class="acciones" style="margin-top:32px"><a class="btn" href="<?= e($ruta) ?>">← Hacer otra consulta</a></div>
<?php endif; ?>

<script>
  // Pronóstico de demanda: se dibuja en el navegador a partir de los datos incrustados, un gráfico a la vez
  (function () {
    var el = document.getElementById('pron-datos');
    if (!el) { return; }
    var D = JSON.parse(el.textContent), H = 7;
    var sel = document.getElementById('pron-sel'), cont = document.getElementById('pron-vista');
    var DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    function esc(t) { return String(t).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
    function miles(v) { return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
    function dec(v, n) { return v.toFixed(n).replace('.', ','); }
    function fecha(f) { var p = f.split('-'); return p[2] + '/' + p[1]; }
    function dia(f) { var p = f.split('-'); return DIAS[new Date(+p[0], +p[1] - 1, +p[2]).getDay()]; }
    function fmt(v, g) { return g ? (v >= 1000 ? dec(v / 1000, 2) + ' kg' : miles(v) + ' g') : dec(v, 1) + ' porciones'; }
    function r1(v) { return Math.round(v * 10) / 10; }

    function svg(it, dias) {
      var n = dias.length, W = 720, Hh = 290, ml = 56, mr = 16, mt = 16, mb = 34, g = it.unidad === 'g';
      var X = function (i) { return ml + i * (W - ml - mr) / (n - 1); };
      var max = 0;
      dias.forEach(function (d) { max = Math.max(max, d.r || 0, d.a + (d.u ? d.sd : 0), d.t); });
      var pasos = [0.5, 1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 2500, 5000, 10000], paso = pasos[pasos.length - 1];
      for (var k = 0; k < pasos.length; k++) { if (max / pasos[k] <= 5) { paso = pasos[k]; break; } }
      var tope = Math.max(paso, Math.ceil(max * 1.05 / paso) * paso);
      var Y = function (v) { return mt + (Hh - mt - mb) * (1 - v / tope); };
      var hoy = 0; for (var i = 0; i < n; i++) { if (dias[i].u) { hoy = i; break; } }
      var cada = n > 45 ? 14 : 7, o = '<svg viewBox="0 0 ' + W + ' ' + Hh + '" role="img" aria-label="' + esc('Demanda de ' + it.nombre) + '" class="grafico">';
      for (var v = 0; v <= tope + 1e-9; v += paso) {
        o += '<line x1="' + ml + '" x2="' + (W - mr) + '" y1="' + r1(Y(v)) + '" y2="' + r1(Y(v)) + '" class="g-rej"/>' +
             '<text x="' + (ml - 8) + '" y="' + r1(Y(v) + 4) + '" class="g-eje" text-anchor="end">' + miles(v) + '</text>';
      }
      o += '<text x="' + ml + '" y="11" class="g-eje">' + (g ? 'g' : 'porciones') + ' por día</text>';
      dias.forEach(function (d, i) {
        if ((i - hoy) % cada === 0) { o += '<text x="' + r1(X(i)) + '" y="' + (Hh - 10) + '" class="g-eje" text-anchor="middle">' + fecha(d.f) + '</text>'; }
      });
      o += '<rect x="' + r1(X(hoy)) + '" y="' + mt + '" width="' + r1(W - mr - X(hoy)) + '" height="' + (Hh - mt - mb) + '" class="g-fut"/>';
      var alto = [], bajo = [];
      dias.forEach(function (d, i) { if (d.u) { alto.push(r1(X(i)) + ',' + r1(Y(d.a + d.sd))); bajo.push(r1(X(i)) + ',' + r1(Y(Math.max(0, d.a - d.sd)))); } });
      o += '<polygon points="' + alto.concat(bajo.reverse()).join(' ') + '" class="g-banda"/>';
      o += '<line x1="' + r1(X(hoy)) + '" x2="' + r1(X(hoy)) + '" y1="' + mt + '" y2="' + (Hh - mb) + '" class="g-hoy"/>';
      o += '<text x="' + r1(X(hoy) - 7) + '" y="' + (mt + 12) + '" class="g-eje g-et" text-anchor="end">← lo que ya se vendió</text>';
      o += '<text x="' + r1(X(hoy) + 7) + '" y="' + (mt + 12) + '" class="g-eje g-et">hoy: pronóstico →</text>';
      var linea = function (desde, hasta, f) { var p = []; for (var i = desde; i < hasta; i++) { p.push(r1(X(i)) + ',' + r1(Y(f(dias[i])))); } return p.join(' '); };
      o += '<polyline points="' + linea(0, n, function (d) { return d.t; }) + '" class="g-tend"/>';
      o += '<text x="' + (ml + 6) + '" y="' + r1(Y(dias[0].t) - 9) + '" class="g-eje g-et g-et-tend">tendencia</text>';
      o += '<polyline points="' + linea(0, hoy, function (d) { return d.a; }) + '" class="g-mod"/>';
      o += '<polyline points="' + linea(Math.max(0, hoy - 1), n, function (d) { return d.a; }) + '" class="g-pron"/>';
      var tramo = [];
      dias.forEach(function (d, i) {
        if (d.r !== null && !d.u) { tramo.push(r1(X(i)) + ',' + r1(Y(d.r))); }
        else { if (tramo.length > 1) { o += '<polyline points="' + tramo.join(' ') + '" class="g-real"/>'; } tramo = []; }
      });
      if (tramo.length > 1) { o += '<polyline points="' + tramo.join(' ') + '" class="g-real"/>'; }
      dias.forEach(function (d, i) {
        if (d.r !== null && !d.u) {
          o += '<circle cx="' + r1(X(i)) + '" cy="' + r1(Y(d.r)) + '" r="3.4" class="g-punto"><title>' + fecha(d.f) + ': ' + (g ? miles(d.r) + ' g' : dec(d.r, 0) + ' porciones') + '</title></circle>';
        }
      });
      return o + '</svg>';
    }

    function dibujar() {
      var it = D[+sel.value], g = it.unidad === 'g', dias = it.dias;
      var pasados = dias.filter(function (d) { return !d.u; }).length;
      var disp = dias.length - pasados;
      var btn30 = document.querySelector('.chip-h[data-h="30"]');
      if (btn30) { btn30.hidden = disp < 30; }
      var h = Math.min(H, disp);
      var vista = dias.slice(0, pasados + h), fut = dias.slice(pasados, pasados + h);
      var reales = dias.filter(function (d) { return !d.u && d.r !== null; });
      var prom = function (a, k) { return a.length ? a.reduce(function (s, d) { return s + d[k]; }, 0) / a.length : 0; };
      var promRec = prom(reales, 'r'), promFut = prom(fut, 'a'), cambio = promRec > 0 ? (promFut / promRec - 1) * 100 : null;
      var semana = it.pendiente * 7, sdFin = fut.length ? fut[fut.length - 1].sd : it.s;
      var ult = reales.length ? fecha(reales[reales.length - 1].f) : null;
      var txt = 'La tendencia es <strong>' + (semana >= 0 ? 'de crecimiento' : 'a la baja') + '</strong>: ' + (semana >= 0 ? '+' : '−') + fmt(Math.abs(semana), g) + ' por semana. ';
      txt += h >= 30 ? 'En los próximos 30 días se esperan <strong>' + fmt(promFut * 30, g) + ' en total</strong> (' + fmt(promFut, g) + ' por día)'
                     : 'En los próximos ' + h + ' días se esperan en promedio <strong>' + fmt(promFut, g) + ' por día</strong>';
      if (cambio !== null) { txt += ', ' + (Math.abs(cambio) < 1 ? 'casi igual' : Math.round(Math.abs(cambio)) + '\u00a0% ' + (cambio > 0 ? 'más' : 'menos')) + ' que el promedio de los últimos 28 días con ventas (' + fmt(promRec, g) + ' por día)'; }
      txt += '. Cada día puede desviarse unos ±' + fmt(sdFin, g) + '. ';
      if (h >= 30) { txt += '<span class="sec">Cuanto más lejos, menos seguro: el cálculo solo conoce los últimos 6 meses y no incluye fiestas, temporadas ni cambios de precio.</span> '; }
      if (ult) { txt += '<span class="sec">Última venta registrada: ' + ult + '.</span>'; }
      var filas = fut.map(function (d) {
        return '<tr><td>' + dia(d.f) + ' ' + fecha(d.f) + '</td><td class="num"><strong>' + fmt(d.a, g) + '</strong></td><td class="num sec">' +
               fmt(Math.max(0, d.a - d.sd), g) + ' a ' + fmt(d.a + d.sd, g) + '</td></tr>';
      }).join('');
      var sub = it.tipo === 'insumo' ? 'gramos por día' + (it.platos.length ? ' · se calcula con: ' + esc(it.platos.join(', ')) : '') : 'porciones por día';
      cont.innerHTML = '<h3 class="sub-g">' + esc(it.nombre) + ' <span class="sec" style="font-size:.9rem">· ' + sub + '</span></h3>' +
        svg(it, vista) + '<p class="lectura">' + txt + '</p>' +
        '<details class="tec"><summary>Ver el pronóstico día por día</summary><table class="menu"><tr><th>Día</th><th class="num">Esperado</th><th class="num">Rango probable</th></tr>' + filas + '</table></details>';
    }

    sel.addEventListener('change', dibujar);
    document.querySelectorAll('.chip-h').forEach(function (b) {
      b.addEventListener('click', function () {
        H = +b.getAttribute('data-h');
        document.querySelectorAll('.chip-h').forEach(function (x) { x.classList.toggle('on', x === b); });
        dibujar();
      });
    });
    dibujar();
  })();
</script>
</main>
<?php if (is_file(__DIR__ . '/chat_widget.php')) { require __DIR__ . '/chat_widget.php'; } ?>
</body>
</html>