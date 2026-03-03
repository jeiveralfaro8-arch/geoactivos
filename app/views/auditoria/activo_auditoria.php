<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$activoId = (int)($_GET['id'] ?? 0);

if ($activoId <= 0) {
  header('Location: ' . base_url() . '/index.php?route=activos&err=ID inválido');
  exit;
}

function table_exists($table) {
  $st = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $st->execute([':t' => $table]);
  return (bool)$st->fetchColumn();
}

if (!table_exists('audit_log')) {
  header('Location: ' . base_url() . '/index.php?route=activos&err=Auditoría no disponible');
  exit;
}

// Activo básico (aunque esté eliminado)
$st = db()->prepare("SELECT id, codigo_interno, nombre, estado FROM activos WHERE id = :id AND tenant_id = :t LIMIT 1");
$st->execute([':id' => $activoId, ':t' => $tenantId]);
$activo = $st->fetch();

if (!$activo) {
  header('Location: ' . base_url() . '/index.php?route=activos&err=Activo no encontrado');
  exit;
}

$st = db()->prepare("
  SELECT
    al.id,
    al.created_at,
    al.action,
    al.message,
    al.ip,
    al.user_agent,
    al.user_id,
    u.nombre AS user_nombre,
    u.email  AS user_email
  FROM audit_log al
  LEFT JOIN usuarios u
    ON u.id = al.user_id AND u.tenant_id = al.tenant_id
  WHERE al.tenant_id = :t
    AND al.entity = 'activo'
    AND al.entity_id = :eid
  ORDER BY al.created_at DESC, al.id DESC
  LIMIT 300
");
$st->execute([':t' => $tenantId, ':eid' => $activoId]);
$rows = $st->fetchAll();

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-stream"></i>
      Timeline · Activo <?= e((string)$activo['codigo_interno']) ?> — <?= e((string)$activo['nombre']) ?>
    </h3>
    <div class="card-tools">
      <a class="btn btn-secondary btn-sm" href="<?= e(base_url()) ?>/index.php?route=activos">
        <i class="fas fa-arrow-left"></i> Volver
      </a>
    </div>
  </div>

  <div class="card-body">
    <?php if (!$rows): ?>
      <div class="alert alert-light text-muted mb-0">
        No hay eventos de auditoría para este activo.
      </div>
    <?php else: ?>
      <div class="timeline">
        <?php foreach ($rows as $r): ?>
          <?php
            $act = (string)($r['action'] ?? '');

            $badge = 'secondary';
            if ($act === 'CREATE') $badge = 'success';
            elseif ($act === 'UPDATE') $badge = 'primary';
            elseif ($act === 'DELETE') $badge = 'warning';
            elseif ($act === 'RESTORE') $badge = 'success';
            elseif ($act === 'PURGE') $badge = 'danger';

            $when = (string)($r['created_at'] ?? '');
            $msg  = (string)($r['message'] ?? '');

            $userNombre = (string)($r['user_nombre'] ?? '');
            $userEmail  = (string)($r['user_email'] ?? '');
            $who = $userNombre !== '' ? $userNombre : 'Sistema';
            if ($userNombre !== '' && $userEmail !== '') $who .= " · {$userEmail}";

            $ip = (string)($r['ip'] ?? '');
            $ua = (string)($r['user_agent'] ?? '');
          ?>

          <div>
            <i class="fas fa-circle bg-<?= e($badge) ?>"></i>
            <div class="timeline-item" style="margin-left: 15px;">
              <span class="time text-sm"><i class="far fa-clock"></i> <?= e($when) ?></span>
              <h3 class="timeline-header">
                <span class="badge badge-<?= e($badge) ?> mr-1"><?= e($act) ?></span>
                <span class="text-muted">· <?= e($who) ?></span>
              </h3>

              <div class="timeline-body">
                <?= nl2br(e($msg)) ?>
                <div class="mt-2 text-muted text-sm">
                  <?php if ($ip !== ''): ?>
                    <span><i class="fas fa-network-wired"></i> <?= e($ip) ?></span>
                  <?php endif; ?>
                  <?php if ($ua !== ''): ?>
                    <span class="ml-2"><i class="fas fa-desktop"></i> <?= e($ua) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
