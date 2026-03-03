<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$q = trim($_GET['q'] ?? '');

// detectar si existe pass_hash
$hasPass = false;
try {
  $c = db()->prepare("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'usuarios'
      AND column_name IN ('pass_hash','password_hash','password')
    LIMIT 1
  ");
  $c->execute();
  $hasPass = (bool)$c->fetch();
} catch(Exception $e) { $hasPass = false; }

$sql = "
  SELECT u.id, u.tenant_id, t.nombre AS tenant_nombre, u.nombre, u.email, u.estado, r.nombre AS rol, u.creado_en
  FROM usuarios u
  INNER JOIN roles r ON r.id = u.rol_id
  INNER JOIN tenants t ON t.id = u.tenant_id
";

$params = [];
if ($q !== '') {
  $sql .= " WHERE (u.nombre LIKE :q OR u.email LIKE :q OR t.nombre LIKE :q OR r.nombre LIKE :q) ";
  $params[':q'] = '%'.$q.'%';
}

$sql .= " ORDER BY u.id DESC LIMIT 500";

$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-users"></i> Usuarios</h3>
    <div class="card-tools">
      <a class="btn btn-sm btn-primary" href="<?= e(base_url()) ?>/index.php?route=usuario_form">
        <i class="fas fa-plus"></i> Nuevo
      </a>
    </div>
  </div>

  <div class="card-body">

    <?php if (!$hasPass): ?>
      <div class="alert alert-warning text-sm">
        No se detectó columna de contraseña en la tabla <b>usuarios</b>.
        El módulo funcionará, pero no podrá guardar passwords hasta que exista un campo tipo password o password_hash.
        <br>
        <span class="text-muted">Tip: En tu caso ya existe <b>pass_hash</b>. Si ves este mensaje, revisa que la DB seleccionada sea la correcta.</span>
      </div>
    <?php endif; ?>

    <form class="form-inline mb-3" method="get" action="<?= e(base_url()) ?>/index.php">
      <input type="hidden" name="route" value="usuarios">
      <input class="form-control mr-2" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, email, empresa, rol...">
      <button class="btn btn-outline-primary"><i class="fas fa-search"></i> Buscar</button>
    </form>

    <div class="table-responsive">
      <table class="table table-hover text-nowrap">
        <thead>
          <tr>
            <th>ID</th>
            <th>Empresa</th>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Creado</th>
            <th class="text-right" style="width:160px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted p-4">No hay usuarios.</td></tr>
          <?php endif; ?>

          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['tenant_nombre']) ?></td>
              <td><?= e($r['nombre']) ?></td>
              <td><?= e($r['email']) ?></td>
              <td><?= e($r['rol']) ?></td>
              <td>
                <?php $b = ($r['estado']==='ACTIVO')?'success':'secondary'; ?>
                <span class="badge badge-<?= $b ?>"><?= e($r['estado']) ?></span>
              </td>
              <td><?= e(substr((string)$r['creado_en'],0,19)) ?></td>
              <td class="text-right">
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= e(base_url()) ?>/index.php?route=usuario_form&id=<?= (int)$r['id'] ?>"
                   title="Editar">
                  <i class="fas fa-edit"></i>
                </a>

                <a class="btn btn-sm btn-outline-danger"
                   href="<?= e(base_url()) ?>/index.php?route=usuario_delete&id=<?= (int)$r['id'] ?>"
                   title="Eliminar">
                  <i class="fas fa-trash"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

    <div class="text-muted text-sm mt-2">Mostrando máximo 500 registros.</div>

  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
