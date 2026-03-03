<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }

/* ---------------- Tenant (Empresa) ---------------- */
$tenant = null;
try {
  $tq = db()->prepare("SELECT id, nombre, nit, email, telefono, direccion, ciudad FROM tenants WHERE id=:t LIMIT 1");
  $tq->execute([':t'=>$tenantId]);
  $tenant = $tq->fetch();
} catch (Exception $e) { $tenant = null; }

function e2($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---------------- Activo (info compacta) ---------------- */
$st = db()->prepare("
  SELECT a.id, a.codigo_interno, a.nombre, a.estado, a.modelo, a.serial, a.placa,
         c.nombre AS categoria,
         t.nombre AS tipo, t.codigo AS tipo_codigo,
         ar.nombre AS area, s.nombre AS sede
  FROM activos a
  INNER JOIN categorias_activo c ON c.id = a.categoria_id AND c.tenant_id = a.tenant_id
  LEFT JOIN tipos_activo t ON t.id = a.tipo_activo_id AND t.tenant_id = a.tenant_id
  LEFT JOIN areas ar ON ar.id = a.area_id AND ar.tenant_id = a.tenant_id
  LEFT JOIN sedes s ON s.id = ar.sede_id AND s.tenant_id = a.tenant_id
  WHERE a.id=:id AND a.tenant_id=:t
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$activo = $st->fetch();

if (!$activo) { http_response_code(404); echo "Activo no encontrado"; exit; }

/* ---------------- URL destino del QR ---------------- */
$modo = ($_GET['to'] ?? 'hoja'); // 'hoja' | 'detalle'
if ($modo === 'detalle') {
  $dest = base_url() . "/index.php?route=activo_detalle&id=".(int)$activo['id'];
} else {
  $dest = base_url() . "/index.php?route=activo_hoja_vida&id=".(int)$activo['id'];
}

/* ---------------- Tamaño etiqueta (mm) ---------------- */
$w = (int)($_GET['w'] ?? 80);
$h = (int)($_GET['h'] ?? 50);
if ($w < 40) $w = 40;
if ($h < 25) $h = 25;
if ($w > 120) $w = 120;
if ($h > 80) $h = 80;

/* ---------------- Textos ---------------- */
$empresaNombre = $tenant && !empty($tenant['nombre']) ? (string)$tenant['nombre'] : '—';
$empresaNit    = $tenant && !empty($tenant['nit']) ? (string)$tenant['nit'] : '';

$ubic = '';
if (!empty($activo['sede'])) $ubic .= (string)$activo['sede'];
if (!empty($activo['area'])) $ubic .= ($ubic ? ' - ' : '') . (string)$activo['area'];
if ($ubic === '') $ubic = '—';

$tipoNombre = !empty($activo['tipo']) ? (string)$activo['tipo'] : '';
$tipoCod    = !empty($activo['tipo_codigo']) ? (string)$activo['tipo_codigo'] : '';
$tipoTxt    = ($tipoNombre === '') ? '—' : (($tipoCod !== '' ? ($tipoCod . ' · ') : '') . $tipoNombre);

$codigo = (string)($activo['codigo_interno'] ?? '');
$nombre = (string)($activo['nombre'] ?? '');
$estado = (string)($activo['estado'] ?? '');

function badge_estado($estado){
  $estado = (string)$estado;
  if ($estado === 'ACTIVO') return 'verde';
  if ($estado === 'EN_MANTENIMIENTO') return 'amarillo';
  if ($estado === 'BAJA') return 'rojo';
  return 'gris';
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Etiqueta QR · <?= e2($codigo) ?></title>

  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

  <style>
    body{ margin:0; font-family: Arial, Helvetica, sans-serif; color:#111; }
    .no-print{ padding:10px; text-align:right; }
    .btn{
      display:inline-block; padding:8px 10px; border-radius:6px;
      background:#0d6efd; color:#fff; text-decoration:none; font-size:13px;
      border:0; cursor:pointer;
    }
    .btn.secondary{ background:#6c757d; }

    .badge{
      display:inline-block;
      padding: 1px 6px;
      border-radius: 12px;
      font-size: 9px;
      font-weight:900;
      border:1px solid #111;
    }
    .verde{ background:#dcfce7; }
    .amarillo{ background:#fef9c3; }
    .rojo{ background:#fee2e2; }
    .gris{ background:#f3f4f6; }

    /* Mini carta */
    .paper{
      width: <?= (int)$w ?>mm;
      height: <?= (int)$h ?>mm;
      box-sizing:border-box;
      border: 1px solid #111;
      border-radius: 3mm;
      overflow:hidden;
      background:#fff;
    }
    .head{
      padding: 2.2mm 3.2mm;
      border-bottom:1px solid #111;
      background:#f8fafc;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 2mm;
    }
    .h-left{
      min-width:0;
    }
    .h-title{
      font-weight:900;
      font-size: 10px;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
      line-height:1.1;
    }
    .h-sub{
      font-size: 8px;
      color:#444;
      margin-top: .6mm;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .h-right{
      font-size:8px;
      text-align:right;
      line-height:1.1;
      color:#111;
      white-space:nowrap;
    }

    .body{
      padding: 3mm;
      display:flex;
      gap: 3mm;
      align-items: stretch;
    }
    .left{
      flex:1;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      min-width:0;
    }
    .asset-code{
      font-weight:900;
      font-size: 13px;
      line-height:1.1;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .asset-name{
      font-size: 10px;
      font-weight:700;
      line-height:1.15;
      max-height: 22mm;
      overflow:hidden;
    }
    .meta{
      font-size: 8.8px;
      color:#333;
      line-height:1.2;
      margin-top: 1mm;
    }

    .qr{
      width: 22mm;
      height: 22mm;
      display:flex;
      align-items:center;
      justify-content:center;
      border: 1px dashed #888;
      border-radius: 2mm;
      padding: 1.5mm;
      box-sizing:border-box;
      background:#fff;
    }
    .qr-caption{
      text-align:center;
      font-size: 7.5px;
      color:#444;
      margin-top: 1mm;
      line-height:1.1;
    }

    @media print{
      .no-print{ display:none !important; }
      @page{ margin: 6mm; }
      body{ -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>

  <div class="no-print">
    <a class="btn secondary" href="<?= e2(base_url()) ?>/index.php?route=activo_detalle&id=<?= (int)$activo['id'] ?>">Volver</a>
    <button class="btn" onclick="window.print()">Imprimir / Guardar PDF</button>
  </div>

  <div class="paper">
    <!-- membrete mini -->
    <div class="head">
      <div class="h-left">
        <div class="h-title"><?= e2($empresaNombre) ?></div>
        <div class="h-sub"><?= $empresaNit !== '' ? ('NIT: '.e2($empresaNit)) : 'GeoActivos' ?></div>
      </div>
      <div class="h-right">
        <div><b>ETIQUETA QR</b></div>
        <div><?= e2($modo==='detalle'?'Detalle':'Hoja de vida') ?></div>
      </div>
    </div>

    <div class="body">
      <div class="left">
        <div>
          <div class="asset-code"><?= e2($codigo) ?></div>
          <div class="asset-name"><?= e2($nombre) ?></div>

          <div class="meta">
            <div><b>Tipo:</b> <?= e2($tipoTxt) ?></div>
            <div><b>Ubicación:</b> <?= e2($ubic) ?></div>
            <div><span class="badge <?= e2(badge_estado($estado)) ?>"><?= e2($estado ?: '—') ?></span></div>
          </div>
        </div>

        <div class="meta">
          ID: <?= (int)$activo['id'] ?> · GeoActivos
        </div>
      </div>

      <div>
        <div class="qr" id="qrcode"></div>
        <div class="qr-caption">
          Escanea para ver<br><?= ($modo==='detalle'?'Detalle':'Hoja de vida') ?>
        </div>
      </div>
    </div>
  </div>

  <script>document.getElementById('qrcode').dataset.qrText = <?= json_encode($dest) ?>;</script>
  <script src="<?= e(base_url()) ?>/assets/js/activo-qr.js"></script>

</body>
</html>
