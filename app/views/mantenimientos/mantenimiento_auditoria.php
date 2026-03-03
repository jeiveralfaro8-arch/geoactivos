<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  header('Location: ' . base_url() . '/index.php?route=mantenimientos&err=ID inválido');
  exit;
}

function table_exists($table) {
  $st = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $st->execute([':t' => $table]);
  return (bool)$st->fetchColumn();
}

if (!table_exists('audit_log')) {
  header('Location: ' . base_url() . '/index.php?route=mantenimientos&err=Auditoría no disponible');
  exit;
}

/* =========================================================
   CONTEXTO (volver)
========================================================= */
$returnTo = (string)($_GET['return'] ?? '');
$returnId = (int)($_GET['return_id'] ?? 0);

$st = db()->prepare("
  SELECT
    m.*,
    a.codigo_interno,
    a.nombre AS activo_nombre
  FROM mantenimientos m
  INNER JOIN activos a
    ON a.id = m.activo_id AND a.tenant_id = m.tenant_id
  WHERE m.id = :id AND m.tenant_id = :t
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$m = $st->fetch();

if (!$m) {
  http_response_code(404);
  echo "Mantenimiento no encontrado";
  exit;
}

$backUrl = e(base_url()) . '/index.php?route=mantenimientos';
$activoUrl = e(base_url()) . '/index.php?route=activo_detalle&id=' . (int)$m['activo_id'];

if ($returnTo === 'activo_detalle' && $returnId > 0) {
  $chk = db()->prepare("SELECT id FROM activos WHERE id=:id AND tenant_id=:t LIMIT 1");
  $chk->execute([':id'=>$returnId, ':t'=>$tenantId]);
  if ($chk->fetch()) {
    $backUrl = e(base_url()) . '/index.php?route=activo_detalle&id=' . (int)$returnId;
  }
} else {
  $backUrl = $activoUrl;
}

/* =========================================================
   Auditoría del mantenimiento
   Nota: entity = 'mantenimiento'
========================================================= */
$st = db()->prepare("
  SELECT
    al.id,
    al.created_at,
    al.action,
    al.entity,
    al.entity_id,
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
    AND al.entity = 'mantenimiento'
    AND al.entity_id = :eid
  ORDER BY al.created_at DESC, al.id DESC
  LIMIT 400
");
$st->execute([':t' => $tenantId, ':eid' => $id]);
$rows = $st->fetchAll();

/* --------- Helpers badge --------- */
function badge_action($act){
  $b = 'secondary';
  if ($act === 'CREATE') $b = 'success';
  elseif ($act === 'UPDATE') $b = 'primary';
  elseif ($act === 'DELETE') $b = 'warning';
  elseif ($act === 'RESTORE') $b = 'success';
  elseif ($act === 'PURGE') $b = 'danger';
  elseif ($act === 'LOGIN') $b = 'info';
  elseif ($act === 'LOGOUT') $b = 'info';
  return $b;
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card card-outline card-info">
  <div class="card-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
      <div>
        <h3 class="card-title mb-0">
          <i class="fas fa-stream"></i> Timeline · Mantenimiento #<?= (int)$m['id'] ?>
        </h3>
        <div class="text-muted text-sm mt-1">
          <?= e($m['codigo_interno']) ?> · <?= e($m['activo_nombre']) ?>
        </div>
      </div>

      <div class="mt-2 mt-md-0">
        <a class="btn btn-sm btn-secondary" href="<?= e($backUrl) ?>">
          <i class="fas fa-arrow-left"></i> Volver
        </a>

        <a class="btn btn-sm btn-outline-info" href="<?= e(base_url()) ?>/index.php?route=mantenimiento_detalle&id=<?= (int)$m['id'] ?>&return=<?= e($returnTo) ?>&return_id=<?= (int)$returnId ?>">
          <i class="fas fa-tools"></i> Ver mantenimiento
        </a>

        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url()) ?>/index.php?route=audit_log">
          <i class="fas fa-clipboard-list"></i> Auditoría general
        </a>
      </div>
    </div>
  </div>

  <div class="card-body">

    <?php if (!$rows): ?>
      <div class="alert alert-light text-muted mb-0">
        No hay eventos de auditoría para este mantenimiento.
      </div>
    <?php else: ?>

      <div class="timeline">
        <?php foreach ($rows as $r): ?>
          <?php
            $act = (string)($r['action'] ?? '');
            $badge = badge_action($act);

            $when = (string)($r['created_at'] ?? '');
            $msg  = (string)($r['message'] ?? '');

            $userNombre = (string)($r['user_nombre'] ?? '');
            $userEmail  = (string)($r['user_email'] ?? '');
            $uid = (int)($r['user_id'] ?? 0);

            $who = $userNombre !== '' ? $userNombre : 'Sistema';
            if ($userNombre !== '' && $userEmail !== '') $who .= " · {$userEmail}";

            $ip = (string)($r['ip'] ?? '');
            $ua = (string)($r['user_agent'] ?? '');
          ?>

          <div>
            <i class="fas fa-circle bg-<?= e($badge) ?>"></i>

            <div class="timeline-item" style="margin-left: 15px;">
              <span class="time text-sm">
                <i class="far fa-clock"></i> <?= e($when) ?>
              </span>

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

              <?php if ($uid > 0): ?>
              <div class="timeline-footer">
                <a class="btn btn-sm btn-outline-secondary"
                   href="<?= e(base_url()) ?>/index.php?route=auditoria_usuario&user_id=<?= (int)$uid ?>">
                  <i class="fas fa-user"></i> Auditoría de este usuario
                </a>
              </div>
              <?php endif; ?>

            </div>
          </div>

        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
