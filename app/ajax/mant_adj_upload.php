<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireLogin();

$tenantId = Auth::tenantId();

/* userId robusto (porque en tu proyecto Auth::userId() no existe) */
$userId = null;
if (method_exists('Auth', 'userId')) $userId = Auth::userId();
elseif (method_exists('Auth', 'id')) $userId = Auth::id();
elseif (method_exists('Auth', 'user')) {
  $u = Auth::user();
  if (is_array($u) && isset($u['id'])) $userId = (int)$u['id'];
}

$mantenimientoId = (int)($_POST['mantenimiento_id'] ?? 0);
$nota = trim((string)($_POST['nota'] ?? ''));

if ($mantenimientoId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Mantenimiento inválido']);
  exit;
}

if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'No se recibió archivo']);
  exit;
}

/* Validar que mantenimiento pertenezca al tenant */
$chk = db()->prepare("SELECT id FROM mantenimientos WHERE id=:id AND tenant_id=:t LIMIT 1");
$chk->execute([':id'=>$mantenimientoId, ':t'=>$tenantId]);
if (!$chk->fetch()) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}

/* Detectar tabla real */
$adjTable = null;
$te = db()->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('mant_adjuntos','mantenimientos_adjuntos') LIMIT 1");
$te->execute();
$r = $te->fetch();
if ($r && !empty($r['table_name'])) $adjTable = $r['table_name'];

if (!$adjTable) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No existe tabla de adjuntos (mant_adjuntos / mantenimientos_adjuntos).']);
  exit;
}

/* Leer columnas reales */
$colsQ = db()->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name=:t");
$colsQ->execute([':t'=>$adjTable]);
$cols = array_map(function($x){ return $x['column_name']; }, $colsQ->fetchAll());

/* Mapear columnas */
$colArchivo = null; foreach (['archivo','ruta','path','filename','nombre_archivo','stored_name'] as $c) { if (in_array($c,$cols,true)) { $colArchivo=$c; break; } }
$colNombre  = null; foreach (['nombre_original','nombre','original_name','archivo_nombre','file_name'] as $c) { if (in_array($c,$cols,true)) { $colNombre=$c; break; } }
$colMime    = null; foreach (['mime','mime_type','tipo_mime'] as $c) { if (in_array($c,$cols,true)) { $colMime=$c; break; } }
$colTam     = null; foreach (['tamano','size','peso'] as $c) { if (in_array($c,$cols,true)) { $colTam=$c; break; } }
$colNota    = null; foreach (['nota','observacion','comentario'] as $c) { if (in_array($c,$cols,true)) { $colNota=$c; break; } }
$colCreadoEn= null; foreach (['creado_en','created_at','subido_en','fecha_subida'] as $c) { if (in_array($c,$cols,true)) { $colCreadoEn=$c; break; } }
$colCreadoPor= null; foreach (['creado_por','user_id','usuario_id'] as $c) { if (in_array($c,$cols,true)) { $colCreadoPor=$c; break; } }

if (!$colArchivo || !in_array('tenant_id',$cols,true) || !in_array('mantenimiento_id',$cols,true)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'La tabla de adjuntos no tiene columnas mínimas (tenant_id, mantenimiento_id, archivo/ruta).']);
  exit;
}

/* Guardar archivo físico en public/uploads/mantenimientos/{tenant}/{mantenimiento}/ */
$origName = (string)($_FILES['archivo']['name'] ?? 'archivo');
$origName = basename($origName);
$size = (int)($_FILES['archivo']['size'] ?? 0);

$mime = '';
if (class_exists('finfo')) {
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = (string)$fi->file($_FILES['archivo']['tmp_name']);
}

/* límite sugerido 10MB */
if ($size > 10 * 1024 * 1024) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'El archivo supera 10MB.']);
  exit;
}

/* nombre guardado */
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$safeExt = preg_replace('/[^a-z0-9]+/i', '', $ext);
$stored = uniqid('adj_', true) . ($safeExt ? ('.'.$safeExt) : '');

/* rutas */
$publicRoot = realpath(__DIR__ . '/../../public');
if (!$publicRoot) $publicRoot = __DIR__ . '/../../public';

$dir = $publicRoot . '/uploads/mantenimientos/' . (int)$tenantId . '/' . (int)$mantenimientoId;
if (!is_dir($dir)) {
  @mkdir($dir, 0775, true);
}
$destAbs = $dir . '/' . $stored;

/* mover */
if (!@move_uploaded_file($_FILES['archivo']['tmp_name'], $destAbs)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No se pudo guardar el archivo en el servidor. Verifica permisos de carpeta.']);
  exit;
}

/* ruta relativa para guardar en DB (clave para que download lo encuentre) */
$relPath = 'uploads/mantenimientos/' . (int)$tenantId . '/' . (int)$mantenimientoId . '/' . $stored;

/* Insert dinámico */
$fields = ['tenant_id','mantenimiento_id', $colArchivo];
$values = [':tenant_id',':mantenimiento_id', ':archivo'];
$params = [
  ':tenant_id'=>$tenantId,
  ':mantenimiento_id'=>$mantenimientoId,
  ':archivo'=>$relPath
];

if ($colNombre) { $fields[] = $colNombre; $values[]=':nombre'; $params[':nombre']=$origName; }
if ($colMime)   { $fields[] = $colMime;   $values[]=':mime';   $params[':mime']=$mime ?: null; }
if ($colTam)    { $fields[] = $colTam;    $values[]=':tam';    $params[':tam']=$size; }
if ($colNota)   { $fields[] = $colNota;   $values[]=':nota';   $params[':nota']=($nota!==''?$nota:null); }
if ($colCreadoPor && $userId) { $fields[]=$colCreadoPor; $values[]=':uid'; $params[':uid']=$userId; }
/* creado_en: si existe y NO tiene default, lo ponemos */
if ($colCreadoEn) {
  $fields[] = $colCreadoEn; $values[]='NOW()';
}

$sql = "INSERT INTO $adjTable (" . implode(',', array_map(function($f){ return "`$f`"; }, $fields)) . ")
        VALUES (" . implode(',', $values) . ")";

$ins = db()->prepare($sql);
$ok = $ins->execute($params);

echo json_encode(['ok'=>(bool)$ok]);
