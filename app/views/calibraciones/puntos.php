<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = (int)Auth::tenantId();
$user = Auth::user();
$userId = isset($user['id']) ? (int)$user['id'] : null;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }

/* =========================================================
   Helpers PRO
========================================================= */
function e2($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function has_table($table){
  try{
    $q = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
    $q->execute([':t'=>$table]);
    return (bool)$q->fetchColumn();
  } catch(Exception $e){
    return false;
  }
}

function has_column($table, $col){
  try{
    $q = db()->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1");
    $q->execute([':t'=>$table, ':c'=>$col]);
    return (bool)$q->fetchColumn();
  } catch(Exception $e){
    return false;
  }
}

function fmt_num($v){
  if ($v === null || $v === '') return '';
  // no forzamos decimales; el usuario puede escribir como 12.3
  return (string)$v;
}

function as_float($v){
  if ($v === null) return null;
  $s = trim((string)$v);
  if ($s === '') return null;
  // soporta coma o punto
  $s = str_replace(',', '.', $s);
  if (!is_numeric($s)) return null;
  return (float)$s;
}

/* =========================================================
   Cargar calibración + activo
========================================================= */
$st = db()->prepare("
  SELECT
    c.*,
    a.codigo_interno,
    a.nombre AS activo_nombre,
    a.modelo AS activo_modelo,
    a.serial AS activo_serial
  FROM calibraciones c
  LEFT JOIN activos a ON a.id=c.activo_id AND a.tenant_id=c.tenant_id
  WHERE c.id=:id AND c.tenant_id=:t AND COALESCE(c.eliminado,0)=0
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$cal = $st->fetch();
if (!$cal) { http_response_code(404); echo "Calibración no encontrada"; exit; }

/* =========================================================
   Acciones: generar plantilla / guardar puntos
========================================================= */
$msg = '';
$err = '';

/**
 * Estrategias de plantilla (en orden):
 *  A) Tabla "plantillas_puntos" (si existe)
 *  B) Tabla "patrones_puntos" usando el 1er patrón asociado (si existe calibraciones_patrones)
 *
 * NOTA: esto es robusto para tu BD real, sin adivinar a ciegas: si no existe, lo reporta.
 */
function template_source_info(){
  $info = [
    'ok' => false,
    'mode' => '',
    'reason' => ''
  ];
  if (has_table('plantillas_puntos')) {
    $info['ok'] = true;
    $info['mode'] = 'plantillas_puntos';
    return $info;
  }
  if (has_table('calibraciones_patrones') && has_table('patrones_puntos')) {
    $info['ok'] = true;
    $info['mode'] = 'patrones_puntos';
    return $info;
  }
  $info['reason'] = "No se encontró una fuente de plantilla. Se recomienda crear la tabla 'plantillas_puntos' o asociar un patrón con puntos.";
  return $info;
}

/**
 * Obtiene puntos plantilla según modo detectado
 * Retorna array de filas: magnitud, unidad, punto_nominal, tolerancia, incertidumbre_expandida, k, notas, orden
 */
function get_template_points($tenantId, $calibracionId){
  $src = template_source_info();
  if (!$src['ok']) return ['rows'=>[], 'mode'=>'', 'note'=>$src['reason']];

  // A) plantillas_puntos
  if ($src['mode'] === 'plantillas_puntos') {
    // columnas típicas esperadas:
    // tenant_id, formato, magnitud, unidad, orden, punto_nominal, tolerancia, incertidumbre_expandida, k, notas
    // si no existe "formato", se trae todo para tenant (y el usuario ajusta)
    $whereFmt = '';
    $bind = [':t'=>$tenantId];

    $fmt = '';
    // leemos el formato si existe en calibraciones (cert_formato)
    try{ $fmt = strtolower(trim((string)($GLOBALS['cal']['cert_formato'] ?? ''))); } catch(Exception $e){ $fmt=''; }

    if (has_column('plantillas_puntos','formato') && $fmt !== '') {
      $whereFmt = " AND formato = :f ";
      $bind[':f'] = $fmt;
    }

    $sql = "
      SELECT
        COALESCE(orden, 0) AS orden,
        COALESCE(magnitud,'') AS magnitud,
        COALESCE(unidad,'') AS unidad,
        punto_nominal,
        tolerancia,
        incertidumbre_expandida,
        k,
        COALESCE(notas,'') AS notas
      FROM plantillas_puntos
      WHERE tenant_id = :t
      {$whereFmt}
      ORDER BY COALESCE(magnitud,''), COALESCE(orden,0), id
    ";
    try{
      $q = db()->prepare($sql);
      $q->execute($bind);
      $rows = $q->fetchAll();
      return ['rows'=>$rows ?: [], 'mode'=>'plantillas_puntos', 'note'=>($whereFmt ? "Plantilla filtrada por formato: {$fmt}" : "Plantilla general (sin filtro de formato)")];
    } catch(Exception $e){
      return ['rows'=>[], 'mode'=>'plantillas_puntos', 'note'=>"Error consultando plantillas_puntos: ".$e->getMessage()];
    }
  }

  // B) patrones_puntos (desde patrón asociado)
  if ($src['mode'] === 'patrones_puntos') {

    // 1) traer el primer patrón asociado (calibraciones_patrones)
    try{
      $cp = db()->prepare("
        SELECT patron_id
        FROM calibraciones_patrones
        WHERE tenant_id = :t AND calibracion_id = :c
        ORDER BY id ASC
        LIMIT 1
      ");
      $cp->execute([':t'=>$tenantId, ':c'=>$calibracionId]);
      $patronId = (int)($cp->fetchColumn() ?? 0);
      if ($patronId <= 0) {
        return ['rows'=>[], 'mode'=>'patrones_puntos', 'note'=>"No hay patrones asociados. Asocia al menos 1 patrón para copiar sus puntos."];
      }

      // 2) copiar puntos de patrones_puntos
      // columnas típicas: patron_id, tenant_id, orden, magnitud, unidad, punto_nominal, tolerancia, incertidumbre_expandida, k, notas
      $pp = db()->prepare("
        SELECT
          COALESCE(orden,0) AS orden,
          COALESCE(magnitud,'') AS magnitud,
          COALESCE(unidad,'') AS unidad,
          punto_nominal,
          tolerancia,
          incertidumbre_expandida,
          k,
          COALESCE(notas,'') AS notas
        FROM patrones_puntos
        WHERE tenant_id = :t AND patron_id = :p
        ORDER BY COALESCE(magnitud,''), COALESCE(orden,0), id
      ");
      $pp->execute([':t'=>$tenantId, ':p'=>$patronId]);
      $rows = $pp->fetchAll();

      return ['rows'=>$rows ?: [], 'mode'=>'patrones_puntos', 'note'=>"Plantilla tomada desde puntos del patrón asociado #{$patronId}"];
    } catch(Exception $e){
      return ['rows'=>[], 'mode'=>'patrones_puntos', 'note'=>"Error consultando patrones_puntos: ".$e->getMessage()];
    }
  }

  return ['rows'=>[], 'mode'=>'', 'note'=>"Sin fuente de plantilla disponible."];
}

/* =========================================================
   POST: Generar puntos desde plantilla
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_template') {

  $wipe = (int)($_POST['wipe'] ?? 0); // 1 = borrar puntos existentes
  $tpl = get_template_points($tenantId, $id);

  if (!$tpl['rows']) {
    $err = "No se pudieron generar puntos. " . ($tpl['note'] ? $tpl['note'] : '');
  } else {
    try{
      db()->beginTransaction();

      if ($wipe === 1) {
        $del = db()->prepare("DELETE FROM calibraciones_puntos WHERE tenant_id=:t AND calibracion_id=:c");
        $del->execute([':t'=>$tenantId, ':c'=>$id]);
      }

      $ins = db()->prepare("
        INSERT INTO calibraciones_puntos
          (tenant_id, calibracion_id, orden, magnitud, unidad, punto_nominal, lectura_equipo, lectura_patron,
           error_abs, error_rel, tolerancia, conforme, incertidumbre_expandida, k, notas)
        VALUES
          (:t, :c, :orden, :magnitud, :unidad, :pnom, NULL, NULL,
           NULL, NULL, :tol, NULL, :u, :k, :notas)
      ");

      $n = 0;
      foreach($tpl['rows'] as $r){
        $ins->execute([
          ':t'=>$tenantId,
          ':c'=>$id,
          ':orden'=>(int)($r['orden'] ?? 0),
          ':magnitud'=>trim((string)($r['magnitud'] ?? '')),
          ':unidad'=>trim((string)($r['unidad'] ?? '')),
          ':pnom'=>($r['punto_nominal'] !== '' ? $r['punto_nominal'] : null),
          ':tol'=>($r['tolerancia'] !== '' ? $r['tolerancia'] : null),
          ':u'=>($r['incertidumbre_expandida'] !== '' ? $r['incertidumbre_expandida'] : null),
          ':k'=>($r['k'] !== '' ? $r['k'] : null),
          ':notas'=>($r['notas'] !== '' ? $r['notas'] : null),
        ]);
        $n++;
      }

      db()->commit();
      $msg = "Plantilla aplicada: se generaron {$n} puntos. " . ($tpl['note'] ? $tpl['note'] : '');
    } catch(Exception $e){
      db()->rollBack();
      $err = "Error al generar puntos: " . $e->getMessage();
    }
  }
}

/* =========================================================
   POST: Guardar lecturas / calcular errores
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_points') {

  $ids = isset($_POST['pid']) && is_array($_POST['pid']) ? $_POST['pid'] : [];
  if (!$ids) {
    $err = "No hay puntos para guardar.";
  } else {
    try{
      db()->beginTransaction();

      $up = db()->prepare("
        UPDATE calibraciones_puntos SET
          orden = :orden,
          magnitud = :magnitud,
          unidad = :unidad,
          punto_nominal = :pnom,
          lectura_equipo = :leq,
          lectura_patron = :lpa,
          error_abs = :eabs,
          error_rel = :erel,
          tolerancia = :tol,
          incertidumbre_expandida = :u,
          k = :k,
          conforme = :conf,
          notas = :notas
        WHERE id = :id AND tenant_id = :t AND calibracion_id = :c
        LIMIT 1
      ");

      $count = 0;

      foreach($ids as $i => $pidRaw){
        $pid = (int)$pidRaw;
        if ($pid <= 0) continue;

        $orden = (int)($_POST['orden'][$i] ?? 0);
        $magnitud = trim((string)($_POST['magnitud'][$i] ?? ''));
        $unidad = trim((string)($_POST['unidad'][$i] ?? ''));

        $pnom = trim((string)($_POST['pnom'][$i] ?? ''));
        $leq  = trim((string)($_POST['leq'][$i] ?? ''));
        $lpa  = trim((string)($_POST['lpa'][$i] ?? ''));

        $tol  = trim((string)($_POST['tol'][$i] ?? ''));
        $u    = trim((string)($_POST['u'][$i] ?? ''));
        $k    = trim((string)($_POST['k'][$i] ?? ''));
        $notas = trim((string)($_POST['notas'][$i] ?? ''));

        // cálculo de error abs/rel si hay lecturas válidas
        $f_pnom = as_float($pnom);
        $f_leq  = as_float($leq);
        $f_lpa  = as_float($lpa);

        $eabs = null;
        $erel = null;

        // error abs = lectura_equipo - lectura_patron (si ambos existen)
        if ($f_leq !== null && $f_lpa !== null) {
          $eabs = $f_leq - $f_lpa;
          // error rel (%) si nominal != 0
          if ($f_pnom !== null && abs($f_pnom) > 0.0000001) {
            $erel = ($eabs / $f_pnom) * 100.0;
          }
        }

        // conforme: si hay tolerancia numérica y hay error abs, compara |error| <= tol
        $conf = null;
        $f_tol = as_float($tol);
        if ($f_tol !== null && $eabs !== null) {
          $conf = (abs($eabs) <= abs($f_tol)) ? 1 : 0;
        }

        $up->execute([
          ':orden'=>$orden,
          ':magnitud'=>($magnitud !== '' ? $magnitud : null),
          ':unidad'=>($unidad !== '' ? $unidad : null),

          ':pnom'=>($pnom !== '' ? $pnom : null),
          ':leq'=>($leq !== '' ? $leq : null),
          ':lpa'=>($lpa !== '' ? $lpa : null),

          ':eabs'=>($eabs !== null ? $eabs : null),
          ':erel'=>($erel !== null ? $erel : null),

          ':tol'=>($tol !== '' ? $tol : null),
          ':u'=>($u !== '' ? $u : null),
          ':k'=>($k !== '' ? $k : null),

          ':conf'=>$conf,
          ':notas'=>($notas !== '' ? $notas : null),

          ':id'=>$pid,
          ':t'=>$tenantId,
          ':c'=>$id
        ]);

        $count++;
      }

      db()->commit();
      $msg = "Cambios guardados. Puntos actualizados: {$count}. (Errores y conformidad calculados automáticamente cuando aplica)";
    } catch(Exception $e){
      db()->rollBack();
      $err = "Error guardando puntos: " . $e->getMessage();
    }
  }
}

/* =========================================================
   Cargar puntos existentes
========================================================= */
$qs = db()->prepare("
  SELECT
    id, orden, magnitud, unidad,
    punto_nominal, lectura_equipo, lectura_patron,
    error_abs, error_rel, tolerancia, conforme,
    incertidumbre_expandida, k, notas
  FROM calibraciones_puntos
  WHERE tenant_id = :t AND calibracion_id = :c
  ORDER BY COALESCE(magnitud,''), COALESCE(orden,0), id
");
$qs->execute([':t'=>$tenantId, ':c'=>$id]);
$puntos = $qs->fetchAll();

/* =========================================================
   UI PRO
========================================================= */
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';

$codigo = trim((string)($cal['codigo_interno'] ?? ''));
if ($codigo === '') $codigo = '#'.(int)$cal['activo_id'];

$nombre = trim((string)($cal['activo_nombre'] ?? ''));
$modelo = trim((string)($cal['activo_modelo'] ?? ''));
$serial = trim((string)($cal['activo_serial'] ?? ''));

$sub = [];
if ($nombre !== '') $sub[] = $nombre;
if ($modelo !== '') $sub[] = 'Modelo: '.$modelo;
if ($serial !== '') $sub[] = 'Serial: '.$serial;
$subTxt = $sub ? implode(' · ', $sub) : '—';

$detalleUrl = e2(base_url())."/index.php?route=calibracion_detalle&id=".$id;
?>

<style>
  .badge{border-radius:999px; padding:6px 10px; font-weight:700;}
  .small-muted{font-size:12px; color:#6c757d;}
  .card-soft{border:1px solid #e5e7eb; border-radius:12px;}
  .pill{display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:#f4f6f9; border:1px solid #e5e7eb; font-size:12px;}
  .pill.ok{ background: rgba(22,163,74,.10); border-color: rgba(22,163,74,.35); color:#0b6b2a; }
  .pill.bad{ background: rgba(220,38,38,.10); border-color: rgba(220,38,38,.35); color:#8a1212; }
  .pill.neu{ background: #f8fafc; border-color:#e2e8f0; color:#334155; }
  .tbtn{display:inline-flex; align-items:center; gap:8px;}
  .table td, .table th{ vertical-align:middle; }
  .table input, .table textarea{ font-size:12px; }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>

<div class="content">
  <div class="container-fluid">

    <div class="card card-outline card-primary">
      <div class="card-header d-flex align-items-start justify-content-between flex-wrap" style="gap:10px;">
        <div>
          <h3 class="card-title mb-0">
            <i class="fas fa-bullseye"></i> Puntos de medición · Calibración #<?= (int)$id ?>
          </h3>
          <div class="small-muted"><?= e2($codigo) ?> · <?= e2($subTxt) ?></div>
        </div>

        <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
          <a class="btn btn-outline-secondary btn-sm" href="<?= $detalleUrl ?>">
            <i class="fas fa-arrow-left"></i> Volver al detalle
          </a>

          <form method="post" class="d-inline" onsubmit="return confirm('¿Aplicar plantilla de puntos?');">
            <input type="hidden" name="action" value="generate_template">
            <input type="hidden" name="wipe" value="0">
            <button class="btn btn-outline-primary btn-sm" type="submit">
              <i class="fas fa-magic"></i> Aplicar plantilla
            </button>
          </form>

          <form method="post" class="d-inline" onsubmit="return confirm('Esto borrará los puntos actuales. ¿Continuar?');">
            <input type="hidden" name="action" value="generate_template">
            <input type="hidden" name="wipe" value="1">
            <button class="btn btn-outline-danger btn-sm" type="submit">
              <i class="fas fa-broom"></i> Reemplazar con plantilla
            </button>
          </form>
        </div>
      </div>

      <div class="card-body">

        <?php if ($err): ?>
          <div class="alert alert-danger"><b>Error:</b> <?= e2($err) ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
          <div class="alert alert-success"><?= e2($msg) ?></div>
        <?php endif; ?>

        <?php
          $src = template_source_info();
          if (!$src['ok']) {
            echo "<div class='alert alert-warning'>";
            echo "<b>Plantillas no configuradas.</b> ";
            echo e2($src['reason']);
            echo "<br><span class='small-muted'>Sugerencia PRO: crear una tabla 'plantillas_puntos' para generar puntos por formato (general/balanza/termometro/manometro/electrico) o asociar un patrón con puntos.</span>";
            echo "</div>";
          } else {
            echo "<div class='pill neu'><i class='fas fa-database'></i> Fuente de plantilla detectada: <b>".e2($src['mode'])."</b></div>";
          }
        ?>

        <?php if (!$puntos): ?>
          <div class="card-soft p-3 mt-3">
            <div style="font-weight:800;">Aún no hay puntos registrados</div>
            <div class="small-muted mt-1">
              Use <b>Aplicar plantilla</b> para generar puntos sugeridos, o implemente una plantilla / patrón con puntos.
            </div>
          </div>
        <?php else: ?>

          <form method="post" class="mt-3">
            <input type="hidden" name="action" value="save_points">

            <div class="table-responsive">
              <table class="table table-hover text-nowrap">
                <thead>
                  <tr>
                    <th style="width:70px">Orden</th>
                    <th style="width:160px">Magnitud</th>
                    <th style="width:90px">Unidad</th>
                    <th style="width:120px">Nominal</th>
                    <th style="width:120px">Equipo</th>
                    <th style="width:120px">Patrón</th>
                    <th style="width:110px">Error abs</th>
                    <th style="width:110px">Tol</th>
                    <th style="width:90px">U</th>
                    <th style="width:70px">k</th>
                    <th style="width:110px">Conforme</th>
                    <th style="width:220px">Notas</th>
                  </tr>
                </thead>
                <tbody>

                <?php foreach($puntos as $idx => $pt): ?>
                  <?php
                    $conf = ($pt['conforme'] === null ? null : (int)$pt['conforme']);
                    $confHtml = "<span class='pill neu'>—</span>";
                    if ($conf === 1) $confHtml = "<span class='pill ok'>SI</span>";
                    if ($conf === 0) $confHtml = "<span class='pill bad'>NO</span>";
                  ?>
                  <tr>
                    <td>
                      <input type="hidden" name="pid[]" value="<?= (int)$pt['id'] ?>">
                      <input class="form-control form-control-sm mono" name="orden[]" value="<?= (int)($pt['orden'] ?? 0) ?>" style="width:70px;">
                    </td>

                    <td>
                      <input class="form-control form-control-sm" name="magnitud[]" value="<?= e2($pt['magnitud'] ?? '') ?>" placeholder="Ej: Voltaje DC">
                    </td>

                    <td>
                      <input class="form-control form-control-sm mono" name="unidad[]" value="<?= e2($pt['unidad'] ?? '') ?>" placeholder="V / A / Ω">
                    </td>

                    <td>
                      <input class="form-control form-control-sm mono" name="pnom[]" value="<?= e2(fmt_num($pt['punto_nominal'] ?? '')) ?>" placeholder="Nominal">
                    </td>

                    <td>
                      <input class="form-control form-control-sm mono" name="leq[]" value="<?= e2(fmt_num($pt['lectura_equipo'] ?? '')) ?>" placeholder="Lect. equipo">
                      <div class="small-muted">Lo digita el técnico</div>
                    </td>

                    <td>
                      <input class="form-control form-control-sm mono" name="lpa[]" value="<?= e2(fmt_num($pt['lectura_patron'] ?? '')) ?>" placeholder="Lect. patrón">
                      <div class="small-muted">Lo digita el técnico</div>
                    </td>

                    <td class="mono">
                      <?= ($pt['error_abs'] !== null && $pt['error_abs'] !== '') ? e2((string)$pt['error_abs']) : '—' ?>
                      <?php if ($pt['error_rel'] !== null && $pt['error_rel'] !== ''): ?>
                        <div class="small-muted">Rel: <?= e2((string)$pt['error_rel']) ?>%</div>
                      <?php endif; ?>
                    </td>

                    <td>
                      <input class="form-control form-control-sm mono" name="tol[]" value="<?= e2(fmt_num($pt['tolerancia'] ?? '')) ?>" placeholder="±">
                    </td>

                    <td>
                      <input class="form-control form-control-sm mono" name="u[]" value="<?= e2(fmt_num($pt['incertidumbre_expandida'] ?? '')) ?>" placeholder="U">
                    </td>

                    <td>
                      <input class="form-control form-control-sm mono" name="k[]" value="<?= e2(fmt_num($pt['k'] ?? '')) ?>" placeholder="k">
                    </td>

                    <td><?= $confHtml ?></td>

                    <td>
                      <textarea class="form-control form-control-sm" name="notas[]" rows="2" placeholder="Notas"><?= e2($pt['notas'] ?? '') ?></textarea>
                    </td>
                  </tr>
                <?php endforeach; ?>

                </tbody>
              </table>
            </div>

            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:10px;">
              <div class="pill neu">
                <i class="fas fa-info-circle"></i>
                Al guardar, el sistema recalcula <b>error abs/rel</b> y <b>conforme</b> cuando hay lecturas y tolerancia.
              </div>
              <div>
                <a class="btn btn-outline-secondary" href="<?= $detalleUrl ?>">
                  <i class="fas fa-arrow-left"></i> Volver
                </a>
                <button class="btn btn-primary" type="submit">
                  <i class="fas fa-save"></i> Guardar puntos
                </button>
              </div>
            </div>

          </form>

        <?php endif; ?>

      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
