<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  echo "Adjunto inválido";
  exit;
}

/* Buscar adjunto */
$st = db()->prepare("
  SELECT id, tenant_id, ruta, mime, nombre_original
  FROM activos_adjuntos
  WHERE id = :id AND tenant_id = :t
  LIMIT 1
");
$st->execute([':id' => $id, ':t' => $tenantId]);
$adj = $st->fetch();

if (!$adj) {
  http_response_code(404);
  echo "Adjunto no encontrado";
  exit;
}

/* -------------------------
   Resolver ruta física real (robusta)
------------------------- */
$rawRel = (string)$adj['ruta'];
$rawRel = trim($rawRel);

/* Normaliza: decode de URL y separadores */
$rel = rawurldecode($rawRel);
$rel = str_replace('\\', '/', $rel);

/* Quita prefijos típicos si los guardaron por error */
$rel = preg_replace('#^(https?://[^/]+/)#i', '', $rel); // por si guardaron URL completa
$rel = ltrim($rel, "/ \t\n\r\0\x0B");

/* Bloqueo básico de traversal */
if (strpos($rel, '../') !== false || strpos($rel, '..\\') !== false) {
  http_response_code(400);
  echo "Ruta inválida";
  exit;
}

/* Raíces conocidas */
$projectRoot = realpath(__DIR__ . '/../../');
if (!$projectRoot) $projectRoot = __DIR__ . '/../../';

$publicRoot = realpath($projectRoot . '/public');
if (!$publicRoot) $publicRoot = $projectRoot . '/public';

/* Construir candidatos */
$candidates = array();

/* 1) Si en DB ya viene algo como "public/xxxx" lo resolvemos desde projectRoot */
if (stripos($rel, 'public/') === 0) {
  $candidates[] = rtrim($projectRoot, '/\\') . '/' . $rel;
}

/* 2) Ruta normal: se asume relativa a public/ */
$candidates[] = rtrim($publicRoot, '/\\') . '/' . $rel;

/* 3) A veces guardan relativo a la raíz del proyecto (sin public) */
$candidates[] = rtrim($projectRoot, '/\\') . '/' . $rel;

/* 4) Si guardaron con prefijo "uploads/..." u otra carpeta conocida */
if (stripos($rel, 'uploads/') === 0) {
  $candidates[] = rtrim($publicRoot, '/\\') . '/' . $rel;   // public/uploads/...
  $candidates[] = rtrim($projectRoot, '/\\') . '/' . $rel;  // project/uploads/...
}

/* 5) Si en DB guardaron ruta absoluta (Windows/Linux), solo permitir si cae dentro del proyecto */
if (preg_match('#^([a-zA-Z]:/|/|\\\\\\\\)#', $rel)) {
  $candidates[] = $rel;
}

/* Buscar el primer archivo existente y válido dentro del proyecto */
$file = '';
$fileReal = '';

/* Directorios permitidos (seguridad) */
$allowedBases = array();
$allowedBases[] = realpath($projectRoot);
$allowedBases[] = realpath($publicRoot);

foreach ($candidates as $cand) {
  $cand = str_replace('\\', '/', $cand);
  $cand = preg_replace('#/+#', '/', $cand);

  if (!is_file($cand)) continue;

  $r = realpath($cand);
  if (!$r) continue;

  $rNorm = str_replace('\\', '/', $r);

  /* Validar que esté dentro de projectRoot o publicRoot */
  $ok = false;
  foreach ($allowedBases as $base) {
    if (!$base) continue;
    $baseNorm = str_replace('\\', '/', $base);
    if (strpos($rNorm, rtrim($baseNorm, '/') . '/') === 0 || $rNorm === $baseNorm) {
      $ok = true;
      break;
    }
  }

  if ($ok) {
    $file = $cand;
    $fileReal = $r;
    break;
  }
}

if ($file === '' || !is_file($file)) {
  http_response_code(404);
  echo "Archivo no existe en disco";
  exit;
}

/* Nombre de descarga */
$downloadName = (string)$adj['nombre_original'];
$downloadName = trim($downloadName);
$downloadName = $downloadName !== '' ? basename($downloadName) : basename($file);

/* MIME */
$mime = (string)$adj['mime'];
$mime = trim($mime);

if ($mime === '' || $mime === 'application/octet-stream') {
  if (function_exists('finfo_open')) {
    $fi = @finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
      $det = @finfo_file($fi, $file);
      @finfo_close($fi);
      if (is_string($det) && $det !== '') $mime = $det;
    }
  }
  if ($mime === '') $mime = 'application/octet-stream';
}

/* Headers de descarga */
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($file);
exit;
