<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
Auth::requireCan('roles.view');

$tenantId = Auth::tenantId();

$st = db()->prepare("
  SELECT id, tenant_id, nombre
  FROM roles
  WHERE tenant_id = :t
  ORDER BY nombre ASC
");
$st->execute([':t'=>$tenantId]);
$roles = $st->fetchAll();

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-user-shield"></i> Roles</h3>
    <div class="card-tools">
      <?php if (Auth::can('roles.edit')): ?>
      <a class="btn btn-sm btn-primary" href="<?= e(base_url()) ?>/index.php?route=rol_form">
        <i class="fas fa-plus"></i> Nuevo rol
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-body">
    <?php if (!$roles): ?>
      <div class="text-muted">No hay roles.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th style="width:90px">ID</th>
              <th>Nombre</th>
              <th style="width:260px" class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($roles as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><b><?= e($r['nombre']) ?></b></td>
              <td class="text-right">
                <a class="btn btn-sm btn-outline-secondary"
                   href="<?= e(base_url()) ?>/index.php?route=rol_permisos&id=<?= (int)$r['id'] ?>"
                   title="Permisos">
                  <i class="fas fa-key"></i> Permisos
                </a>

                <?php if (Auth::can('roles.edit')): ?>
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= e(base_url()) ?>/index.php?route=rol_form&id=<?= (int)$r['id'] ?>"
                   title="Editar">
                  <i class="fas fa-edit"></i>
                </a>

                <a class="btn btn-sm btn-outline-danger"
                   href="<?= e(base_url()) ?>/index.php?route=rol_delete&id=<?= (int)$r['id'] ?>"
                   title="Eliminar">
                  <i class="fas fa-trash"></i>
                </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
