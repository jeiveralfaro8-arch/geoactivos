<?php
require_once __DIR__ . '/../../../core/Helpers.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

$data = ['nombre' => '', 'direccion' => ''];
if ($id > 0) {
  $st = db()->prepare("SELECT nombre, direccion FROM sedes WHERE id=:id AND tenant_id=:t LIMIT 1");
  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "No encontrado"; exit; }
  $data = $row;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre'] ?? '');
  $direccion = trim($_POST['direccion'] ?? '');

  if ($nombre === '') {
    $error = "El nombre es obligatorio.";
  } else {
    try {
      if ($id > 0) {
        $up = db()->prepare("UPDATE sedes SET nombre=:n, direccion=:d WHERE id=:id AND tenant_id=:t");
        $up->execute([':n'=>$nombre, ':d'=>$direccion, ':id'=>$id, ':t'=>$tenantId]);
      } else {
        $ins = db()->prepare("INSERT INTO sedes (tenant_id, nombre, direccion) VALUES (:t, :n, :d)");
        $ins->execute([':t'=>$tenantId, ':n'=>$nombre, ':d'=>$direccion]);
      }
      redirect('index.php?route=sedes');
    } catch (Exception $e) {
      $error = "No se pudo guardar. Verifica si la sede ya existe.";
    }
  }
}

require __DIR__ . '/../../layout/header.php';
require __DIR__ . '/../../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= $id>0 ? 'Editar sede' : 'Nueva sede' ?></h3>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label>Nombre *</label>
        <input class="form-control" name="nombre" value="<?= e($data['nombre']) ?>" required>
      </div>

      <div class="form-group">
        <label>Dirección</label>
        <input class="form-control" name="direccion" value="<?= e($data['direccion']) ?>">
      </div>

      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=sedes">Volver</a>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../layout/footer.php'; ?>
