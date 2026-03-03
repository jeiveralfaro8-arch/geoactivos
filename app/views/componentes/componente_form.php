<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();
$userId   = method_exists('Auth','userId') ? Auth::userId() : null;

$id       = (int)($_GET['id'] ?? 0);
$activoId = (int)($_GET['activo_id'] ?? 0);

if ($id <= 0 && $activoId <= 0) {
  http_response_code(400);
  echo "Falta activo_id";
  exit;
}

/* =========================
   Validar que el activo exista y sea del tenant
========================= */
if ($activoId > 0) {
  $st = db()->prepare("SELECT id, codigo_interno, nombre FROM activos WHERE id=:a AND tenant_id=:t LIMIT 1");
  $st->execute([':a'=>$activoId, ':t'=>$tenantId]);
  $activo = $st->fetch();
  if (!$activo) { http_response_code(404); echo "Activo no encontrado"; exit; }
} else {
  // si estamos editando, tomaremos activo_id desde componente
  $activo = null;
}

/* =========================
   Cargar componente si edita
========================= */
$data = [
  'activo_id' => $activoId,
  'nombre' => '',
  'tipo' => '',
  'marca' => '',
  'modelo' => '',
  'serial' => '',
  'cantidad' => 1,
  'estado' => 'ACTIVO',
  'observaciones' => '',
];

if ($id > 0) {
  $st = db()->prepare("
    SELECT *
    FROM activos_componentes
    WHERE id=:id AND tenant_id=:t
    LIMIT 1
  ");
  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "Componente no encontrado"; exit; }

  // si fue eliminado, no se edita desde UI (puedes cambiarlo si quieres)
  if (isset($row['eliminado']) && (int)$row['eliminado'] === 1) {
    http_response_code(404);
    echo "Componente no disponible";
    exit;
  }

  $data['activo_id'] = (int)$row['activo_id'];
  $data['nombre'] = (string)($row['nombre'] ?? '');
  $data['tipo'] = (string)($row['tipo'] ?? '');
  $data['marca'] = (string)($row['marca'] ?? '');
  $data['modelo'] = (string)($row['modelo'] ?? '');
  $data['serial'] = (string)($row['serial'] ?? '');
  $data['cantidad'] = (int)($row['cantidad'] ?? 1);
  $data['estado'] = (string)($row['estado'] ?? 'ACTIVO');
  $data['observaciones'] = (string)($row['observaciones'] ?? '');

  // cargar el activo padre para header
  $st = db()->prepare("SELECT id, codigo_interno, nombre FROM activos WHERE id=:a AND tenant_id=:t LIMIT 1");
  $st->execute([':a'=>$data['activo_id'], ':t'=>$tenantId]);
  $activo = $st->fetch();
  if (!$activo) { http_response_code(404); echo "Activo no encontrado"; exit; }
}

$error = '';

