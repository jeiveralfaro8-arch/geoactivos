<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$tenantId = Auth::tenantId();
$user = Auth::user();
$userId = isset($user['id']) ? (int)$user['id'] : null;

$patronId = (int)($_POST['patron_id'] ?? 0);

if ($tenantId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Tenant inválido.']); exit; }
if ($patronId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Patrón inválido.']); exit; }

/* Validar patrón */
$st = db()->prepare("
  SELECT id
  FROM patrones
  WHERE id=:id AND tenant_id=:t AND eliminado=0
  LIMIT 1
");
$st->execute([':id'=>$patronId, ':t'=>$tenantId]);
if (!$st->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Patrón no encontrado.']); exit; }

/* Validar archivo */
if (!isset($_FILES['certificado']) || !is_uploaded_file($_FILES['certificado']['tmp_name'])) {
  echo json_encode(['ok'=>false,'msg'=>'No se recibió archivo.']); exit;
}

$f = $_FILES['certificado'];
if ((int)$f['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['ok'=>false,'msg'=>'Error al subir archivo.']); exit;
}

$maxBytes = 10 * 1024 * 1024; // 10MB
if ((int)$f['size'] > $maxBytes) {
  echo json_encode(['ok'=>false,'msg'=>'Archivo demasiado grande (máx 10MB).']); exit;
}

$tmp = $f['tmp_name'];

/* MIME real */
$mime = '';
if (function_exists('finfo_open')) {
  $fi = finfo_open(FILEINFO_MIME_TYPE);
  if ($fi) { $mime = (string)finfo_file($fi, $tmp); finfo_close($fi); }
}

$allowed = [
  'application/pdf' => 'pdf',
  'image/jpeg'      => 'jpg',
  'image/png'       => 'png',
  'image/webp'      => 'webp',
];

if (!isset($allowed[$mime])) {
  echo json_encode(['ok'=>false,'msg'=>'Formato no permitido. Usa PDF, JPG, PNG o WEBP.']); exit;
}
$ext = $allowed[$mime];

/* Carpeta destino dentro de /public */
$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) {
  echo json_encode(['ok'=>false,'msg'=>'No se encontró la carpeta /public.']); exit;
}

$relDir = 'uploads/patrones/' . (int)$tenantId;
$absDir = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);

if (!is_dir($absDir)) {
  @mkdir($absDir, 0775, true);
}
if (!is_dir($absDir)) {
  echo json_encode(['ok'=>false,'msg'=>'No se pudo crear carpeta de carga.']); exit;
}

/* Nombre estable: 1 certificado por patrón (sobrescribe) */
$filename = 'patron_' . (int)$patronId . '_cert.' . $ext;
$absPath  = $absDir . DIRECTORY_SEPARATOR . $filename;

/* Guardar */
if (!@move_uploaded_file($tmp, $absPath)) {
  echo json_encode(['ok'=>false,'msg'=>'No se pudo guardar el archivo.']); exit;
}

/* Persistir ruta relativa */
$relPath = $relDir . '/' . $filename;

$up = db()->prepare("
  UPDATE patrones
  SET
    archivo_certificado_path = :p,
    archivo_certificado_mime = :m,
    archivo_certificado_updated_en = :w
  WHERE id=:id AND tenant_id=:t
  LIMIT 1
");
$up->execute([
  ':p'  => $relPath,
  ':m'  => $mime,
  ':w'  => date('Y-m-d H:i:s'),
  ':id' => $patronId,
  ':t'  => $tenantId,
]);

echo json_encode([
  'ok'   => true,
  'path' => $relPath,
  'mime' => $mime
]);
exit;
