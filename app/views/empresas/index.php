<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$isSuper  = Auth::isSuperadmin();

$q = trim((string)($_GET['q'] ?? ''));
$estado = trim((string)($_GET['estado'] ?? ''));

function table_columns($table) {
  $st = db()->prepare("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = :t
  ");
  $st->execute([':t'=>$table]);
  $cols = [];
  foreach ($st->fetchAll() as $r) $cols[] = $r['column_name'];
  return $cols;
}

$cols = table_columns('tenants');
$has = function($c) use ($cols){ return in_array($c, $cols, true); };

$col_id        = 'id';
$col_nombre    = $has('nombre') ? 'nombre' : null;
$col_nit       = $has('nit') ? 'nit' : null;
$col_email     = $has('email') ? 'email' : null;
$col_telefono  = $has('telefono') ? 'telefono' : null;
$col_ciudad    = $has('ciudad') ? 'ciudad' : null;
$col_estado    = $has('estado') ? 'estado' : null;
$col_creado_en = $has('creado_en') ? 'creado_en' : ($has('created_at') ? 'created_at' : null);

$where = [];
$params = [];

if (!$isSuper) {
  $where[] = "id = :tenant";
  $params[':tenant'] = $tenantId;
}

if ($q !== '') {
  $like = "%$q%";
  $tmp = [];
  if ($col_nombre) { $tmp[] = "$col_nombre LIKE :q"; }
  if ($col_nit) { $tmp[] = "$col_nit LIKE :q"; }
  if ($col_email) { $tmp[] = "$col_email LIKE :q"; }
  if ($col_ciudad) { $tmp[] = "$col_ciudad LIKE :q"; }
  if ($tmp) {
    $where[] = "(" . implode(" OR ", $tmp) . ")";
    $params[':q'] = $like;
  }
}

if ($estado !== '' && $col_estado) {
  $where[] = "$col_estado = :estado";
  $params[':estado'] = $estado;
}

$sqlWhere = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$select = [
  "id",
  ($col_nombre ? "$col_nombre AS nombre" : "NULL AS nombre"),
  ($col_nit ? "$col_nit AS nit" : "NULL AS nit"),
  ($col_email ? "$col_email AS email" : "NULL AS email"),
  ($col_telefono ? "$col_telefono AS telefono" : "NULL AS telefono"),
  ($col_ciudad ? "$col_ciudad AS ciudad" : "NULL AS ciudad"),
  ($col_estado ? "$col_estado AS estado" : "NULL AS estado"),
  ($col_creado_en ? "$col_creado_en AS creado_en" : "NULL AS creado_en"),
];

$st = db()->prepare("
  SELECT " . implode(", ", $select) . "
  FROM tenants
  $sqlWhere
  ORDER BY id DESC
  LIMIT 500
");
$st->execute($params);
$rows = $st->fetchAll();

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-building"></i> Empresas</h3>
    <div class="card-tools">
      <?php if ($isSuper): ?>
        <a class="btn btn-sm btn-primary" href="<?= e(base_url()) ?>/index.php?route=empresa_form">
          <i class="fas fa-plus"></i> Nueva empresa
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-body">

    <form class="mb-3" method="get" action="<?= e(base_url()) ?>/index.php">
      <input type="hidden" name="route" value="empresas">
      <div class="form-row">
        <div class="col-md-6 mb-2">
          <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, NIT, email, ciudad...">
        </div>
        <div class="col-md-3 mb-2">
          <select class="form-control" name="estado">
            <option value="">-- Estado (todos) --</option>
            <option value="ACTIVO" <?= ($estado==='ACTIVO')?'selected':'' ?>>ACTIVO</option>
            <option value="INACTIVO" <?= ($estado==='INACTIVO')?'selected':'' ?>>INACTIVO</option>
          </select>
        </div>
        <div class="col-md-3 mb-2">
          <button class="btn btn-primary btn-block"><i class="fas fa-search"></i> Buscar</button>
        </div>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-hover text-nowrap">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>Empresa</th>
            <th>NIT</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Ciudad</th>
            <th>Estado</th>
            <th>Creado</th>
            <th style="width:190px" class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="9" class="text-center text-muted p-4">No hay empresas para mostrar.</td></tr>
          <?php endif; ?>

          <?php foreach ($rows as $r): ?>
            <?php
              $est = (string)($r['estado'] ?? '');
              $badge = 'secondary';
              if ($est === 'ACTIVO') $badge = 'success';
              elseif ($est === 'INACTIVO') $badge = 'danger';
            ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><b><?= e($r['nombre'] ?: '—') ?></b></td>
              <td><?= e($r['nit'] ?: '—') ?></td>
              <td><?= e($r['email'] ?: '—') ?></td>
              <td><?= e($r['telefono'] ?: '—') ?></td>
              <td><?= e($r['ciudad'] ?: '—') ?></td>
              <td><span class="badge badge-<?= e($badge) ?>"><?= e($est ?: '—') ?></span></td>
              <td><?= e($r['creado_en'] ? substr((string)$r['creado_en'],0,19) : '—') ?></td>
              <td class="text-right">

                <a class="btn btn-sm btn-outline-primary"
                   href="<?= e(base_url()) ?>/index.php?route=empresa_form&id=<?= (int)$r['id'] ?>">
                  <i class="fas fa-edit"></i>
                </a>

                <?php if ($isSuper): ?>
                  <a class="btn btn-sm btn-outline-info"
                     href="<?= e(base_url()) ?>/index.php?route=usuarios&tenant_id=<?= (int)$r['id'] ?>"
                     title="Usuarios de esta empresa">
                    <i class="fas fa-users"></i>
                  </a>

                  <!-- OPCIONAL PRO: entrar como empresa (lo hacemos después) -->
                  <a class="btn btn-sm btn-outline-secondary"
                     href="<?= e(base_url()) ?>/index.php?route=tenant_switch&id=<?= (int)$r['id'] ?>"
                     title="Entrar como esta empresa">
                    <i class="fas fa-random"></i>
                  </a>
                <?php endif; ?>

              </td>
            </tr>
          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

    <?php if (!$isSuper): ?>
      <div class="text-muted text-sm mt-2">
        * Estás viendo únicamente tu empresa (tenant).
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
