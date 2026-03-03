<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$tenantId = Auth::tenantId();

$id = (int)($_POST['id'] ?? 0);
$nombre = trim((string)($_POST['nombre'] ?? ''));
$cargo  = trim((string)($_POST['cargo'] ?? ''));
$firmaPngDataUrl = (string)($_POST['firma_png'] ?? '');

if ($tenantId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Tenant inválido.']); exit; }
if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'Calibración inválida.']); exit; }
if ($nombre === '') { echo json_encode(['ok'=>false,'msg'=>'Nombre obligatorio.']); exit; }
if ($firmaPngDataUrl === '' || stripos($firmaPngDataUrl, 'data:image/png;base64,') !== 0) {
  echo json_encode(['ok'=>false,'msg'=>'Firma inválida (PNG).']); exit;
}

/* Validar calibración del tenant */
$st = db()->prepare("
  SELECT id, estado
  FROM calibraciones
  WHERE id=:id AND tenant_id=:t AND eliminado=0
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$cal = $st->fetch();

if (!$cal) { echo json_encode(['ok'=>false,'msg'=>'Calibración no encontrada.']); exit; }
if ((string)$cal['estado'] === 'ANULADO') { echo json_encode(['ok'=>false,'msg'=>'No se puede firmar una calibración ANULADA.']); exit; }

/* Decodificar base64 */
$parts = explode(',', $firmaPngDataUrl, 2);
$rawBase64 = isset($parts[1]) ? $parts[1] : '';
$bin = base64_decode($rawBase64, true);

if ($bin === false || strlen($bin) < 200) {
  echo json_encode(['ok'=>false,'msg'=>'Firma vacía o inválida.']); exit;
}

/* Carpeta destino en /public */
$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) { echo json_encode(['ok'=>false,'msg'=>'No se encontró la carpeta /public.']); exit; }

$relDir = 'uploads/calibraciones/' . (int)$tenantId;
$absDir = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);

if (!is_dir($absDir)) {
  @mkdir($absDir, 0775, true);
}
if (!is_dir($absDir)) {
  echo json_encode(['ok'=>false,'msg'=>'No se pudo crear carpeta de firmas.']); exit;
}

/* Guardar PNG (sobrescribe) */
$filename = 'cal_' . (int)$id . '_firma_recibido.png';
$absPath  = $absDir . DIRECTORY_SEPARATOR . $filename;

if (file_put_contents($absPath, $bin) === false) {
  echo json_encode(['ok'=>false,'msg'=>'No se pudo guardar la firma en disco.']); exit;
}

/* Hash */
$hash = hash('sha256', $bin);
$relPath = $relDir . '/' . $filename;

/* Actualizar calibración */
$up = db()->prepare("
  UPDATE calibraciones
  SET
    recibido_por_nombre = :n,
    recibido_por_cargo = :c,
    recibido_firma_png = :p,
    recibido_firma_hash = :h
  WHERE id=:id AND tenant_id=:t
  LIMIT 1
");

$up->execute([
  ':n'  => $nombre,
  ':c'  => $cargo,
  ':p'  => $relPath,
  ':h'  => $hash,
  ':id' => $id,
  ':t'  => $tenantId,
]);

echo json_encode(['ok'=>true, 'path'=>$relPath, 'hash'=>$hash]);
exit;
