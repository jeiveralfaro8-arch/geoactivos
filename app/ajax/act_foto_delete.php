<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$tenantId = Auth::tenantId();
$activoId = (int)($_POST['activo_id'] ?? 0);

if ($tenantId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Tenant inválido.']); exit; }
if ($activoId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Activo inválido.']); exit; }

/* Validar activo y obtener foto_path actual */
$st = db()->prepare("
  SELECT id, foto_path
  FROM activos
  WHERE id=:id AND tenant_id=:t
  LIMIT 1
");
$st->execute([':id'=>$activoId, ':t'=>$tenantId]);
$row = $st->fetch();

if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Activo no encontrado.']); exit; }

$fotoPath = trim((string)($row['foto_path'] ?? ''));
if ($fotoPath === '') {
  /* Ya no hay foto, igual limpiamos campos por seguridad */
  $up = db()->prepare("
    UPDATE activos
    SET foto_path=NULL, foto_mime=NULL, foto_updated_en=NULL
    WHERE id=:id AND tenant_id=:t
    LIMIT 1
  ");
  $up->execute([':id'=>$activoId, ':t'=>$tenantId]);
  echo json_encode(['ok'=>true,'msg'=>'Sin foto (ya estaba eliminada).']);
  exit;
}

/* Resolver ruta física dentro de /public */
$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) $publicRoot = __DIR__ . '/../../public';

/* Normalizar y evitar path traversal */
$rel = ltrim(str_replace(['\\'], ['/'], $fotoPath), '/');
if (strpos($rel, '../') !== false || strpos($rel, '..\\') !== false) {
  echo json_encode(['ok'=>false,'msg'=>'Ruta inválida.']); exit;
}

$absFile = rtrim($publicRoot, '/\\') . '/' . $rel;

/* Por seguridad: solo permitimos borrar dentro de uploads/activos/{tenant}/ */
$allowedPrefix = 'uploads/activos/' . (int)$tenantId . '/';
if (strpos($rel, $allowedPrefix) !== 0) {
  echo json_encode(['ok'=>false,'msg'=>'No autorizado para eliminar esta ruta.']); exit;
}

/* Borrar archivo si existe */
$deleted = false;
if (is_file($absFile)) {
  $deleted = @unlink($absFile);
  if (!$deleted) {
    $last = error_get_last();
    echo json_encode(['ok'=>false,'msg'=>'No se pudo eliminar el archivo.','detail'=>$last ? $last['message'] : '']); exit;
  }
} else {
  /* Si no existe en disco, igual limpiamos BD */
  $deleted = true;
}

/* Limpiar BD */
$up = db()->prepare("
  UPDATE activos
  SET foto_path=NULL, foto_mime=NULL, foto_updated_en=NULL
  WHERE id=:id AND tenant_id=:t
  LIMIT 1
");
$up->execute([':id'=>$activoId, ':t'=>$tenantId]);

echo json_encode(['ok'=>true,'msg'=>'Foto eliminada.','deleted'=>$deleted]);
exit;
