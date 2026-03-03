<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) { http_response_code(400); echo "Adjunto inválido"; exit; }

$st = db()->prepare("
  SELECT id, ruta, mime, nombre_original
  FROM calibraciones_adjuntos
  WHERE id=:id AND tenant_id=:t AND eliminado=0
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$adj = $st->fetch();

if (!$adj) { http_response_code(404); echo "Adjunto no encontrado"; exit; }

$mime = (string)($adj['mime'] ?: 'application/octet-stream');
$isPreview = (stripos($mime,'application/pdf')===0) || (stripos($mime,'image/')===0);
if (!$isPreview) { http_response_code(415); echo "Vista previa no disponible para este tipo"; exit; }

$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) $publicRoot = __DIR__ . '/../../public';

$rel = (string)$adj['ruta'];
$file = rtrim($publicRoot, '/\\') . '/' . ltrim($rel, '/\\');

if (!is_file($file)) { http_response_code(404); echo "Archivo no existe en disco"; exit; }

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
readfile($file);
exit;
