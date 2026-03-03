<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

Auth::requireLogin();

$tenantId = Auth::tenantId();
$tipoId = (int)($_GET['tipo_activo_id'] ?? 0);

if ($tipoId <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'tipo_activo_id inválido']);
  exit;
}

$st = db()->prepare("SELECT codigo FROM tipos_activo WHERE id=:id AND tenant_id=:t LIMIT 1");
$st->execute([':id'=>$tipoId, ':t'=>$tenantId]);
$row = $st->fetch();

$pref = $row ? strtoupper(trim((string)$row['codigo'])) : '';
if ($pref === '') {
  echo json_encode(['ok'=>false,'msg'=>'Este tipo no tiene código (prefijo).']);
  exit;
}

// Buscar el mayor consecutivo existente en activos para ese prefijo (por tenant)
// Formato: PREFIJO-0001
$st2 = db()->prepare("
  SELECT MAX(CAST(SUBSTRING_INDEX(codigo_interno,'-',-1) AS UNSIGNED)) AS max_n
  FROM activos
  WHERE tenant_id=:t
    AND codigo_interno LIKE CONCAT(:p,'-%')
");
$st2->execute([':t'=>$tenantId, ':p'=>$pref]);
$maxN = (int)($st2->fetchColumn() ?: 0);

$next = $maxN + 1;

// Robustez: evitar devolver un código que ya exista (por huecos o concurrencia)
$try = 0;
$codigo = '';
while ($try < 50) {
  $codigo = $pref . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);

  $chk = db()->prepare("
    SELECT id
    FROM activos
    WHERE tenant_id=:t AND codigo_interno=:c
    LIMIT 1
  ");
  $chk->execute([':t'=>$tenantId, ':c'=>$codigo]);

  if (!$chk->fetch()) {
    echo json_encode(['ok'=>true,'codigo'=>$codigo,'prefijo'=>$pref,'next'=>$next]);
    exit;
  }

  $next++;
  $try++;
}

echo json_encode(['ok'=>false,'msg'=>'No fue posible generar un código interno libre. Intenta de nuevo.']);
exit;
