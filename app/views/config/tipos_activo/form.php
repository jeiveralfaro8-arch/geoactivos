<?php
require_once __DIR__ . '/../../../core/Helpers.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

/* =========================
   Helpers locales
========================= */
function col_exists($table, $col){
  try {
    $st = db()->prepare("
      SELECT COUNT(*) c
      FROM information_schema.columns
      WHERE table_schema = DATABASE()
        AND table_name = :t
        AND column_name = :c
    ");
    $st->execute([':t'=>$table, ':c'=>$col]);
    return ((int)($st->fetch()['c'] ?? 0) > 0);
  } catch (Exception $e) {
    return false;
  }
}

/* =========================
   Datos base
========================= */
$hasFamilia = col_exists('tipos_activo', 'familia');

$data = [
  'nombre'  => '',
  'codigo'  => '',
  'familia' => 'TI', // default
];

// reglas
$reglas = [
  'usa_red'             => 0,
  'usa_software'        => 0,
  'es_biomedico'        => 0,
  'requiere_calibracion'=> 0,
  'periodicidad_meses'  => '',
];

if ($id > 0) {

  if ($hasFamilia) {
    $st = db()->prepare("
      SELECT nombre, codigo, familia
      FROM tipos_activo
      WHERE id = :id AND tenant_id = :t
      LIMIT 1
    ");
  } else {
    $st = db()->prepare("
      SELECT nombre, codigo
      FROM tipos_activo
      WHERE id = :id AND tenant_id = :t
      LIMIT 1
    ");
  }

  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "No encontrado"; exit; }

  $data['nombre'] = (string)($row['nombre'] ?? '');
  $data['codigo'] = (string)($row['codigo'] ?? '');
  if ($hasFamilia) $data['familia'] = (string)($row['familia'] ?? 'TI');

  // cargar reglas del tipo (si existen)
  $rst = db()->prepare("
    SELECT usa_red, usa_software, es_biomedico, requiere_calibracion, periodicidad_meses
    FROM tipo_activo_reglas
    WHERE tenant_id=:t AND tipo_activo_id=:id
    LIMIT 1
  ");
  $rst->execute([':t'=>$tenantId, ':id'=>$id]);
  $rr = $rst->fetch();
  if ($rr) {
    $reglas['usa_red']              = (int)($rr['usa_red'] ?? 0);
    $reglas['usa_software']         = (int)($rr['usa_software'] ?? 0);
    $reglas['es_biomedico']         = (int)($rr['es_biomedico'] ?? 0);
    $reglas['requiere_calibracion'] = (int)($rr['requiere_calibracion'] ?? 0);
    $reglas['periodicidad_meses']   = ($rr['periodicidad_meses'] === null ? '' : (string)$rr['periodicidad_meses']);
  }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $nombre = trim($_POST['nombre'] ?? '');
  $codigo = strtoupper(trim($_POST['codigo'] ?? ''));

  // Normalizar código: solo letras/números/guion/underscore
  $codigo = preg_replace('/[^A-Z0-9_-]/', '', $codigo);

  $familia = strtoupper(trim($_POST['familia'] ?? 'TI'));
  if (!in_array($familia, ['TI','INFRA','BIOMED'], true)) $familia = 'TI';

  // reglas
  $usa_red       = isset($_POST['usa_red']) ? 1 : 0;
  $usa_software  = isset($_POST['usa_software']) ? 1 : 0;
  $es_biomedico  = isset($_POST['es_biomedico']) ? 1 : 0;
  $req_cal       = isset($_POST['requiere_calibracion']) ? 1 : 0;

  $periodicidad  = trim($_POST['periodicidad_meses'] ?? '');
  $periodicidad  = ($periodicidad === '' ? null : (int)$periodicidad);
  if ($periodicidad !== null && $periodicidad <= 0) $periodicidad = null;

  // coherencia: si requiere calibración => es biomédico
  if ($req_cal === 1) $es_biomedico = 1;

  // coherencia: si familia BIOMED => es biomédico
  if ($hasFamilia && $familia === 'BIOMED') $es_biomedico = 1;

  // si NO requiere calibración, periodicidad no aplica
  if ($req_cal === 0) $periodicidad = null;

  if ($nombre === '') {
    $error = 'El nombre es obligatorio.';
  } elseif ($codigo === '') {
    $error = 'El código/prefijo es obligatorio (Ej: PC, MON, SW).';
  } elseif (strlen($codigo) > 10) {
    $error = 'El código/prefijo no puede superar 10 caracteres.';
  } elseif ($req_cal === 1 && $periodicidad === null) {
    $error = 'Si el tipo requiere calibración, debes indicar la periodicidad en meses (Ej: 6, 12).';
  } else {

    // Validar que no exista el mismo código en el mismo tenant (excepto el mismo id en edición)
    if ($id > 0) {
      $chk = db()->prepare("
        SELECT id
        FROM tipos_activo
        WHERE tenant_id=:t AND codigo=:c AND id<>:id
        LIMIT 1
      ");
      $chk->execute([':t'=>$tenantId, ':c'=>$codigo, ':id'=>$id]);
    } else {
      $chk = db()->prepare("
        SELECT id
        FROM tipos_activo
        WHERE tenant_id=:t AND codigo=:c
        LIMIT 1
      ");
      $chk->execute([':t'=>$tenantId, ':c'=>$codigo]);
    }

    if ($chk->fetch()) {
      $error = 'Ya existe un Tipo de Activo con ese código/prefijo en este cliente.';
    } else {

      // Guardar tipo
      if ($id > 0) {

        if ($hasFamilia) {
          $up = db()->prepare("
            UPDATE tipos_activo
            SET nombre=:n, codigo=:c, familia=:f
            WHERE id=:id AND tenant_id=:t
          ");
          $up->execute([':n'=>$nombre, ':c'=>$codigo, ':f'=>$familia, ':id'=>$id, ':t'=>$tenantId]);
        } else {
          // legacy: si no existe familia, no rompemos
          $up = db()->prepare("
            UPDATE tipos_activo
            SET nombre=:n, codigo=:c
            WHERE id=:id AND tenant_id=:t
          ");
          $up->execute([':n'=>$nombre, ':c'=>$codigo, ':id'=>$id, ':t'=>$tenantId]);
        }

        $tipoId = $id;

      } else {

        if ($hasFamilia) {
          $ins = db()->prepare("
            INSERT INTO tipos_activo (tenant_id, nombre, codigo, familia)
            VALUES (:t, :n, :c, :f)
          ");
          $ins->execute([':t'=>$tenantId, ':n'=>$nombre, ':c'=>$codigo, ':f'=>$familia]);
        } else {
          $ins = db()->prepare("
            INSERT INTO tipos_activo (tenant_id, nombre, codigo)
            VALUES (:t, :n, :c)
          ");
          $ins->execute([':t'=>$tenantId, ':n'=>$nombre, ':c'=>$codigo]);
        }

        $tipoId = (int)db()->lastInsertId();
      }

      // UPSERT reglas
      $ex = db()->prepare("
        SELECT id
        FROM tipo_activo_reglas
        WHERE tenant_id=:t AND tipo_activo_id=:ta
        LIMIT 1
      ");
      $ex->execute([':t'=>$tenantId, ':ta'=>$tipoId]);
      $exRow = $ex->fetch();

      if ($exRow) {
        $rup = db()->prepare("
          UPDATE tipo_activo_reglas
          SET usa_red=:ur, usa_software=:us, es_biomedico=:eb, requiere_calibracion=:rc, periodicidad_meses=:pm
          WHERE tenant_id=:t AND tipo_activo_id=:ta
        ");
        $rup->execute([
          ':ur'=>$usa_red,
          ':us'=>$usa_software,
          ':eb'=>$es_biomedico,
          ':rc'=>$req_cal,
          ':pm'=>$periodicidad,
          ':t'=>$tenantId,
          ':ta'=>$tipoId
        ]);
      } else {
        $rin = db()->prepare("
          INSERT INTO tipo_activo_reglas
            (tenant_id, tipo_activo_id, usa_red, usa_software, es_biomedico, requiere_calibracion, periodicidad_meses)
          VALUES
            (:t, :ta, :ur, :us, :eb, :rc, :pm)
        ");
        $rin->execute([
          ':t'=>$tenantId,
          ':ta'=>$tipoId,
          ':ur'=>$usa_red,
          ':us'=>$usa_software,
          ':eb'=>$es_biomedico,
          ':rc'=>$req_cal,
          ':pm'=>$periodicidad
        ]);
      }

      redirect('index.php?route=tipos_activo');
    }
  }

  // repintar
  $data['nombre'] = $nombre;
  $data['codigo'] = $codigo;
  if ($hasFamilia) $data['familia'] = $familia;

  $reglas['usa_red']              = $usa_red;
  $reglas['usa_software']         = $usa_software;
  $reglas['es_biomedico']         = $es_biomedico;
  $reglas['requiere_calibracion'] = $req_cal;
  $reglas['periodicidad_meses']   = ($periodicidad === null ? '' : (string)$periodicidad);
}

require __DIR__ . '/../../layout/header.php';
require __DIR__ . '/../../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= $id>0 ? 'Editar Tipo de Activo' : 'Nuevo Tipo de Activo' ?></h3>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">

      <div class="form-group">
        <label>Nombre *</label>
        <input class="form-control" name="nombre" value="<?= e($data['nombre']) ?>" required
               placeholder="Ej: Computador, Monitor, Switch, Cámara...">
      </div>

      <div class="form-group">
        <label>Código / Prefijo *</label>
        <input class="form-control" name="codigo" maxlength="10" value="<?= e($data['codigo']) ?>" required
               placeholder="Ej: PC, MON, SW, CAM">
        <small class="text-muted">
          Se usa para generar automáticamente el código interno del activo (PC-0001, SW-0001, etc).
        </small>
      </div>

      <?php if ($hasFamilia): ?>
      <div class="form-group">
        <label>Familia *</label>
        <select class="form-control" name="familia" id="familia" required>
          <option value="TI" <?= ($data['familia']==='TI'?'selected':'') ?>>TI (Computadores / usuarios)</option>
          <option value="INFRA" <?= ($data['familia']==='INFRA'?'selected':'') ?>>INFRA (Red / Servidores / CCTV)</option>
          <option value="BIOMED" <?= ($data['familia']==='BIOMED'?'selected':'') ?>>BIOMED (Biomédico)</option>
        </select>
        <small class="text-muted">Ayuda a clasificar reportes y reglas (especialmente calibración).</small>
      </div>
      <?php endif; ?>

      <hr>

      <h6 class="text-muted mb-2"><i class="fas fa-cogs"></i> Reglas del tipo (auto-aplican en el formulario de activos)</h6>

      <div class="form-row">
        <div class="form-group col-md-3">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="usa_red" name="usa_red" <?= ((int)$reglas['usa_red']===1?'checked':'') ?>>
            <label class="custom-control-label" for="usa_red">Usa red (hostname/IP/MAC)</label>
          </div>
        </div>

        <div class="form-group col-md-3">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="usa_software" name="usa_software" <?= ((int)$reglas['usa_software']===1?'checked':'') ?>>
            <label class="custom-control-label" for="usa_software">Control de software</label>
          </div>
        </div>

        <div class="form-group col-md-3">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="es_biomedico" name="es_biomedico" <?= ((int)$reglas['es_biomedico']===1?'checked':'') ?>>
            <label class="custom-control-label" for="es_biomedico">Es biomédico</label>
          </div>
        </div>

        <div class="form-group col-md-3">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="requiere_calibracion" name="requiere_calibracion" <?= ((int)$reglas['requiere_calibracion']===1?'checked':'') ?>>
            <label class="custom-control-label" for="requiere_calibracion">Requiere calibración</label>
          </div>
        </div>
      </div>

      <div class="form-row" id="row_periodicidad" style="display:none;">
        <div class="form-group col-md-4">
          <label>Periodicidad (meses)</label>
          <input type="number" min="1" class="form-control" name="periodicidad_meses" id="periodicidad_meses"
                 value="<?= e($reglas['periodicidad_meses']) ?>"
                 placeholder="Ej: 6, 12">
          <small class="text-muted">Solo aplica si el tipo requiere calibración.</small>
        </div>

        <div class="form-group col-md-8">
          <div class="alert alert-light" style="border:1px solid #e5e7eb;">
            <b>Nota:</b> estas reglas alimentan el panel de “Biomédico/Calibración” del formulario de activos y
            determinan si se muestra/oculta el bloque de red.
          </div>
        </div>
      </div>

      <script src="<?= e(base_url()) ?>/assets/js/tipo-activo-form.js"></script>

      <button class="btn btn-primary">
        <i class="fas fa-save"></i> Guardar
      </button>

      <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=tipos_activo">
        Volver
      </a>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../layout/footer.php'; ?>
