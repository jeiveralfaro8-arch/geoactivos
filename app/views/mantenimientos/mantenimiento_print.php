<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = (int)Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }

/* =========================
   Empresa (Tenant)
========================= */
$tenant = null;
try {
  $tq = db()->prepare("SELECT id, nombre, nit, email, telefono, direccion, ciudad, logo_path, logo_url FROM tenants WHERE id=:t LIMIT 1");
  $tq->execute([':t'=>$tenantId]);
  $tenant = $tq->fetch();
} catch (Exception $e) { $tenant = null; }

function e2($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_dt2($v, $len=19){ return $v ? substr((string)$v,0,$len) : '—'; }
function money0($v){ return '$ '.number_format((float)$v,0,',','.'); }

/* =========================
   Helpers: tablas/columnas
========================= */
function table_exists2($table) {
  $q = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $q->execute([':t'=>$table]);
  return (bool)$q->fetch();
}
function table_columns2($table) {
  $q = db()->prepare("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = :t
  ");
  $q->execute([':t'=>$table]);
  $cols = [];
  foreach ($q->fetchAll() as $r) $cols[] = $r['column_name'];
  return $cols;
}

/* =========================
   URL pública robusta (firma/logo)
========================= */
function build_public_url2($relPath, $absBaseDir=null){
  $rel = trim((string)$relPath);
  if ($rel === '') return '';

  $rel = ltrim($rel, '/');
  $rel = preg_replace('#^public/#i', '', $rel); // quita "public/" si lo guardaron así

  $base = rtrim((string)base_url(), '/');

  // Si base_url ya trae /public, NO dupliques
  $baseHasPublic = (preg_match('#/public$#i', $base) || stripos($base, '/public/') !== false);
  $url = $baseHasPublic ? ($base.'/'.$rel) : ($base.'/public/'.$rel);

  // cache-bust con filemtime si existe
  if ($absBaseDir) {
    $absBaseDir = rtrim($absBaseDir, '/\\');
    $absTry = $absBaseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    if (is_file($absTry)) {
      $url .= (strpos($url,'?')===false ? '?' : '&') . 'v='.(int)@filemtime($absTry);
    } else {
      $url .= (strpos($url,'?')===false ? '?' : '&') . 'v='.(int)time();
    }
  } else {
    $url .= (strpos($url,'?')===false ? '?' : '&') . 'v='.(int)time();
  }

  return $url;
}

/* =========================
   Mantenimiento + Activo
========================= */
$st = db()->prepare("
  SELECT
    m.*,
    a.codigo_interno,
    a.nombre AS activo_nombre,
    a.modelo,
    a.serial,
    a.placa,
    a.hostname,
    a.usa_dhcp,
    a.ip_fija,
    a.mac,
    c.nombre AS categoria,
    ta.nombre AS tipo_activo,
    ta.codigo AS tipo_codigo,
    ar.nombre AS area,
    s.nombre AS sede
  FROM mantenimientos m
  INNER JOIN activos a
    ON a.id = m.activo_id AND a.tenant_id = m.tenant_id
  INNER JOIN categorias_activo c
    ON c.id = a.categoria_id AND c.tenant_id = a.tenant_id
  LEFT JOIN tipos_activo ta
    ON ta.id = a.tipo_activo_id AND ta.tenant_id = a.tenant_id
  LEFT JOIN areas ar
    ON ar.id = a.area_id AND ar.tenant_id = a.tenant_id
  LEFT JOIN sedes s
    ON s.id = ar.sede_id AND s.tenant_id = a.tenant_id
  WHERE m.id = :id AND m.tenant_id = :t
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$m = $st->fetch();

if (!$m) { http_response_code(404); echo "Mantenimiento no encontrado"; exit; }

/* =========================
   Ubicación + Tipo
========================= */
$tipoNombre = $m['tipo_activo'] ? (string)$m['tipo_activo'] : '';
$tipoCod    = $m['tipo_codigo'] ? (string)$m['tipo_codigo'] : '';
$tipoTxt    = ($tipoNombre === '') ? '—' : (($tipoCod !== '' ? ($tipoCod . ' · ') : '') . $tipoNombre);

$ubic = '';
if (!empty($m['sede'])) $ubic .= (string)$m['sede'];
if (!empty($m['area'])) $ubic .= ($ubic ? ' - ' : '') . (string)$m['area'];
if ($ubic === '') $ubic = '—';

/* =========================
   Red (si aplica)
========================= */
$host = trim((string)($m['hostname'] ?? ''));
$usaDhcp = (int)($m['usa_dhcp'] ?? 1);
$ip = trim((string)($m['ip_fija'] ?? ''));
$mac = trim((string)($m['mac'] ?? ''));
$redTxt = '';

if ($host !== '') $redTxt .= $host;
$redTxt .= ($redTxt ? ' · ' : '') . ($usaDhcp ? 'DHCP' : ($ip !== '' ? $ip : 'IP fija (sin dato)'));
if ($mac !== '') $redTxt .= " · MAC: ".$mac;
if ($redTxt === '') $redTxt = '—';

/* =========================
   Firmas y responsables (FIX REAL)
   - Resuelve usuario técnico/recibido por:
     1) tecnico_usuario_id / recibido_por_usuario_id
     2) creado_por (si es id/email/nombre)
     3) tecnico_nombre contra usuarios.nombre (LIKE)
     4) usuario logueado si coincide
========================= */
$mCols = table_columns2('mantenimientos');
$mHas  = function($c) use ($mCols){ return in_array($c, $mCols, true); };

$hasTecUser = $mHas('tecnico_usuario_id');
$hasRecUser = $mHas('recibido_por_usuario_id');

$tecnicoUserId  = $hasTecUser ? (int)($m['tecnico_usuario_id'] ?? 0) : 0;
$recibidoUserId = $hasRecUser ? (int)($m['recibido_por_usuario_id'] ?? 0) : 0;

$tecnicoNombre = trim((string)($m['tecnico_nombre'] ?? ''));
$tecnicoCargo  = trim((string)($m['tecnico_cargo'] ?? ''));
$tecnicoTP     = trim((string)($m['tecnico_tarjeta_prof'] ?? ''));

$recibidoNombre = trim((string)($m['recibido_por_nombre'] ?? ''));
$recibidoCargo  = trim((string)($m['recibido_por_cargo'] ?? ''));

$firmaHashLegacy = trim((string)($m['firma_hash'] ?? ''));
$recibidoFirmaPngLegacy  = (string)($m['recibido_firma_png'] ?? '');
$recibidoFirmaHashLegacy = (string)($m['recibido_firma_hash'] ?? '');

$publicDir = realpath(__DIR__ . '/../../public');
if (!$publicDir) $publicDir = __DIR__ . '/../../public';

$uCols = table_exists2('usuarios') ? table_columns2('usuarios') : [];
$uHas  = function($c) use ($uCols){ return in_array($c, $uCols, true); };

$uHasFirmaPath = $uHas('firma_path');
$uHasFirmaHash = $uHas('firma_hash');
$uHasCargo     = $uHas('cargo');
$uHasTP        = $uHas('tarjeta_profesional');

$tecnicoFirmaUrl = '';
$tecnicoFirmaHash = '';
$recibidoFirmaUrl = '';
$recibidoFirmaHash = '';

/* ---- resolver técnico por otros campos numéricos típicos ---- */
if ($tecnicoUserId <= 0) {
  foreach (['tecnico_id','tecnico_user_id','usuario_tecnico_id','creado_por_id','usuario_id','user_id'] as $c) {
    if ($mHas($c) && (int)($m[$c] ?? 0) > 0) { $tecnicoUserId = (int)$m[$c]; break; }
  }
}

/* ---- si creado_por existe, puede ser id/email/nombre ---- */
$creadoPor = '';
if ($mHas('creado_por')) $creadoPor = trim((string)($m['creado_por'] ?? ''));

/* ---- lookup técnico por creado_por ---- */
if (!empty($uCols) && $tecnicoUserId <= 0 && $creadoPor !== '') {
  if (ctype_digit($creadoPor)) {
    $tecnicoUserId = (int)$creadoPor;
  } else {
    if (strpos($creadoPor, '@') !== false) {
      $qs = db()->prepare("SELECT id FROM usuarios WHERE tenant_id=:t AND email=:e LIMIT 1");
      $qs->execute([':t'=>$tenantId, ':e'=>$creadoPor]);
      $u = $qs->fetch();
      if ($u) $tecnicoUserId = (int)$u['id'];
    }
    if ($tecnicoUserId <= 0) {
      $qs = db()->prepare("SELECT id FROM usuarios WHERE tenant_id=:t AND nombre=:n LIMIT 1");
      $qs->execute([':t'=>$tenantId, ':n'=>$creadoPor]);
      $u = $qs->fetch();
      if ($u) $tecnicoUserId = (int)$u['id'];
    }
  }
}

/* ---- lookup técnico por tecnico_nombre (LIKE robusto) ---- */
if (!empty($uCols) && $tecnicoUserId <= 0 && $tecnicoNombre !== '') {
  $qs = db()->prepare("SELECT id FROM usuarios WHERE tenant_id=:t AND nombre LIKE :n ORDER BY id ASC LIMIT 1");
  $qs->execute([':t'=>$tenantId, ':n'=>'%'.$tecnicoNombre.'%']);
  $u = $qs->fetch();
  if ($u) $tecnicoUserId = (int)$u['id'];
}

/* ---- último recurso: usuario logueado si coincide ---- */
$sessionUser = $_SESSION['user'] ?? [];
$sessionUserId = isset($sessionUser['id']) ? (int)$sessionUser['id'] : 0;
$sessionUserName = isset($sessionUser['nombre']) ? trim((string)$sessionUser['nombre']) : '';
if ($tecnicoUserId <= 0 && $sessionUserId > 0) {
  if ($tecnicoNombre === '' || ($sessionUserName !== '' && strcasecmp($sessionUserName, $tecnicoNombre) === 0)) {
    $tecnicoUserId = $sessionUserId;
  }
}

/* ---- resolver recibido por (si tienes ids en tabla) ---- */
if ($recibidoUserId <= 0) {
  foreach (['recibido_por_id','recibido_usuario_id','usuario_recibido_id','cierre_por','cierre_por_id'] as $c) {
    if ($mHas($c) && (int)($m[$c] ?? 0) > 0) { $recibidoUserId = (int)$m[$c]; break; }
  }
}
if (!empty($uCols) && $recibidoUserId <= 0 && $recibidoNombre !== '') {
  $qs = db()->prepare("SELECT id FROM usuarios WHERE tenant_id=:t AND nombre LIKE :n ORDER BY id ASC LIMIT 1");
  $qs->execute([':t'=>$tenantId, ':n'=>'%'.$recibidoNombre.'%']);
  $u = $qs->fetch();
  if ($u) $recibidoUserId = (int)$u['id'];
}

/* ---- cargar usuario técnico ---- */
if (!empty($uCols) && $tecnicoUserId > 0) {
  $qs = db()->prepare("SELECT * FROM usuarios WHERE id=:id AND tenant_id=:t LIMIT 1");
  $qs->execute([':id'=>$tecnicoUserId, ':t'=>$tenantId]);
  $u = $qs->fetch();

  if ($u) {
    if ($tecnicoNombre === '' && !empty($u['nombre'])) $tecnicoNombre = (string)$u['nombre'];
    if ($tecnicoCargo  === '' && $uHasCargo && !empty($u['cargo'])) $tecnicoCargo = (string)$u['cargo'];
    if ($tecnicoTP     === '' && $uHasTP && !empty($u['tarjeta_profesional'])) $tecnicoTP = (string)$u['tarjeta_profesional'];

    if ($uHasFirmaHash && !empty($u['firma_hash'])) $tecnicoFirmaHash = (string)$u['firma_hash'];
    if ($uHasFirmaPath && !empty($u['firma_path'])) {
      $tecnicoFirmaUrl = build_public_url2((string)$u['firma_path'], $publicDir);
    }
  }
}

/* ---- cargar usuario recibido por ---- */
if (!empty($uCols) && $recibidoUserId > 0) {
  $qs = db()->prepare("SELECT * FROM usuarios WHERE id=:id AND tenant_id=:t LIMIT 1");
  $qs->execute([':id'=>$recibidoUserId, ':t'=>$tenantId]);
  $u = $qs->fetch();

  if ($u) {
    if ($recibidoNombre === '' && !empty($u['nombre'])) $recibidoNombre = (string)$u['nombre'];
    if ($recibidoCargo  === '' && $uHasCargo && !empty($u['cargo'])) $recibidoCargo = (string)$u['cargo'];

    if ($uHasFirmaHash && !empty($u['firma_hash'])) $recibidoFirmaHash = (string)$u['firma_hash'];
    if ($uHasFirmaPath && !empty($u['firma_path'])) {
      $recibidoFirmaUrl = build_public_url2((string)$u['firma_path'], $publicDir);
    }
  }
}

/* ---- hash fallback ---- */
if ($tecnicoFirmaHash === '' && $firmaHashLegacy !== '') $tecnicoFirmaHash = $firmaHashLegacy;
if ($recibidoFirmaHash === '' && $recibidoFirmaHashLegacy !== '') $recibidoFirmaHash = $recibidoFirmaHashLegacy;

/* ---- recibido PNG legacy ---- */
if ($recibidoFirmaUrl === '' && $recibidoFirmaPngLegacy) {
  $recibidoFirmaUrl = build_public_url2($recibidoFirmaPngLegacy, $publicDir);
}

/* =========================
   Adjuntos del mantenimiento (robusto)
========================= */
$adjTable = null;
if (table_exists2('mant_adjuntos')) $adjTable = 'mant_adjuntos';
elseif (table_exists2('mantenimientos_adjuntos')) $adjTable = 'mantenimientos_adjuntos';

$adjEnabled = ($adjTable !== null);
$adjuntos = [];

$col = [
  'archivo' => null,
  'nombre' => null,
  'mime' => null,
  'tamano' => null,
  'nota' => null,
  'creado_en' => null,
];

if ($adjEnabled) {
  $cols = table_columns2($adjTable);

  foreach (['archivo','ruta','path','filename','nombre_archivo','stored_name'] as $cname) {
    if (in_array($cname, $cols, true)) { $col['archivo'] = $cname; break; }
  }
  foreach (['nombre_original','nombre','original_name','archivo_nombre','file_name'] as $cname) {
    if (in_array($cname, $cols, true)) { $col['nombre'] = $cname; break; }
  }
  foreach (['mime','mime_type','tipo_mime'] as $cname) {
    if (in_array($cname, $cols, true)) { $col['mime'] = $cname; break; }
  }
  foreach (['tamano','size','peso'] as $cname) {
    if (in_array($cname, $cols, true)) { $col['tamano'] = $cname; break; }
  }
  foreach (['nota','observacion','comentario'] as $cname) {
    if (in_array($cname, $cols, true)) { $col['nota'] = $cname; break; }
  }
  foreach (['creado_en','created_at','subido_en','fecha_subida'] as $cname) {
    if (in_array($cname, $cols, true)) { $col['creado_en'] = $cname; break; }
  }

  if (!$col['archivo'] || !in_array('tenant_id', $cols, true) || !in_array('mantenimiento_id', $cols, true)) {
    $adjEnabled = false;
  }

  if ($adjEnabled) {
    $selectNombre = $col['nombre'] ? ("`".$col['nombre']."` AS nombre") : "NULL AS nombre";
    $selectMime   = $col['mime'] ? ("`".$col['mime']."` AS mime") : "NULL AS mime";
    $selectTam    = $col['tamano'] ? ("`".$col['tamano']."` AS tamano") : "NULL AS tamano";
    $selectNota   = $col['nota'] ? ("`".$col['nota']."` AS nota") : "NULL AS nota";
    $selectFecha  = $col['creado_en'] ? ("`".$col['creado_en']."` AS creado_en") : "NULL AS creado_en";

    $q = db()->prepare("
      SELECT
        id,
        `".$col['archivo']."` AS archivo,
        $selectNombre,
        $selectMime,
        $selectTam,
        $selectNota,
        $selectFecha
      FROM $adjTable
      WHERE tenant_id = :t AND mantenimiento_id = :m
      ORDER BY id DESC
    ");
    $q->execute([':t'=>$tenantId, ':m'=>$id]);
    $adjuntos = $q->fetchAll();
  }
}

/* =========================
   Empresa (campos)
========================= */
$costoTotal = ((float)($m['costo_mano_obra'] ?? 0) + (float)($m['costo_repuestos'] ?? 0));

$empresaNombre = $tenant && !empty($tenant['nombre']) ? (string)$tenant['nombre'] : '—';
$empresaNit    = $tenant && !empty($tenant['nit']) ? (string)$tenant['nit'] : '';
$empresaTel    = $tenant && !empty($tenant['telefono']) ? (string)$tenant['telefono'] : '';
$empresaEmail  = $tenant && !empty($tenant['email']) ? (string)$tenant['email'] : '';
$empresaDir    = $tenant && !empty($tenant['direccion']) ? (string)$tenant['direccion'] : '';
$empresaCiu    = $tenant && !empty($tenant['ciudad']) ? (string)$tenant['ciudad'] : '';

$logoUrl = '';
if ($tenant) {
  if (!empty($tenant['logo_url'])) $logoUrl = (string)$tenant['logo_url'];
  elseif (!empty($tenant['logo_path'])) $logoUrl = build_public_url2((string)$tenant['logo_path'], $publicDir);
}

/* URL volver */
$backUrl = e2(base_url()) . "/index.php?route=mantenimiento_detalle&id=".(int)$m['id']."&return=activo_detalle&return_id=".(int)$m['activo_id'];

$estado = strtoupper(trim((string)($m['estado'] ?? '')));
$estadoCss = 'st';
if ($estado === 'CERRADO' || $estado === 'FINALIZADO') $estadoCss = 'st ok';
elseif ($estado === 'EN PROCESO') $estadoCss = 'st warn';
elseif ($estado === 'CANCELADO') $estadoCss = 'st bad';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orden de Mantenimiento #<?= (int)$m['id'] ?></title>

  <style>
    :root{
      --ink:#111; --muted:#555; --line:#1a1a1a; --soft:#f2f4f7; --soft2:#eef1f5; --accent:#0d6efd;
      --ok:#198754; --warn:#f59f00; --bad:#dc3545;
    }
    *{ box-sizing:border-box; }
    body{ margin:0; font-family: Arial, Helvetica, sans-serif; color:var(--ink); background:#fff; }
    .no-print{ padding:10px 12mm; text-align:right; background:#f7f7f7; border-bottom:1px solid #eee; }
    .btn{ display:inline-block; padding:8px 12px; border-radius:10px; background:var(--accent); color:#fff; text-decoration:none; font-size:13px; border:0; cursor:pointer; }
    .btn.secondary{ background:#6c757d; }
    .page{ width: 210mm; min-height: 297mm; margin: 0 auto; padding: 12mm; }

    .hero{ border:2px solid var(--line); border-radius:14px; overflow:hidden; }
    .hero-top{
      display:flex; justify-content:space-between; gap:12px;
      padding:12px;
      background:linear-gradient(90deg, #0d6efd 0%, #0b5ed7 40%, #0a58ca 100%);
      color:#fff;
    }
    .brand{ display:flex; gap:10px; align-items:center; min-width:0; flex:1; }
    .logo{
      width:44px;height:44px;border-radius:12px;
      background:rgba(255,255,255,.18);
      display:flex;align-items:center;justify-content:center;
      overflow:hidden;
      border:1px solid rgba(255,255,255,.25);
      flex:0 0 auto;
    }
    .logo img{ width:100%; height:100%; object-fit:contain; background:#fff; }
    .brand .t1{ font-weight:900; font-size:14px; line-height:1.1; text-transform:uppercase; margin:0; }
    .brand .t2{ font-weight:700; font-size:12px; opacity:.92; margin-top:2px; }
    .brand .meta{ font-size:11px; opacity:.92; margin-top:4px; line-height:1.25; }

    .hero-right{ text-align:right; min-width:220px; }
    .pill{
      display:inline-block;
      background:rgba(255,255,255,.18);
      border:1px solid rgba(255,255,255,.25);
      color:#fff;
      padding:6px 10px;
      border-radius:999px;
      font-weight:900;
      font-size:12px;
      letter-spacing:.2px;
    }
    .hero-right .mini{ margin-top:8px; font-size:11px; opacity:.95; line-height:1.35; }

    .hero-bottom{
      display:flex; gap:10px; padding:10px 12px;
      border-top:2px solid var(--line);
      background:#fff; flex-wrap:wrap;
    }
    .chip{
      display:inline-flex; gap:6px; align-items:center;
      padding:6px 10px; border-radius:999px;
      border:1px solid #cfd6df; background:var(--soft);
      font-size:11px; font-weight:700; color:#111;
    }
    .chip b{ font-weight:900; }

    .section{ margin-top:12px; border:1px solid #1a1a1a; border-radius:14px; overflow:hidden; }
    .section .head{
      background:var(--soft2); border-bottom:1px solid #1a1a1a;
      padding:10px 12px;
      font-weight:900; text-transform:uppercase;
      font-size:12px; letter-spacing:.3px;
    }
    .section .body{ padding:10px 12px; }

    .grid{ display:flex; gap:10px; flex-wrap:wrap; }
    .col{ flex:1; min-width: 280px; }

    table.kv{ width:100%; border-collapse:collapse; font-size:11px; }
    table.kv th, table.kv td{ border:1px solid #1a1a1a; padding:7px; vertical-align:top; }
    table.kv th{ width:34%; background:#f8f9fb; text-align:left; font-weight:900; }

    .box{
      border:1px solid #1a1a1a; border-radius:12px;
      padding:10px; background:#fff; font-size:11px;
      white-space:pre-wrap; min-height:18mm;
    }
    .muted{ color:var(--muted); }
    .small{ font-size:10px; color:var(--muted); }

    .sign-grid{ display:flex; gap:10px; flex-wrap:wrap; }
    .sign{ flex:1; min-width:280px; border:1px solid #1a1a1a; border-radius:14px; overflow:hidden; background:#fff; }
    .sign .sh{ background:#f8f9fb; padding:10px 12px; border-bottom:1px solid #1a1a1a; font-weight:900; font-size:12px; text-transform:uppercase; }
    .sign .sb{ padding:10px 12px; }
    .sig-img{
      border:1px dashed #aab4c2; border-radius:12px;
      padding:10px; background:#fff;
      height:48mm; display:flex; align-items:center; justify-content:center;
      overflow:hidden;
    }
    .sig-img img{ max-width:100%; max-height:100%; object-fit:contain; display:block; }
    .sig-line{
      border-top:1px solid #1a1a1a; margin-top:10px; padding-top:6px;
      font-size:11px; display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap;
    }

    .st{
      display:inline-block;
      padding:2px 10px;
      border-radius:999px;
      border:1px solid rgba(255,255,255,.45);
      background:rgba(255,255,255,.18);
      font-size:11px;
      font-weight:900;
      color:#fff;
      text-transform:uppercase;
    }
    .ok{ background:rgba(25,135,84,.25); border-color:rgba(25,135,84,.55); }
    .warn{ background:rgba(245,159,0,.25); border-color:rgba(245,159,0,.55); }
    .bad{ background:rgba(220,53,69,.25); border-color:rgba(220,53,69,.55); }

    .footer{
      margin-top:12px; font-size:10px; color:#444;
      display:flex; justify-content:space-between; gap:10px;
      border-top:1px dashed #9aa6b2; padding-top:8px;
    }

    @media print{
      .no-print{ display:none !important; }
      @page{ size: A4; margin: 10mm; }
      .page{ padding:0; width:auto; min-height:auto; }
      body{ -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>

  <div class="no-print">
    <a class="btn secondary" href="<?= $backUrl ?>">Volver</a>
    <button class="btn" onclick="window.print()">Imprimir / Guardar PDF</button>
  </div>

  <div class="page">

    <div class="hero">
      <div class="hero-top">
        <div class="brand">
          <div class="logo">
            <?php if ($logoUrl): ?>
              <img src="<?= e2($logoUrl) ?>" alt="Logo">
            <?php else: ?>
              <div style="font-weight:900;font-size:14px;opacity:.95;">GA</div>
            <?php endif; ?>
          </div>
          <div style="min-width:0;">
            <div class="t1">Orden / Reporte de Mantenimiento</div>
            <div class="t2"><?= e2($empresaNombre) ?></div>
            <div class="meta">
              <?php if ($empresaNit !== ''): ?>NIT: <?= e2($empresaNit) ?> · <?php endif; ?>
              <?php if ($empresaCiu !== ''): ?><?= e2($empresaCiu) ?> · <?php endif; ?>
              <?php if ($empresaDir !== ''): ?><?= e2($empresaDir) ?><?php endif; ?><br>
              <?php if ($empresaTel !== ''): ?>Tel: <?= e2($empresaTel) ?> · <?php endif; ?>
              <?php if ($empresaEmail !== ''): ?>Email: <?= e2($empresaEmail) ?><?php endif; ?>
            </div>
          </div>
        </div>

        <div class="hero-right">
          <div class="pill">No. <?= (int)$m['id'] ?></div>
          <div class="mini">
            Creado: <b><?= e2(fmt_dt2($m['creado_en'],19)) ?></b><br>
            Estado: <span class="<?= $estadoCss ?>"><?= e2($estado ?: '—') ?></span><br>
            Tipo: <b><?= e2((string)($m['tipo'] ?? '—')) ?></b><br>
            Prioridad: <b><?= e2((string)($m['prioridad'] ?? '—')) ?></b>
          </div>
        </div>
      </div>

      <div class="hero-bottom">
        <div class="chip">Activo: <b><?= e2($m['activo_nombre'] ?: '—') ?></b></div>
        <div class="chip">Código: <b><?= e2($m['codigo_interno'] ?: '—') ?></b></div>
        <div class="chip">Ubicación: <b><?= e2($ubic) ?></b></div>
        <div class="chip">Categoría: <b><?= e2($m['categoria'] ?: '—') ?></b></div>
        <div class="chip">Tipo activo: <b><?= e2($tipoTxt) ?></b></div>
      </div>
    </div>

    <div class="section">
      <div class="head">Datos del activo</div>
      <div class="body">
        <div class="grid">
          <div class="col">
            <table class="kv">
              <tr><th>Código interno</th><td><?= e2($m['codigo_interno'] ?: '—') ?></td></tr>
              <tr><th>Nombre</th><td><?= e2($m['activo_nombre'] ?: '—') ?></td></tr>
              <tr><th>Categoría</th><td><?= e2($m['categoria'] ?: '—') ?></td></tr>
              <tr><th>Tipo</th><td><?= e2($tipoTxt) ?></td></tr>
              <tr><th>Ubicación</th><td><?= e2($ubic) ?></td></tr>
            </table>
          </div>
          <div class="col">
            <table class="kv">
              <tr><th>Modelo</th><td><?= e2($m['modelo'] ?: '—') ?></td></tr>
              <tr><th>Serial</th><td><?= e2($m['serial'] ?: '—') ?></td></tr>
              <tr><th>Placa</th><td><?= e2($m['placa'] ?: '—') ?></td></tr>
              <tr><th>Red / Identidad</th><td><?= e2($redTxt) ?></td></tr>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="head">Planificación y costos</div>
      <div class="body">
        <div class="grid">
          <div class="col">
            <table class="kv">
              <tr><th>Fecha programada</th><td><?= e2(fmt_dt2($m['fecha_programada'],19)) ?></td></tr>
              <tr><th>Fecha inicio</th><td><?= e2(fmt_dt2($m['fecha_inicio'],19)) ?></td></tr>
              <tr><th>Fecha fin</th><td><?= e2(fmt_dt2($m['fecha_fin'],19)) ?></td></tr>
            </table>
          </div>
          <div class="col">
            <table class="kv">
              <tr><th>Mano de obra</th><td><?= e2(money0((float)($m['costo_mano_obra'] ?? 0))) ?></td></tr>
              <tr><th>Repuestos</th><td><?= e2(money0((float)($m['costo_repuestos'] ?? 0))) ?></td></tr>
              <tr><th><b>Total</b></th><td><b><?= e2(money0((float)$costoTotal)) ?></b></td></tr>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="head">Descripción técnica</div>
      <div class="body">
        <div class="grid">
          <div class="col">
            <div class="small muted" style="margin-bottom:6px;"><b>Falla reportada</b></div>
            <div class="box"><?= e2($m['falla_reportada'] ?: '—') ?></div>

            <div class="small muted" style="margin:10px 0 6px;"><b>Diagnóstico</b></div>
            <div class="box"><?= e2($m['diagnostico'] ?: '—') ?></div>
          </div>
          <div class="col">
            <div class="small muted" style="margin-bottom:6px;"><b>Actividades</b></div>
            <div class="box"><?= e2($m['actividades'] ?: '—') ?></div>

            <div class="small muted" style="margin:10px 0 6px;"><b>Recomendaciones</b></div>
            <div class="box"><?= e2($m['recomendaciones'] ?: '—') ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="head">Firmas y responsables</div>
      <div class="body">

        <!-- hashes quedan ocultos (solo auditoría interna) -->
        <!-- tecnico_hash: <?= e2($tecnicoFirmaHash) ?> -->
        <!-- recibido_hash: <?= e2($recibidoFirmaHash) ?> -->

        <div class="sign-grid">
          <div class="sign" data-user-id="<?= (int)$tecnicoUserId ?>" data-hash="<?= e2($tecnicoFirmaHash) ?>">
            <div class="sh">Técnico / Responsable</div>
            <div class="sb">
              <div class="small muted" style="margin-bottom:6px;">
                <b>Nombre:</b> <?= e2($tecnicoNombre !== '' ? $tecnicoNombre : '—') ?><br>
                <b>Cargo:</b> <?= e2($tecnicoCargo !== '' ? $tecnicoCargo : '—') ?><br>
                <b>Tarjeta profesional:</b> <?= e2($tecnicoTP !== '' ? $tecnicoTP : '—') ?>
              </div>

              <div class="sig-img">
                <?php if ($tecnicoFirmaUrl): ?>
                  <img src="<?= e2($tecnicoFirmaUrl) ?>" alt="Firma técnico"
                       onerror="this.style.display='none'; this.parentNode.innerHTML='<div class=&quot;small muted&quot; style=&quot;text-align:center;&quot;>No se pudo cargar la firma.<br><span class=&quot;small&quot;>URL: <?= e2($tecnicoFirmaUrl) ?></span></div>';">
                <?php else: ?>
                  <div class="small muted" style="text-align:center;">
                    No hay imagen de firma registrada.<br>
                    <span class="small">Cargue la firma del usuario en el módulo de Usuarios.</span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="sig-line">
                <div class="muted">Firma del técnico registrada.</div>
                <div class="muted">Usuario ID: <?= (int)$tecnicoUserId ?></div>
              </div>
            </div>
          </div>

          <div class="sign" data-user-id="<?= (int)$recibidoUserId ?>" data-hash="<?= e2($recibidoFirmaHash) ?>">
            <div class="sh">Recibido por</div>
            <div class="sb">
              <div class="small muted" style="margin-bottom:6px;">
                <b>Nombre:</b> <?= e2($recibidoNombre !== '' ? $recibidoNombre : '—') ?><br>
                <b>Cargo:</b> <?= e2($recibidoCargo !== '' ? $recibidoCargo : '—') ?>
              </div>

              <div class="sig-img">
                <?php if ($recibidoFirmaUrl): ?>
                  <img src="<?= e2($recibidoFirmaUrl) ?>" alt="Firma recibido"
                       onerror="this.style.display='none'; this.parentNode.innerHTML='<div class=&quot;small muted&quot; style=&quot;text-align:center;&quot;>No se pudo cargar la firma.<br><span class=&quot;small&quot;>URL: <?= e2($recibidoFirmaUrl) ?></span></div>';">
                <?php else: ?>
                  <div class="small muted" style="text-align:center;">
                    No hay imagen de firma registrada.<br>
                    <span class="small">Cargue la firma del usuario en el módulo de Usuarios.</span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="sig-line">
                <div class="muted">Firma de recibido registrada.</div>
                <div class="muted">Usuario ID: <?= (int)$recibidoUserId ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="small muted" style="margin-top:10px;">
          Para que el reporte sea 100% exacto siempre, lo ideal es guardar en <b>mantenimientos</b>:
          <b>tecnico_usuario_id</b> y <b>recibido_por_usuario_id</b>.
          Este reporte ya hace fallback por nombre/email si esos campos aún no existen.
        </div>

      </div>
    </div>

    <div class="footer">
      <div>GeoActivos · Multi-cliente</div>
      <div class="muted">Generado: <?= date('Y-m-d H:i') ?> · Mantenimiento #<?= (int)$m['id'] ?></div>
    </div>

  </div>
</body>
</html>
