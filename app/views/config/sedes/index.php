<?php
require_once __DIR__ . '/../../../core/Helpers.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../config/db.php';

$tenantId = Auth::tenantId();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $id = (int)($_POST['id'] ?? 0);

  if ($id > 0) {
    // No eliminar si hay áreas asociadas (FK) o activos/areas usándola indirectamente
    $chk = db()->prepare("SELECT COUNT(*) c FROM areas WHERE tenant_id=:t AND sede_id=:id");
    $chk->execute([':t'=>$tenantId, ':id'=>$id]);
    $usada = (int)$chk->fetch()['c'];

    if ($usada > 0) {
      $msg = "No se puede eliminar: la sede tiene $usada área(s) asociada(s).";
    } else {
      $del = db()->prepare("DELETE FROM sedes WHERE id=:id AND tenant_id=:t");
      $del->execute([':id'=>$id, ':t'=>$tenantId]);
      $msg = "Sede eliminada.";
    }
  }
}

$st = db()->prepare("SELECT id, nombre, direccion FROM sedes WHERE tenant_id=:t ORDER BY nombre ASC");
$st->execute([':t'=>$tenantId]);
$rows = $st->fetchAll();

require __DIR__ . '/../../layout/header.php';
require __DIR__ . '/../../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-map-marker-alt"></i> Sedes</h3>
    <div class="card-tools">
      <a class="btn btn-primary btn-sm" href="<?= e(base_url()) ?>/index.php?route=sede_form">
        <i class="fas fa-plus"></i> Nueva
      </a>
    </div>
  </div>

  <div class="card-body">
    <?php if ($msg): ?>
      <div class="alert alert-info text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if (!$rows): ?>
      <div class="text-muted">Aún no hay sedes. Crea la primera.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover text-nowrap">
          <thead>
            <tr>
              <th style="width:90px">ID</th>
              <th>Nombre</th>
              <th>Dirección</th>
              <th style="width:160px" class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['nombre']) ?></td>
              <td><?= e($r['direccion']) ?></td>
              <td class="text-right">
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= e(base_url()) ?>/index.php?route=sede_form&id=<?= (int)$r['id'] ?>" title="Editar">
                  <i class="fas fa-edit"></i>
                </a>

                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar esta sede?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit" title="Eliminar">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../layout/footer.php'; ?>
