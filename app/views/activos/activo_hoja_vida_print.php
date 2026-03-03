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

$empresaNombre = $tenant && !empty($tenant['nombre']) ? (string)$tenant['nombre'] : '—';
$empresaNit    = $tenant && !empty($tenant['nit']) ? (string)$tenant['nit'] : '';
$empresaEmail  = $tenant && !empty($tenant['email']) ? (string)$tenant['email'] : '';
$empresaTel    = $tenant && !empty($tenant['telefono']) ? (string)$tenant['telefono'] : '';
$empresaDir    = $tenant && !empty($tenant['direccion']) ? (string)$tenant['direccion'] : '';
$empresaCiu    = $tenant && !empty($tenant['ciudad']) ? (string)$tenant['ciudad'] : '';

/* ---------------- Activo ---------------- */
$st = db()->prepare("
  SELECT a.*,
         c.nombre AS categoria,
         t.nombre AS tipo,
         t.codigo AS tipo_codigo,
         m.nombre AS marca,
         p.nombre AS proveedor,
         ar.nombre AS area,
         s.nombre AS sede
  FROM activos a
  INNER JOIN categorias_activo c ON c.id = a.categoria_id AND c.tenant_id = a.tenant_id
  LEFT JOIN tipos_activo t ON t.id = a.tipo_activo_id AND t.tenant_id = a.tenant_id
  LEFT JOIN marcas m ON m.id = a.marca_id AND m.tenant_id = a.tenant_id
  LEFT JOIN proveedores p ON p.id = a.proveedor_id AND p.tenant_id = a.tenant_id
  LEFT JOIN areas ar ON ar.id = a.area_id AND ar.tenant_id = a.tenant_id
  LEFT JOIN sedes s ON s.id = ar.sede_id AND s.tenant_id = a.tenant_id
  WHERE a.id=:id AND a.tenant_id=:t
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$activo = $st->fetch();

if (!$activo) { http_response_code(404); echo "Activo no encontrado"; exit; }

/* ---------------- Regla del tipo (usa_red / usa_software) ---------------- */
$usaRed = 0;
$usaSoftware = 0;

if (!empty($activo['tipo_activo_id'])) {
  $rg = db()->prepare("
    SELECT usa_red, usa_software
    FROM tipo_activo_reglas
    WHERE tenant_id=:t AND tipo_activo_id=:id
    LIMIT 1
  ");
  $rg->execute([':t'=>$tenantId, ':id'=>(int)$activo['tipo_activo_id']]);
  $regla = $rg->fetch();
  if ($regla) { $usaRed = (int)$regla['usa_red']; $usaSoftware = (int)$regla['usa_software']; }
}

/* ---------------- Componentes (PRO: activos_componentes) ---------------- */
$componentes = [];
try {
  $compSt = db()->prepare("
    SELECT id, nombre, tipo, marca, modelo, serial, cantidad, estado
    FROM activos_componentes
    WHERE tenant_id=:t AND activo_id=:a AND eliminado=0
    ORDER BY nombre ASC, id DESC
  ");
  $compSt->execute([':t'=>$tenantId, ':a'=>$id]);
  $componentes = $compSt->fetchAll();
} catch (Exception $e) { $componentes = []; }

/* ---------------- Software ---------------- */
$swRows = [];
if ($usaSoftware === 1) {
  $sw = db()->prepare("
    SELECT id, nombre, version, licencia_tipo, fecha_vencimiento
    FROM activos_software
    WHERE tenant_id=:t AND activo_id=:a
    ORDER BY id DESC
    LIMIT 200
  ");
  $sw->execute([':t'=>$tenantId, ':a'=>$id]);
  $swRows = $sw->fetchAll();
}

/* ---------------- Adjuntos del activo ---------------- */
$adjRows = [];
$al = db()->prepare("
  SELECT id, nombre_original, mime, tamano, creado_en
  FROM activos_adjuntos
  WHERE tenant_id=:t AND activo_id=:a
  ORDER BY id DESC
  LIMIT 500
");
$al->execute([':t'=>$tenantId, ':a'=>$id]);
$adjRows = $al->fetchAll();

/* ---------------- Mantenimientos (DETALLE) ---------------- */
$mantRows = [];
$mq = db()->prepare("
  SELECT
    id, tipo, estado, prioridad,
    fecha_programada, fecha_inicio, fecha_fin,
    falla_reportada, diagnostico, actividades, recomendaciones,
    costo_mano_obra, costo_repuestos, creado_en
  FROM mantenimientos
  WHERE tenant_id=:t AND activo_id=:a
  ORDER BY id DESC
  LIMIT 500
");
$mq->execute([':t'=>$tenantId, ':a'=>$id]);
$mantRows = $mq->fetchAll();

/* ---------------- Helpers ---------------- */
function e2($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_fecha10($v){ return $v ? substr((string)$v,0,10) : '—'; }
function fmt_fecha16($v){ return $v ? substr((string)$v,0,16) : '—'; }
function fmt_bytes($bytes) {
  $bytes = (int)$bytes;
  if ($bytes <= 0) return '0 B';
  $units = ['B','KB','MB','GB','TB']; $i=0;
  while ($bytes >= 1024 && $i < count($units)-1) { $bytes/=1024; $i++; }
  return round($bytes,1).' '.$units[$i];
}

function badge_estado_activo($estado){
  $estado = (string)$estado;
  $b = 'gris';
  if ($estado === 'ACTIVO') $b = 'verde';
  elseif ($estado === 'EN_MANTENIMIENTO') $b = 'amarillo';
  elseif ($estado === 'BAJA') $b = 'rojo';
  return $b;
}

function badge_estado_mant($estado){
  $estado = (string)$estado;
  $b = 'gris';
  if ($estado === 'PROGRAMADO') $b = 'azul';
  elseif ($estado === 'EN_PROCESO') $b = 'amarillo';
  elseif ($estado === 'CERRADO') $b = 'verde';
  elseif ($estado === 'ANULADO') $b = 'rojo';
  return $b;
}

function badge_tipo_mant($tipo){
  $tipo = (string)$tipo;
  $b = 'gris';
  if ($tipo === 'PREVENTIVO') $b = 'azul';
  elseif ($tipo === 'CORRECTIVO') $b = 'amarillo';
  elseif ($tipo === 'PREDICTIVO') $b = 'gris';
  return $b;
}

function badge_prio($p){
  $p = (string)$p;
  $b = 'gris';
  if ($p === 'MEDIA') $b = 'azul';
  elseif ($p === 'ALTA') $b = 'amarillo';
  elseif ($p === 'CRITICA') $b = 'rojo';
  return $b;
}

/* ---------------- Ubicación ---------------- */
$ubic = '';
if (!empty($activo['sede'])) $ubic .= (string)$activo['sede'];
if (!empty($activo['area'])) $ubic .= ($ubic ? ' - ' : '') . (string)$activo['area'];
if ($ubic === '') $ubic = '—';

/* ---------------- Tipo con prefijo ---------------- */
$tipoNombre = $activo['tipo'] ? (string)$activo['tipo'] : '';
$tipoCod    = $activo['tipo_codigo'] ? (string)$activo['tipo_codigo'] : '';
$tipoTxt    = ($tipoNombre === '') ? '—' : (($tipoCod !== '' ? ($tipoCod . ' · ') : '') . $tipoNombre);

/* ---------------- Red ---------------- */
$host = trim((string)($activo['hostname'] ?? ''));
$usaDhcp = (int)($activo['usa_dhcp'] ?? 1);
$ip = trim((string)($activo['ip_fija'] ?? ''));
$mac = trim((string)($activo['mac'] ?? ''));
$redTxt = '';
if ($host !== '') $redTxt .= $host;
$redTxt .= ($redTxt ? ' · ' : '') . ($usaDhcp ? 'DHCP' : ($ip !== '' ? $ip : 'IP fija (sin dato)'));
if ($mac !== '') $redTxt .= " · MAC: ".$mac;
if (trim($redTxt) === '') $redTxt = '—';

/* ---------------- KPIs ---------------- */
$sumTotal = 0.0;
foreach ($mantRows as $mm) $sumTotal += ((float)$mm['costo_mano_obra'] + (float)$mm['costo_repuestos']);

$now = date('Y-m-d H:i');
$docTitle = "HOJA DE VIDA DEL ACTIVO";
$docCode  = "GA-HV";
$version  = "v1.0";

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hoja de vida · <?= e2($activo['codigo_interno']) ?></title>
  <style>
    body{ margin:0; font-family: Arial, Helvetica, sans-serif; color:#111; background:#fff; }
    .no-print{ padding:10px; text-align:right; }
    .btn{
      display:inline-block; padding:8px 10px; border-radius:6px;
      background:#0d6efd; color:#fff; text-decoration:none; font-size:13px;
      border:0; cursor:pointer;
    }
    .btn.secondary{ background:#6c757d; }

    /* Carta */
    .page{ max-width: 820px; margin: 10px auto; padding: 0 10px; }
    .paper{ border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; }

    /* Membrete tipo carta */
    .letterhead{
      padding:14px 18px;
      border-bottom:2px solid #111;
      background:#f8fafc;
    }
    .lh-row{ display:flex; gap:14px; align-items:flex-start; }
    .logo{
      width:64px; height:64px;
      border:1px solid #111;
      border-radius:10px;
      display:flex; align-items:center; justify-content:center;
      font-weight:900;
      font-size:14px;
      background:#fff;
      flex:0 0 auto;
    }
    .lh-main{ flex:1; min-width:0; }
    .empresa{
      font-weight:900;
      font-size:18px;
      line-height:1.15;
      margin:0;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .empresa2{
      margin-top:4px;
      font-size:12px;
      color:#374151;
      line-height:1.35;
    }
    .lh-meta{
      text-align:right;
      min-width:220px;
      flex:0 0 auto;
      font-size:12px;
      color:#111;
      line-height:1.35;
    }
    .lh-meta b{ font-weight:900; }
    .doc-title{
      margin-top:10px;
      font-size:16px;
      font-weight:900;
      letter-spacing:.2px;
      text-transform:uppercase;
    }

    /* Secciones */
    .content{ padding: 14px 18px 6px 18px; }
    .block{ margin: 10px 0 14px; }
    .block h3{
      margin:0 0 8px;
      font-size:13px;
      text-transform:uppercase;
      letter-spacing:.3px;
      border-left:5px solid #111;
      padding-left:8px;
    }

    table{ width:100%; border-collapse: collapse; }
    .kv td, .kv th{
      border:1px solid #e5e7eb;
      padding:6px 8px;
      font-size:12px;
      vertical-align:top;
    }
    .kv th{ width:210px; background:#f9fafb; text-align:left; }

    .tbl th, .tbl td{
      border:1px solid #e5e7eb;
      padding:6px 8px;
      font-size:12px;
      vertical-align:top;
    }
    .tbl th{ background:#f9fafb; text-align:left; font-weight:900; }

    .kpis{
      display:flex; gap:10px; flex-wrap:wrap;
      margin: 10px 0 12px;
    }
    .kpi{
      border:1px solid #e5e7eb; border-radius:10px;
      padding:10px 12px;
      min-width: 180px;
      flex:1;
      background:#fff;
    }
    .kpi .t{ font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.2px; }
    .kpi .n{ font-size:18px; font-weight:900; margin-top:4px; }
    .kpi .s{ font-size:11px; color:#6b7280; margin-top:2px; }

    .badge{
      display:inline-block; padding:2px 8px; border-radius:999px;
      font-weight:900; font-size:11px; border:1px solid #111;
    }
    .verde{ background:#dcfce7; }
    .amarillo{ background:#fef9c3; }
    .rojo{ background:#fee2e2; }
    .azul{ background:#dbeafe; }
    .gris{ background:#f3f4f6; }

    /* Pie carta */
    .footer{
      border-top:1px solid #e5e7eb;
      padding:10px 18px;
      font-size:11px;
      color:#374151;
      display:flex;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
    }

    .muted{ color:#6b7280; }

    @media print{
      .no-print{ display:none !important; }
      @page{ margin: 10mm; }
      body{ -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .page{ max-width:none; margin:0; padding:0; }
      .paper{ border:0; border-radius:0; }
    }
  </style>
</head>
<body>

  <div class="no-print">
    <a class="btn secondary" href="<?= e2(base_url()) ?>/index.php?route=activo_hoja_vida&id=<?= (int)$id ?>">
      Volver
    </a>
    <button class="btn" onclick="window.print()">Imprimir / Guardar PDF</button>
  </div>

  <div class="page">
    <div class="paper">

      <!-- MEMBRETE (TIPO CARTA) -->
      <div class="letterhead">
        <div class="lh-row">
          <div class="logo">GA</div>

          <div class="lh-main">
            <div class="empresa"><?= e2($empresaNombre) ?></div>
            <div class="empresa2">
              <?php
                $line1 = [];
                if ($empresaNit !== '') $line1[] = "NIT: <b>".e2($empresaNit)."</b>";
                if ($empresaTel !== '') $line1[] = "Tel: <b>".e2($empresaTel)."</b>";
                if ($empresaEmail !== '') $line1[] = "Email: <b>".e2($empresaEmail)."</b>";
                echo $line1 ? implode(" · ", $line1) : "<span class='muted'>—</span>";
              ?>
              <br>
              <?php
                $loc = trim($empresaDir);
                $ciu = trim($empresaCiu);
                if ($ciu !== '') $loc .= ($loc ? " · " : "") . $ciu;
                echo $loc ? ("Dirección: <b>".e2($loc)."</b>") : "<span class='muted'>Dirección: —</span>";
              ?>
            </div>
            <div class="doc-title"><?= e2($docTitle) ?></div>
          </div>

          <div class="lh-meta">
            <div><b>Código:</b> <?= e2($docCode) ?></div>
            <div><b>Versión:</b> <?= e2($version) ?></div>
            <div><b>Fecha:</b> <?= e2($now) ?></div>
            <div><b>Página:</b> 1/1</div>
          </div>
        </div>
      </div>

      <div class="content">

        <!-- Encabezado del activo -->
        <div class="block">
          <h3>Identificación del activo</h3>
          <table class="kv">
            <tr><th>Código interno</th><td><b><?= e2($activo['codigo_interno']) ?></b></td></tr>
            <tr><th>Nombre</th><td><?= e2($activo['nombre']) ?></td></tr>
            <tr><th>Categoría</th><td><?= e2($activo['categoria'] ?: '—') ?></td></tr>
            <tr><th>Tipo</th><td><?= e2($tipoTxt) ?></td></tr>
            <tr><th>Marca / Modelo</th><td><?= e2(($activo['marca'] ?: '—')) ?> <?= !empty($activo['modelo']) ? ('· '.e2($activo['modelo'])) : '' ?></td></tr>
            <tr><th>Serial / Placa</th><td><?= e2(($activo['serial'] ?: '—')) ?> <?= !empty($activo['placa']) ? ('· Placa: '.e2($activo['placa'])) : '' ?></td></tr>
            <tr><th>Ubicación</th><td><?= e2($ubic) ?></td></tr>
            <tr><th>Estado</th><td><span class="badge <?= e2(badge_estado_activo($activo['estado'])) ?>"><?= e2($activo['estado'] ?: '—') ?></span></td></tr>
            <tr><th>Observaciones</th><td><?= nl2br(e2($activo['observaciones'] ?: '—')) ?></td></tr>
          </table>

          <div class="kpis">
            <div class="kpi">
              <div class="t">Mantenimientos</div>
              <div class="n"><?= (int)count($mantRows) ?></div>
              <div class="s">Historial completo</div>
            </div>
            <div class="kpi">
              <div class="t">Adjuntos</div>
              <div class="n"><?= (int)count($adjRows) ?></div>
              <div class="s">Documentos del activo</div>
            </div>
            <div class="kpi">
              <div class="t">Costos acumulados</div>
              <div class="n">$ <?= number_format($sumTotal,0,',','.') ?></div>
              <div class="s">MO + Repuestos</div>
            </div>
          </div>
        </div>

        <!-- Red -->
        <div class="block">
          <h3>Red / Identidad tecnológica</h3>
          <?php if ($usaRed !== 1): ?>
            <div class="muted" style="font-size:12px;">No aplica (regla del tipo: usa_red=0).</div>
          <?php else: ?>
            <table class="kv">
              <tr><th>Datos</th><td><?= e2($redTxt) ?></td></tr>
            </table>
          <?php endif; ?>
        </div>

        <!-- Componentes -->
        <div class="block">
          <h3>Componentes</h3>
          <?php if (!$componentes): ?>
            <div class="muted" style="font-size:12px;">Sin componentes registrados.</div>
          <?php else: ?>
            <table class="tbl">
              <thead>
                <tr>
                  <th>Componente</th>
                  <th>Tipo</th>
                  <th>Marca/Modelo</th>
                  <th>Serial</th>
                  <th style="width:70px; text-align:center;">Cant</th>
                  <th style="width:120px;">Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($componentes as $c): ?>
                  <tr>
                    <td><b><?= e2($c['nombre'] ?: '—') ?></b></td>
                    <td><?= e2($c['tipo'] ?: '—') ?></td>
                    <td><?= e2($c['marca'] ?: '—') ?><?= !empty($c['modelo']) ? (' · '.e2($c['modelo'])) : '' ?></td>
                    <td><?= e2($c['serial'] ?: '—') ?></td>
                    <td style="text-align:center;"><?= (int)($c['cantidad'] ?? 1) ?></td>
                    <td><span class="badge <?= e2(badge_estado_activo($c['estado'] ?? '')) ?>"><?= e2($c['estado'] ?: '—') ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <!-- Software -->
        <div class="block">
          <h3>Software / Licencias</h3>
          <?php if ($usaSoftware !== 1): ?>
            <div class="muted" style="font-size:12px;">No aplica (regla del tipo: usa_software=0).</div>
          <?php elseif (!$swRows): ?>
            <div class="muted" style="font-size:12px;">Sin software registrado.</div>
          <?php else: ?>
            <table class="tbl">
              <thead>
                <tr>
                  <th>Software</th>
                  <th>Licencia</th>
                  <th style="width:120px;">Vencimiento</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($swRows as $r): ?>
                  <?php
                    $vence = !empty($r['fecha_vencimiento']) ? substr((string)$r['fecha_vencimiento'],0,10) : '—';
                    $swName = (string)$r['nombre'];
                    if (!empty($r['version'])) $swName .= " (v".$r['version'].")";
                  ?>
                  <tr>
                    <td><?= e2($swName) ?></td>
                    <td><?= e2($r['licencia_tipo'] ?: '—') ?></td>
                    <td><?= e2($vence) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <!-- Adjuntos -->
        <div class="block">
          <h3>Adjuntos del activo</h3>
          <?php if (!$adjRows): ?>
            <div class="muted" style="font-size:12px;">Sin adjuntos.</div>
          <?php else: ?>
            <table class="tbl">
              <thead>
                <tr>
                  <th>Archivo</th>
                  <th style="width:160px;">Tipo</th>
                  <th style="width:90px;">Tamaño</th>
                  <th style="width:160px;">Fecha</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($adjRows as $a): ?>
                  <tr>
                    <td><?= e2($a['nombre_original'] ?: '—') ?></td>
                    <td><?= e2($a['mime'] ?: '—') ?></td>
                    <td><?= e2(fmt_bytes($a['tamano'] ?? 0)) ?></td>
                    <td><?= e2(!empty($a['creado_en']) ? substr((string)$a['creado_en'],0,19) : '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <!-- Mantenimientos -->
        <div class="block">
          <h3>Mantenimientos (detalle)</h3>
          <?php if (!$mantRows): ?>
            <div class="muted" style="font-size:12px;">Sin mantenimientos.</div>
          <?php else: ?>
            <?php foreach ($mantRows as $mm): ?>
              <?php $costo = ((float)$mm['costo_mano_obra'] + (float)$mm['costo_repuestos']); ?>
              <table class="kv" style="margin-bottom:10px;">
                <tr>
                  <th>Identificación</th>
                  <td>
                    <b>#<?= (int)$mm['id'] ?></b>
                    · <span class="badge <?= e2(badge_tipo_mant($mm['tipo'])) ?>"><?= e2($mm['tipo']) ?></span>
                    · <span class="badge <?= e2(badge_estado_mant($mm['estado'])) ?>"><?= e2($mm['estado']) ?></span>
                    · <span class="badge <?= e2(badge_prio($mm['prioridad'])) ?>"><?= e2($mm['prioridad']) ?></span>
                    · <b>Costo:</b> $ <?= number_format($costo,0,',','.') ?>
                  </td>
                </tr>
                <tr><th>Fechas</th><td>
                  Programado: <b><?= e2(fmt_fecha10($mm['fecha_programada'])) ?></b>
                  · Inicio: <b><?= e2(fmt_fecha16($mm['fecha_inicio'])) ?></b>
                  · Fin: <b><?= e2(fmt_fecha16($mm['fecha_fin'])) ?></b>
                </td></tr>
                <tr><th>Falla reportada</th><td><?= nl2br(e2($mm['falla_reportada'] ?: '—')) ?></td></tr>
                <tr><th>Diagnóstico</th><td><?= nl2br(e2($mm['diagnostico'] ?: '—')) ?></td></tr>
                <tr><th>Actividades</th><td><?= nl2br(e2($mm['actividades'] ?: '—')) ?></td></tr>
                <tr><th>Recomendaciones</th><td><?= nl2br(e2($mm['recomendaciones'] ?: '—')) ?></td></tr>
                <tr><th>Registro</th><td>
                  Creado: <b><?= e2(!empty($mm['creado_en']) ? substr((string)$mm['creado_en'],0,19) : '—') ?></b>
                  · Mano de obra: <b>$ <?= number_format((float)$mm['costo_mano_obra'],0,',','.') ?></b>
                  · Repuestos: <b>$ <?= number_format((float)$mm['costo_repuestos'],0,',','.') ?></b>
                </td></tr>
              </table>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>

      <!-- PIE TIPO CARTA -->
      <div class="footer">
        <div>
          <b><?= e2($empresaNombre) ?></b>
          <?php if ($empresaNit !== ''): ?> · NIT <?= e2($empresaNit) ?><?php endif; ?>
          · GeoActivos
        </div>
        <div class="muted">
          Activo: <?= e2($activo['codigo_interno']) ?> · ID <?= (int)$activo['id'] ?> · Generado <?= e2($now) ?>
        </div>
      </div>

    </div>
  </div>

</body>
</html>
