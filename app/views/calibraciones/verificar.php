<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = (int)($_GET['tenant'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));

if ($token === '') {
  http_response_code(400);
  exit("Solicitud inválida");
}

/* =========================================================
   Resolver tenant por token (ROBUSTO + PDO SAFE)
========================================================= */
if ($tenantId <= 0) {
  $ts = db()->prepare("
    SELECT tenant_id
    FROM calibraciones
    WHERE (public_token = :k1 OR token_verificacion = :k2)
      AND COALESCE(eliminado,0)=0
    LIMIT 1
  ");
  $ts->execute([
    ':k1' => $token,
    ':k2' => $token
  ]);
  $tmp = $ts->fetch();
  if ($tmp && isset($tmp['tenant_id'])) {
    $tenantId = (int)$tmp['tenant_id'];
  }
}

if ($tenantId <= 0) {
  http_response_code(400);
  exit("Solicitud inválida (tenant)");
}

/* =========================================================
   Consulta pública por token (SIN LOGIN)
   FIX: NO existe c.proxima_calibracion en tu BD -> se elimina
========================================================= */
$st = db()->prepare("
  SELECT
    c.id,
    c.estado,
    c.resultado_global AS resultado,
    c.fecha_inicio AS fecha_calibracion,
    c.metodo,
    c.norma_ref AS norma_referencia,
    c.observaciones,

    c.tecnico_nombre,
    c.tecnico_cargo,
    c.tecnico_tarjeta_prof,

    c.recibido_por_nombre,
    c.recibido_por_cargo,

    a.codigo_interno,
    a.nombre AS activo_nombre,
    a.modelo,
    a.serial,

    ar.nombre AS area,
    s.nombre AS sede

  FROM calibraciones c
  INNER JOIN activos a ON a.id=c.activo_id AND a.tenant_id=c.tenant_id
  LEFT JOIN areas ar ON ar.id=a.area_id AND ar.tenant_id=a.tenant_id
  LEFT JOIN sedes s ON s.id=ar.sede_id AND s.tenant_id=a.tenant_id

  WHERE c.tenant_id = :t
    AND (c.public_token = :k1 OR c.token_verificacion = :k2)
    AND COALESCE(c.eliminado,0)=0
  LIMIT 1
");

$st->execute([
  ':t'  => $tenantId,
  ':k1' => $token,
  ':k2' => $token
]);

$cal = $st->fetch();

if (!$cal) {
  http_response_code(404);
  exit("Certificado no encontrado o token inválido");
}

/* =========================================================
   Helpers
========================================================= */
function f10($v){ return $v ? substr((string)$v,0,10) : '—'; }

$ubic = '—';
if (!empty($cal['sede'])) $ubic = $cal['sede'];
if (!empty($cal['area'])) $ubic .= ' - '.$cal['area'];

$estado = strtoupper(trim((string)($cal['estado'] ?? '')));
$resultado = strtoupper(trim((string)($cal['resultado'] ?? '')));

$recNom = trim((string)($cal['recibido_por_nombre'] ?? ''));
$recCar = trim((string)($cal['recibido_por_cargo'] ?? ''));

/* Si está en proceso y no hay recibido, mostrar pendiente */
if ($recNom === '' && $estado !== 'CERRADA') {
  $recNom = 'Pendiente de recepción';
  $recCar = 'Calibración en proceso';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verificación certificado #<?= (int)$cal['id'] ?></title>
<style>
body{margin:0;background:#0b1220;color:#e5e7eb;font-family:Arial}
.wrap{max-width:980px;margin:auto;padding:18px}
.card{background:#0f172a;border:1px solid #1f2937;border-radius:14px;padding:14px}
h1{font-size:18px;margin:0 0 10px}
.sub{color:#94a3b8;font-size:12px;margin:0 0 12px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.box{background:#0b1326;border:1px solid #1f2937;border-radius:12px;padding:10px}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border:1px solid #1f2937;padding:8px;vertical-align:top}
th{background:#0b1326;color:#cbd5e1;text-align:left;width:160px}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:800;margin-right:6px}
.ok{background:#064e3b;color:#d1fae5}
.bad{background:#7f1d1d;color:#fee2e2}
.warn{background:#78350f;color:#fef3c7}
.gray{background:#334155;color:#e2e8f0}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
  <h1>Verificación de certificado de calibración</h1>
  <p class="sub">GeoActivos · Consulta pública (token)</p>

  <?php
    $clsRes = 'gray';
    if ($resultado === 'CONFORME') $clsRes = 'ok';
    else if ($resultado === 'NO_CONFORME') $clsRes = 'bad';
    else if ($resultado === 'OBSERVADO') $clsRes = 'warn';

    $clsEst = 'gray';
    if ($estado === 'CERRADA') $clsEst = 'ok';
    else if ($estado === 'EN_PROCESO') $clsEst = 'warn';
    else if ($estado === 'ANULADA') $clsEst = 'bad';
  ?>

  <div style="margin:8px 0 12px;">
    <span class="badge <?= $clsRes ?>">Resultado: <?= htmlspecialchars($resultado ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
    <span class="badge <?= $clsEst ?>">Estado: <?= htmlspecialchars($estado ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
    <span class="badge gray">ID: #<?= (int)$cal['id'] ?></span>
  </div>

  <div class="grid">
    <div class="box">
      <div style="color:#94a3b8;font-size:12px;margin:0 0 8px;">Equipo</div>
      <table>
        <tr><th>Código</th><td><?= htmlspecialchars((string)($cal['codigo_interno'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>Nombre</th><td><?= htmlspecialchars((string)($cal['activo_nombre'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>Modelo</th><td><?= htmlspecialchars((string)($cal['modelo'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>Serial</th><td><?= htmlspecialchars((string)($cal['serial'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>Ubicación</th><td><?= htmlspecialchars((string)$ubic, ENT_QUOTES, 'UTF-8') ?></td></tr>
      </table>
    </div>

    <div class="box">
      <div style="color:#94a3b8;font-size:12px;margin:0 0 8px;">Calibración</div>
      <table>
        <tr><th>Fecha</th><td><?= htmlspecialchars(f10($cal['fecha_calibracion']), ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>Método</th><td><?= htmlspecialchars((string)($cal['metodo'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>Norma</th><td><?= htmlspecialchars((string)($cal['norma_referencia'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>Observaciones</th><td><?= htmlspecialchars((string)($cal['observaciones'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
      </table>
    </div>
  </div>

  <div class="grid" style="margin-top:10px;">
    <div class="box">
      <div style="color:#94a3b8;font-size:12px;margin:0 0 8px;">Técnico</div>
      <table>
        <tr><th>Nombre</th><td><?= htmlspecialchars((string)($cal['tecnico_nombre'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>Cargo</th><td><?= htmlspecialchars((string)($cal['tecnico_cargo'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>Tarjeta</th><td><?= htmlspecialchars((string)($cal['tecnico_tarjeta_prof'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
      </table>
    </div>

    <div class="box">
      <div style="color:#94a3b8;font-size:12px;margin:0 0 8px;">Recibido por</div>
      <table>
        <tr><th>Nombre</th><td><?= htmlspecialchars((string)($recNom ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><th>Cargo</th><td><?= htmlspecialchars((string)($recCar ?: '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
      </table>
    </div>
  </div>

  <div style="margin-top:10px;color:#94a3b8;font-size:12px;">
    Token consultado: <?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>
  </div>

</div>
</div>
</body>
</html>
