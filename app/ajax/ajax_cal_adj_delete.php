<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();
header('Content-Type: application/json; charset=UTF-8');

$tenantId = Auth::tenantId();
$user = Auth::user();
$userId = isset($user['id']) ? (int)$user['id'] : null;

$id = (int)($_POST['id'] ?? 0);

if ($tenantId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Tenant inválido.']); exit; }
if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }

$st = db()->prepare("
  SELECT id
  FROM calibraciones_adjuntos
  WHERE id=:id AND tenant_id=:t AND eliminado=0
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$row = $st->fetch();

if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Adjunto no encontrado.']); exit; }

$up = db()->prepare("
  UPDATE calibraciones_adjuntos
  SET eliminado=1, eliminado_en=:e, eliminado_por=:u
  WHERE id=:id AND tenant_id=:t
  LIMIT 1
");
$up->execute([
  ':e'=>date('Y-m-d H:i:s'),
  ':u'=>$userId,
  ':id'=>$id,
  ':t'=>$tenantId
]);

echo json_encode(['ok'=>true]);
