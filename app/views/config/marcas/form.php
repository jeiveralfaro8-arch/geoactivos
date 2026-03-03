<?php
require_once __DIR__ . '/../../../core/Helpers.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

$data = ['nombre' => ''];
if ($id > 0) {
  $st = db()->prepare("SELECT nombre FROM marcas WHERE id=:id AND tenant_id=:t LIMIT 1");
  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "No encontrado"; exit; }
  $data = $row;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre'] ?? '');

  if ($nombre === '') {
    $error = "El nombre es obligatorio.";
  } else {
    try {
      if ($id > 0) {
        $up = db()->prepare("UPDATE marcas SET nombre=:n WHERE id=:id AND tenant_id=:t");
        $up->execute([':n'=>$nombre, ':id'=>$id, ':t'=>$tenantId]);
      } else {
        $ins = db()->prepare("INSERT INTO marcas (tenant_id, nombre) VALUES (:t, :n)");
        $ins->execute([':t'=>$tenantId, ':n'=>$nombre]);
      }
      redirect('index.php?route=marcas');
    } catch (Exception $e) {
      $error = "No se pudo guardar. Verifica si la marca ya existe.";
    }
  }
}

require __DIR__ . '/../../layout/header.php';
require __DIR__ . '/../../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= $id>0 ? 'Editar marca' : 'Nueva marca' ?></h3>
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

      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=marcas">Volver</a>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../layout/footer.php'; ?>
