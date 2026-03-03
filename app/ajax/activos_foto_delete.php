<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$tenantId = Auth::tenantId();
$activoId = (int)($_POST['activo_id'] ?? 0);

if ($tenantId <= 0 || $activoId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Datos inválidos.']); exit; }

$st = db()->prepare("SELECT foto_path FROM activos WHERE id=:id AND tenant_id=:t LIMIT 1");
$st->execute([':id'=>$activoId, ':t'=>$tenantId]);
$row = $st->fetch();
if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Activo no encontrado.']); exit; }

$foto = (string)($row['foto_path'] ?? '');

$baseDir = realpath(__DIR__ . '/../../public');
if ($baseDir && $foto) {
  $abs = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $foto);
  if (is_file($abs)) { @unlink($abs); }
}

$up = db()->prepare("UPDATE activos SET foto_path=NULL, foto_mime=NULL, foto_updated_en=NULL WHERE id=:id AND tenant_id=:t LIMIT 1");
$up->execute([':id'=>$activoId, ':t'=>$tenantId]);

echo json_encode(['ok'=>true]);
