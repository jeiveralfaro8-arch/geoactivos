<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$user = Auth::user();
$userId = isset($user['id']) ? (int)$user['id'] : null;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo "ID inválido";
  exit;
}

/* Validar existencia */
$st = db()->prepare("
  SELECT id, nombre
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

/* Soft delete */
$up = db()->prepare("
  UPDATE patrones
  SET eliminado=1, eliminado_en=:en, eliminado_por=:u
  WHERE id=:id AND tenant_id=:t
  LIMIT 1
");
$up->execute([
  ':en' => date('Y-m-d H:i:s'),
  ':u'  => $userId,
  ':id' => $id,
  ':t'  => $tenantId
]);

header('Location: '.base_url().'/index.php?route=patrones');
exit;
