<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$userId   = Auth::userId();

$id = (int)($_GET['id'] ?? 0);

/* =========================================================
   MODO HOJA DE VIDA:
   - Si viene ?activo_id=XX, preselecciona y bloquea selector
   - También permite ?return=activo_detalle&return_id=XX (opcional)
========================================================= */
$activoFromGet = (int)($_GET['activo_id'] ?? 0);
$returnTo = (string)($_GET['return'] ?? '');
$returnId = (int)($_GET['return_id'] ?? 0);

$prefillActivoId = 0;
$lockActivo = false;
$activoMini = null;

if ($activoFromGet > 0) {
  $chk = db()->prepare("
    SELECT id, codigo_interno, nombre
    FROM activos
    WHERE id=:id AND tenant_id=:t
    LIMIT 1
  ");
  $chk->execute([':id'=>$activoFromGet, ':t'=>$tenantId]);
  $activoMini = $chk->fetch();
  if ($activoMini) {
    $prefillActivoId = (int)$activoMini['id'];
    $lockActivo = true;
  }
}

/* =========================================================
   Activos (selector)
========================================================= */
$actSt = db()->prepare("
  SELECT id, codigo_interno, nombre
  FROM activos
  WHERE tenant_id = :t
  ORDER BY nombre ASC
  LIMIT 1000
");
$actSt->execute([':t'=>$tenantId]);
$activos = $actSt->fetchAll();

/* =========================================================
   Técnicos (usuarios del tenant) para selector
   - usamos campos reales: nombre, cargo, tarjeta_profesional, firma_habilitada, estado
========================================================= */
$tecSt = db()->prepare("
  SELECT
    id, nombre, cargo, tarjeta_profesional, firma_habilitada, estado
  FROM usuarios
  WHERE tenant_id = :t
  ORDER BY nombre ASC
");
$tecSt->execute([':t'=>$tenantId]);
$tecnicos = $tecSt->fetchAll();

/* =========================================================
   Defaults
========================================================= */
$data = [
  'activo_id' => ($prefillActivoId > 0 ? $prefillActivoId : 0),
  'tipo' => 'PREVENTIVO',
  'estado' => 'PROGRAMADO',
  'fecha_programada' => '',
  'fecha_inicio' => '',
  'fecha_fin' => '',
  'prioridad' => 'MEDIA',
  'falla_reportada' => '',
  'diagnostico' => '',
  'actividades' => '',
  'recomendaciones' => '',
  'costo_mano_obra' => '0.00',
  'costo_repuestos' => '0.00',

  // NUEVO: técnico/firma
  'firma_tecnico_id' => 0,
  'firma_hash' => '',
  'tecnico_nombre' => '',
  'tecnico_cargo' => '',
  'tecnico_tarjeta_prof' => '',
];

/* =========================================================
   Helpers: auditoría (robusta)
========================================================= */
function table_exists($table) {
  $st = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $st->execute([':t' => $table]);
  return (bool)$st->fetchColumn();
}
function table_columns($table) {
  $st = db()->prepare("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = :t
  ");
  $st->execute([':t' => $table]);
  $cols = [];
  foreach ($st->fetchAll() as $r) $cols[] = $r['column_name'];
  return $cols;
}
function client_ip() {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    return trim($parts[0]);
  }
  return $_SERVER['REMOTE_ADDR'] ?? '';
}

function audit_write($tenantId, $userId, $action, $entity, $entityId, $message) {
  if (!table_exists('audit_log')) return;

  $cols = table_columns('audit_log');

  $colTenant = in_array('tenant_id', $cols, true) ? 'tenant_id' : null;
  $colUser   = in_array('user_id', $cols, true) ? 'user_id' : null;
  $colAction = in_array('action', $cols, true) ? 'action' : (in_array('evento', $cols, true) ? 'evento' : null);
  $colEntity = in_array('entity', $cols, true) ? 'entity' : (in_array('tabla', $cols, true) ? 'tabla' : null);
  $colEid    = in_array('entity_id', $cols, true) ? 'entity_id' : (in_array('registro_id', $cols, true) ? 'registro_id' : null);
  $colMsg    = in_array('message', $cols, true) ? 'message' : (in_array('descripcion', $cols, true) ? 'descripcion' : null);
  $colIp     = in_array('ip', $cols, true) ? 'ip' : null;
  $colUa     = in_array('user_agent', $cols, true) ? 'user_agent' : null;
  $colWhen   = in_array('created_at', $cols, true) ? 'created_at' : (in_array('creado_en', $cols, true) ? 'creado_en' : null);

  if (!$colTenant || !$colAction || !$colEntity || !$colEid || !$colMsg) return;

  $fields = [];
  $params = [];

  $fields[] = "`$colTenant`"; $params[":t"] = (int)$tenantId;
  if ($colUser) { $fields[] = "`$colUser`"; $params[":u"] = (int)$userId; }
  $fields[] = "`$colAction`"; $params[":a"] = (string)$action;
  $fields[] = "`$colEntity`"; $params[":e"] = (string)$entity;
  $fields[] = "`$colEid`";    $params[":i"] = (int)$entityId;
  $fields[] = "`$colMsg`";    $params[":m"] = (string)$message;

  if ($colIp) { $fields[] = "`$colIp`"; $params[":ip"] = (string)client_ip(); }
  if ($colUa) { $fields[] = "`$colUa`"; $params[":ua"] = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255); }

  if ($colWhen) {
    $fields[] = "`$colWhen`";
    $params[":w"] = date('Y-m-d H:i:s');
  }

  $placeholders = [];
  foreach ($fields as $f) {
    if ($f === "`$colTenant`") $placeholders[] = ":t";
    elseif ($colUser && $f === "`$colUser`") $placeholders[] = ":u";
    elseif ($f === "`$colAction`") $placeholders[] = ":a";
    elseif ($f === "`$colEntity`") $placeholders[] = ":e";
    elseif ($f === "`$colEid`") $placeholders[] = ":i";
    elseif ($f === "`$colMsg`") $placeholders[] = ":m";
    elseif ($colIp && $f === "`$colIp`") $placeholders[] = ":ip";
    elseif ($colUa && $f === "`$colUa`") $placeholders[] = ":ua";
    elseif ($colWhen && $f === "`$colWhen`") $placeholders[] = ":w";
  }

  $sql = "INSERT INTO audit_log (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
  try {
    $st = db()->prepare($sql);
    $st->execute($params);
  } catch (Exception $e) {
    // no romper UX
  }
}

/* =========================================================
   Cargar en edición (incluye técnico/firma)
========================================================= */
$oldRow = null;

if ($id > 0) {
  $st = db()->prepare("
    SELECT
      activo_id, tipo, estado, fecha_programada, fecha_inicio, fecha_fin,
      prioridad, falla_reportada, diagnostico, actividades, recomendaciones,
      costo_mano_obra, costo_repuestos,
      firma_tecnico_id, firma_hash, tecnico_nombre, tecnico_cargo, tecnico_tarjeta_prof
    FROM mantenimientos
    WHERE id=:id AND tenant_id=:t
    LIMIT 1
  ");
  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "No encontrado"; exit; }

  $oldRow = $row;
  $data = array_merge($data, $row);

  if (!empty($data['fecha_programada'])) $data['fecha_programada'] = substr((string)$data['fecha_programada'], 0, 10);
  if (!empty($data['fecha_inicio'])) $data['fecha_inicio'] = substr((string)$data['fecha_inicio'], 0, 16);
  if (!empty($data['fecha_fin'])) $data['fecha_fin'] = substr((string)$data['fecha_fin'], 0, 16);

  $data['costo_mano_obra'] = (string)$data['costo_mano_obra'];
  $data['costo_repuestos'] = (string)$data['costo_repuestos'];

  $data['firma_tecnico_id'] = (int)($data['firma_tecnico_id'] ?? 0);
  $data['firma_hash'] = (string)($data['firma_hash'] ?? '');
}

/* =========================================================
   Resolver retorno (Volver / Guardar)
========================================================= */
$backUrl = e(base_url()) . '/index.php?route=mantenimientos';
$afterSaveUrl = $backUrl;

if ($prefillActivoId > 0) {
  $backUrl = e(base_url()) . '/index.php?route=activo_detalle&id=' . (int)$prefillActivoId;
  $afterSaveUrl = $backUrl;
}

if ($returnTo === 'activo_detalle' && $returnId > 0) {
  $chk2 = db()->prepare("SELECT id FROM activos WHERE id=:id AND tenant_id=:t LIMIT 1");
  $chk2->execute([':id'=>$returnId, ':t'=>$tenantId]);
  if ($chk2->fetch()) {
    $backUrl = e(base_url()) . '/index.php?route=activo_detalle&id=' . (int)$returnId;
    $afterSaveUrl = $backUrl;
  }
}

/* =========================================================
   SUBIDA FIRMA (Opción B):
   - archivo PNG/JPG
   - guarda firma_hash
   - NO usa tabla extra (se guarda en mantenimientos)
========================================================= */
function is_img_mime($mime) {
  return in_array($mime, ['image/png','image/jpeg','image/jpg','image/webp'], true);
}

/* Directorio para firmas (fuera de public si puedes) */
function firma_dir($tenantId) {
  $base = __DIR__ . '/../../storage/firmas'; // ajusta si tu proyecto maneja otra ruta
  if (!is_dir($base)) @mkdir($base, 0775, true);
  $dir = $base . '/t' . (int)$tenantId;
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir;
}

/* =========================================================
   POST (guardar)
========================================================= */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $activoId = (int)($_POST['activo_id'] ?? 0);
  if ($lockActivo && $prefillActivoId > 0) $activoId = $prefillActivoId;

  $tipo = $_POST['tipo'] ?? 'PREVENTIVO';
  $estado = $_POST['estado'] ?? 'PROGRAMADO';
  $prioridad = $_POST['prioridad'] ?? 'MEDIA';

  $fechaProg = trim($_POST['fecha_programada'] ?? '');
  $fechaIni  = trim($_POST['fecha_inicio'] ?? '');
  $fechaFin  = trim($_POST['fecha_fin'] ?? '');

  $falla = trim($_POST['falla_reportada'] ?? '');
  $diag  = trim($_POST['diagnostico'] ?? '');
  $acts  = trim($_POST['actividades'] ?? '');
  $reco  = trim($_POST['recomendaciones'] ?? '');

  $cmo = trim($_POST['costo_mano_obra'] ?? '0');
  $cre = trim($_POST['costo_repuestos'] ?? '0');

  // NUEVO: técnico seleccionado
  $tecnicoId = (int)($_POST['firma_tecnico_id'] ?? 0);

  if ($activoId <= 0) {
    $error = 'Debes seleccionar un activo.';
  } else {
    $chk = db()->prepare("SELECT id FROM activos WHERE id=:id AND tenant_id=:t LIMIT 1");
    $chk->execute([':id'=>$activoId, ':t'=>$tenantId]);
    if (!$chk->fetch()) $error = 'Activo inválido para este tenant.';
  }

  $fechaProgDb = ($fechaProg !== '' ? $fechaProg : null);
  $fechaIniDb  = ($fechaIni !== '' ? $fechaIni : null);
  $fechaFinDb  = ($fechaFin !== '' ? $fechaFin : null);

  $cmoDb = is_numeric($cmo) ? $cmo : '0.00';
  $creDb = is_numeric($cre) ? $cre : '0.00';

  $tiposOk = ['PREVENTIVO','CORRECTIVO','PREDICTIVO'];
  $estOk   = ['PROGRAMADO','EN_PROCESO','CERRADO','ANULADO'];
  $prioOk  = ['BAJA','MEDIA','ALTA','CRITICA'];

  if ($error === '' && !in_array($tipo, $tiposOk, true)) $error = 'Tipo inválido.';
  if ($error === '' && !in_array($estado, $estOk, true)) $error = 'Estado inválido.';
  if ($error === '' && !in_array($prioridad, $prioOk, true)) $error = 'Prioridad inválida.';

  // Validar técnico (si se selecciona)
  $tecRow = null;
  if ($error === '' && $tecnicoId > 0) {
    $tq = db()->prepare("
      SELECT id, nombre, cargo, tarjeta_profesional, firma_habilitada, estado
      FROM usuarios
      WHERE id=:id AND tenant_id=:t
      LIMIT 1
    ");
    $tq->execute([':id'=>$tecnicoId, ':t'=>$tenantId]);
    $tecRow = $tq->fetch();
    if (!$tecRow) $error = 'Técnico inválido para este tenant.';
    elseif ((string)$tecRow['estado'] !== 'ACTIVO') $error = 'El técnico seleccionado está INACTIVO.';
  }

  // Preparar firma (si viene archivo)
  $firmaHashNuevo = null;
  if ($error === '' && isset($_FILES['firma_file']) && is_array($_FILES['firma_file']) && (int)$_FILES['firma_file']['error'] !== UPLOAD_ERR_NO_FILE) {

    if ($tecnicoId <= 0) {
      $error = 'Para adjuntar firma primero selecciona un técnico.';
    } else {
      if ((int)$_FILES['firma_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error al subir la firma.';
      } else {
        $tmpName = (string)$_FILES['firma_file']['tmp_name'];
        $mime = '';
        if (function_exists('mime_content_type')) $mime = (string)@mime_content_type($tmpName);

        if ($mime === '' || !is_img_mime($mime)) {
          $error = 'La firma debe ser una imagen (PNG/JPG/WEBP).';
        } else {
          $raw = @file_get_contents($tmpName);
          if ($raw === false) {
            $error = 'No se pudo leer la firma subida.';
          } else {
            $hash = hash('sha256', $raw);
            $firmaHashNuevo = $hash;

            $dir = firma_dir($tenantId);
            $ext = 'png';
            if ($mime === 'image/jpeg' || $mime === 'image/jpg') $ext = 'jpg';
            if ($mime === 'image/webp') $ext = 'webp';

            $fname = 'mant_' . ($id > 0 ? $id : 'new') . '_tec_' . $tecnicoId . '_' . $hash . '.' . $ext;
            $dest = $dir . '/' . $fname;

            // mover
            if (!@move_uploaded_file($tmpName, $dest)) {
              // fallback
              if (@file_put_contents($dest, $raw) === false) {
                $error = 'No se pudo guardar la firma en el servidor.';
              }
            }
          }
        }
      }
    }
  }

  if ($error === '') {

    // snapshot técnico (aunque no suba firma)
    $tecNombre = $tecRow ? (string)($tecRow['nombre'] ?? '') : '';
    $tecCargo  = $tecRow ? (string)($tecRow['cargo'] ?? '') : '';
    $tecTp     = $tecRow ? (string)($tecRow['tarjeta_profesional'] ?? '') : '';

    // si no hay técnico seleccionado, limpiar snapshot
    if ($tecnicoId <= 0) {
      $tecNombre = '';
      $tecCargo  = '';
      $tecTp     = '';
    }

    if ($id > 0) {

      if (!$oldRow) {
        $stOld = db()->prepare("
          SELECT
            activo_id, tipo, estado, fecha_programada, fecha_inicio, fecha_fin,
            prioridad, falla_reportada, diagnostico, actividades, recomendaciones,
            costo_mano_obra, costo_repuestos,
            firma_tecnico_id, firma_hash, tecnico_nombre, tecnico_cargo, tecnico_tarjeta_prof
          FROM mantenimientos
          WHERE id=:id AND tenant_id=:t
          LIMIT 1
        ");
        $stOld->execute([':id'=>$id, ':t'=>$tenantId]);
        $oldRow = $stOld->fetch();
      }

      // si no subió firma nueva, conservar la existente
      $firmaHashToSave = ($firmaHashNuevo !== null) ? $firmaHashNuevo : (string)($oldRow['firma_hash'] ?? '');

      $up = db()->prepare("
        UPDATE mantenimientos
        SET activo_id=:a, tipo=:tipo, estado=:e,
            fecha_programada=:fp, fecha_inicio=:fi, fecha_fin=:ff,
            prioridad=:pr,
            falla_reportada=:fa, diagnostico=:di, actividades=:ac, recomendaciones=:re,
            costo_mano_obra=:cmo, costo_repuestos=:cre,
            firma_tecnico_id=:tid, firma_hash=:fh,
            tecnico_nombre=:tn, tecnico_cargo=:tc, tecnico_tarjeta_prof=:ttp
        WHERE id=:id AND tenant_id=:t
      ");
      $up->execute([
        ':a'=>$activoId, ':tipo'=>$tipo, ':e'=>$estado,
        ':fp'=>$fechaProgDb, ':fi'=>$fechaIniDb, ':ff'=>$fechaFinDb,
        ':pr'=>$prioridad,
        ':fa'=>$falla ?: null,
        ':di'=>$diag ?: null,
        ':ac'=>$acts ?: null,
        ':re'=>$reco ?: null,
        ':cmo'=>$cmoDb, ':cre'=>$creDb,
        ':tid'=>($tecnicoId > 0 ? $tecnicoId : null),
        ':fh'=>$firmaHashToSave ?: null,
        ':tn'=>$tecNombre ?: null,
        ':tc'=>$tecCargo ?: null,
        ':ttp'=>$tecTp ?: null,
        ':id'=>$id, ':t'=>$tenantId
      ]);

      // auditoría resumida
      $msg = "Actualización de mantenimiento #{$id}.";
      if ($firmaHashNuevo !== null) $msg .= " (Firma actualizada)";
      audit_write($tenantId, $userId, 'UPDATE', 'mantenimiento', $id, $msg);

    } else {

      $ins = db()->prepare("
        INSERT INTO mantenimientos
          (tenant_id, activo_id, tipo, estado, fecha_programada, fecha_inicio, fecha_fin,
           prioridad, falla_reportada, diagnostico, actividades, recomendaciones,
           costo_mano_obra, costo_repuestos, creado_por,
           firma_tecnico_id, firma_hash, tecnico_nombre, tecnico_cargo, tecnico_tarjeta_prof)
        VALUES
          (:t, :a, :tipo, :e, :fp, :fi, :ff,
           :pr, :fa, :di, :ac, :re,
           :cmo, :cre, :cp,
           :tid, :fh, :tn, :tc, :ttp)
      ");

      $ins->execute([
        ':t'=>$tenantId, ':a'=>$activoId, ':tipo'=>$tipo, ':e'=>$estado,
        ':fp'=>$fechaProgDb, ':fi'=>$fechaIniDb, ':ff'=>$fechaFinDb,
        ':pr'=>$prioridad,
        ':fa'=>$falla ?: null,
        ':di'=>$diag ?: null,
        ':ac'=>$acts ?: null,
        ':re'=>$reco ?: null,
        ':cmo'=>$cmoDb, ':cre'=>$creDb,
        ':cp'=>$userId > 0 ? $userId : null,
        ':tid'=>($tecnicoId > 0 ? $tecnicoId : null),
        ':fh'=>($firmaHashNuevo !== null ? $firmaHashNuevo : null),
        ':tn'=>$tecNombre ?: null,
        ':tc'=>$tecCargo ?: null,
        ':ttp'=>$tecTp ?: null
      ]);

      $newId = (int)db()->lastInsertId();
      $id = $newId;

      audit_write($tenantId, $userId, 'CREATE', 'mantenimiento', $newId, "Creación de mantenimiento #{$newId} (Activo ID {$activoId}).");

      // Si subió firma antes de tener ID, renombrar el archivo (opcional)
      // No es obligatorio porque lo guardamos como mant_new_... y con hash queda único.
    }

    redirect(str_replace(e(base_url()).'/', '', $afterSaveUrl));
  }

  // repintar
  $data = [
    'activo_id'=>$activoId,
    'tipo'=>$tipo,
    'estado'=>$estado,
    'fecha_programada'=>$fechaProg,
    'fecha_inicio'=>$fechaIni,
    'fecha_fin'=>$fechaFin,
    'prioridad'=>$prioridad,
    'falla_reportada'=>$falla,
    'diagnostico'=>$diag,
    'actividades'=>$acts,
    'recomendaciones'=>$reco,
    'costo_mano_obra'=>$cmoDb,
    'costo_repuestos'=>$creDb,
    'firma_tecnico_id'=>$tecnicoId,
    'firma_hash'=> (string)($data['firma_hash'] ?? ''),
    'tecnico_nombre'=> (string)($data['tecnico_nombre'] ?? ''),
    'tecnico_cargo'=> (string)($data['tecnico_cargo'] ?? ''),
    'tecnico_tarjeta_prof'=> (string)($data['tecnico_tarjeta_prof'] ?? ''),
  ];
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<style>
.sig-box{
  border:1px dashed rgba(0,0,0,.2);
  border-radius:.5rem;
  padding:.75rem;
  background:#fafafa;
}
.sig-preview{
  display:inline-block;
  border:1px solid rgba(0,0,0,.15);
  border-radius:.35rem;
  padding:.25rem;
  background:#fff;
  max-width:100%;
}
</style>

<div class="card card-outline card-warning">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-tools"></i> <?= ($id>0?'Editar':'Nuevo') ?> mantenimiento</h3>
    <div class="card-tools">
      <a class="btn btn-sm btn-secondary" href="<?= e($backUrl) ?>">
        <i class="fas fa-arrow-left"></i> Volver
      </a>
    </div>
  </div>

  <div class="card-body">
    <?php if ($prefillActivoId > 0 && $activoMini): ?>
      <div class="alert alert-light border text-sm">
        <i class="fas fa-link text-warning"></i>
        Creando mantenimiento desde la hoja de vida de:
        <b><?= e($activoMini['codigo_interno']) ?></b> · <?= e($activoMini['nombre']) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

      <?php if ($lockActivo && $prefillActivoId > 0): ?>
        <input type="hidden" name="activo_id" value="<?= (int)$prefillActivoId ?>">
      <?php endif; ?>

      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Activo *</label>
          <select class="form-control" name="activo_id" required <?= ($lockActivo ? 'disabled' : '') ?>>
            <option value="">-- Selecciona --</option>
            <?php foreach ($activos as $a): ?>
              <option value="<?= (int)$a['id'] ?>" <?= ((int)$data['activo_id']===(int)$a['id'])?'selected':'' ?>>
                <?= e($a['codigo_interno']) ?> · <?= e($a['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($lockActivo): ?>
            <small class="text-muted">Este mantenimiento está asociado a la hoja de vida del activo y no se puede cambiar aquí.</small>
          <?php endif; ?>
        </div>

        <div class="form-group col-md-3">
          <label>Tipo</label>
          <select class="form-control" name="tipo">
            <?php foreach (['PREVENTIVO','CORRECTIVO','PREDICTIVO'] as $op): ?>
              <option value="<?= e($op) ?>" <?= ($data['tipo']===$op)?'selected':'' ?>><?= e($op) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group col-md-3">
          <label>Estado</label>
          <select class="form-control" name="estado">
            <?php foreach (['PROGRAMADO','EN_PROCESO','CERRADO','ANULADO'] as $op): ?>
              <option value="<?= e($op) ?>" <?= ($data['estado']===$op)?'selected':'' ?>><?= e($op) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-3">
          <label>Fecha programada</label>
          <input type="date" class="form-control" name="fecha_programada" value="<?= e($data['fecha_programada']) ?>">
        </div>
        <div class="form-group col-md-3">
          <label>Fecha inicio</label>
          <input type="datetime-local" class="form-control" name="fecha_inicio" value="<?= e($data['fecha_inicio']) ?>">
        </div>
        <div class="form-group col-md-3">
          <label>Fecha fin</label>
          <input type="datetime-local" class="form-control" name="fecha_fin" value="<?= e($data['fecha_fin']) ?>">
        </div>
        <div class="form-group col-md-3">
          <label>Prioridad</label>
          <select class="form-control" name="prioridad">
            <?php foreach (['BAJA','MEDIA','ALTA','CRITICA'] as $op): ?>
              <option value="<?= e($op) ?>" <?= ($data['prioridad']===$op)?'selected':'' ?>><?= e($op) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Falla reportada</label>
        <textarea class="form-control" name="falla_reportada" rows="2"><?= e($data['falla_reportada']) ?></textarea>
      </div>

      <div class="form-group">
        <label>Diagnóstico</label>
        <textarea class="form-control" name="diagnostico" rows="2"><?= e($data['diagnostico']) ?></textarea>
      </div>

      <div class="form-group">
        <label>Actividades</label>
        <textarea class="form-control" name="actividades" rows="3"><?= e($data['actividades']) ?></textarea>
      </div>

      <div class="form-group">
        <label>Recomendaciones</label>
        <textarea class="form-control" name="recomendaciones" rows="2"><?= e($data['recomendaciones']) ?></textarea>
      </div>

      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Costo mano de obra</label>
          <input class="form-control" name="costo_mano_obra" value="<?= e($data['costo_mano_obra']) ?>" placeholder="0.00">
        </div>
        <div class="form-group col-md-6">
          <label>Costo repuestos</label>
          <input class="form-control" name="costo_repuestos" value="<?= e($data['costo_repuestos']) ?>" placeholder="0.00">
        </div>
      </div>

      <!-- ======================== BLOQUE TÉCNICO + FIRMA ======================== -->
      <div class="sig-box mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
          <div>
            <b><i class="fas fa-user-check"></i> Técnico responsable</b>
            <div class="text-muted text-sm">Se guarda snapshot: nombre, cargo y tarjeta profesional en el mantenimiento.</div>
          </div>
          <div class="text-muted text-sm">
            (Opcional) Adjunta la firma digital del técnico
          </div>
        </div>

        <div class="form-row mt-2">
          <div class="form-group col-md-6">
            <label>Técnico</label>
            <select class="form-control" name="firma_tecnico_id">
              <option value="0">-- Sin asignar --</option>
              <?php foreach ($tecnicos as $t): ?>
                <?php
                  $tid = (int)$t['id'];
                  $nom = (string)$t['nombre'];
                  $car = (string)($t['cargo'] ?? '');
                  $tp  = (string)($t['tarjeta_profesional'] ?? '');
                  $stt = (string)($t['estado'] ?? 'ACTIVO');
                  $hab = (int)($t['firma_habilitada'] ?? 0);

                  $label = $nom;
                  if ($car !== '') $label .= " · " . $car;
                  if ($tp !== '')  $label .= " · TP " . $tp;
                  if ($stt !== 'ACTIVO') $label .= " (INACTIVO)";
                  if ($hab !== 1) $label .= " (Firma NO habilitada)";
                ?>
                <option value="<?= $tid ?>" <?= ((int)$data['firma_tecnico_id']===$tid ? 'selected' : '') ?>>
                  <?= e($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Recomendado: habilitar firma en el usuario (firma_habilitada=1).</small>
          </div>

          <div class="form-group col-md-6">
            <label>Firma (PNG/JPG/WEBP)</label>
            <input type="file" class="form-control" name="firma_file" accept="image/*">
            <?php if (!empty($data['firma_hash'])): ?>
              <small class="text-success">
                <i class="fas fa-check-circle"></i>
                Firma registrada (hash): <?= e(substr((string)$data['firma_hash'], 0, 16)) ?>...
              </small>
            <?php else: ?>
              <small class="text-muted">Si adjuntas una firma nueva, se reemplaza la anterior.</small>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <!-- ====================== FIN BLOQUE TÉCNICO + FIRMA ====================== -->

      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="<?= e($backUrl) ?>">Cancelar</a>

    </form>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
