<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);
$activoId = (int)($_GET['activo_id'] ?? 0);

if ($id > 0) {
  $del = db()->prepare("DELETE FROM activos_software WHERE id=:id AND tenant_id=:t LIMIT 1");
  $del->execute([':id'=>$id, ':t'=>$tenantId]);
}

redirect('index.php?route=activo_software&id=' . (int)$activoId);
