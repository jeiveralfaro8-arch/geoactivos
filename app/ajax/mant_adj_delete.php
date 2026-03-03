<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireLogin();

$tenantId = Auth::tenantId();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'ID inválido']);
  exit;
}

/* detectar tabla */
$adjTable = null;
$te = db()->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('mant_adjuntos','mantenimientos_adjuntos') LIMIT 1");
$te->execute();
$r = $te->fetch();
if ($r && !empty($r['table_name'])) $adjTable = $r['table_name'];
if (!$adjTable) { http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Tabla adjuntos no existe']); exit; }

/* columnas */
$colsQ = db()->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name=:t");
$colsQ->execute([':t'=>$adjTable]);
$cols = array_map(function($x){ return $x['column_name']; }, $colsQ->fetchAll());

$colArchivo = null; foreach (['archivo','ruta','path','filename','nombre_archivo','stored_name'] as $c) { if (in_array($c,$cols,true)) { $colArchivo=$c; break; } }
if (!$colArchivo) { http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Columna archivo no encontrada']); exit; }

$q = db()->prepare("SELECT id, mantenimiento_id, `$colArchivo` AS archivo FROM $adjTable WHERE id=:id AND tenant_id=:t LIMIT 1");
$q->execute([':id'=>$id, ':t'=>$tenantId]);
$a = $q->fetch();
if (!$a) { http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Adjunto no encontrado']); exit; }

/* borrar archivo físico (si existe) */
$stored = (string)$a['archivo'];
$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) $publicRoot = __DIR__ . '/../../public';

$path = '';
if (strpos($stored, '/') !== false) {
  $path = $publicRoot . '/' . ltrim($stored, '/');
} else {
  $path = $publicRoot . '/uploads/mantenimientos/' . (int)$tenantId . '/' . (int)$a['mantenimiento_id'] . '/' . $stored;
}

if (is_file($path)) {
  @unlink($path);
}

/* borrar registro */
$del = db()->prepare("DELETE FROM $adjTable WHERE id=:id AND tenant_id=:t");
$ok = $del->execute([':id'=>$id, ':t'=>$tenantId]);

echo json_encode(['ok'=>(bool)$ok]);
