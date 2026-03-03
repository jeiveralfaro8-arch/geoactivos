<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();
$activoId = (int)($_GET['id'] ?? 0);

if ($activoId <= 0) {
  http_response_code(400);
  echo "ID de activo inválido";
  exit;
}

// Validar que el activo existe y es del tenant
$chk = db()->prepare("SELECT id, nombre FROM activos WHERE id=:id AND tenant_id=:t LIMIT 1");
$chk->execute([':id'=>$activoId, ':t'=>$tenantId]);
$activo = $chk->fetch();
if (!$activo) {
  http_response_code(404);
  echo "Activo no encontrado";
  exit;
}

$error = '';
$okMsg = '';

// Para repoblar si falla
$form = array(
  'nombre' => '',
  'version' => '',
  'licencia_tipo' => 'OTRA',
  'licencia_clave' => '',
  'fecha_instalacion' => '',
  'fecha_vencimiento' => '',
  'observaciones' => '',
);

/* ------------------- Guardar (insert) ------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form['nombre'] = trim($_POST['nombre'] ?? '');
  $form['version'] = trim($_POST['version'] ?? '');
  $form['licencia_tipo'] = $_POST['licencia_tipo'] ?? 'OTRA';
  $form['licencia_clave'] = trim($_POST['licencia_clave'] ?? '');
  $form['fecha_instalacion'] = trim($_POST['fecha_instalacion'] ?? '');
  $form['fecha_vencimiento'] = trim($_POST['fecha_vencimiento'] ?? '');
  $form['observaciones'] = trim($_POST['observaciones'] ?? '');

  $validTipos = array('FREE','OEM','VOLUMEN','SUSCRIPCION','OTRA');

  if ($form['nombre'] === '') {
    $error = 'El nombre del software es obligatorio.';
  } elseif (!in_array($form['licencia_tipo'], $validTipos, true)) {
    $error = 'Tipo de licencia inválido.';
  } else {

    $nombre = $form['nombre'];
    $version = ($form['version'] !== '' ? $form['version'] : null);
    $licTipo = $form['licencia_tipo'];
    $licClave = ($form['licencia_clave'] !== '' ? $form['licencia_clave'] : null);
    $fIns = ($form['fecha_instalacion'] !== '' ? $form['fecha_instalacion'] : null);
    $fVen = ($form['fecha_vencimiento'] !== '' ? $form['fecha_vencimiento'] : null);
    $obs = ($form['observaciones'] !== '' ? $form['observaciones'] : null);

    $ins = db()->prepare("
      INSERT INTO activos_software
        (tenant_id, activo_id, software_id, nombre, version, licencia_tipo, licencia_clave, fecha_instalacion, fecha_vencimiento, observaciones)
      VALUES
        (:t, :a, NULL, :n, :v, :lt, :lc, :fi, :fv, :o)
    ");
    $ins->execute([
      ':t'=>$tenantId, ':a'=>$activoId,
      ':n'=>$nombre, ':v'=>$version,
      ':lt'=>$licTipo, ':lc'=>$licClave,
      ':fi'=>$fIns, ':fv'=>$fVen,
      ':o'=>$obs
    ]);

    // Reset form al guardar bien
    $form = array(
      'nombre' => '',
      'version' => '',
      'licencia_tipo' => 'OTRA',
      'licencia_clave' => '',
      'fecha_instalacion' => '',
      'fecha_vencimiento' => '',
      'observaciones' => '',
    );

    $okMsg = 'Software agregado correctamente.';
  }
}

/* ------------------- Listado ------------------- */
$st = db()->prepare("
  SELECT id, nombre, version, licencia_tipo, licencia_clave, fecha_instalacion, fecha_vencimiento, observaciones, creado_en
  FROM activos_software
  WHERE tenant_id=:t AND activo_id=:a
  ORDER BY id DESC
");
$st->execute([':t'=>$tenantId, ':a'=>$activoId]);
$rows = $st->fetchAll();

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-desktop"></i> Software · <?= e($activo['nombre']) ?>
    </h3>
    <div class="card-tools">
      <a class="btn btn-sm btn-secondary"
         href="<?= e(base_url()) ?>/index.php?route=activo_detalle&id=<?= (int)$activoId ?>">
        <i class="fas fa-arrow-left"></i> Volver al detalle
      </a>
    </div>
  </div>

  <div class="card-body">

    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($okMsg): ?>
      <div class="alert alert-success text-sm"><?= e($okMsg) ?></div>
    <?php endif; ?>

    <div class="row">
      <div class="col-md-5">
        <div class="card card-outline card-primary">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-plus"></i> Agregar software</h3>
          </div>
          <div class="card-body">
            <form method="post" autocomplete="off">
              <div class="form-group">
                <label>Nombre *</label>
                <input class="form-control" name="nombre" required
                       value="<?= e($form['nombre']) ?>"
                       placeholder="Ej: Windows Server, Office, Antivirus, AnyDesk">
              </div>

              <div class="form-group">
                <label>Versión</label>
                <input class="form-control" name="version"
                       value="<?= e($form['version']) ?>"
                       placeholder="Ej: 2019, 365, v16, 7.1">
              </div>

              <div class="form-group">
                <label>Tipo licencia</label>
                <select class="form-control" name="licencia_tipo">
                  <?php foreach (array('FREE','OEM','VOLUMEN','SUSCRIPCION','OTRA') as $op): ?>
                    <option value="<?= e($op) ?>" <?= ($form['licencia_tipo'] === $op ? 'selected' : '') ?>>
                      <?= e($op) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label>Clave / Serial licencia</label>
                <input class="form-control" name="licencia_clave"
                       value="<?= e($form['licencia_clave']) ?>"
                       placeholder="Opcional">
              </div>

              <div class="form-row">
                <div class="form-group col-md-6">
                  <label>Instalación</label>
                  <input type="date" class="form-control" name="fecha_instalacion"
                         value="<?= e($form['fecha_instalacion']) ?>">
                </div>
                <div class="form-group col-md-6">
                  <label>Vencimiento</label>
                  <input type="date" class="form-control" name="fecha_vencimiento"
                         value="<?= e($form['fecha_vencimiento']) ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Observaciones</label>
                <textarea class="form-control" name="observaciones" rows="3"
                          placeholder="Ej: Licencia asignada a usuario, renovaciones, etc."><?= e($form['observaciones']) ?></textarea>
              </div>

              <button class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-7">
        <div class="card card-outline card-info">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> Software registrado</h3>
          </div>

          <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
              <thead>
                <tr>
                  <th>Software</th>
                  <th>Licencia</th>
                  <th>Fechas</th>
                  <th style="width:120px" class="text-right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr><td colspan="4" class="text-center text-muted p-4">No hay software registrado.</td></tr>
                <?php endif; ?>

                <?php foreach ($rows as $r): ?>
                  <?php
                    $lic = e($r['licencia_tipo']);
                    if (!empty($r['licencia_clave'])) $lic .= "<br><span class='text-muted text-sm'>".e($r['licencia_clave'])."</span>";

                    $fechas = '—';
                    if (!empty($r['fecha_instalacion']) || !empty($r['fecha_vencimiento'])) {
                      $fi = !empty($r['fecha_instalacion']) ? substr((string)$r['fecha_instalacion'],0,10) : '—';
                      $fv = !empty($r['fecha_vencimiento']) ? substr((string)$r['fecha_vencimiento'],0,10) : '—';
                      $fechas = "Inst: ".e($fi)."<br>Vence: ".e($fv);
                    }
                  ?>
                  <tr>
                    <td>
                      <b><?= e($r['nombre']) ?></b>
                      <?php if (!empty($r['version'])): ?>
                        <div class="text-muted text-sm">Versión: <?= e($r['version']) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($r['observaciones'])): ?>
                        <div class="text-muted text-sm"><?= e($r['observaciones']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= $lic ?></td>
                    <td><?= $fechas ?></td>
                    <td class="text-right">
                      <a class="btn btn-sm btn-outline-danger"
                         href="<?= e(base_url()) ?>/index.php?route=activo_software_delete&id=<?= (int)$r['id'] ?>&activo_id=<?= (int)$activoId ?>"
                         onclick="return confirm('¿Eliminar este software del activo?');"
                         title="Eliminar">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>

  </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
