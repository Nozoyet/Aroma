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

</main>
</body>
</html>