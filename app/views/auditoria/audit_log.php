<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();

function table_exists($table) {
  $st = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $st->execute([':t' => $table]);
  return (bool)$st->fetchColumn();
}

if (!table_exists('audit_log')) {
  require __DIR__ . '/../layout/header.php';
  require __DIR__ . '/../layout/sidebar.php';
  echo "
    <div class='card'>
      <div class='card-header'><h3 class='card-title'><i class='fas fa-clipboard-list'></i> Auditoría</h3></div>
      <div class='card-body'>
        <div class='alert alert-warning mb-0'>
          <i class='fas fa-exclamation-triangle mr-1'></i>
          La tabla <b>audit_log</b> no existe. Ejecuta el script SQL de auditoría.
        </div>
      </div>
    </div>
  ";
  require __DIR__ . '/../layout/footer.php';
  exit;
}

// Filtros
$action = trim((string)($_GET['action'] ?? ''));
$entity = trim((string)($_GET['entity'] ?? ''));
$q      = trim((string)($_GET['q'] ?? ''));
$days   = (int)($_GET['days'] ?? 30);
if ($days <= 0) $days = 30;
if ($days > 365) $days = 365;

$where = " WHERE al.tenant_id = :t ";
$params = [':t' => $tenantId];

if ($action !== '') {
  $where .= " AND al.action = :action ";
  $params[':action'] = $action;
}
if ($entity !== '') {
  $where .= " AND al.entity = :entity ";
  $params[':entity'] = $entity;
}
if ($q !== '') {
  $where .= " AND (al.message LIKE :q OR al.entity LIKE :q OR al.action LIKE :q OR CAST(al.entity_id AS CHAR) LIKE :q) ";
  $params[':q'] = '%' . $q . '%';
}
$where .= " AND al.created_at >= (NOW() - INTERVAL {$days} DAY) ";

$sql = "
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
  {$where}
  ORDER BY al.created_at DESC, al.id DESC
  LIMIT 300
";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$actionsList = ['CREATE','UPDATE','DELETE','RESTORE','PURGE','LOGIN','LOGOUT'];
$entitiesList = ['activo','mantenimiento','adjunto','usuario','tenant','sistema'];

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-clipboard-list"></i> Auditoría · Activity Log</h3>
    <div class="card-tools">
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url()) ?>/index.php?route=auditoria_usuario">
        <i class="fas fa-user-check"></i> Por usuario
      </a>
      <a class="btn btn-secondary btn-sm" href="<?= e(base_url()) ?>/index.php?route=dashboard">
        <i class="fas fa-home"></i> Dashboard
      </a>
    </div>
  </div>

  <div class="card-body">

    <form class="mb-3" method="get" action="<?= e(base_url()) ?>/index.php">
      <input type="hidden" name="route" value="audit_log">

      <div class="row">
        <div class="col-md-2 mb-2">
          <label class="text-sm mb-1">Acción</label>
          <select name="action" class="form-control form-control-sm">
            <option value="">Todas</option>
            <?php foreach ($actionsList as $a): ?>
              <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2 mb-2">
          <label class="text-sm mb-1">Entidad</label>
          <select name="entity" class="form-control form-control-sm">
            <option value="">Todas</option>
            <?php foreach ($entitiesList as $en): ?>
              <option value="<?= e($en) ?>" <?= $entity === $en ? 'selected' : '' ?>><?= e($en) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2 mb-2">
          <label class="text-sm mb-1">Últimos</label>
          <select name="days" class="form-control form-control-sm">
            <?php foreach ([7,15,30,60,90,180,365] as $d): ?>
              <option value="<?= (int)$d ?>" <?= $days === (int)$d ? 'selected' : '' ?>><?= (int)$d ?> días</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4 mb-2">
          <label class="text-sm mb-1">Buscar</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Texto, ID, acción, mensaje...">
        </div>

        <div class="col-md-2 mb-2 d-flex align-items-end">
          <button class="btn btn-primary btn-sm btn-block">
            <i class="fas fa-filter"></i> Filtrar
          </button>
        </div>
      </div>
    </form>

    <?php if (!$rows): ?>
      <div class="alert alert-light text-muted mb-0">
        No hay registros de auditoría para los filtros seleccionados.
      </div>
    <?php else: ?>
      <div class="timeline">
        <?php foreach ($rows as $r): ?>
          <?php
            $act = (string)($r['action'] ?? '');
            $ent = (string)($r['entity'] ?? '');
            $eid = (int)($r['entity_id'] ?? 0);

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
            $uid = (int)($r['user_id'] ?? 0);

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
                <b><?= e($ent) ?></b>
                <?php if ($eid > 0): ?>
                  <span class="text-muted">#<?= (int)$eid ?></span>
                <?php endif; ?>
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

              <div class="timeline-footer">
                <?php if ($ent === 'activo' && $eid > 0): ?>
                  <a class="btn btn-sm btn-outline-info"
                     href="<?= e(base_url()) ?>/index.php?route=activo_auditoria&id=<?= (int)$eid ?>">
                    <i class="fas fa-stream"></i> Ver timeline del activo
                  </a>
                <?php endif; ?>

                <?php if ($uid > 0): ?>
                  <a class="btn btn-sm btn-outline-secondary"
                     href="<?= e(base_url()) ?>/index.php?route=auditoria_usuario&user_id=<?= (int)$uid ?>">
                    <i class="fas fa-user"></i> Auditoría de usuario
                  </a>
                <?php endif; ?>
              </div>

            </div>
          </div>

        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
