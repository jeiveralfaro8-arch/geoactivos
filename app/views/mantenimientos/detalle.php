<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

/* =========================================================
   CONTEXTO (Hoja de Vida)
   - Si vienes desde activo_detalle: ?return=activo_detalle&return_id=XX
   - Si no viene nada: vuelve a mantenimientos
========================================================= */
$returnTo = (string)($_GET['return'] ?? '');
$returnId = (int)($_GET['return_id'] ?? 0);

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

if (!$m) {
  http_response_code(404);
  echo "Mantenimiento no encontrado";
  exit;
}

/* --------- URLS (volver/editar/activo) --------- */
$backUrl = e(base_url()) . '/index.php?route=mantenimientos';
$activoUrl = e(base_url()) . '/index.php?route=activo_detalle&id=' . (int)$m['activo_id'];

// Si viene return explícito y es válido, volvemos allá
if ($returnTo === 'activo_detalle' && $returnId > 0) {
  // validamos tenant
  $chk = db()->prepare("SELECT id FROM activos WHERE id=:id AND tenant_id=:t LIMIT 1");
  $chk->execute([':id'=>$returnId, ':t'=>$tenantId]);
  if ($chk->fetch()) {
    $backUrl = e(base_url()) . '/index.php?route=activo_detalle&id=' . (int)$returnId;
  }
} else {
  // por defecto: hoja de vida del activo del mantenimiento (mejor UX)
  $backUrl = $activoUrl;
}

// Editar manteniendo retorno al activo
$editUrl = e(base_url()) . '/index.php?route=mantenimiento_form&id=' . (int)$m['id']
        . '&return=activo_detalle&return_id=' . (int)$m['activo_id'];

/* ====== NUEVO: URL auditoría mantenimiento (mantiene retorno) ====== */
$auditUrl = e(base_url()) . '/index.php?route=mantenimiento_auditoria&id=' . (int)$m['id']
        . '&return=' . urlencode($returnTo)
        . '&return_id=' . (int)$returnId;

/* --------- Helpers badges --------- */
function badge_estado_mant($estado){
  $b = 'secondary';
  if ($estado === 'PROGRAMADO') $b = 'info';
  elseif ($estado === 'EN_PROCESO') $b = 'warning';
  elseif ($estado === 'CERRADO') $b = 'success';
  elseif ($estado === 'ANULADO') $b = 'danger';
  return $b;
}
function badge_tipo($tipo){
  $b = 'secondary';
  if ($tipo === 'PREVENTIVO') $b = 'info';
  elseif ($tipo === 'CORRECTIVO') $b = 'warning';
  elseif ($tipo === 'PREDICTIVO') $b = 'secondary';
  return $b;
}
function badge_prio($p){
  $b = 'secondary';
  if ($p === 'MEDIA') $b = 'info';
  elseif ($p === 'ALTA') $b = 'warning';
  elseif ($p === 'CRITICA') $b = 'danger';
  return $b;
}
function fmt_dt($v, $len=16){
  if (!$v) return '—';
  return substr((string)$v, 0, $len);
}

/* ---------------- Tipo con prefijo ---------------- */
$tipoNombre = $m['tipo_activo'] ? (string)$m['tipo_activo'] : '';
$tipoCod    = $m['tipo_codigo'] ? (string)$m['tipo_codigo'] : '';
$tipoTxt    = ($tipoNombre === '') ? '—' : (($tipoCod !== '' ? ($tipoCod . ' · ') : '') . $tipoNombre);

/* ---------------- Ubicación ---------------- */
$ubic = '';
if (!empty($m['sede'])) $ubic .= $m['sede'];
if (!empty($m['area'])) $ubic .= ($ubic ? ' - ' : '') . $m['area'];
if ($ubic === '') $ubic = '—';

/* ---------------- Red ---------------- */
$red = '—';
$host = trim((string)($m['hostname'] ?? ''));
$usaDhcp = (int)($m['usa_dhcp'] ?? 1);
$ip = trim((string)($m['ip_fija'] ?? ''));
$mac = trim((string)($m['mac'] ?? ''));

$tmp = '';
if ($host !== '') $tmp .= e($host);
$tmp .= ($tmp ? ' · ' : '') . ($usaDhcp ? 'DHCP' : e($ip !== '' ? $ip : 'IP fija (sin dato)'));
if ($mac !== '') $tmp .= '<br><span class="text-muted text-sm">MAC: ' . e($mac) . '</span>';
if ($tmp !== '') $red = $tmp;

