<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireLogin();

$tenantId = Auth::tenantId();

/*
  Soporta:
  - POST: id (recomendado para fetch)
  - GET : id (para links antiguos)
*/
$id = 0;
if (isset($_POST['id'])) $id = (int)$_POST['id'];
elseif (isset($_GET['id'])) $id = (int)$_GET['id'];

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'msg'=>'Adjunto inválido.']);
  exit;
}

/* Buscar adjunto (valida tenant) */
$st = db()->prepare("
  SELECT id, ruta, activo_id
  FROM activos_adjuntos
  WHERE id=:id AND tenant_id=:t
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$adj = $st->fetch();

if (!$adj) {
  http_response_code(404);
  echo json_encode(['ok'=>false, 'msg'=>'Adjunto no encontrado.']);
  exit;
}

/* Resolver ruta física: public/ + ruta relativa guardada en BD */
$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) $publicRoot = __DIR__ . '/../../public';

$rel = (string)$adj['ruta'];
$file = rtrim($publicRoot, '/\\') . '/' . ltrim($rel, '/\\');

/* Borrar en BD */
$del = db()->prepare("DELETE FROM activos_adjuntos WHERE id=:id AND tenant_id=:t LIMIT 1");
$ok = $del->execute([':id'=>$id, ':t'=>$tenantId]);

if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'msg'=>'No se pudo eliminar en base de datos.']);
  exit;
}

/* Borrar archivo físico (si existe) */
if (is_file($file)) {
  @unlink($file);
}

/* Respuesta JSON OK */
echo json_encode([
  'ok' => true,
  'activo_id' => (int)$adj['activo_id']
]);
exit;
