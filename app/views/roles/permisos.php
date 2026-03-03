<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = (int) Auth::tenantId();
$rolId = (int)($_GET['id'] ?? 0);

if ($tenantId <= 0) {
  http_response_code(403);
  echo "403 - Tenant no válido.";
  exit;
}
if ($rolId <= 0) {
  http_response_code(400);
  echo "Rol inválido.";
  exit;
}

/* =========================
   1) Validar rol pertenece al tenant
========================= */
$stRol = db()->prepare("
  SELECT id, tenant_id, nombre
  FROM roles
  WHERE id = :id AND tenant_id = :t
  LIMIT 1
");
$stRol->execute([':id'=>$rolId, ':t'=>$tenantId]);
$rol = $stRol->fetch();

if (!$rol) {
  http_response_code(404);
  echo "Rol no encontrado.";
  exit;
}

/* =========================
   2) Cargar permisos del sistema (OJO: columna real = codigo)
========================= */
$stPerm = db()->prepare("
  SELECT codigo, nombre, grupo
  FROM permisos
  ORDER BY grupo ASC, nombre ASC
");
$stPerm->execute();
$permisos = $stPerm->fetchAll();

/* Agrupar por grupo */
$permisosPorGrupo = [];
foreach ($permisos as $p) {
  $g = (string)($p['grupo'] ?? 'General');
  if ($g === '') $g = 'General';
  if (!isset($permisosPorGrupo[$g])) $permisosPorGrupo[$g] = [];
  $permisosPorGrupo[$g][] = $p;
}

/* =========================
   3) Permisos actuales del rol
========================= */
$stSel = db()->prepare("
  SELECT permiso_codigo
  FROM rol_permisos
  WHERE tenant_id = :t AND rol_id = :r
");
$stSel->execute([':t'=>$tenantId, ':r'=>$rolId]);
$seleccionados = [];
foreach ($stSel->fetchAll() as $r) {
  $seleccionados[(string)$r['permiso_codigo']] = true;
}

/* =========================
   4) Guardar (POST)
========================= */
$error = '';
$okMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $codes = $_POST['permisos'] ?? [];
  if (!is_array($codes)) $codes = [];

  /* Normalizar y filtrar a permisos existentes */
  $permitidos = [];
  $mapPerm = [];
  foreach ($permisos as $p) {
    $mapPerm[(string)$p['codigo']] = true;
  }
  foreach ($codes as $c) {
    $c = trim((string)$c);
    if ($c !== '' && isset($mapPerm[$c])) $permitidos[$c] = true;
  }
  $codesFinal = array_keys($permitidos);

  try {
    db()->beginTransaction();

    /* borrar actuales */
    $del = db()->prepare("DELETE FROM rol_permisos WHERE tenant_id=:t AND rol_id=:r");
    $del->execute([':t'=>$tenantId, ':r'=>$rolId]);

    /* insertar nuevos */
    if (!empty($codesFinal)) {
      $ins = db()->prepare("
        INSERT INTO rol_permisos (tenant_id, rol_id, permiso_codigo)
        VALUES (:t, :r, :p)
      ");
      foreach ($codesFinal as $pc) {
        $ins->execute([':t'=>$tenantId, ':r'=>$rolId, ':p'=>$pc]);
      }
    }

    db()->commit();

    /* recargar seleccionados */
    $seleccionados = [];
    foreach ($codesFinal as $pc) $seleccionados[$pc] = true;

    $okMsg = 'Permisos actualizados correctamente.';
  } catch (Exception $e) {
    if (db()->inTransaction()) db()->rollBack();
    $error = 'No se pudieron guardar los permisos: ' . $e->getMessage();
  }
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-user-shield"></i>
      Permisos del rol: <b><?= e($rol['nombre']) ?></b>
    </h3>

    <div class="card-tools">
      <a class="btn btn-sm btn-secondary" href="<?= e(base_url()) ?>/index.php?route=roles">
        <i class="fas fa-arrow-left"></i> Volver
      </a>
    </div>
  </div>

  <div class="card-body">

    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($okMsg): ?>
      <div class="alert alert-success text-sm"><?= e($okMsg) ?></div>
    <?php endif; ?>

    <form method="post">

      <div class="d-flex flex-wrap mb-3">
        <button type="button" class="btn btn-sm btn-outline-secondary mr-2" onclick="toggleAll(true)">
          <i class="fas fa-check-square"></i> Marcar todo
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary mr-2" onclick="toggleAll(false)">
          <i class="fas fa-square"></i> Desmarcar todo
        </button>
      </div>

      <?php if (!$permisos): ?>
        <div class="text-muted">No hay permisos registrados en la tabla <b>permisos</b>.</div>
      <?php else: ?>

        <div class="row">
          <?php foreach ($permisosPorGrupo as $grupo => $items): ?>
            <div class="col-md-6">
              <div class="card card-outline card-primary">
                <div class="card-header">
                  <h3 class="card-title">
                    <i class="fas fa-layer-group"></i> <?= e($grupo) ?>
                  </h3>
                  <div class="card-tools">
                    <button type="button" class="btn btn-xs btn-outline-primary" onclick="toggleGroup('<?= e($grupo) ?>', true)">
                      Marcar
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleGroup('<?= e($grupo) ?>', false)">
                      Quitar
                    </button>
                  </div>
                </div>

                <div class="card-body p-2" data-group="<?= e($grupo) ?>">
                  <?php foreach ($items as $p): ?>
                    <?php
                      $cod = (string)$p['codigo'];
                      $checked = isset($seleccionados[$cod]) ? 'checked' : '';
                    ?>
                    <div class="custom-control custom-checkbox mb-2">
                      <input
                        type="checkbox"
                        class="custom-control-input perm-check"
                        id="perm_<?= e($cod) ?>"
                        name="permisos[]"
                        value="<?= e($cod) ?>"
                        <?= $checked ?>
                      >
                      <label class="custom-control-label" for="perm_<?= e($cod) ?>">
                        <b><?= e($cod) ?></b>
                        <div class="text-muted text-sm"><?= e($p['nombre']) ?></div>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      <?php endif; ?>

      <div class="mt-3">
        <button class="btn btn-primary">
          <i class="fas fa-save"></i> Guardar permisos
        </button>
        <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=roles">Cancelar</a>
      </div>

    </form>

  </div>
</div>

<script src="<?= e(base_url()) ?>/assets/js/permisos.js"></script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
