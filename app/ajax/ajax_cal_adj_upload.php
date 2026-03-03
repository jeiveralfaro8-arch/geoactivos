<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();
header('Content-Type: application/json; charset=UTF-8');

$tenantId = Auth::tenantId();
$user = Auth::user();
$userId = isset($user['id']) ? (int)$user['id'] : null;

$calId = (int)($_POST['calibracion_id'] ?? 0);

if ($tenantId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Tenant inválido.']); exit; }
if ($calId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Calibración inválida.']); exit; }

$st = db()->prepare("SELECT id, estado FROM calibraciones WHERE id=:id AND tenant_id=:t AND eliminado=0 LIMIT 1");
$st->execute([':id'=>$calId, ':t'=>$tenantId]);
$cal = $st->fetch();
if (!$cal) { echo json_encode(['ok'=>false,'msg'=>'Calibración no encontrada.']); exit; }
if ((string)$cal['estado'] === 'ANULADO') { echo json_encode(['ok'=>false,'msg'=>'Calibración anulada.']); exit; }

if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
  echo json_encode(['ok'=>false,'msg'=>'No se recibió archivo.']); exit;
}

$f = $_FILES['archivo'];
if ((int)$f['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['ok'=>false,'msg'=>'Error al subir archivo.']); exit;
}

$maxBytes = 15 * 1024 * 1024; // 15MB
if ((int)$f['size'] > $maxBytes) {
  echo json_encode(['ok'=>false,'msg'=>'Archivo demasiado grande (máx 15MB).']); exit;
}

$tmp = $f['tmp_name'];

/* MIME real */
$mime = '';
if (function_exists('finfo_open')) {
  $fi = finfo_open(FILEINFO_MIME_TYPE);
  if ($fi) { $mime = (string)finfo_file($fi, $tmp); finfo_close($fi); }
}
if ($mime === '') $mime = 'application/octet-stream';

/* Extensión segura */
$ext = '';
$map = [
  'application/pdf' => 'pdf',
  'image/jpeg' => 'jpg',
  'image/png' => 'png',
  'image/webp' => 'webp',
  'application/zip' => 'zip',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
  'application/msword' => 'doc',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
  'application/vnd.ms-excel' => 'xls',
  'text/plain' => 'txt'
];
if (isset($map[$mime])) $ext = $map[$mime];
if ($ext === '') {
  // fallback por nombre
  $orig = (string)($f['name'] ?? '');
  $ext2 = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (preg_match('/^[a-z0-9]{1,5}$/', $ext2)) $ext = $ext2;
  if ($ext === '') $ext = 'bin';
}

/* Carpeta destino */
$baseDir = realpath(__DIR__ . '/../../public');
if (!$baseDir) { echo json_encode(['ok'=>false,'msg'=>'No se encontró /public.']); exit; }

$relDir = 'uploads/calibraciones/' . (int)$tenantId . '/' . (int)$calId;
$absDir = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);

if (!is_dir($absDir)) { @mkdir($absDir, 0775, true); }
if (!is_dir($absDir)) { echo json_encode(['ok'=>false,'msg'=>'No se pudo crear carpeta de carga.']); exit; }

/* Nombre único */
$nombreOriginal = (string)($f['name'] ?? 'archivo');
$nombreOriginal = trim($nombreOriginal) !== '' ? $nombreOriginal : 'archivo.' . $ext;

$unique = date('Ymd_His') . '_' . bin2hex(substr(md5(uniqid('', true)), 0, 6));
$filename = 'cal_' . (int)$calId . '_' . $unique . '.' . $ext;

$absPath = $absDir . DIRECTORY_SEPARATOR . $filename;

if (!@move_uploaded_file($tmp, $absPath)) {
  echo json_encode(['ok'=>false,'msg'=>'No se pudo guardar el archivo.']); exit;
}

$relPath = $relDir . '/' . $filename;

$ins = db()->prepare("
  INSERT INTO calibraciones_adjuntos
    (tenant_id, calibracion_id, ruta, mime, tamano, nombre_original, creado_por, creado_en, eliminado)
  VALUES
    (:t, :c, :r, :m, :s, :n, :u, :e, 0)
");
$ins->execute([
  ':t'=>$tenantId,
  ':c'=>$calId,
  ':r'=>$relPath,
  ':m'=>$mime,
  ':s'=>(int)filesize($absPath),
  ':n'=>$nombreOriginal,
  ':u'=>$userId,
  ':e'=>date('Y-m-d H:i:s')
]);

echo json_encode(['ok'=>true]);
