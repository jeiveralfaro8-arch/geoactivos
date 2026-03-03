<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);
$inline = (int)($_GET['inline'] ?? 0);

if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }

/* detectar tabla */
$adjTable = null;
$te = db()->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('mant_adjuntos','mantenimientos_adjuntos') LIMIT 1");
$te->execute();
$r = $te->fetch();
if ($r && !empty($r['table_name'])) $adjTable = $r['table_name'];
if (!$adjTable) { http_response_code(500); echo "Tabla adjuntos no existe"; exit; }

/* columnas */
$colsQ = db()->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name=:t");
$colsQ->execute([':t'=>$adjTable]);
$cols = array_map(function($x){ return $x['column_name']; }, $colsQ->fetchAll());

$colArchivo = null; foreach (['archivo','ruta','path','filename','nombre_archivo','stored_name'] as $c) { if (in_array($c,$cols,true)) { $colArchivo=$c; break; } }
$colNombre  = null; foreach (['nombre_original','nombre','original_name','archivo_nombre','file_name'] as $c) { if (in_array($c,$cols,true)) { $colNombre=$c; break; } }
$colMime    = null; foreach (['mime','mime_type','tipo_mime'] as $c) { if (in_array($c,$cols,true)) { $colMime=$c; break; } }

if (!$colArchivo) { http_response_code(500); echo "Columna archivo no encontrada"; exit; }

$selNombre = $colNombre ? ("`$colNombre` AS nombre") : "NULL AS nombre";
$selMime   = $colMime ? ("`$colMime` AS mime") : "NULL AS mime";

$q = db()->prepare("
  SELECT id, tenant_id, mantenimiento_id,
         `$colArchivo` AS archivo,
         $selNombre,
         $selMime
  FROM $adjTable
  WHERE id=:id AND tenant_id=:t
  LIMIT 1
");
$q->execute([':id'=>$id, ':t'=>$tenantId]);
$a = $q->fetch();
if (!$a) { http_response_code(404); echo "Adjunto no encontrado"; exit; }

/* construir ruta física */
$stored = (string)$a['archivo'];
$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) $publicRoot = __DIR__ . '/../../public';

$path = '';
if (strpos($stored, '/') !== false) {
  // viene como ruta relativa tipo uploads/...
  $path = $publicRoot . '/' . ltrim($stored, '/');
} else {
  // viene como solo filename -> asumimos carpeta estándar
  $path = $publicRoot . '/uploads/mantenimientos/' . (int)$tenantId . '/' . (int)$a['mantenimiento_id'] . '/' . $stored;
}

if (!is_file($path)) {
  http_response_code(404);
  echo "Archivo no existe en disco: " . htmlspecialchars($stored, ENT_QUOTES, 'UTF-8');
  exit;
}

$downloadName = $a['nombre'] ? (string)$a['nombre'] : basename($path);
$mime = $a['mime'] ? (string)$a['mime'] : 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));

if ($inline === 1) {
  header('Content-Disposition: inline; filename="' . basename($downloadName) . '"');
} else {
  header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
}

readfile($path);
exit;
