<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  echo "Patrón inválido";
  exit;
}

$st = db()->prepare("
  SELECT nombre, archivo_certificado_path, archivo_certificado_mime
  FROM patrones
  WHERE id=:id AND tenant_id=:t AND eliminado=0
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$row = $st->fetch();

if (!$row) {
  http_response_code(404);
  echo "Patrón no encontrado";
  exit;
}

$rel = trim((string)($row['archivo_certificado_path'] ?? ''));
$mime = trim((string)($row['archivo_certificado_mime'] ?? ''));
$nombrePatron = trim((string)($row['nombre'] ?? 'patron'));

if ($rel === '') {
  http_response_code(404);
  echo "Este patrón no tiene certificado cargado";
  exit;
}

$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) $publicRoot = __DIR__ . '/../../public';

$file = rtrim($publicRoot, '/\\') . '/' . ltrim($rel, '/\\');

if (!is_file($file)) {
  http_response_code(404);
  echo "Archivo no existe en disco";
  exit;
}

if ($mime === '') $mime = 'application/octet-stream';

$ext = pathinfo($file, PATHINFO_EXTENSION);
$downloadName = 'Certificado_' . preg_replace('/[^a-zA-Z0-9\-_]+/', '_', $nombrePatron) . '.' . $ext;

header('Content-Description: File Transfer');
header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.str_replace('"','',$downloadName).'"');
header('Content-Length: '.filesize($file));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($file);
exit;
