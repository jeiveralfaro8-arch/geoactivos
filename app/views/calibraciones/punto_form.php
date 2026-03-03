<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = (int)Auth::tenantId();
$user = Auth::user();
$userId = isset($user['id']) ? (int)$user['id'] : null;

$calId = (int)($_GET['cal_id'] ?? 0);
$id    = (int)($_GET['id'] ?? 0);

if ($calId <= 0) { http_response_code(400); echo "cal_id inválido"; exit; }

function e2($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nvl($v, $dash=''){ return ($v===null) ? $dash : (string)$v; }
function to_num($v){
  // acepta coma o punto
  $v = trim((string)$v);
  if ($v === '') return null;
  $v = str_replace(' ', '', $v);
  $v = str_replace(',', '.', $v);
  if (!is_numeric($v)) return null;
  return (float)$v;
}

/* =========================
   Verificar calibración
========================= */
$stc = db()->prepare("
  SELECT c.id, c.activo_id, c.numero_certificado,
         a.codigo_interno, a.nombre AS activo_nombre
  FROM calibraciones c
  LEFT JOIN activos a ON a.id=c.activo_id AND a.tenant_id=c.tenant_id
  WHERE c.id=:id AND c.tenant_id=:t AND COALESCE(c.eliminado,0)=0
  LIMIT 1
");
$stc->execute([':id'=>$calId, ':t'=>$tenantId]);
$cal = $stc->fetch();
if (!$cal) { http_response_code(404); echo "Calibración no encontrada"; exit; }

$codigo = trim((string)($cal['codigo_interno'] ?? ''));
if ($codigo === '') $codigo = '#'.(int)$cal['activo_id'];
$nombre = trim((string)($cal['activo_nombre'] ?? ''));

/* =========================
   Cargar punto si edita
========================= */
$p = [
  'orden' => 1,
  'magnitud' => '',
  'unidad' => '',
  'punto_nominal' => '',
  'lectura_equipo' => '',
  'lectura_patron' => '',
  'tolerancia' => '',
  'incertidumbre_expandida' => '',
  'k' => '',
  'notas' => ''
];

if ($id > 0) {
  $st = db()->prepare("
    SELECT *
    FROM calibraciones_puntos
    WHERE id=:id AND tenant_id=:t AND calibracion_id=:c AND COALESCE(eliminado,0)=0
    LIMIT 1
  ");
  $st->execute([':id'=>$id, ':t'=>$tenantId, ':c'=>$calId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "Punto no encontrado"; exit; }

  foreach($p as $k=>$v){
    if (array_key_exists($k, $row)) $p[$k] = nvl($row[$k], '');
  }
}

/* =========================
   POST guardar
========================= */
$err = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $p['orden'] = (int)($_POST['orden'] ?? 1);
  if ($p['orden'] <= 0) $p['orden'] = 1;

  $p['magnitud'] = trim((string)($_POST['magnitud'] ?? ''));
  $p['unidad']   = trim((string)($_POST['unidad'] ?? ''));

  $p['punto_nominal']   = trim((string)($_POST['punto_nominal'] ?? ''));
  $p['lectura_equipo']  = trim((string)($_POST['lectura_equipo'] ?? ''));
  $p['lectura_patron']  = trim((string)($_POST['lectura_patron'] ?? ''));
  $p['tolerancia']      = trim((string)($_POST['tolerancia'] ?? ''));
  $p['incertidumbre_expandida'] = trim((string)($_POST['incertidumbre_expandida'] ?? ''));
  $p['k'] = trim((string)($_POST['k'] ?? ''));
  $p['notas'] = trim((string)($_POST['notas'] ?? ''));

  // Validaciones mínimas PRO:
  if ($p['magnitud'] === '') $err = 'La magnitud es obligatoria (ej: Voltaje DC, Temperatura, Presión).';
  if ($err === '' && $p['unidad'] === '') $err = 'La unidad es obligatoria (V, °C, psi, bar, etc.).';

  // Cálculo automático: error_abs = equipo - patrón (si ambos numéricos)
  $equ = to_num($p['lectura_equipo']);
  $pat = to_num($p['lectura_patron']);
  $tol = to_num($p['tolerancia']);

  $error_abs = null;
  if ($equ !== null && $pat !== null) {
    $error_abs = $equ - $pat;
  }

  // Conforme automático si hay error_abs y tolerancia
  $conforme = null;
  if ($error_abs !== null && $tol !== null) {
    $conforme = (abs($error_abs) <= abs($tol)) ? 1 : 0;
  }

  if ($err === '') {
    if ($id > 0) {
      $up = db()->prepare("
        UPDATE calibraciones_puntos SET
          orden=:orden,
          magnitud=:magnitud,
          unidad=:unidad,
          punto_nominal=:nominal,
          lectura_equipo=:equipo,
          lectura_patron=:patron,
          error_abs=:eabs,
          tolerancia=:tol,
          conforme=:conf,
          incertidumbre_expandida=:u,
          k=:k,
          notas=:notas
        WHERE id=:id AND tenant_id=:t AND calibracion_id=:c AND COALESCE(eliminado,0)=0
        LIMIT 1
      ");
      $up->execute([
        ':orden'=>$p['orden'],
        ':magnitud'=>$p['magnitud'],
        ':unidad'=>$p['unidad'],
        ':nominal'=>($p['punto_nominal']!=='' ? $p['punto_nominal'] : null),
        ':equipo'=>($p['lectura_equipo']!=='' ? $p['lectura_equipo'] : null),
        ':patron'=>($p['lectura_patron']!=='' ? $p['lectura_patron'] : null),
        ':eabs'=>($error_abs!==null ? $error_abs : null),
        ':tol'=>($p['tolerancia']!=='' ? $p['tolerancia'] : null),
        ':conf'=>($conforme!==null ? $conforme : null),
        ':u'=>($p['incertidumbre_expandida']!=='' ? $p['incertidumbre_expandida'] : null),
        ':k'=>($p['k']!=='' ? $p['k'] : null),
        ':notas'=>($p['notas']!=='' ? $p['notas'] : null),
        ':id'=>$id, ':t'=>$tenantId, ':c'=>$calId
      ]);

      header('Location: '.base_url().'/index.php?route=calibracion_puntos&id='.$calId.'&ok=1');
      exit;

    } else {
      $ins = db()->prepare("
        INSERT INTO calibraciones_puntos
          (tenant_id, calibracion_id, orden, magnitud, unidad,
           punto_nominal, lectura_equipo, lectura_patron,
           error_abs, tolerancia, conforme,
           incertidumbre_expandida, k, notas, creado_en, creado_por)
        VALUES
          (:t, :c, :orden, :magnitud, :unidad,
           :nominal, :equipo, :patron,
           :eabs, :tol, :conf,
           :u, :k, :notas, NOW(), :uId)
      ");
      try{
        $ins->execute([
          ':t'=>$tenantId, ':c'=>$calId,
          ':orden'=>$p['orden'],
          ':magnitud'=>$p['magnitud'],
          ':unidad'=>$p['unidad'],
          ':nominal'=>($p['punto_nominal']!=='' ? $p['punto_nominal'] : null),
          ':equipo'=>($p['lectura_equipo']!=='' ? $p['lectura_equipo'] : null),
          ':patron'=>($p['lectura_patron']!=='' ? $p['lectura_patron'] : null),
          ':eabs'=>($error_abs!==null ? $error_abs : null),
          ':tol'=>($p['tolerancia']!=='' ? $p['tolerancia'] : null),
          ':conf'=>($conforme!==null ? $conforme : null),
          ':u'=>($p['incertidumbre_expandida']!=='' ? $p['incertidumbre_expandida'] : null),
          ':k'=>($p['k']!=='' ? $p['k'] : null),
          ':notas'=>($p['notas']!=='' ? $p['notas'] : null),
          ':uId'=>$userId
        ]);
      }catch(Exception $e){
        // Si tu tabla no tiene creado_en/creado_por, intenta sin esos campos:
        $ins2 = db()->prepare("
          INSERT INTO calibraciones_puntos
            (tenant_id, calibracion_id, orden, magnitud, unidad,
             punto_nominal, lectura_equipo, lectura_patron,
             error_abs, tolerancia, conforme,
             incertidumbre_expandida, k, notas)
          VALUES
            (:t, :c, :orden, :magnitud, :unidad,
             :nominal, :equipo, :patron,
             :eabs, :tol, :conf,
             :u, :k, :notas)
        ");
        $ins2->execute([
          ':t'=>$tenantId, ':c'=>$calId,
          ':orden'=>$p['orden'],
          ':magnitud'=>$p['magnitud'],
          ':unidad'=>$p['unidad'],
          ':nominal'=>($p['punto_nominal']!=='' ? $p['punto_nominal'] : null),
          ':equipo'=>($p['lectura_equipo']!=='' ? $p['lectura_equipo'] : null),
          ':patron'=>($p['lectura_patron']!=='' ? $p['lectura_patron'] : null),
          ':eabs'=>($error_abs!==null ? $error_abs : null),
          ':tol'=>($p['tolerancia']!=='' ? $p['tolerancia'] : null),
          ':conf'=>($conforme!==null ? $conforme : null),
          ':u'=>($p['incertidumbre_expandida']!=='' ? $p['incertidumbre_expandida'] : null),
          ':k'=>($p['k']!=='' ? $p['k'] : null),
          ':notas'=>($p['notas']!=='' ? $p['notas'] : null),
        ]);
      }

      header('Location: '.base_url().'/index.php?route=calibracion_puntos&id='.$calId.'&ok=1');
      exit;
    }
  }
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<style>
.badge{border-radius:999px; padding:6px 10px; font-weight:600;}
.small-muted{font-size:12px; color:#6c757d;}
.card-soft{border:1px solid #e5e7eb; border-radius:12px;}
.pill{display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:#f4f6f9; border:1px solid #e5e7eb; font-size:12px;}
</style>

<div class="content">
  <div class="container-fluid">

    <div class="card card-outline card-primary">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:10px;">
          <div>
            <h3 class="card-title mb-0">
              <i class="fas fa-ruler"></i> <?= ($id>0 ? 'Editar' : 'Nuevo') ?> punto · Calibración #<?= (int)$calId ?>
            </h3>
            <div class="small-muted"><?= e2($codigo) ?><?= ($nombre!=='' ? ' · '.e2($nombre) : '') ?></div>
          </div>

          <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
            <a class="btn btn-outline-secondary btn-sm"
               href="<?= e2(base_url()) ?>/index.php?route=calibracion_puntos&id=<?= (int)$calId ?>">
              <i class="fas fa-arrow-left"></i> Volver a puntos
            </a>
          </div>
        </div>
      </div>

      <form method="post">
        <div class="card-body">

          <?php if ($err): ?><div class="alert alert-danger"><?= e2($err) ?></div><?php endif; ?>

          <div class="row">
            <div class="col-lg-2">
              <div class="form-group">
                <label class="small-muted">Orden</label>
                <input type="number" class="form-control" name="orden" value="<?= (int)$p['orden'] ?>" min="1">
              </div>
            </div>
            <div class="col-lg-5">
              <div class="form-group">
                <label class="small-muted">Magnitud</label>
                <input class="form-control" name="magnitud" value="<?= e2($p['magnitud']) ?>" placeholder="Ej: Voltaje DC, Temperatura, Presión">
              </div>
            </div>
            <div class="col-lg-5">
              <div class="form-group">
                <label class="small-muted">Unidad</label>
                <input class="form-control" name="unidad" value="<?= e2($p['unidad']) ?>" placeholder="Ej: V, °C, psi, bar, Ω">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-3">
              <div class="form-group">
                <label class="small-muted">Punto nominal</label>
                <input class="form-control" name="punto_nominal" value="<?= e2($p['punto_nominal']) ?>" placeholder="Ej: 5.00">
              </div>
            </div>
            <div class="col-lg-3">
              <div class="form-group">
                <label class="small-muted">Lectura equipo</label>
                <input class="form-control" name="lectura_equipo" value="<?= e2($p['lectura_equipo']) ?>" placeholder="Ej: 5.03">
              </div>
            </div>
            <div class="col-lg-3">
              <div class="form-group">
                <label class="small-muted">Lectura patrón</label>
                <input class="form-control" name="lectura_patron" value="<?= e2($p['lectura_patron']) ?>" placeholder="Ej: 5.00">
              </div>
            </div>
            <div class="col-lg-3">
              <div class="form-group">
                <label class="small-muted">Tolerancia</label>
                <input class="form-control" name="tolerancia" value="<?= e2($p['tolerancia']) ?>" placeholder="Ej: 0.10 (o ±0.10)">
                <small class="text-muted">Si la tolerancia es numérica, se calcula OK automáticamente.</small>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-3">
              <div class="form-group">
                <label class="small-muted">U (incertidumbre expandida)</label>
                <input class="form-control" name="incertidumbre_expandida" value="<?= e2($p['incertidumbre_expandida']) ?>" placeholder="Ej: 0.05">
              </div>
            </div>
            <div class="col-lg-2">
              <div class="form-group">
                <label class="small-muted">k</label>
                <input class="form-control" name="k" value="<?= e2($p['k']) ?>" placeholder="Ej: 2">
              </div>
            </div>
            <div class="col-lg-7">
              <div class="form-group">
                <label class="small-muted">Notas</label>
                <input class="form-control" name="notas" value="<?= e2($p['notas']) ?>" placeholder="Ej: Rango 20V, puntas nuevas, estabilizado 3 min...">
              </div>
            </div>
          </div>

          <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="pill mt-2">
              <i class="fas fa-calculator"></i>
              PRO: si Equipo y Patrón son numéricos, se calcula <b>Error abs</b>. Si además Tolerancia es numérica, se calcula <b>OK</b>.
            </div>

            <div class="mt-2">
              <a class="btn btn-outline-secondary"
                 href="<?= e2(base_url()) ?>/index.php?route=calibracion_puntos&id=<?= (int)$calId ?>">
                <i class="fas fa-times"></i> Cancelar
              </a>

              <button class="btn btn-primary" type="submit">
                <i class="fas fa-save"></i> Guardar punto
              </button>
            </div>
          </div>

        </div>
      </form>

    </div>

  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
