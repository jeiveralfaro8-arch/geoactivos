<?php
require_once __DIR__ . '/../../../core/Helpers.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

$data = ['nombre' => ''];

if ($id > 0) {
  $st = db()->prepare("
    SELECT nombre
    FROM categorias_activo
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

  if ($nombre === '') {
    $error = "El nombre es obligatorio.";
  } else {

    // Validar duplicado por tenant (case-insensitive) sin depender de UNIQUE
    if ($id > 0) {
      $chk = db()->prepare("
        SELECT id
        FROM categorias_activo
        WHERE tenant_id=:t
          AND LOWER(nombre)=LOWER(:n)
          AND id<>:id
        LIMIT 1
      ");
      $chk->execute([':t'=>$tenantId, ':n'=>$nombre, ':id'=>$id]);
    } else {
      $chk = db()->prepare("
        SELECT id
        FROM categorias_activo
        WHERE tenant_id=:t
          AND LOWER(nombre)=LOWER(:n)
        LIMIT 1
      ");
      $chk->execute([':t'=>$tenantId, ':n'=>$nombre]);
    }

    if ($chk->fetch()) {
      $error = "Ya existe una categoría con ese nombre en este cliente.";
    } else {
      try {
        if ($id > 0) {
          $up = db()->prepare("
            UPDATE categorias_activo
            SET nombre=:n
            WHERE id=:id AND tenant_id=:t
          ");
          $up->execute([':n'=>$nombre, ':id'=>$id, ':t'=>$tenantId]);
        } else {
          $ins = db()->prepare("
            INSERT INTO categorias_activo (tenant_id, nombre)
            VALUES (:t, :n)
          ");
          $ins->execute([':t'=>$tenantId, ':n'=>$nombre]);
        }

        redirect('index.php?route=categorias');

      } catch (Exception $e) {
        $error = "No se pudo guardar. Verifica si la categoría ya existe.";
      }
    }
  }

  $data['nombre'] = $nombre;
}

require __DIR__ . '/../../layout/header.php';
require __DIR__ . '/../../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-tags"></i>
      <?= $id>0 ? 'Editar categoría' : 'Nueva categoría' ?>
    </h3>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label>Nombre *</label>
        <input class="form-control" name="nombre" value="<?= e($data['nombre']) ?>" required
               placeholder="Ej: Cómputo, Biomédico, Infraestructura, CCTV...">
      </div>

      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=categorias">Volver</a>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../../layout/footer.php'; ?>
