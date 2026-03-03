<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
Auth::requireCan('roles.edit');

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare("SELECT id, nombre FROM roles WHERE id=:id AND tenant_id=:t LIMIT 1");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$rol = $st->fetch();

if (!$rol) { http_response_code(404); echo "Rol no encontrado"; exit; }

// proteger rol en uso
$use = db()->prepare("SELECT COUNT(*) c FROM usuarios WHERE tenant_id=:t AND rol_id=:r");
$use->execute([':t'=>$tenantId, ':r'=>$id]);
$enUso = ((int)$use->fetch()['c'] > 0);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($enUso) {
    $error = 'No se puede eliminar: hay usuarios usando este rol.';
  } else {
    $del = db()->prepare("DELETE FROM roles WHERE id=:id AND tenant_id=:t LIMIT 1");
    $del->execute([':id'=>$id, ':t'=>$tenantId]);
    redirect('index.php?route=roles');
  }
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>
<div class="card card-outline card-danger">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-trash"></i> Eliminar rol</h3>
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

    <p>¿Seguro que deseas eliminar el rol <b><?= e($rol['nombre']) ?></b>?</p>

    <?php if ($enUso): ?>
      <div class="alert alert-warning text-sm">
        Este rol está en uso por usuarios. Primero cambia el rol de esos usuarios.
      </div>
      <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=roles">Volver</a>
    <?php else: ?>
      <form method="post">
        <button class="btn btn-danger"><i class="fas fa-trash"></i> Sí, eliminar</button>
        <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=roles">Cancelar</a>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
