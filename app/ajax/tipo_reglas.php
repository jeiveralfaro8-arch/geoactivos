<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireLogin();

$tenantId = Auth::tenantId();
$tipoId = (int)($_GET['tipo_activo_id'] ?? 0);

function out($arr){
  echo json_encode($arr);
  exit;
}

if ($tipoId <= 0) {
  out([
    'ok'=>true,
    'usa_red'=>0,
    'usa_software'=>0,
    'es_biomedico'=>0,
    'requiere_calibracion'=>0,
    'periodicidad_meses'=>null,
    'familia'=>'',
    'tipo_codigo'=>'',
    'tipo_nombre'=>''
  ]);
}

$st = db()->prepare("
  SELECT r.usa_red, r.usa_software, r.es_biomedico, r.requiere_calibracion, r.periodicidad_meses,
         t.familia, t.codigo, t.nombre
  FROM tipos_activo t
  LEFT JOIN tipo_activo_reglas r
    ON r.tenant_id=t.tenant_id AND r.tipo_activo_id=t.id
  WHERE t.tenant_id=:t AND t.id=:id
  LIMIT 1
");
$st->execute([':t'=>$tenantId, ':id'=>$tipoId]);
$row = $st->fetch();

if (!$row) out(['ok'=>false,'msg'=>'Tipo de activo no encontrado']);

$familia = (string)($row['familia'] ?? '');
$codigo  = strtoupper(trim((string)($row['codigo'] ?? '')));
$nombre  = strtolower(trim((string)($row['nombre'] ?? '')));

$esComputo = in_array($codigo, ['PC','SRV','LAP','NB','SERVER','NAS','NVR'], true)
          || strpos($nombre,'comput') !== false
          || strpos($nombre,'servid') !== false
          || strpos($nombre,'laptop') !== false;

$usa_red = ($row['usa_red'] === null) ? ($esComputo ? 1 : 0) : (int)$row['usa_red'];
$usa_sw  = ($row['usa_software'] === null) ? ($esComputo ? 1 : 0) : (int)$row['usa_software'];

$es_biomed = ($row['es_biomedico'] === null) ? 0 : (int)$row['es_biomedico'];
$req_cal   = ($row['requiere_calibracion'] === null) ? 0 : (int)$row['requiere_calibracion'];
$per_meses = $row['periodicidad_meses'];

// Si familia BIOMED, por defecto biomédico
if (strtoupper($familia) === 'BIOMED' && $es_biomed === 0) $es_biomed = 1;

out([
  'ok'=>true,
  'usa_red'=>$usa_red,
  'usa_software'=>$usa_sw,
  'es_biomedico'=>$es_biomed,
  'requiere_calibracion'=>$req_cal,
  'periodicidad_meses'=>($per_meses === null ? null : (int)$per_meses),

  // NUEVO (para sugerir categoría)
  'familia'=>$familia,
  'tipo_codigo'=>$codigo,
  'tipo_nombre'=>(string)($row['nombre'] ?? '')
]);
