<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

/* Salida JSON limpia SIEMPRE */
ini_set('display_errors', '0');
error_reporting(E_ALL);
if (!headers_sent()) {
  header('Content-Type: application/json; charset=UTF-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}
ob_start();

function json_fail($msg, $extra = array(), $http = 200) {
  if (!headers_sent()) http_response_code($http);
  if (ob_get_length()) @ob_clean();
  $out = array_merge(array('ok'=>false,'msg'=>$msg), $extra);
  echo json_encode($out);
  exit;
}

function json_ok($data = array()) {
  if (ob_get_length()) @ob_clean();
  $out = array_merge(array('ok'=>true), $data);
  echo json_encode($out);
  exit;
}

try {
  $tenantId = Auth::tenantId();
  $activoId = (int)($_POST['activo_id'] ?? 0);

  if ($tenantId <= 0) json_fail('Tenant inválido.');
  if ($activoId <= 0) json_fail('Activo inválido.');

  $st = db()->prepare("SELECT id FROM activos WHERE id=:id AND tenant_id=:t LIMIT 1");
  $st->execute(array(':id'=>$activoId, ':t'=>$tenantId));
  if (!$st->fetch()) json_fail('Activo no encontrado.');

  if (!isset($_FILES['foto']) || !is_uploaded_file($_FILES['foto']['tmp_name'])) {
    json_fail('No se recibió archivo.', array(
      'has_files' => isset($_FILES['foto']) ? 1 : 0,
      'post_size' => isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0
    ));
  }

  $f = $_FILES['foto'];
  if ((int)$f['error'] !== UPLOAD_ERR_OK) {
    json_fail('Error al subir archivo.', array(
      'upload_error' => (int)$f['error']
    ));
  }

  $maxBytes = 5 * 1024 * 1024; // 5MB
  if ((int)$f['size'] > $maxBytes) {
    json_fail('Archivo demasiado grande (máx 5MB).', array('size'=>(int)$f['size']));
  }

  $tmp = $f['tmp_name'];

  /* MIME real */
  $mime = '';
  if (function_exists('finfo_open')) {
    $fi = @finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) { $mime = (string)@finfo_file($fi, $tmp); @finfo_close($fi); }
  }
  if ($mime === '') $mime = (string)($f['type'] ?? '');

  $allowed = array(
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
  );

  if (!isset($allowed[$mime])) {
    json_fail('Formato no permitido. Usa JPG, PNG o WEBP.', array('mime'=>$mime));
  }

  $ext = $allowed[$mime];

  /* Resolver projectRoot y publicRoot robusto */
  $projectRoot = realpath(__DIR__ . '/../../');
  if (!$projectRoot) $projectRoot = __DIR__ . '/../../';

  $publicRoot = realpath($projectRoot . '/public');
  if (!$publicRoot || !is_dir($publicRoot)) {
    // fallback: asume que este archivo está en /public/ajax/ o /public/
    $fallback = realpath(__DIR__ . '/..');
    if ($fallback && is_dir($fallback)) $publicRoot = $fallback;
  }

  if (!$publicRoot || !is_dir($publicRoot)) {
    json_fail('No se encontró la carpeta /public.', array(
      'projectRoot' => $projectRoot,
      'publicRoot'  => $publicRoot
    ));
  }

  $relDir = 'uploads/activos/' . (int)$tenantId;
  $absDir = rtrim($publicRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);

  if (!is_dir($absDir)) {
    @mkdir($absDir, 0775, true);
  }
  if (!is_dir($absDir)) {
    json_fail('No se pudo crear carpeta de carga.', array('absDir'=>$absDir));
  }
  if (!is_writable($absDir)) {
    json_fail('La carpeta de carga no tiene permisos de escritura.', array('absDir'=>$absDir));
  }

  /* 1 foto por activo: borrar otras extensiones */
  $baseName = 'activo_' . (int)$activoId;
  $variants = array('jpg','png','webp');
  foreach ($variants as $v) {
    $p = $absDir . DIRECTORY_SEPARATOR . $baseName . '.' . $v;
    if (is_file($p) && $v !== $ext) @unlink($p);
  }

  $filename = $baseName . '.' . $ext;
  $absPath  = $absDir . DIRECTORY_SEPARATOR . $filename;

  if (!@move_uploaded_file($tmp, $absPath)) {
    $last = error_get_last();
    json_fail('No se pudo guardar el archivo.', array(
      'absPath' => $absPath,
      'detail'  => $last ? $last['message'] : ''
    ));
  }

  @chmod($absPath, 0644);

  $relPath = $relDir . '/' . $filename;

  $up = db()->prepare("
    UPDATE activos
    SET foto_path=:p, foto_mime=:m, foto_updated_en=:w
    WHERE id=:id AND tenant_id=:t
    LIMIT 1
  ");
  $up->execute(array(
    ':p'  => $relPath,
    ':m'  => $mime,
    ':w'  => date('Y-m-d H:i:s'),
    ':id' => $activoId,
    ':t'  => $tenantId
  ));

  json_ok(array('path'=>$relPath,'mime'=>$mime));

} catch (Exception $e) {
  json_fail('Error interno del servidor.', array('error'=>$e->getMessage()), 500);
}
