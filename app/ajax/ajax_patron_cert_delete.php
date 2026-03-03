<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$tenantId = Auth::tenantId();
$patronId = (int)($_POST['patron_id'] ?? 0);

if ($tenantId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Tenant inválido.']); exit; }
if ($patronId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Patrón inválido.']); exit; }

$st = db()->prepare("
  SELECT archivo_certificado_path
  FROM patrones
  WHERE id=:id AND tenant_id=:t AND eliminado=0
  LIMIT 1
");
$st->execute([':id'=>$patronId, ':t'=>$tenantId]);
$row = $st->fetch();

if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Patrón no encontrado.']); exit; }

$rel = trim((string)($row['archivo_certificado_path'] ?? ''));

/* Borrar archivo físico */
if ($rel !== '') {
  $publicRoot = realpath(__DIR__ . '/../../public');
  if ($publicRoot) {
    $file = rtrim($publicRoot, '/\\') . '/' . ltrim($rel, '/\\');
    if (is_file($file)) {
      @unlink($file);
    }
  }
}

/* Limpiar DB */
$up = db()->prepare("
  UPDATE patrones
  SET
    archivo_certificado_path = NULL,
    archivo_certificado_mime = NULL,
    archivo_certificado_updated_en = NULL
  WHERE id=:id AND tenant_id=:t
  LIMIT 1
");
$up->execute([':id'=>$patronId, ':t'=>$tenantId]);

echo json_encode(['ok'=>true]);
exit;
