<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
Auth::requirePerm('auditoria.view');

$tenantId = Auth::tenantId();
$activoId = (int)($_GET['id'] ?? 0);

if ($activoId <= 0) {
  echo "<div class='alert alert-danger m-4'>Activo no válido</div>";
  return;
}

$st = db()->prepare("
  SELECT 
    a.created_at,
    a.accion,
    a.modulo,
    a.descripcion,
    u.nombre AS usuario
  FROM audit_log a
  LEFT JOIN usuarios u
    ON u.id = a.user_id
   AND u.tenant_id = a.tenant_id
  WHERE a.tenant_id = :t
    AND a.modulo = 'activos'
    AND a.ref_id = :id
  ORDER BY a.created_at DESC
");
$st->execute([
  ':t'  => $tenantId,
  ':id' => $activoId
]);

$rows = $st->fetchAll();

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <h1>
        <i class="fas fa-clipboard-list"></i>
        Auditoría del Activo
      </h1>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <?php if (!$rows): ?>
        <div class="alert alert-info">
          No hay eventos registrados para este activo.
        </div>
      <?php else: ?>

      <ul class="timeline">

        <?php foreach ($rows as $r): ?>
        <li>
          <i class="fas fa-history bg-info"></i>
          <div class="timeline-item">
            <span class="time">
              <i class="far fa-clock"></i>
              <?= e(date('Y-m-d H:i', strtotime($r['created_at']))) ?>
            </span>

            <h3 class="timeline-header">
              <?= e($r['accion']) ?>
              <small class="text-muted">
                por <?= e($r['usuario'] ?: 'Sistema') ?>
              </small>
            </h3>

            <div class="timeline-body">
              <?= nl2br(e($r['descripcion'])) ?>
            </div>
          </div>
        </li>
        <?php endforeach; ?>

        <li>
          <i class="far fa-clock bg-gray"></i>
        </li>

      </ul>
      <?php endif; ?>

    </div>
  </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
