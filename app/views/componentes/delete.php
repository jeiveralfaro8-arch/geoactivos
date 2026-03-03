<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();
$userId   = method_exists('Auth','userId') ? Auth::userId() : null;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  redirect('index.php?route=dashboard&err=ID inválido');
}

$st = db()->prepare("SELECT id, activo_id, nombre FROM activos_componentes WHERE id=:id AND tenant_id=:t LIMIT 1");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$row = $st->fetch();

if (!$row) {
  redirect('index.php?route=dashboard&err=Componente no encontrado');
}

$activoId = (int)$row['activo_id'];

db()->prepare("
  UPDATE activos_componentes
  SET eliminado=1, eliminado_en=NOW(), eliminado_por=:u
  WHERE id=:id AND tenant_id=:t
  LIMIT 1
")->execute([
  ':u' => ((int)$userId > 0 ? (int)$userId : null),
  ':id'=> $id,
  ':t' => $tenantId
]);

redirect('index.php?route=activo_detalle&id='.$activoId.'&ok=Componente eliminado');
