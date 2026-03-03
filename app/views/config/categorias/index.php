<?php
require_once __DIR__ . '/../../../core/Helpers.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../config/db.php';

$tenantId = Auth::tenantId();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $id = (int)($_POST['id'] ?? 0);

  if ($id > 0) {

    // ¿cuántos activos usan esta categoría?
    $chk = db()->prepare("
      SELECT COUNT(*) c
      FROM activos
      WHERE tenant_id=:t AND categoria_id=:id
    ");
    $chk->execute([':t'=>$tenantId, ':id'=>$id]);
    $usada = (int)($chk->fetch()['c'] ?? 0);

    if ($usada > 0) {
      $msg = "No se puede eliminar: esta categoría está usada en $usada activo(s).";
    } else {
      $del = db()->prepare("
        DELETE FROM categorias_activo
        WHERE id=:id AND tenant_id=:t
      ");
      $del->execute([':id'=>$id, ':t'=>$tenantId]);
      $msg = "Categoría eliminada.";
    }
  }
}

// Listado con contador
$st = db()->prepare("
  SELECT
    c.id,
    c.nombre,
    COUNT(a.id) AS total_activos
  FROM categorias_activo c
  LEFT JOIN activos a
    ON a.tenant_id = c.tenant_id
   AND a.categoria_id = c.id
  WHERE c.tenant_id = :t
  GROUP BY c.id, c.nombre
  ORDER BY c.nombre ASC
");
$st->execute([':t'=>$tenantId]);
$rows = $st->fetchAll();

require __DIR__ . '/../../layout/header.php';
require __DIR__ . '/../../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-tags"></i> Categorías</h3>
    <div class="card-tools">
      <a class="btn btn-primary btn-sm" href="<?= e(base_url()) ?>/index.php?route=categoria_form">
        <i class="fas fa-plus"></i> Nueva
      </a>
    </div>
  </div>

  <div class="card-body">

    <?php if ($msg): ?>
      <div class="alert alert-info text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if (!$rows): ?>
      <div class="text-muted">Aún no hay categorías. Crea la primera.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover text-nowrap">
          <thead>
            <tr>
              <th style="width:90px">ID</th>
              <th>Nombre</th>
              <th style="width:160px">Activos</th>
              <th style="width:160px" class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <?php
              $total = (int)($r['total_activos'] ?? 0);
              $badgeCls = ($total > 0) ? 'badge badge-success' : 'badge badge-secondary';
            ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['nombre']) ?></td>
              <td>
                <span class="<?= $badgeCls ?>">
                  <i class="fas fa-boxes"></i> <?= $total ?>
                </span>
              </td>
              <td class="text-right">
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= e(base_url()) ?>/index.php?route=categoria_form&id=<?= (int)$r['id'] ?>"
                   title="Editar">
                  <i class="fas fa-edit"></i>
                </a>

                <?php if ($total > 0): ?>
                  <button class="btn btn-sm btn-outline-secondary" type="button"
                          title="No se puede eliminar: está en uso" disabled>
                    <i class="fas fa-lock"></i>
                  </button>
                <?php else: ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar esta categoría?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Eliminar">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-3 text-muted text-sm">
        Tip: categorías recomendadas: <b>Cómputo</b>, <b>Infraestructura</b>, <b>Biomédico</b>, <b>CCTV</b>, <b>Mobiliario</b>, <b>Climatización</b>.
      </div>

    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../layout/footer.php'; ?>
