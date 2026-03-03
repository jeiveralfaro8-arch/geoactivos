<?php
require_once __DIR__ . '/../../../core/Helpers.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

$data = [
  'nombre' => '',
  'nit' => '',
  'telefono' => '',
  'email' => '',
  'direccion' => '',
];
if ($id > 0) {
  $st = db()->prepare("
    SELECT nombre, nit, telefono, email, direccion
    FROM proveedores
    WHERE id=:id AND tenant_id=:t
    LIMIT 1
  ");
  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "No encontrado"; exit; }
  $data = $row;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre'] ?? '');
  $nit = trim($_POST['nit'] ?? '');
  $telefono = trim($_POST['telefono'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $direccion = trim($_POST['direccion'] ?? '');

  if ($nombre === '') {
    $error = "El nombre es obligatorio.";
  } else {
    try {
      if ($id > 0) {
        $up = db()->prepare("
          UPDATE proveedores
          SET nombre=:n, nit=:nit, telefono=:tel, email=:em, direccion=:dir
          WHERE id=:id AND tenant_id=:t
        ");
        $up->execute([
          ':n'=>$nombre, ':nit'=>$nit, ':tel'=>$telefono, ':em'=>$email, ':dir'=>$direccion,
          ':id'=>$id, ':t'=>$tenantId
        ]);
      } else {
        $ins = db()->prepare("
          INSERT INTO proveedores (tenant_id, nombre, nit, telefono, email, direccion)
          VALUES (:t, :n, :nit, :tel, :em, :dir)
        ");
        $ins->execute([
          ':t'=>$tenantId, ':n'=>$nombre, ':nit'=>$nit, ':tel'=>$telefono, ':em'=>$email, ':dir'=>$direccion
        ]);
      }
      redirect('index.php?route=proveedores');
    } catch (Exception $e) {
      $error = "No se pudo guardar. Verifica si el proveedor ya existe.";
    }
  }
}

require __DIR__ . '/../../layout/header.php';
require __DIR__ . '/../../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= $id>0 ? 'Editar proveedor' : 'Nuevo proveedor' ?></h3>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Nombre *</label>
          <input class="form-control" name="nombre" value="<?= e($data['nombre']) ?>" required>
        </div>
        <div class="form-group col-md-6">
          <label>NIT</label>
          <input class="form-control" name="nit" value="<?= e($data['nit']) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Teléfono</label>
          <input class="form-control" name="telefono" value="<?= e($data['telefono']) ?>">
        </div>
        <div class="form-group col-md-6">
          <label>Email</label>
          <input class="form-control" name="email" value="<?= e($data['email']) ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Dirección</label>
        <input class="form-control" name="direccion" value="<?= e($data['direccion']) ?>">
      </div>

      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=proveedores">Volver</a>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../layout/footer.php'; ?>
