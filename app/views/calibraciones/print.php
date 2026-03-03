<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }

$st = db()->prepare("
  SELECT
    c.*,
    a.codigo_interno, a.nombre AS activo_nombre, a.modelo, a.serial, a.placa,
    ar.nombre AS area, s.nombre AS sede
  FROM calibraciones c
  INNER JOIN activos a ON a.id=c.activo_id AND a.tenant_id=c.tenant_id
  LEFT JOIN areas ar ON ar.id=a.area_id AND ar.tenant_id=a.tenant_id
  LEFT JOIN sedes s ON s.id=ar.sede_id AND s.tenant_id=a.tenant_id
  WHERE c.id=:id AND c.tenant_id=:t AND c.eliminado=0
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$cal = $st->fetch();
if (!$cal) { http_response_code(404); echo "Calibración no encontrada"; exit; }

$pat = db()->prepare("
  SELECT
    p.nombre, p.marca, p.modelo, p.serial,
    p.certificado_numero, p.certificado_emisor,
    p.certificado_fecha, p.certificado_vigencia_hasta
  FROM calibraciones_patrones cp
  INNER JOIN patrones p ON p.id=cp.patron_id AND p.tenant_id=cp.tenant_id
  WHERE cp.tenant_id=:t AND cp.calibracion_id=:c
  ORDER BY p.nombre ASC
");
$pat->execute([':t'=>$tenantId, ':c'=>$id]);
$patrones = $pat->fetchAll();

$pt = db()->prepare("
  SELECT orden, magnitud, unidad, valor_referencia, valor_equipo, error, tolerancia, cumple
  FROM calibraciones_puntos
  WHERE tenant_id=:t AND calibracion_id=:c
  ORDER BY orden ASC, id ASC
");
$pt->execute([':t'=>$tenantId, ':c'=>$id]);
$puntos = $pt->fetchAll();

$empresa = [
  'nombre'=>'', 'nit'=>'', 'telefono'=>'', 'email'=>'', 'direccion'=>'', 'logo_path'=>''
];
try {
  $e = db()->prepare("
    SELECT nombre, nit, telefono, email, direccion, logo_path
    FROM empresas
    WHERE tenant_id=:t
    LIMIT 1
  ");
  $e->execute([':t'=>$tenantId]);
  $er = $e->fetch();
  if ($er) $empresa = array_merge($empresa, $er);
} catch (Exception $ex) {}

function f10($v){ return $v ? substr((string)$v,0,10) : '—'; }
function txt_cumple($c){
  if ($c === null || $c === '') return '—';
  return ((int)$c===1) ? 'CUMPLE' : 'NO CUMPLE';
}

$ubic = '';
if (!empty($cal['sede'])) $ubic .= $cal['sede'];
if (!empty($cal['area'])) $ubic .= ($ubic ? ' - ' : '') . $cal['area'];
if ($ubic === '') $ubic = '—';

$logoUrl = '';
if (!empty($empresa['logo_path'])) {
  $logoUrl = rtrim((string)e(base_url()),'/') . '/' . ltrim((string)$empresa['logo_path'], '/');
}

?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificado de Calibración #<?= (int)$cal['id'] ?></title>
<style>
  @page { size: letter; margin: 12mm; }
  body { font-family: Arial, sans-serif; color:#111827; margin:0; }
  .sheet { width: 100%; }
  .top { display:flex; align-items:center; justify-content:space-between; gap:12px; border-bottom:2px solid #111827; padding-bottom:10px; }
  .brand { display:flex; gap:12px; align-items:center; }
  .logo { width:70px; height:70px; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; display:flex; align-items:center; justify-content:center; background:#fff; }
  .logo img { width:100%; height:100%; object-fit:contain; }
  .h1 { font-size:18px; font-weight:900; margin:0; }
  .muted { color:#374151; font-size:12px; margin-top:2px; }
  .right { text-align:right; }
  .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px; font-weight:700; }
  .b-ok{ background:#dcfce7; color:#14532d; }
  .b-bad{ background:#fee2e2; color:#7f1d1d; }
  .b-warn{ background:#fef3c7; color:#78350f; }
  .b-gray{ background:#e5e7eb; color:#111827; }
  h2 { font-size:14px; margin:14px 0 6px; }
  table { width:100%; border-collapse:collapse; font-size:12px; }
  th, td { border:1px solid #e5e7eb; padding:6px 8px; vertical-align:top; }
  th { background:#f3f4f6; text-align:left; }
  .grid { display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:12px; }
  .box { border:1px solid #e5e7eb; border-radius:10px; padding:10px; }
  .sigbox { height:90px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; }
  .sigbox img { max-width:100%; max-height:100%; }
  .footer { margin-top:10px; font-size:11px; color:#374151; }
  .noprint { margin:10px 0; }
  @media print { .noprint { display:none; } }
</style>
</head>
<body>
<div class="sheet">

  <div class="noprint">
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
  </div>

  <div class="top">
    <div class="brand">
      <div class="logo">
        <?php if ($logoUrl): ?><img src="<?= $logoUrl ?>" alt="Logo"><?php else: ?><span style="color:#6b7280;font-size:12px;">LOGO</span><?php endif; ?>
      </div>
      <div>
        <div class="h1"><?= e($empresa['nombre'] ?: 'CERTIFICADO DE CALIBRACIÓN') ?></div>
        <div class="muted">
          <?= $empresa['nit'] ? 'NIT: '.e($empresa['nit']).' · ' : '' ?>
          <?= e($empresa['telefono'] ?: '') ?>
          <?= $empresa['email'] ? ' · '.e($empresa['email']) : '' ?>
        </div>
        <div class="muted"><?= e($empresa['direccion'] ?: '') ?></div>
      </div>
    </div>

    <div class="right">
      <div class="h1">Certificado de Calibración</div>
      <div class="muted">No. <?= (int)$cal['id'] ?></div>
      <div class="muted">Fecha: <b><?= e(f10($cal['fecha_calibracion'])) ?></b></div>
      <div class="muted">Próxima: <b><?= e(f10($cal['proxima_calibracion'])) ?></b></div>
      <?php
        $res = (string)$cal['resultado'];
        $cls = 'b-gray';
        if ($res==='CONFORME') $cls='b-ok';
        elseif ($res==='NO_CONFORME') $cls='b-bad';
        elseif ($res==='OBSERVADO') $cls='b-warn';
      ?>
      <div style="margin-top:6px;"><span class="badge <?= $cls ?>"><?= e($res) ?></span></div>
    </div>
  </div>

  <div class="grid">
    <div class="box">
      <h2>Datos del equipo</h2>
      <table>
        <tr><th style="width:160px;">Código</th><td><?= e($cal['codigo_interno'] ?: ('Activo #'.(int)$cal['activo_id'])) ?></td></tr>
        <tr><th>Nombre</th><td><?= e($cal['activo_nombre'] ?: '—') ?></td></tr>
        <tr><th>Modelo</th><td><?= e($cal['modelo'] ?: '—') ?></td></tr>
        <tr><th>Serial</th><td><?= e($cal['serial'] ?: '—') ?></td></tr>
        <tr><th>Ubicación</th><td><?= e($ubic) ?></td></tr>
      </table>
    </div>

    <div class="box">
      <h2>Datos del proceso</h2>
      <table>
        <tr><th style="width:160px;">Método</th><td><?= e($cal['metodo'] ?: '—') ?></td></tr>
        <tr><th>Norma/Referencia</th><td><?= e($cal['norma_referencia'] ?: '—') ?></td></tr>
        <tr><th>Condiciones</th><td><?= e($cal['condiciones_ambientales'] ?: '—') ?></td></tr>
        <tr><th>Observaciones</th><td><?= e($cal['observaciones'] ?: '—') ?></td></tr>
      </table>
    </div>
  </div>

  <h2>Patrones utilizados</h2>
  <table>
    <thead>
      <tr>
        <th>Patrón</th>
        <th>Certificado</th>
        <th>Emisor</th>
        <th>Fecha</th>
        <th>Vigencia</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$patrones): ?>
        <tr><td colspan="5" class="muted">No se registraron patrones.</td></tr>
      <?php endif; ?>
      <?php foreach ($patrones as $p): ?>
        <tr>
          <td>
            <b><?= e($p['nombre']) ?></b><br>
            <span class="muted">
              <?= e(trim(($p['marca'] ?: '').' '.($p['modelo'] ?: ''))) ?>
              <?= !empty($p['serial']) ? ' · S/N '.e($p['serial']) : '' ?>
            </span>
          </td>
          <td><?= e($p['certificado_numero'] ?: '—') ?></td>
          <td><?= e($p['certificado_emisor'] ?: '—') ?></td>
          <td><?= e(f10($p['certificado_fecha'])) ?></td>
          <td><?= e(f10($p['certificado_vigencia_hasta'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Resultados (puntos)</h2>
  <table>
    <thead>
      <tr>
        <th>Orden</th>
        <th>Magnitud</th>
        <th>Unidad</th>
        <th style="text-align:right;">Referencia</th>
        <th style="text-align:right;">Equipo</th>
        <th style="text-align:right;">Error</th>
        <th style="text-align:right;">Tolerancia</th>
        <th style="text-align:right;">Cumple</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$puntos): ?>
        <tr><td colspan="8" class="muted">No hay puntos registrados.</td></tr>
      <?php endif; ?>
      <?php foreach ($puntos as $r): ?>
        <tr>
          <td><?= (int)($r['orden'] ?? 0) ?></td>
          <td><?= e($r['magnitud'] ?: '—') ?></td>
          <td><?= e($r['unidad'] ?: '—') ?></td>
          <td style="text-align:right;"><?= ($r['valor_referencia']===null?'—':e($r['valor_referencia'])) ?></td>
          <td style="text-align:right;"><?= ($r['valor_equipo']===null?'—':e($r['valor_equipo'])) ?></td>
          <td style="text-align:right;"><?= ($r['error']===null?'—':e($r['error'])) ?></td>
          <td style="text-align:right;"><?= ($r['tolerancia']===null?'—':e($r['tolerancia'])) ?></td>
          <td style="text-align:right;"><b><?= e(txt_cumple($r['cumple'])) ?></b></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="grid" style="margin-top:14px;">
    <div class="box">
      <h2>Técnico</h2>
      <div class="muted"><b>Nombre:</b> <?= e($cal['tecnico_nombre'] ?: '—') ?></div>
      <div class="muted"><b>Cargo:</b> <?= e($cal['tecnico_cargo'] ?: '—') ?></div>
      <div class="muted"><b>Tarjeta:</b> <?= e($cal['tecnico_tarjeta_prof'] ?: '—') ?></div>
      <div style="margin-top:8px;" class="sigbox">
        <?php if (!empty($cal['firma_tecnico_png'])): ?>
          <img src="<?= e($cal['firma_tecnico_png']) ?>" alt="Firma técnico">
        <?php else: ?>
          <span class="muted">Sin firma</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="box">
      <h2>Recibido por</h2>
      <div class="muted"><b>Nombre:</b> <?= e($cal['recibido_por_nombre'] ?: '—') ?></div>
      <div class="muted"><b>Cargo:</b> <?= e($cal['recibido_por_cargo'] ?: '—') ?></div>
      <div style="margin-top:8px;" class="sigbox">
        <?php if (!empty($cal['recibido_firma_png'])): ?>
          <img src="<?= e($cal['recibido_firma_png']) ?>" alt="Firma recibido">
        <?php else: ?>
          <span class="muted">Sin firma</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="footer">
    Este certificado fue generado por el sistema GeoActivos. Verificación interna: Calibración #<?= (int)$cal['id'] ?>.
  </div>

</div>
</body>
</html>
