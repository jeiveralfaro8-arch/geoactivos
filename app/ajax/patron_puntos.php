<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

Auth::requireLogin();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$tenantId = (int)Auth::tenantId();
$patronId = (int)($_GET['patron_id'] ?? 0);

if ($patronId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'msg'=>'patron_id inválido'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

/* =========================
   Helpers
========================= */
function table_exists_geo($table) {
  $q = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $q->execute([':t'=>$table]);
  return (bool)$q->fetch();
}
function column_exists_geo($table, $col){
  $q = db()->prepare("
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = :t
      AND column_name = :c
    LIMIT 1
  ");
  $q->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$q->fetch();
}

try {

  // Validar patrón (mismo tenant, activo, no eliminado)
  $st = db()->prepare("
    SELECT
      id, tenant_id, nombre, tipo_patron, marca, modelo, serial,
      certificado_numero, certificado_emisor, certificado_fecha, certificado_vigencia_hasta
    FROM patrones
    WHERE id=:id AND tenant_id=:t AND COALESCE(eliminado,0)=0 AND estado='ACTIVO'
    LIMIT 1
  ");
  $st->execute([':id'=>$patronId, ':t'=>$tenantId]);
  $p = $st->fetch();

  if (!$p) {
    http_response_code(404);
    echo json_encode(['ok'=>false, 'msg'=>'Patrón no encontrado / no activo / no pertenece a tu empresa.'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  if (!table_exists_geo('patrones_puntos')) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'msg'=>'No existe la tabla patrones_puntos.'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  // Detectar columnas reales (para que funcione en geoactivos_clean y en tu PRO)
  $hasTenant   = column_exists_geo('patrones_puntos', 'tenant_id');
  $hasElim     = column_exists_geo('patrones_puntos', 'eliminado');
  $hasNota     = column_exists_geo('patrones_puntos', 'nota');

  // Armar SELECT robusto
  $cols = "orden, magnitud, unidad, valor_referencia, tolerancia";
  if ($hasNota) $cols .= ", nota";

  $where = "patron_id = :p";
  $params = [':p'=>$patronId];

  if ($hasTenant) { $where .= " AND tenant_id = :t"; $params[':t'] = $tenantId; }
  if ($hasElim)   { $where .= " AND COALESCE(eliminado,0)=0"; }

  $sql = "
    SELECT $cols
    FROM patrones_puntos
    WHERE $where
    ORDER BY orden ASC, id ASC
    LIMIT 500
  ";

  $pt = db()->prepare($sql);
  $pt->execute($params);
  $puntos = $pt->fetchAll();

  echo json_encode([
    'ok' => true,
    'patron' => [
      'id' => (int)$p['id'],
      'nombre' => (string)$p['nombre'],
      'tipo_patron' => (string)($p['tipo_patron'] ?? ''),
      'marca' => (string)($p['marca'] ?? ''),
      'modelo' => (string)($p['modelo'] ?? ''),
      'serial' => (string)($p['serial'] ?? ''),
      'certificado_numero' => (string)($p['certificado_numero'] ?? ''),
      'certificado_emisor' => (string)($p['certificado_emisor'] ?? ''),
      'certificado_fecha' => !empty($p['certificado_fecha']) ? substr((string)$p['certificado_fecha'],0,10) : '',
      'certificado_vigencia_hasta' => !empty($p['certificado_vigencia_hasta']) ? substr((string)$p['certificado_vigencia_hasta'],0,10) : '',
    ],
    'puntos' => $puntos
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'msg'=>'Error interno cargando puntos.',
    'debug'=> $e->getMessage()
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