/* ---------------- Costos ---------------- */
$costoTotal = (float)$m['costo_mano_obra'] + (float)$m['costo_repuestos'];

/* =====================================================================
   ADJUNTOS
   - Detecta tabla real: mant_adjuntos o mantenimientos_adjuntos
   - Detecta columnas reales (nombre_original, nombre, archivo, ruta, etc.)
===================================================================== */
function table_exists($table) {
  $q = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $q->execute([':t'=>$table]);
  return (bool)$q->fetch();
}
function table_columns($table) {
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

/* Detectar tabla */
$adjTable = null;
if (table_exists('mant_adjuntos')) $adjTable = 'mant_adjuntos';
elseif (table_exists('mantenimientos_adjuntos')) $adjTable = 'mantenimientos_adjuntos';

$adjEnabled = ($adjTable !== null);
$adjuntos = [];
$totalAdjuntos = 0;

$col = [
  'archivo' => null,
  'nombre' => null,
  'mime' => null,
  'tamano' => null,
  'nota' => null,
  'creado_en' => null,
];

if ($adjEnabled) {
  $cols = table_columns($adjTable);

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

  // mínimo: columna de archivo y llaves
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
    $totalAdjuntos = count($adjuntos);
  }
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<style>
/* ====== Ajustes PRO sin romper AdminLTE ====== */
.kpi-mini{
  border:1px solid rgba(0,0,0,.06);
  background: #fff;
  border-radius: .5rem;
  padding: .75rem .9rem;
  height: 100%;
}
.kpi-mini .label{font-size:.78rem;color:#6c757d}
.kpi-mini .value{font-size:1.3rem;font-weight:900;line-height:1.1}
.kpi-mini .sub{font-size:.78rem;color:#6c757d;margin-top:.2rem}
.hero-mant{
  border:1px solid rgba(0,0,0,.08);
  border-radius:.75rem;
  padding:1rem;
  background: linear-gradient(135deg, rgba(23,162,184,.08), rgba(0,123,255,.05));
}
.hero-mant .title{
  font-weight:900;
  font-size:1.1rem;
}
.hero-mant .meta{
  color:#6c757d;
  font-size:.85rem;
}
.soft-box{
  background:#f8f9fa;
  border:1px solid rgba(0,0,0,.08);
  border-radius:.6rem;
  padding:1rem;
}
.tab-pane .section-title{
  font-weight:800;
  font-size:.9rem;
  color:#495057;
  margin-bottom:.5rem;
}
</style>

<div class="card card-outline card-warning">
  <div class="card-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
      <div>
        <h3 class="card-title mb-0">
          <i class="fas fa-tools"></i> Mantenimiento #<?= (int)$m['id'] ?>
        </h3>
        <div class="text-muted text-sm mt-1">
          <?= e($m['codigo_interno']) ?> · <?= e($m['activo_nombre']) ?>
        </div>
      </div>

      <div class="mt-2 mt-md-0">
        <span class="badge badge-<?= badge_estado_mant($m['estado']) ?> mr-1"><?= e($m['estado']) ?></span>
        <span class="badge badge-<?= badge_tipo($m['tipo']) ?> mr-1"><?= e($m['tipo']) ?></span>
        <span class="badge badge-<?= badge_prio($m['prioridad']) ?> mr-2"><?= e($m['prioridad']) ?></span>

        <a class="btn btn-sm btn-secondary" href="<?= e($backUrl) ?>">
          <i class="fas fa-arrow-left"></i> Volver
        </a>
        <a class="btn btn-sm btn-primary" href="<?= e($editUrl) ?>">
          <i class="fas fa-edit"></i> Editar
        </a>

<?php
$printUrl = e(base_url()) . '/index.php?route=mantenimiento_print&id='.(int)$m['id'];
?>
<a class="btn btn-sm btn-outline-dark" target="_blank" href="<?= e($printUrl) ?>">
  <i class="fas fa-print"></i> Imprimir
</a>


        <a class="btn btn-sm btn-outline-info" href="<?= e($activoUrl) ?>">
          <i class="fas fa-cube"></i> Hoja de vida
        </a>

        <!-- ✅ NUEVO: Auditoría del mantenimiento -->
        <a class="btn btn-sm btn-outline-secondary" href="<?= e($auditUrl) ?>">
          <i class="fas fa-stream"></i> Auditoría
        </a>
      </div>
    </div>
  </div>

  <div class="card-body">

    <!-- HERO + KPIs -->
    <div class="row">
      <div class="col-lg-6 mb-3">
        <div class="hero-mant">
          <div class="d-flex align-items-center">
            <div class="mr-3" style="font-size:28px;opacity:.75">
              <i class="fas fa-laptop-medical"></i>
            </div>
            <div style="min-width:0">
              <div class="title">
                <?= e($m['codigo_interno']) ?> · <?= e($m['activo_nombre']) ?>
              </div>
              <div class="meta">
                Categoría: <b><?= e($m['categoria']) ?></b> · Tipo: <b><?= e($tipoTxt) ?></b><br>
                Ubicación: <b><?= e($ubic) ?></b>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6 mb-3">
        <div class="row">
          <div class="col-md-4 mb-2">
            <div class="kpi-mini">
              <div class="label"><i class="fas fa-dollar-sign text-muted mr-1"></i> Total</div>
              <div class="value">$ <?= number_format($costoTotal, 0, ',', '.') ?></div>
              <div class="sub">MO + Repuestos</div>
            </div>
          </div>
          <div class="col-md-4 mb-2">
            <div class="kpi-mini">
              <div class="label"><i class="fas fa-paperclip text-muted mr-1"></i> Adjuntos</div>
              <div class="value"><?= (int)$totalAdjuntos ?></div>
              <div class="sub"><?= $adjEnabled ? 'Disponibles' : 'No habilitado' ?></div>
            </div>
          </div>
          <div class="col-md-4 mb-2">
            <div class="kpi-mini">
              <div class="label"><i class="fas fa-calendar-alt text-muted mr-1"></i> Programado</div>
              <div class="value" style="font-size:1.05rem;font-weight:900"><?= e($m['fecha_programada'] ? substr((string)$m['fecha_programada'],0,10) : '—') ?></div>
              <div class="sub">Planificación</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TABS -->
    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item">
        <a class="nav-link active" data-toggle="tab" href="#resumen" role="tab">
          <i class="fas fa-info-circle"></i> Resumen
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#fallas" role="tab">
          <i class="fas fa-bug"></i> Falla / Diagnóstico
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#actividades" role="tab">
          <i class="fas fa-clipboard-list"></i> Actividades
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#costos" role="tab">
          <i class="fas fa-dollar-sign"></i> Costos
        </a>
      </li>

      <li class="nav-item">
        <?php if ($adjEnabled): ?>
          <a class="nav-link" data-toggle="tab" href="#adjuntos" role="tab">
            <i class="fas fa-paperclip"></i> Adjuntos
            <span class="badge badge-light ml-1"><?= (int)$totalAdjuntos ?></span>
          </a>
        <?php else: ?>
          <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true"
             title="Adjuntos no disponibles (tabla/columnas incompletas).">
            <i class="fas fa-paperclip"></i> Adjuntos
          </a>
        <?php endif; ?>
      </li>

      <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#activo" role="tab">
          <i class="fas fa-cube"></i> Datos del activo
        </a>
      </li>
    </ul>

    <div class="tab-content pt-3">

      <!-- RESUMEN -->
      <div class="tab-pane fade show active" id="resumen">
        <div class="soft-box">
          <div class="section-title"><i class="fas fa-clipboard-check mr-1"></i> Información general</div>
          <div class="row">
            <div class="col-md-6">
              <table class="table table-sm mb-0">
                <tr><th style="width:210px">Tipo</th><td><?= e($m['tipo']) ?></td></tr>
                <tr><th>Estado</th><td><span class="badge badge-<?= badge_estado_mant($m['estado']) ?>"><?= e($m['estado']) ?></span></td></tr>
                <tr><th>Prioridad</th><td><span class="badge badge-<?= badge_prio($m['prioridad']) ?>"><?= e($m['prioridad']) ?></span></td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-sm mb-0">
                <tr><th style="width:210px">Fecha programada</th><td><?= e($m['fecha_programada'] ?: '—') ?></td></tr>
                <tr><th>Fecha inicio</th><td><?= e($m['fecha_inicio'] ? fmt_dt($m['fecha_inicio'],16) : '—') ?></td></tr>
                <tr><th>Fecha fin</th><td><?= e($m['fecha_fin'] ? fmt_dt($m['fecha_fin'],16) : '—') ?></td></tr>
                <tr><th>Creado en</th><td><?= e($m['creado_en'] ? fmt_dt($m['creado_en'],19) : '—') ?></td></tr>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- FALLA / DIAGNOSTICO -->
      <div class="tab-pane fade" id="fallas">
        <div class="row">
          <div class="col-md-6">
            <div class="section-title"><i class="fas fa-exclamation-triangle mr-1"></i> Falla reportada</div>
            <div class="soft-box" style="min-height:140px">
              <?= nl2br(e($m['falla_reportada'] ?: '—')) ?>
            </div>
          </div>
          <div class="col-md-6">
            <div class="section-title"><i class="fas fa-stethoscope mr-1"></i> Diagnóstico</div>
            <div class="soft-box" style="min-height:140px">
              <?= nl2br(e($m['diagnostico'] ?: '—')) ?>
            </div>
          </div>
          <div class="col-md-12 mt-3">
            <div class="section-title"><i class="fas fa-lightbulb mr-1"></i> Recomendaciones</div>
            <div class="soft-box" style="min-height:120px">
              <?= nl2br(e($m['recomendaciones'] ?: '—')) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ACTIVIDADES -->
      <div class="tab-pane fade" id="actividades">
        <div class="section-title"><i class="fas fa-tasks mr-1"></i> Actividades realizadas / plan</div>
        <div class="soft-box" style="min-height:220px">
          <?= nl2br(e($m['actividades'] ?: '—')) ?>
        </div>
      </div>

      <!-- COSTOS -->
      <div class="tab-pane fade" id="costos">
        <div class="soft-box">
          <div class="section-title"><i class="fas fa-coins mr-1"></i> Resumen de costos</div>
          <table class="table table-sm mb-0">
            <tr><th style="width:220px">Costo mano de obra</th><td>$ <?= number_format((float)$m['costo_mano_obra'], 0, ',', '.') ?></td></tr>
            <tr><th>Costo repuestos</th><td>$ <?= number_format((float)$m['costo_repuestos'], 0, ',', '.') ?></td></tr>
            <tr><th><b>Total</b></th><td><b>$ <?= number_format($costoTotal, 0, ',', '.') ?></b></td></tr>
          </table>
        </div>
      </div>

      <!-- ADJUNTOS -->
      <?php if ($adjEnabled): ?>
      <div class="tab-pane fade" id="adjuntos">

        <div class="row">
          <div class="col-lg-8 mb-3">
            <div class="soft-box">
              <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                  <div class="section-title mb-0"><i class="fas fa-upload mr-1"></i> Adjuntar archivo</div>
                  <div class="text-muted text-sm">Imágenes, PDF, Office, ZIP (sugerido máx. 10MB).</div>
                </div>
                <div class="text-muted text-sm mt-2 mt-lg-0">
                  Mantenimiento #<?= (int)$m['id'] ?> · Activo <?= e($m['codigo_interno']) ?>
                </div>
              </div>

              <div id="adjAlert" class="alert alert-danger text-sm mt-2" style="display:none;"></div>

              <form id="formAdj" enctype="multipart/form-data" onsubmit="return false;" class="mt-2">
                <input type="hidden" name="mantenimiento_id" value="<?= (int)$m['id'] ?>">

                <div class="form-row">
                  <div class="form-group col-md-7">
                    <input type="file" class="form-control" name="archivo" id="adjArchivo" required>
                    <small class="text-muted">Tip: PDF e imágenes se pueden previsualizar.</small>
                  </div>
                  <div class="form-group col-md-5">
                    <button class="btn btn-primary btn-block" id="btnAdjSubir" type="button">
                      <i class="fas fa-upload"></i> Subir adjunto
                    </button>
                  </div>
                </div>

                <div class="form-group mb-0">
                  <input class="form-control" name="nota" placeholder="Nota (opcional) - Ej: Orden, evidencia, foto...">
                </div>
              </form>
            </div>
          </div>

          <div class="col-lg-4 mb-3">
            <div class="kpi-mini">
              <div class="label"><i class="fas fa-paperclip text-muted mr-1"></i> Total adjuntos</div>
              <div class="value"><?= (int)$totalAdjuntos ?></div>
              <div class="sub">Historial del mantenimiento</div>
            </div>
          </div>
        </div>

        <?php if (!$adjuntos): ?>
          <div class="text-muted">No hay adjuntos para este mantenimiento.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover text-nowrap">
              <thead>
                <tr>
                  <th>Archivo</th>
                  <th style="width:180px">Tipo</th>
                  <th style="width:160px">Subido</th>
                  <th style="width:220px" class="text-right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($adjuntos as $a): ?>
                  <?php
                    $nombreMostrado = $a['nombre'] ? (string)$a['nombre'] : (string)$a['archivo'];
                    $mime = $a['mime'] ? (string)$a['mime'] : '';
                    $subido = $a['creado_en'] ? substr((string)$a['creado_en'], 0, 16) : '—';

                    $isPreview = false;
                    if ($mime) {
                      if (strpos($mime, 'image/') === 0) $isPreview = true;
                      if ($mime === 'application/pdf') $isPreview = true;
                    } else {
                      $ext = strtolower(pathinfo((string)$nombreMostrado, PATHINFO_EXTENSION));
                      if (in_array($ext, ['png','jpg','jpeg','webp','gif','pdf'], true)) $isPreview = true;
                    }

                    $sizeTxt = '';
                    if ($a['tamano'] !== null) {
                      $kb = ((float)$a['tamano'])/1024;
                      $sizeTxt = number_format($kb, 1, ',', '.') . ' KB';
                    }
                  ?>
                  <tr>
                    <td style="max-width:560px;">
                      <div style="font-weight:800;white-space:normal;">
                        <i class="fas fa-paperclip text-muted mr-1"></i>
                        <?= e($nombreMostrado) ?>
                      </div>
                      <?php if ($a['nota']): ?>
                        <div class="text-muted text-sm" style="white-space:normal;">
                          <?= e((string)$a['nota']) ?>
                        </div>
                      <?php endif; ?>
                      <?php if ($sizeTxt): ?>
                        <div class="text-muted text-sm"><?= e($sizeTxt) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= e($mime ?: '—') ?></td>
                    <td><?= e($subido) ?></td>
                    <td class="text-right">
                      <?php if ($isPreview): ?>
                        <a class="btn btn-sm btn-outline-info"
                           target="_blank"
                           href="<?= e(base_url()) ?>/index.php?route=ajax_mant_adj_download&id=<?= (int)$a['id'] ?>&inline=1"
                           title="Vista previa">
                          <i class="fas fa-eye"></i>
                        </a>
                      <?php else: ?>
                        <a class="btn btn-sm btn-outline-secondary disabled" href="#" tabindex="-1" aria-disabled="true"
                           title="Vista previa no disponible">
                          <i class="fas fa-eye"></i>
                        </a>
                      <?php endif; ?>

                      <a class="btn btn-sm btn-outline-primary"
                         href="<?= e(base_url()) ?>/index.php?route=ajax_mant_adj_download&id=<?= (int)$a['id'] ?>"
                         title="Descargar">
                        <i class="fas fa-download"></i>
                      </a>

                      <button class="btn btn-sm btn-outline-danger"
                              type="button"
                              onclick="delAdj(<?= (int)$a['id'] ?>)"
                              title="Eliminar">
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <div id="js-mant-adj" data-base-url="<?= e(base_url()) ?>"></div>
        <script src="<?= e(base_url()) ?>/assets/js/mant-adjuntos.js"></script>

      </div>
      <?php endif; ?>

      <!-- DATOS DEL ACTIVO -->
      <div class="tab-pane fade" id="activo">
        <div class="soft-box">
          <div class="section-title"><i class="fas fa-cube mr-1"></i> Ficha del activo</div>
          <div class="row">
            <div class="col-md-6">
              <table class="table table-sm mb-0">
                <tr><th style="width:200px">Código</th><td><?= e($m['codigo_interno']) ?></td></tr>
                <tr><th>Nombre</th><td><?= e($m['activo_nombre']) ?></td></tr>
                <tr><th>Categoría</th><td><?= e($m['categoria']) ?></td></tr>
                <tr><th>Tipo</th><td><?= e($tipoTxt) ?></td></tr>
                <tr><th>Ubicación</th><td><?= e($ubic) ?></td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-sm mb-0">
                <tr><th style="width:200px">Modelo</th><td><?= e($m['modelo'] ?: '—') ?></td></tr>
                <tr><th>Serial</th><td><?= e($m['serial'] ?: '—') ?></td></tr>
                <tr><th>Placa</th><td><?= e($m['placa'] ?: '—') ?></td></tr>
                <tr><th>Red</th><td><?= $red ?></td></tr>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div><!-- tab-content -->
  </div><!-- card-body -->
</div><!-- card -->

<?php require __DIR__ . '/../layout/footer.php'; ?>
