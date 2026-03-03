<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

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

if (!$activo) {
  http_response_code(404);
  echo "Activo no encontrado";
  exit;
}

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
  if ($regla) {
    $usaRed = (int)$regla['usa_red'];
    $usaSoftware = (int)$regla['usa_software'];
  }
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
$swCount = 0;
if ($usaSoftware === 1) {
  $cnt = db()->prepare("SELECT COUNT(*) c FROM activos_software WHERE tenant_id=:t AND activo_id=:a");
  $cnt->execute([':t'=>$tenantId, ':a'=>$id]);
  $swCount = (int)($cnt->fetch()['c'] ?? 0);

  $sw = db()->prepare("
    SELECT id, nombre, version, licencia_tipo, fecha_vencimiento
    FROM activos_software
    WHERE tenant_id=:t AND activo_id=:a
    ORDER BY id DESC
    LIMIT 50
  ");
  $sw->execute([':t'=>$tenantId, ':a'=>$id]);
  $swRows = $sw->fetchAll();
}

/* ---------------- Adjuntos del activo ---------------- */
$adjRows = [];
$adjCount = 0;

$ac = db()->prepare("SELECT COUNT(*) c FROM activos_adjuntos WHERE tenant_id=:t AND activo_id=:a");
$ac->execute([':t'=>$tenantId, ':a'=>$id]);
$adjCount = (int)($ac->fetch()['c'] ?? 0);

$al = db()->prepare("
  SELECT id, nombre_original, mime, tamano, creado_en
  FROM activos_adjuntos
  WHERE tenant_id=:t AND activo_id=:a
  ORDER BY id DESC
  LIMIT 200
");
$al->execute([':t'=>$tenantId, ':a'=>$id]);
$adjRows = $al->fetchAll();

/* ---------------- Mantenimientos (DETALLE COMPLETO) ---------------- */
$mantRows = [];
$mantCount = 0;

$mc = db()->prepare("SELECT COUNT(*) c FROM mantenimientos WHERE tenant_id=:t AND activo_id=:a");
$mc->execute([':t'=>$tenantId, ':a'=>$id]);
$mantCount = (int)($mc->fetch()['c'] ?? 0);

$mq = db()->prepare("
  SELECT
    id, tipo, estado, prioridad,
    fecha_programada, fecha_inicio, fecha_fin,
    falla_reportada, diagnostico, actividades, recomendaciones,
    costo_mano_obra, costo_repuestos, creado_en
  FROM mantenimientos
  WHERE tenant_id=:t AND activo_id=:a
  ORDER BY id DESC
  LIMIT 200
");
$mq->execute([':t'=>$tenantId, ':a'=>$id]);
$mantRows = $mq->fetchAll();

/* ---------------- Helpers UI ---------------- */
function badge_estado_activo($estado) {
  $b = 'secondary';
  if ($estado === 'ACTIVO') $b = 'success';
  elseif ($estado === 'EN_MANTENIMIENTO') $b = 'warning';
  elseif ($estado === 'BAJA') $b = 'danger';
  return $b;
}
function badge_estado_mant($estado){
  $b = 'secondary';
  if ($estado === 'PROGRAMADO') $b = 'info';
  elseif ($estado === 'EN_PROCESO') $b = 'warning';
  elseif ($estado === 'CERRADO') $b = 'success';
  elseif ($estado === 'ANULADO') $b = 'danger';
  return $b;
}
function badge_tipo_mant($tipo){
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
function fmt_fecha10($v){ return $v ? substr((string)$v,0,10) : '—'; }
function fmt_fecha16($v){ return $v ? substr((string)$v,0,16) : '—'; }
function fmt_bytes($bytes) {
  $bytes = (int)$bytes;
  if ($bytes <= 0) return '0 B';
  $units = ['B','KB','MB','GB','TB'];
  $i = 0;
  while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
  return round($bytes, 1) . ' ' . $units[$i];
}

/* ---------------- Ubicación ---------------- */
$ubic = '';
if (!empty($activo['sede'])) $ubic .= $activo['sede'];
if (!empty($activo['area'])) $ubic .= ($ubic ? ' - ' : '') . $activo['area'];
if ($ubic === '') $ubic = '—';

/* ---------------- Red ---------------- */
$redHtml = '—';
$host = trim((string)($activo['hostname'] ?? ''));
$usaDhcp = (int)($activo['usa_dhcp'] ?? 1);
$ip = trim((string)($activo['ip_fija'] ?? ''));
$mac = trim((string)($activo['mac'] ?? ''));
$tmp = '';
if ($host !== '') $tmp .= e($host);
$tmp .= ($tmp ? ' · ' : '') . ($usaDhcp ? 'DHCP' : e($ip !== '' ? $ip : 'IP fija (sin dato)'));
if ($mac !== '') $tmp .= '<br><span class="text-muted text-sm">MAC: ' . e($mac) . '</span>';
if ($tmp !== '') $redHtml = $tmp;

/* ---------------- Tipo con prefijo ---------------- */
$tipoNombre = $activo['tipo'] ? (string)$activo['tipo'] : '';
$tipoCod    = $activo['tipo_codigo'] ? (string)$activo['tipo_codigo'] : '';
$tipoTxt    = ($tipoNombre === '') ? '—' : (($tipoCod !== '' ? ($tipoCod . ' · ') : '') . $tipoNombre);

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<section class="content">
  <div class="container-fluid">

    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-id-card"></i> Hoja de vida del activo
          <span class="badge badge-<?= badge_estado_activo($activo['estado']) ?> ml-2"><?= e($activo['estado']) ?></span>
        </h3>
        <div class="card-tools">
          <a class="btn btn-sm btn-secondary" href="<?= e(base_url()) ?>/index.php?route=activo_detalle&id=<?= (int)$activo['id'] ?>">
            <i class="fas fa-arrow-left"></i> Volver al detalle
          </a>

          <a class="btn btn-sm btn-outline-primary"
             target="_blank"
             href="<?= e(base_url()) ?>/index.php?route=activo_hoja_vida_print&id=<?= (int)$activo['id'] ?>">
            <i class="fas fa-print"></i> Vista imprimible / PDF
          </a>
        </div>
      </div>

      <div class="card-body">

        <!-- CABECERA PRO (Empresa + Activo) -->
        <div class="p-3 rounded border bg-light mb-3">
          <div class="d-flex align-items-start flex-wrap">
            <div class="mr-3" style="font-size:30px;opacity:.95;">
              <i class="fas fa-building text-primary"></i>
            </div>

            <div class="flex-grow-1">
              <div style="font-size:18px;font-weight:900;line-height:1.2;">
                <?= e($empresaNombre) ?>
              </div>

              <div class="text-muted text-sm">
                <?php if ($empresaNit !== ''): ?>NIT: <b><?= e($empresaNit) ?></b><?php endif; ?>
                <?php if ($empresaNit !== '' && ($empresaTel !== '' || $empresaEmail !== '')): ?> · <?php endif; ?>
                <?php if ($empresaTel !== ''): ?>Tel: <b><?= e($empresaTel) ?></b><?php endif; ?>
                <?php if ($empresaTel !== '' && $empresaEmail !== ''): ?> · <?php endif; ?>
                <?php if ($empresaEmail !== ''): ?>Email: <b><?= e($empresaEmail) ?></b><?php endif; ?>
                <br>
                <?php
                  $dirTxt = trim($empresaDir);
                  $ciuTxt = trim($empresaCiu);
                  $loc = $dirTxt;
                  if ($ciuTxt !== '') $loc .= ($loc ? ' · ' : '') . $ciuTxt;
                  echo $loc ? 'Dirección: <b>'.e($loc).'</b>' : '';
                ?>
              </div>

              <hr class="my-2">

              <div style="font-size:20px;font-weight:900;">
                <i class="fas fa-laptop text-primary mr-1"></i>
                <?= e($activo['codigo_interno']) ?> · <?= e($activo['nombre']) ?>
              </div>
              <div class="text-muted text-sm">
                Categoría: <b><?= e($activo['categoria']) ?></b> · Tipo: <b><?= e($tipoTxt) ?></b><br>
                Ubicación: <b><?= e($ubic) ?></b>
              </div>
            </div>

            <div class="text-right" style="min-width:160px;">
              <span class="badge badge-<?= badge_estado_activo($activo['estado']) ?>" style="font-size:12px;">
                <?= e($activo['estado']) ?>
              </span>
              <div class="text-muted text-sm mt-1">
                GeSaProv Project Design
              </div>
            </div>
          </div>
        </div>

        <!-- KPIs -->
        <div class="row">
          <div class="col-md-4">
            <div class="info-box">
              <span class="info-box-icon bg-info"><i class="fas fa-tools"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Mantenimientos</span>
                <span class="info-box-number"><?= (int)$mantCount ?></span>
                <span class="text-muted text-sm">Historial completo</span>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="info-box">
              <span class="info-box-icon bg-secondary"><i class="fas fa-paperclip"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Adjuntos</span>
                <span class="info-box-number"><?= (int)$adjCount ?></span>
                <span class="text-muted text-sm">Del activo</span>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <?php
              $sumTotal = 0.0;
              foreach ($mantRows as $mm) $sumTotal += ((float)$mm['costo_mano_obra'] + (float)$mm['costo_repuestos']);
            ?>
            <div class="info-box">
              <span class="info-box-icon bg-success"><i class="fas fa-dollar-sign"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Costos acumulados</span>
                <span class="info-box-number">$ <?= number_format($sumTotal, 0, ',', '.') ?></span>
                <span class="text-muted text-sm">MO + Repuestos</span>
              </div>
            </div>
          </div>
        </div>

        <!-- IDENTIFICACIÓN -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-tag"></i> Identificación del activo</h3>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <table class="table table-sm">
                  <tr><th style="width:200px">Código interno</th><td><?= e($activo['codigo_interno']) ?></td></tr>
                  <tr><th>Nombre</th><td><?= e($activo['nombre']) ?></td></tr>
                  <tr><th>Categoría</th><td><?= e($activo['categoria']) ?></td></tr>
                  <tr><th>Tipo</th><td><?= e($tipoTxt) ?></td></tr>
                  <tr><th>Marca</th><td><?= e($activo['marca'] ?: '—') ?></td></tr>
                  <tr><th>Proveedor</th><td><?= e($activo['proveedor'] ?: '—') ?></td></tr>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm">
                  <tr><th style="width:200px">Modelo</th><td><?= e($activo['modelo'] ?: '—') ?></td></tr>
                  <tr><th>Serial</th><td><?= e($activo['serial'] ?: '—') ?></td></tr>
                  <tr><th>Placa</th><td><?= e($activo['placa'] ?: '—') ?></td></tr>
                  <tr><th>Ubicación</th><td><?= e($ubic) ?></td></tr>
                  <tr><th>Estado</th><td><span class="badge badge-<?= badge_estado_activo($activo['estado']) ?>"><?= e($activo['estado']) ?></span></td></tr>
                  <tr><th>Observaciones</th><td><?= e($activo['observaciones'] ?: '—') ?></td></tr>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- RED -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-network-wired"></i> Red / Identidad tecnológica</h3>
          </div>
          <div class="card-body">
            <?php if ($usaRed !== 1): ?>
              <div class="text-muted">Solo aplica si el tipo de activo tiene regla <b>usa_red=1</b>.</div>
            <?php else: ?>
              <div class="p-3 border rounded bg-light"><?= $redHtml ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- COMPONENTES (FIX: ahora sí desde activos_componentes) -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-puzzle-piece"></i> Componentes</h3>
          </div>
          <div class="card-body p-0">
            <?php if (!$componentes): ?>
              <div class="p-3 text-muted">Este activo no tiene componentes registrados.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Componente</th>
                      <th>Tipo</th>
                      <th>Marca/Modelo</th>
                      <th>Serial</th>
                      <th class="text-center">Cant</th>
                      <th>Estado</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($componentes as $c): ?>
                      <tr>
                        <td><b><?= e($c['nombre'] ?: '—') ?></b></td>
                        <td><?= e($c['tipo'] ?: '—') ?></td>
                        <td>
                          <?= e($c['marca'] ?: '—') ?>
                          <?php if (!empty($c['modelo'])): ?>
                            <span class="text-muted">· <?= e($c['modelo']) ?></span>
                          <?php endif; ?>
                        </td>
                        <td><?= e($c['serial'] ?: '—') ?></td>
                        <td class="text-center"><?= (int)($c['cantidad'] ?? 1) ?></td>
                        <td><span class="badge badge-<?= badge_estado_activo($c['estado'] ?? '') ?>"><?= e($c['estado'] ?: '—') ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- SOFTWARE -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-desktop"></i> Software / Licencias</h3>
          </div>
          <div class="card-body p-0">
            <?php if ($usaSoftware !== 1): ?>
              <div class="p-3 text-muted">Solo aplica si el tipo de activo tiene regla <b>usa_software=1</b>.</div>
            <?php elseif (!$swRows): ?>
              <div class="p-3 text-muted">No hay software registrado para este activo.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover text-nowrap">
                  <thead>
                    <tr>
                      <th>Software</th>
                      <th>Licencia</th>
                      <th>Vencimiento</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($swRows as $r): ?>
                      <?php
                        $vence = !empty($r['fecha_vencimiento']) ? substr((string)$r['fecha_vencimiento'],0,10) : '—';
                        $swName = e($r['nombre']);
                        if (!empty($r['version'])) $swName .= " <span class='text-muted'>(v".e($r['version']).")</span>";
                      ?>
                      <tr>
                        <td><?= $swName ?></td>
                        <td><span class="badge badge-light"><?= e($r['licencia_tipo'] ?: '—') ?></span></td>
                        <td><?= e($vence) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ADJUNTOS -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-paperclip"></i> Adjuntos del activo</h3>
          </div>
          <div class="card-body p-0">
            <?php if (!$adjRows): ?>
              <div class="p-3 text-muted">No hay adjuntos para este activo.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover text-nowrap">
                  <thead>
                    <tr>
                      <th>Archivo</th>
                      <th>Tipo</th>
                      <th>Tamaño</th>
                      <th>Fecha</th>
                      <th style="width:170px" class="text-right">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($adjRows as $a): ?>
                      <?php
                        $mime = (string)$a['mime'];
                        $isPreview = (stripos($mime, 'pdf') !== false) || (stripos($mime, 'image/') === 0);
                        $fecha = !empty($a['creado_en']) ? substr((string)$a['creado_en'], 0, 19) : '—';
                      ?>
                      <tr>
                        <td><b><?= e($a['nombre_original']) ?></b></td>
                        <td><span class="badge badge-light"><?= e($mime) ?></span></td>
                        <td><?= e(fmt_bytes($a['tamano'])) ?></td>
                        <td><?= e($fecha) ?></td>
                        <td class="text-right">
                          <?php if ($isPreview): ?>
                            <a class="btn btn-sm btn-outline-info"
                               target="_blank"
                               href="<?= e(base_url()) ?>/index.php?route=ajax_act_adj_preview&id=<?= (int)$a['id'] ?>">
                              <i class="fas fa-eye"></i>
                            </a>
                          <?php endif; ?>

                          <a class="btn btn-sm btn-outline-primary"
                             href="<?= e(base_url()) ?>/index.php?route=ajax_act_adj_download&id=<?= (int)$a['id'] ?>">
                            <i class="fas fa-download"></i>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- MANTENIMIENTOS (DETALLE COMPLETO) -->
        <div class="card card-outline card-warning">
          <div class="card-header">
            <h3 class="card-title"><i class="fas fa-tools"></i> Mantenimientos (detalle completo)</h3>
          </div>
          <div class="card-body">
            <?php if (!$mantRows): ?>
              <div class="text-muted">Este activo aún no tiene mantenimientos registrados.</div>
            <?php else: ?>

              <div class="accordion" id="accMant">
                <?php foreach ($mantRows as $i => $mm): ?>
                  <?php
                    $mantId = (int)$mm['id'];
                    $costo = ((float)$mm['costo_mano_obra'] + (float)$mm['costo_repuestos']);
                    $headId = 'mantHead'.$mantId;
                    $colId  = 'mantCol'.$mantId;
                  ?>
                  <div class="card mb-2">
                    <div class="card-header" id="<?= e($headId) ?>">
                      <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                          <button class="btn btn-link p-0" type="button" data-toggle="collapse" data-target="#<?= e($colId) ?>" aria-expanded="<?= ($i===0?'true':'false') ?>" aria-controls="<?= e($colId) ?>">
                            <b>#<?= $mantId ?></b>
                            <span class="badge badge-<?= badge_tipo_mant($mm['tipo']) ?> ml-2"><?= e($mm['tipo']) ?></span>
                            <span class="badge badge-<?= badge_estado_mant($mm['estado']) ?> ml-1"><?= e($mm['estado']) ?></span>
                            <span class="badge badge-<?= badge_prio($mm['prioridad']) ?> ml-1"><?= e($mm['prioridad']) ?></span>
                          </button>
                          <div class="text-muted text-sm">
                            Programado: <b><?= e(fmt_fecha10($mm['fecha_programada'])) ?></b> ·
                            Inicio: <b><?= e(fmt_fecha16($mm['fecha_inicio'])) ?></b> ·
                            Fin: <b><?= e(fmt_fecha16($mm['fecha_fin'])) ?></b>
                          </div>
                        </div>
                        <div class="text-right">
                          <div style="font-size:16px;font-weight:900;">
                            $ <?= number_format($costo, 0, ',', '.') ?>
                          </div>
                          <div class="text-muted text-sm">MO + Repuestos</div>
                        </div>
                      </div>
                    </div>

                    <div id="<?= e($colId) ?>" class="collapse <?= ($i===0?'show':'') ?>" aria-labelledby="<?= e($headId) ?>" data-parent="#accMant">
                      <div class="card-body">

                        <div class="row">
                          <div class="col-md-6">
                            <div class="text-muted text-sm mb-1"><b>Falla reportada</b></div>
                            <div class="p-2 border rounded bg-light" style="min-height:90px;">
                              <?= nl2br(e($mm['falla_reportada'] ?: '—')) ?>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="text-muted text-sm mb-1"><b>Diagnóstico</b></div>
                            <div class="p-2 border rounded bg-light" style="min-height:90px;">
                              <?= nl2br(e($mm['diagnostico'] ?: '—')) ?>
                            </div>
                          </div>

                          <div class="col-md-12 mt-3">
                            <div class="text-muted text-sm mb-1"><b>Actividades</b></div>
                            <div class="p-2 border rounded bg-light" style="min-height:90px;">
                              <?= nl2br(e($mm['actividades'] ?: '—')) ?>
                            </div>
                          </div>

                          <div class="col-md-12 mt-3">
                            <div class="text-muted text-sm mb-1"><b>Recomendaciones</b></div>
                            <div class="p-2 border rounded bg-light" style="min-height:70px;">
                              <?= nl2br(e($mm['recomendaciones'] ?: '—')) ?>
                            </div>
                          </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                          <div class="text-muted text-sm">
                            Creado: <b><?= e(substr((string)$mm['creado_en'],0,19) ?: '—') ?></b> ·
                            Mano de obra: <b>$ <?= number_format((float)$mm['costo_mano_obra'],0,',','.') ?></b> ·
                            Repuestos: <b>$ <?= number_format((float)$mm['costo_repuestos'],0,',','.') ?></b>
                          </div>
                          <div class="mt-2 mt-md-0">
                            <a class="btn btn-sm btn-outline-info"
                               href="<?= e(base_url()) ?>/index.php?route=mantenimiento_detalle&id=<?= $mantId ?>">
                              <i class="fas fa-eye"></i> Ver mantenimiento
                            </a>
                          </div>
                        </div>

                      </div>
                    </div>

                  </div>
                <?php endforeach; ?>
              </div>

            <?php endif; ?>
          </div>
        </div>

      </div>

    </div>

  </div>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
