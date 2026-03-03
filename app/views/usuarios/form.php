<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$currentUser = $_SESSION['user'] ?? [];
$currentTenantId = (int)Auth::tenantId();

$id = (int)($_GET['id'] ?? 0);

/* =========================================================
   SUPERADMIN (fallback seguro)
   - Si tienes rol_nombre en sesión, úsalo
   - Si no, intenta con campos legacy
========================================================= */
function is_superadmin_user($u){
  $rol = strtolower(trim((string)(
    $u['rol_nombre'] ?? $u['rol'] ?? $u['perfil'] ?? $u['tipo'] ?? $u['role'] ?? ''
  )));
  return ($rol === 'admin' || $rol === 'administrador' || $rol === 'superadmin' || $rol === 'root');
}
$isSuper = is_superadmin_user($currentUser);

/* =========================================================
   Helpers columnas (por si tu tabla cambia)
========================================================= */
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
$uCols = table_columns('usuarios');
$uHas = function($c) use ($uCols){ return in_array($c, $uCols, true); };

/* --- columnas opcionales firma --- */
$hasFirmaPath = $uHas('firma_path');
$hasFirmaHash = $uHas('firma_hash');
$hasFirmaMime = $uHas('firma_mime');
$hasFirmaSize = $uHas('firma_size');
$hasFirmaUpd  = $uHas('firma_actualizada_en');

/* =========================================================
   Tenants y Roles
========================================================= */
if ($isSuper) {
  $tenSt = db()->prepare("SELECT id, nombre, estado FROM tenants ORDER BY nombre ASC");
  $tenSt->execute();
  $tenants = $tenSt->fetchAll();
} else {
  $tenSt = db()->prepare("SELECT id, nombre, estado FROM tenants WHERE id=:t LIMIT 1");
  $tenSt->execute([':t'=>$currentTenantId]);
  $tenants = $tenSt->fetchAll();
}

/* =========================================================
   Data base
========================================================= */
$data = [
  'tenant_id' => $currentTenantId,
  'rol_id' => 0,
  'nombre' => '',
  'cargo' => '',
  'email' => '',
  'telefono' => '',
  'tipo_documento' => '',
  'num_documento' => '',
  'tarjeta_profesional' => '',
  'entidad_tarjeta' => '',
  'firma_habilitada' => 0,
  'estado' => 'ACTIVO',

  // firma (si existen columnas)
  'firma_path' => '',
  'firma_hash' => '',
  'firma_mime' => '',
  'firma_size' => '',
  'firma_actualizada_en' => ''
];

/* =========================================================
   Cargar usuario si edita
========================================================= */
if ($id > 0) {
  $st = db()->prepare("
    SELECT *
    FROM usuarios
    WHERE id=:id
    LIMIT 1
  ");
  $st->execute([':id'=>$id]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "Usuario no encontrado"; exit; }

  // Seguridad tenant si NO es superadmin
  if (!$isSuper && (int)$row['tenant_id'] !== $currentTenantId) {
    http_response_code(403);
    echo "No autorizado.";
    exit;
  }

  $data = array_merge($data, $row);
}

/* =========================================================
   Roles por tenant seleccionado
========================================================= */
$selectedTenantId = (int)($data['tenant_id'] ?? $currentTenantId);
if ($selectedTenantId <= 0) $selectedTenantId = $currentTenantId;

$rolSt = db()->prepare("SELECT id, nombre FROM roles WHERE tenant_id=:t ORDER BY nombre ASC");
$rolSt->execute([':t'=>$selectedTenantId]);
$roles = $rolSt->fetchAll();

/* =========================================================
   Subida firma (helper)
   ✅ FIX REAL: guardar en /public/uploads/firmas (ruta pública)
========================================================= */
function save_user_signature($tenantId, $userId, $file){
  if (!$file || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    return [false, 'Archivo de firma inválido.'];
  }

  $maxBytes = 2 * 1024 * 1024; // 2MB (ajusta si quieres)
  if ((int)$file['size'] > $maxBytes) {
    return [false, 'La firma supera el tamaño máximo (2MB).'];
  }

  $tmp = $file['tmp_name'];
  $mime = '';
  if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) { $mime = (string)finfo_file($fi, $tmp); finfo_close($fi); }
  }

  $allowed = ['image/png','image/jpeg','image/webp'];
  if ($mime !== '' && !in_array($mime, $allowed, true)) {
    return [false, 'Firma no permitida. Usa PNG/JPG/WebP.'];
  }

  // ✅ Carpeta destino pública: geoactivos/public/uploads/firmas/{tenant}
  $base = __DIR__ . '/../../../public/uploads/firmas/' . (int)$tenantId;
  if (!is_dir($base)) {
    @mkdir($base, 0777, true);
  }
  if (!is_dir($base)) {
    return [false, 'No se pudo crear carpeta de firmas en /public/uploads (permiso).'];
  }

  // Extensión según mime
  $ext = 'png';
  if ($mime === 'image/jpeg') $ext = 'jpg';
  if ($mime === 'image/webp') $ext = 'webp';

  $filename = 'user_' . (int)$userId . '.' . $ext;
  $pathAbs = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $filename;

  if (!@move_uploaded_file($tmp, $pathAbs)) {
    return [false, 'No se pudo guardar la firma en disco.'];
  }

  $hash = hash_file('sha256', $pathAbs);

  // ✅ Relativo público (esto es lo que se guarda en BD)
  $rel  = 'uploads/firmas/' . (int)$tenantId . '/' . $filename;

  return [true, [
    'rel' => $rel,
    'hash' => $hash,
    'mime' => $mime ?: ('image/'.$ext),
    'size' => (int)$file['size']
  ]];
}