/* =========================
   Guardar
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $activoId = (int)($_POST['activo_id'] ?? 0);
  $nombre   = trim($_POST['nombre'] ?? '');
  $tipo     = trim($_POST['tipo'] ?? '');
  $marca    = trim($_POST['marca'] ?? '');
  $modelo   = trim($_POST['modelo'] ?? '');
  $serial   = trim($_POST['serial'] ?? '');
  $cantidad = (int)($_POST['cantidad'] ?? 1);
  $estado   = (string)($_POST['estado'] ?? 'ACTIVO');
  $obs      = trim($_POST['observaciones'] ?? '');

  if ($activoId <= 0) $error = 'Activo inválido.';
  elseif ($nombre === '') $error = 'El nombre del componente es obligatorio.';
  elseif ($cantidad <= 0) $error = 'Cantidad debe ser mayor a 0.';
  elseif (!in_array($estado, ['ACTIVO','EN_MANTENIMIENTO','BAJA'], true)) $error = 'Estado inválido.';

  // Validar activo tenant
  if ($error === '') {
    $st = db()->prepare("SELECT id FROM activos WHERE id=:a AND tenant_id=:t LIMIT 1");
    $st->execute([':a'=>$activoId, ':t'=>$tenantId]);
    if (!$st->fetch()) $error = 'Activo no encontrado.';
  }

  if ($error === '') {
    if ($id > 0) {
      $up = db()->prepare("
        UPDATE activos_componentes
        SET nombre=:n, tipo=:ti, marca=:ma, modelo=:mo, serial=:se,
            cantidad=:c, estado=:e, observaciones=:o
        WHERE id=:id AND tenant_id=:t
        LIMIT 1
      ");
      $up->execute([
        ':n'=>$nombre,
        ':ti'=>($tipo!==''?$tipo:null),
        ':ma'=>($marca!==''?$marca:null),
        ':mo'=>($modelo!==''?$modelo:null),
        ':se'=>($serial!==''?$serial:null),
        ':c'=>$cantidad,
        ':e'=>$estado,
        ':o'=>($obs!==''?$obs:null),
        ':id'=>$id,
        ':t'=>$tenantId,
      ]);
    } else {
      $ins = db()->prepare("
        INSERT INTO activos_componentes
          (tenant_id, activo_id, nombre, tipo, marca, modelo, serial, cantidad, estado, observaciones)
        VALUES
          (:t, :a, :n, :ti, :ma, :mo, :se, :c, :e, :o)
      ");
      $ins->execute([
        ':t'=>$tenantId,
        ':a'=>$activoId,
        ':n'=>$nombre,
        ':ti'=>($tipo!==''?$tipo:null),
        ':ma'=>($marca!==''?$marca:null),
        ':mo'=>($modelo!==''?$modelo:null),
        ':se'=>($serial!==''?$serial:null),
        ':c'=>$cantidad,
        ':e'=>$estado,
        ':o'=>($obs!==''?$obs:null),
      ]);
    }

    redirect('index.php?route=activo_detalle&id='.(int)$activoId.'&ok=Componente guardado');
  }

  // repintar
  $data['activo_id'] = $activoId;
  $data['nombre'] = $nombre;
  $data['tipo'] = $tipo;
  $data['marca'] = $marca;
  $data['modelo'] = $modelo;
  $data['serial'] = $serial;
  $data['cantidad'] = $cantidad;
  $data['estado'] = $estado;
  $data['observaciones'] = $obs;

  // recargar activo header si aplica
  $st = db()->prepare("SELECT id, codigo_interno, nombre FROM activos WHERE id=:a AND tenant_id=:t LIMIT 1");
  $st->execute([':a'=>$data['activo_id'], ':t'=>$tenantId]);
  $activo = $st->fetch();
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card card-outline card-primary">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-puzzle-piece"></i>
      <?= $id>0 ? 'Editar componente' : 'Nuevo componente' ?>
      <small class="text-muted">
        · Activo: <b><?= e($activo['codigo_interno'] ?? ('#'.(int)$data['activo_id'])) ?></b> — <?= e($activo['nombre'] ?? '') ?>
      </small>
    </h3>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="activo_id" value="<?= (int)$data['activo_id'] ?>">

      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Nombre *</label>
          <input class="form-control" name="nombre" value="<?= e($data['nombre']) ?>" required placeholder="Ej: Mouse, Teclado, RAM 16GB, SSD 512GB">
        </div>

        <div class="form-group col-md-3">
          <label>Tipo</label>
          <input class="form-control" name="tipo" value="<?= e($data['tipo']) ?>" placeholder="Ej: Periférico, Almacenamiento, Memoria">
        </div>

        <div class="form-group col-md-3">
          <label>Cantidad</label>
          <input type="number" min="1" class="form-control" name="cantidad" value="<?= (int)$data['cantidad'] ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Marca</label>
          <input class="form-control" name="marca" value="<?= e($data['marca']) ?>">
        </div>
        <div class="form-group col-md-4">
          <label>Modelo</label>
          <input class="form-control" name="modelo" value="<?= e($data['modelo']) ?>">
        </div>
        <div class="form-group col-md-4">
          <label>Serial</label>
          <input class="form-control" name="serial" value="<?= e($data['serial']) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Estado</label>
          <select class="form-control" name="estado">
            <?php foreach (['ACTIVO','EN_MANTENIMIENTO','BAJA'] as $op): ?>
              <option value="<?= e($op) ?>" <?= ($data['estado']===$op)?'selected':'' ?>><?= e($op) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-8">
          <label>Observaciones</label>
          <input class="form-control" name="observaciones" value="<?= e($data['observaciones']) ?>" placeholder="Ej: Incluye dongle USB, color negro, etc.">
        </div>
      </div>

      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=activo_detalle&id=<?= (int)$data['activo_id'] ?>">
        Volver
      </a>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
