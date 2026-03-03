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
  header('Location: ' . base_url() . '/index.php?route=dashboard&err=Auditoría no disponible');
  exit;
}

// Parámetros
$userId = (int)($_GET['user_id'] ?? 0);
$q      = trim((string)($_GET['q'] ?? ''));
$days   = (int)($_GET['days'] ?? 30);
if ($days <= 0) $days = 30;
if ($days > 365) $days = 365;

// Lista de usuarios del tenant
$st = db()->prepare("
  SELECT id, nombre, email
  FROM usuarios
  WHERE tenant_id = :t
  ORDER BY nombre ASC
");
$st->execute([':t' => $tenantId]);
$users = $st->fetchAll();

// Query auditoría por usuario
$where = " WHERE al.tenant_id = :t ";
$params = [':t' => $tenantId];

if ($userId > 0) {
  $where .= " AND al.user_id = :u ";
  $params[':u'] = $userId;
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

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-user-check"></i> Auditoría por usuario</h3>
    <div class="card-tools">
      <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url()) ?>/index.php?route=audit_log">
        <i class="fas fa-clipboard-list"></i> Auditoría general
      </a>
    </div>
  </div>

  <div class="card-body">

    <form class="mb-3" method="get" action="<?= e(base_url()) ?>/index.php">
      <input type="hidden" name="route" value="auditoria_usuario">

      <div class="row">
        <div class="col-md-4 mb-2">
          <label class="text-sm mb-1">Usuario</label>
          <select name="user_id" class="form-control form-control-sm">
            <option value="0">Todos</option>
            <?php foreach ($users as $u): ?>
              <?php
                $uid = (int)$u['id'];
                $txt = trim((string)$u['nombre']) . ' · ' . trim((string)$u['email']);
              ?>
              <option value="<?= (int)$uid ?>" <?= $userId === $uid ? 'selected' : '' ?>>
                <?= e($txt) ?>
              </option>
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
        No hay registros para los filtros seleccionados.
      </div>
    <?php else: ?>

      <div class="table-responsive p-0">
        <table class="table table-hover text-nowrap">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Usuario</th>
              <th>Acción</th>
              <th>Entidad</th>
              <th>ID</th>
              <th>Mensaje</th>
              <th style="width:180px" class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php
                $act = (string)($r['action'] ?? '');
                $ent = (string)($r['entity'] ?? '');
                $eid = (int)($r['entity_id'] ?? 0);
                $when = (string)($r['created_at'] ?? '');
                $msg  = (string)($r['message'] ?? '');
                $who  = (string)($r['user_nombre'] ?? 'Sistema');

                $badge = 'secondary';
                if ($act === 'CREATE') $badge = 'success';
                elseif ($act === 'UPDATE') $badge = 'primary';
                elseif ($act === 'DELETE') $badge = 'warning';
                elseif ($act === 'RESTORE') $badge = 'success';
                elseif ($act === 'PURGE') $badge = 'danger';
              ?>
              <tr>
                <td><?= e($when) ?></td>
                <td><?= e($who) ?></td>
                <td><span class="badge badge-<?= e($badge) ?>"><?= e($act) ?></span></td>
                <td><?= e($ent) ?></td>
                <td><?= $eid > 0 ? (int)$eid : '—' ?></td>
                <td><?= e($msg) ?></td>
                <td class="text-right">
                  <?php if ($ent === 'activo' && $eid > 0): ?>
                    <a class="btn btn-sm btn-outline-info"
                       href="<?= e(base_url()) ?>/index.php?route=activo_auditoria&id=<?= (int)$eid ?>">
                      <i class="fas fa-stream"></i> Timeline activo
                    </a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
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
