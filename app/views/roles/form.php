<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
Auth::requireCan('roles.edit');

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

$data = ['nombre'=>''];
$error = '';

if ($id > 0) {
  $st = db()->prepare("SELECT id, tenant_id, nombre FROM roles WHERE id=:id AND tenant_id=:t LIMIT 1");
  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "Rol no encontrado"; exit; }
  $data = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre'] ?? '');

  if ($nombre === '') $error = 'El nombre del rol es obligatorio.';

  if ($error === '') {
    // único por tenant
    $chk = db()->prepare("SELECT id FROM roles WHERE tenant_id=:t AND nombre=:n AND id<>:id LIMIT 1");
    $chk->execute([':t'=>$tenantId, ':n'=>$nombre, ':id'=>$id]);
    if ($chk->fetch()) $error = 'Ya existe un rol con ese nombre en esta empresa.';
  }

  if ($error === '') {
    if ($id > 0) {
      $up = db()->prepare("UPDATE roles SET nombre=:n WHERE id=:id AND tenant_id=:t LIMIT 1");
      $up->execute([':n'=>$nombre, ':id'=>$id, ':t'=>$tenantId]);
    } else {
      $ins = db()->prepare("INSERT INTO roles (tenant_id, nombre) VALUES (:t, :n)");
      $ins->execute([':t'=>$tenantId, ':n'=>$nombre]);
      $id = (int)db()->lastInsertId();
    }
    redirect('index.php?route=roles');
  }

  $data['nombre'] = $nombre;
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-user-shield"></i> <?= ($id>0?'Editar':'Nuevo') ?> rol</h3>
    <div class="card-tools">
      <a class="btn btn-sm btn-secondary" href="<?= e(base_url()) ?>/index.php?route=roles">
        <i class="fas fa-arrow-left"></i> Volver
      </a>
    </div>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>Nombre *</label>
        <input class="form-control" name="nombre" value="<?= e($data['nombre'] ?? '') ?>" required>
      </div>

      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=roles">Cancelar</a>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
