<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  echo "Adjunto inválido";
  exit;
}

/* Buscar adjunto */
$st = db()->prepare("
  SELECT id, ruta, mime
  FROM activos_adjuntos
  WHERE id = :id AND tenant_id = :t
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$adj = $st->fetch();

if (!$adj) {
  http_response_code(404);
  echo "Adjunto no encontrado";
  exit;
}

/* Ruta física */
$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) {
  $publicRoot = __DIR__ . '/../../public';
}

$file = $publicRoot . '/' . ltrim($adj['ruta'], '/');

if (!is_file($file)) {
  http_response_code(404);
  echo "Archivo no existe en disco";
  exit;
}

/* Headers */
$mime = $adj['mime'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));

/* Mostrar inline (PDF / imágenes) */
readfile($file);
exit;
