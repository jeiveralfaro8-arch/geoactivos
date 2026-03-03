<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireLogin();

$tenantId = Auth::tenantId();

/* userId robusto */
$userId = null;
if (method_exists('Auth', 'userId')) $userId = Auth::userId();
elseif (method_exists('Auth', 'id')) $userId = Auth::id();
elseif (method_exists('Auth', 'user')) {
  $u = Auth::user();
  if (is_array($u) && isset($u['id'])) $userId = (int)$u['id'];
}

$activoId = (int)($_POST['activo_id'] ?? 0);
$nota = trim((string)($_POST['nota'] ?? ''));

if ($activoId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Activo inválido']);
  exit;
}

if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'No se recibió archivo']);
  exit;
}

/* Validar activo pertenezca al tenant */
$chk = db()->prepare("SELECT id FROM activos WHERE id=:id AND tenant_id=:t LIMIT 1");
$chk->execute([':id'=>$activoId, ':t'=>$tenantId]);
if (!$chk->fetch()) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}

/* Guardar archivo físico en public/uploads/activos/{tenant}/{activo}/ */
$origName = (string)($_FILES['archivo']['name'] ?? 'archivo');
$origName = basename($origName);
$size = (int)($_FILES['archivo']['size'] ?? 0);

$mime = '';
if (class_exists('finfo')) {
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = (string)$fi->file($_FILES['archivo']['tmp_name']);
}

/* límite sugerido 10MB */
if ($size > 10 * 1024 * 1024) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'El archivo supera 10MB.']);
  exit;
}

/* nombre guardado */
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$safeExt = preg_replace('/[^a-z0-9]+/i', '', $ext);
$stored = uniqid('adj_', true) . ($safeExt ? ('.'.$safeExt) : '');

/* rutas */
$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) $publicRoot = __DIR__ . '/../../public';

$dir = $publicRoot . '/uploads/activos/' . (int)$tenantId . '/' . (int)$activoId;
if (!is_dir($dir)) {
  @mkdir($dir, 0775, true);
}
$destAbs = $dir . '/' . $stored;

/* mover */
if (!@move_uploaded_file($_FILES['archivo']['tmp_name'], $destAbs)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No se pudo guardar el archivo en el servidor. Verifica permisos de carpeta.']);
  exit;
}

/* ruta relativa para guardar en DB */
$relPath = 'uploads/activos/' . (int)$tenantId . '/' . (int)$activoId . '/' . $stored;

/* Insert (tu tabla real) */
try {
  $ins = db()->prepare("
    INSERT INTO activos_adjuntos
      (tenant_id, activo_id, nombre_original, nombre_guardado, ruta, mime, tamano, creado_por)
    VALUES
      (:t, :a, :no, :ng, :r, :m, :s, :cp)
  ");

  $ok = $ins->execute([
    ':t'=>$tenantId,
    ':a'=>$activoId,
    ':no'=>$origName,
    ':ng'=>$stored,
    ':r'=>$relPath,
    ':m'=>($mime !== '' ? $mime : null),
    ':s'=>$size,
    ':cp'=>($userId ? $userId : null)
  ]);

  echo json_encode(['ok'=>(bool)$ok]);

} catch (Exception $e) {
  if (is_file($destAbs)) @unlink($destAbs);
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error guardando en base de datos.','detail'=>$e->getMessage()]);
}
