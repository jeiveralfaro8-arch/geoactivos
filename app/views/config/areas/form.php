<?php
require_once __DIR__ . '/../../../core/Helpers.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

// Cargar sedes para selector
$sedeSt = db()->prepare("SELECT id, nombre FROM sedes WHERE tenant_id=:t ORDER BY nombre ASC");
$sedeSt->execute([':t'=>$tenantId]);
$sedes = $sedeSt->fetchAll();

$data = ['sede_id' => '', 'nombre' => ''];
if ($id > 0) {
  $st = db()->prepare("SELECT sede_id, nombre FROM areas WHERE id=:id AND tenant_id=:t LIMIT 1");
  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "No encontrado"; exit; }
  $data = $row;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $sedeId = (int)($_POST['sede_id'] ?? 0);
  $nombre = trim($_POST['nombre'] ?? '');

  if ($nombre === '') {
    $error = "El nombre del área es obligatorio.";
  } else {
    // Validar sede si viene seleccionada (puede ser NULL)
    if ($sedeId > 0) {
      $chk = db()->prepare("SELECT id FROM sedes WHERE id=:id AND tenant_id=:t LIMIT 1");
      $chk->execute([':id'=>$sedeId, ':t'=>$tenantId]);
      if (!$chk->fetch()) {
        $error = "Sede inválida.";
      }
    }

    if ($error === '') {
      try {
        if ($id > 0) {
          $up = db()->prepare("UPDATE areas SET sede_id=:s, nombre=:n WHERE id=:id AND tenant_id=:t");
          $up->execute([':s'=>($sedeId>0?$sedeId:null), ':n'=>$nombre, ':id'=>$id, ':t'=>$tenantId]);
        } else {
          $ins = db()->prepare("INSERT INTO areas (tenant_id, sede_id, nombre) VALUES (:t, :s, :n)");
          $ins->execute([':t'=>$tenantId, ':s'=>($sedeId>0?$sedeId:null), ':n'=>$nombre]);
        }
        redirect('index.php?route=areas');
      } catch (Exception $e) {
        // UNIQUE(tenant_id, nombre)
        $error = "No se pudo guardar. Verifica si el área ya existe.";
      }
    }
  }
}

require __DIR__ . '/../../layout/header.php';
require __DIR__ . '/../../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= $id>0 ? 'Editar área' : 'Nueva área' ?></h3>
  </div>
  <div class="card-body">

    <?php if (!$sedes): ?>
      <div class="alert alert-warning">
        No tienes sedes creadas. Puedes crear áreas sin sede, pero lo ideal es crear sedes en
        <a href="<?= e(base_url()) ?>/index.php?route=sedes"><b>Configuración → Sedes</b></a>.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Sede</label>
          <select class="form-control" name="sede_id">
            <option value="0">— Sin sede —</option>
            <?php foreach ($sedes as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= ((int)$data['sede_id'] === (int)$s['id']) ? 'selected' : '' ?>>
                <?= e($s['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group col-md-6">
          <label>Nombre del área *</label>
          <input class="form-control" name="nombre" value="<?= e($data['nombre']) ?>" required>
        </div>
      </div>

      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=areas">Volver</a>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../layout/footer.php'; ?>