/* =========================================================
   POST
========================================================= */
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $tenantId = (int)($_POST['tenant_id'] ?? 0);
  if (!$isSuper) $tenantId = $currentTenantId;

  $rolId    = (int)($_POST['rol_id'] ?? 0);
  $nombre   = trim((string)($_POST['nombre'] ?? ''));
  $cargo    = trim((string)($_POST['cargo'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $tipoDoc  = trim((string)($_POST['tipo_documento'] ?? ''));
  $numDoc   = trim((string)($_POST['num_documento'] ?? ''));
  $tarjProf = trim((string)($_POST['tarjeta_profesional'] ?? ''));
  $entTarj  = trim((string)($_POST['entidad_tarjeta'] ?? ''));
  $firmaHab = isset($_POST['firma_habilitada']) ? 1 : 0;

  $estado   = ((string)($_POST['estado'] ?? 'ACTIVO')) === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';

  $password = (string)($_POST['password'] ?? '');
  $password2= (string)($_POST['password2'] ?? '');

  if ($tenantId <= 0) $error = 'Debes seleccionar una empresa.';
  if ($error==='' && $rolId <= 0) $error = 'Debes seleccionar un rol.';
  if ($error==='' && $nombre === '') $error = 'El nombre es obligatorio.';
  if ($error==='' && $email === '') $error = 'El email es obligatorio.';
  if ($error==='' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Email inválido.';

  // Validación password (solo si nuevo o si lo escriben)
  $isNew = ($id <= 0);
  if ($error === '') {
    if ($isNew && $password === '') $error = 'Para un usuario nuevo debes definir una contraseña.';
    if ($error==='' && ($password !== '' || $password2 !== '')) {
      if ($password !== $password2) $error = 'Las contraseñas no coinciden.';
      elseif (strlen($password) < 6) $error = 'La contraseña debe tener mínimo 6 caracteres.';
    }
  }

  // email único por tenant
  if ($error === '') {
    $chk = db()->prepare("
      SELECT id
      FROM usuarios
      WHERE tenant_id=:t AND email=:e AND id <> :id
      LIMIT 1
    ");
    $chk->execute([':t'=>$tenantId, ':e'=>$email, ':id'=>$id]);
    if ($chk->fetch()) $error = 'Ya existe un usuario con ese email en esta empresa.';
  }

  // validar rol pertenece al tenant
  if ($error === '') {
    $rr = db()->prepare("SELECT id FROM roles WHERE id=:r AND tenant_id=:t LIMIT 1");
    $rr->execute([':r'=>$rolId, ':t'=>$tenantId]);
    if (!$rr->fetch()) $error = 'El rol seleccionado no pertenece a la empresa.';
  }

  if ($error === '') {

    if ($id > 0) {

      // UPDATE
      $params = [
        ':t'=>$tenantId,
        ':r'=>$rolId,
        ':n'=>$nombre,
        ':c'=>$cargo ?: null,
        ':e'=>$email,
        ':tel'=>$telefono ?: null,
        ':td'=>$tipoDoc ?: null,
        ':nd'=>$numDoc ?: null,
        ':tp'=>$tarjProf ?: null,
        ':et'=>$entTarj ?: null,
        ':fh'=>$firmaHab,
        ':s'=>$estado,
        ':id'=>$id
      ];

      if ($password !== '') {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $params[':h'] = $hash;
        $sql = "
          UPDATE usuarios
          SET tenant_id=:t, rol_id=:r, nombre=:n, cargo=:c, email=:e, telefono=:tel,
              tipo_documento=:td, num_documento=:nd,
              tarjeta_profesional=:tp, entidad_tarjeta=:et,
              firma_habilitada=:fh, estado=:s, pass_hash=:h
          WHERE id=:id
          LIMIT 1
        ";
      } else {
        $sql = "
          UPDATE usuarios
          SET tenant_id=:t, rol_id=:r, nombre=:n, cargo=:c, email=:e, telefono=:tel,
              tipo_documento=:td, num_documento=:nd,
              tarjeta_profesional=:tp, entidad_tarjeta=:et,
              firma_habilitada=:fh, estado=:s
          WHERE id=:id
          LIMIT 1
        ";
      }

      $up = db()->prepare($sql);
      $up->execute($params);

    } else {

      // INSERT
      $hash = password_hash($password, PASSWORD_BCRYPT);

      $ins = db()->prepare("
        INSERT INTO usuarios (
          tenant_id, rol_id, nombre, cargo, email, telefono,
          tipo_documento, num_documento,
          tarjeta_profesional, entidad_tarjeta,
          firma_habilitada,
          pass_hash, estado
        )
        VALUES (
          :t, :r, :n, :c, :e, :tel,
          :td, :nd,
          :tp, :et,
          :fh,
          :h, :s
        )
      ");
      $ins->execute([
        ':t'=>$tenantId,
        ':r'=>$rolId,
        ':n'=>$nombre,
        ':c'=>$cargo ?: null,
        ':e'=>$email,
        ':tel'=>$telefono ?: null,
        ':td'=>$tipoDoc ?: null,
        ':nd'=>$numDoc ?: null,
        ':tp'=>$tarjProf ?: null,
        ':et'=>$entTarj ?: null,
        ':fh'=>$firmaHab,
        ':h'=>$hash,
        ':s'=>$estado
      ]);

      $id = (int)db()->lastInsertId();
    }

    // Quitar firma (si existe y lo piden)
    $removeFirma = isset($_POST['remove_firma']) ? 1 : 0;
    if ($id > 0 && $removeFirma && $hasFirmaPath) {
      $st = db()->prepare("SELECT firma_path FROM usuarios WHERE id=:id LIMIT 1");
      $st->execute([':id'=>$id]);
      $x = $st->fetch();
      if ($x && !empty($x['firma_path'])) {
        // ✅ FIX REAL: firma_path es relativo a /public
        $abs = __DIR__ . '/../../../public/' . ltrim((string)$x['firma_path'], '/');
        if (is_file($abs)) @unlink($abs);
      }

      $sql = "UPDATE usuarios SET firma_path=NULL";
      if ($hasFirmaHash) $sql .= ", firma_hash=NULL";
      if ($hasFirmaMime) $sql .= ", firma_mime=NULL";
      if ($hasFirmaSize) $sql .= ", firma_size=NULL";
      if ($hasFirmaUpd)  $sql .= ", firma_actualizada_en=NULL";
      $sql .= " WHERE id=:id LIMIT 1";
      $up = db()->prepare($sql);
      $up->execute([':id'=>$id]);
    }

    // Subir firma (si viene archivo y existen columnas)
    if ($id > 0 && isset($_FILES['firma_png']) && $_FILES['firma_png']['error'] === UPLOAD_ERR_OK) {
      if (!$hasFirmaPath) {
        // si no tienes columnas, no rompemos; solo ignoramos
      } else {
        $tenantForSave = $tenantId;
        $res = save_user_signature($tenantForSave, $id, $_FILES['firma_png']);
        if ($res[0] === false) {
          $error = (string)$res[1];
        } else {
          $info = $res[1];

          $sql = "UPDATE usuarios SET firma_path=:p";
          $params = [':p'=>$info['rel'], ':id'=>$id];

          if ($hasFirmaHash) { $sql .= ", firma_hash=:h"; $params[':h']=$info['hash']; }
          if ($hasFirmaMime) { $sql .= ", firma_mime=:m"; $params[':m']=$info['mime']; }
          if ($hasFirmaSize) { $sql .= ", firma_size=:s"; $params[':s']=$info['size']; }
          if ($hasFirmaUpd)  { $sql .= ", firma_actualizada_en=NOW()"; }

          $sql .= " WHERE id=:id LIMIT 1";

          $up = db()->prepare($sql);
          $up->execute($params);
        }
      }
    }

    if ($error === '') {
      redirect('index.php?route=usuarios');
    }
  }

  // Repintar si hubo error
  $data = array_merge($data, [
    'tenant_id'=>$tenantId,
    'rol_id'=>$rolId,
    'nombre'=>$nombre,
    'cargo'=>$cargo,
    'email'=>$email,
    'telefono'=>$telefono,
    'tipo_documento'=>$tipoDoc,
    'num_documento'=>$numDoc,
    'tarjeta_profesional'=>$tarjProf,
    'entidad_tarjeta'=>$entTarj,
    'firma_habilitada'=>$firmaHab,
    'estado'=>$estado
  ]);

  // refrescar roles según tenant elegido
  $selectedTenantId = (int)$tenantId;
  $rolSt = db()->prepare("SELECT id, nombre FROM roles WHERE tenant_id=:t ORDER BY nombre ASC");
  $rolSt->execute([':t'=>$selectedTenantId]);
  $roles = $rolSt->fetchAll();
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-user"></i> <?= ($id>0 ? 'Editar' : 'Nuevo') ?> usuario
    </h3>
    <div class="card-tools">
      <a class="btn btn-sm btn-secondary" href="<?= e(base_url()) ?>/index.php?route=usuarios">
        <i class="fas fa-arrow-left"></i> Volver
      </a>
    </div>
  </div>

  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert alert-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" autocomplete="off">

      <div class="form-row">

        <div class="form-group col-md-6">
          <label>Empresa (tenant) *</label>

          <?php if ($isSuper): ?>
            <select class="form-control" name="tenant_id" required onchange="this.form.submit()">
              <option value="">-- Selecciona --</option>
              <?php foreach ($tenants as $t): ?>
                <?php
                  $tid = (int)$t['id'];
                  $txt = (string)$t['nombre'];
                  $stt = (string)($t['estado'] ?? 'ACTIVO');
                ?>
                <option value="<?= $tid ?>" <?= ((int)$data['tenant_id']===$tid ? 'selected' : '') ?>>
                  <?= e($txt) ?><?= ($stt !== 'ACTIVO' ? ' (INACTIVO)' : '') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Tip: al cambiar empresa, recarga roles de esa empresa.</small>
          <?php else: ?>
            <input type="hidden" name="tenant_id" value="<?= (int)$currentTenantId ?>">
            <input class="form-control" value="<?= e((string)($_SESSION['tenant']['nombre'] ?? 'Empresa')) ?>" disabled>
            <small class="text-muted">Tu usuario no es superadmin, la empresa no se puede cambiar.</small>
          <?php endif; ?>
        </div>

        <div class="form-group col-md-6">
          <label>Rol *</label>
          <select class="form-control" name="rol_id" required>
            <option value="">-- Selecciona --</option>
            <?php foreach ($roles as $r): ?>
              <?php $rid = (int)$r['id']; ?>
              <option value="<?= $rid ?>" <?= ((int)$data['rol_id']===$rid ? 'selected' : '') ?>>
                <?= e($r['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>

      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Nombre *</label>
          <input class="form-control" name="nombre" value="<?= e((string)$data['nombre']) ?>" required>
        </div>
        <div class="form-group col-md-6">
          <label>Cargo</label>
          <input class="form-control" name="cargo" value="<?= e((string)($data['cargo'] ?? '')) ?>" placeholder="Ej: Técnico biomédico, Ingeniero, Soporte TI...">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Email *</label>
          <input type="email" class="form-control" name="email" value="<?= e((string)$data['email']) ?>" required>
        </div>
        <div class="form-group col-md-6">
          <label>Teléfono</label>
          <input class="form-control" name="telefono" value="<?= e((string)($data['telefono'] ?? '')) ?>" placeholder="Ej: 320xxxxxxx">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-3">
          <label>Tipo doc.</label>
          <input class="form-control" name="tipo_documento" value="<?= e((string)($data['tipo_documento'] ?? '')) ?>" placeholder="CC, CE, NIT...">
        </div>
        <div class="form-group col-md-3">
          <label>Número doc.</label>
          <input class="form-control" name="num_documento" value="<?= e((string)($data['num_documento'] ?? '')) ?>">
        </div>
        <div class="form-group col-md-3">
          <label>Tarjeta profesional</label>
          <input class="form-control" name="tarjeta_profesional" value="<?= e((string)($data['tarjeta_profesional'] ?? '')) ?>">
        </div>
        <div class="form-group col-md-3">
          <label>Entidad tarjeta</label>
          <input class="form-control" name="entidad_tarjeta" value="<?= e((string)($data['entidad_tarjeta'] ?? '')) ?>" placeholder="Ej: COPNIA, Colegio, etc.">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Estado</label>
          <select class="form-control" name="estado">
            <option value="ACTIVO" <?= ((string)$data['estado']==='ACTIVO'?'selected':'') ?>>ACTIVO</option>
            <option value="INACTIVO" <?= ((string)$data['estado']==='INACTIVO'?'selected':'') ?>>INACTIVO</option>
          </select>
        </div>

        <div class="form-group col-md-4">
          <label><?= ($id>0 ? 'Nueva contraseña (opcional)' : 'Contraseña *') ?></label>
          <input type="password" class="form-control" name="password" placeholder="<?= ($id>0?'(dejar en blanco para no cambiar)':'') ?>">
        </div>

        <div class="form-group col-md-4">
          <label>Confirmar contraseña<?= ($id>0 ? ' (si la cambias)' : ' *') ?></label>
          <input type="password" class="form-control" name="password2">
        </div>
      </div>

      <div class="form-group">
        <label style="display:block;margin-bottom:6px;">Firma habilitada</label>
        <label style="font-weight:700;">
          <input type="checkbox" name="firma_habilitada" value="1" <?= ((int)($data['firma_habilitada'] ?? 0)===1 ? 'checked' : '') ?>>
          Este usuario puede firmar mantenimientos (técnico/recibido/cierre).
        </label>
      </div>

      <?php if ($hasFirmaPath): ?>
        <hr>
        <h5 class="mb-2"><i class="fas fa-signature"></i> Firma digital (PNG/JPG/WebP)</h5>

        <?php
          $firmaPath = (string)($data['firma_path'] ?? '');
          // ✅ FIX: firma_path es relativo a /public
          $firmaUrl  = $firmaPath ? (e(base_url()).'/'.ltrim($firmaPath,'/').'?v='.time()) : '';
        ?>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Subir firma</label>
            <input type="file" class="form-control" name="firma_png" accept="image/png,image/jpeg,image/webp">
            <small class="text-muted">Recomendado: PNG con fondo transparente, firma centrada.</small>
          </div>

          <div class="form-group col-md-6">
            <label>Acciones firma</label>
            <div>
              <label style="font-weight:700;">
                <input type="checkbox" name="remove_firma" value="1">
                Quitar firma actual (si existe)
              </label>
            </div>

            <?php if ($firmaPath): ?>
              <div class="text-muted text-sm mt-1">
                <b>Archivo:</b> <?= e($firmaPath) ?><br>
                <?php if ($hasFirmaHash && !empty($data['firma_hash'])): ?>
                  <b>Hash:</b> <?= e((string)$data['firma_hash']) ?><br>
                <?php endif; ?>
                <?php if ($hasFirmaUpd && !empty($data['firma_actualizada_en'])): ?>
                  <b>Actualizada:</b> <?= e((string)$data['firma_actualizada_en']) ?>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="text-muted text-sm mt-1">No hay firma cargada.</div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($firmaUrl): ?>
          <div class="mb-3">
            <div class="text-muted text-sm mb-1">Vista previa:</div>
            <div style="border:1px dashed #bbb;border-radius:10px;padding:12px;background:#fff;max-width:520px;">
              <img src="<?= e($firmaUrl) ?>" alt="Firma" style="max-width:100%;height:auto;display:block;">
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      <a class="btn btn-secondary" href="<?= e(base_url()) ?>/index.php?route=usuarios">Cancelar</a>

      <?php if ($id>0): ?>
        <div class="text-muted text-sm mt-3">
          * Si no escribes contraseña, el sistema conserva el <b>pass_hash</b> actual.
        </div>
      <?php endif; ?>

    </form>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
