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
  SELECT archivo_certificado_path, archivo_certificado_mime
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

if ($rel === '') {
  http_response_code(404);
  echo "Este patrón no tiene certificado cargado";
  exit;
}

/* Ruta física absoluta */
$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) $publicRoot = __DIR__ . '/../../public';

$file = rtrim($publicRoot, '/\\') . '/' . ltrim($rel, '/\\');

if (!is_file($file)) {
  http_response_code(404);
  echo "Archivo no existe en disco";
  exit;
}

if ($mime === '') $mime = 'application/octet-stream';

header('Content-Type: '.$mime);
header('Content-Disposition: inline; filename="'.basename($file).'"');
header('Content-Length: '.filesize($file));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($file);
exit;
