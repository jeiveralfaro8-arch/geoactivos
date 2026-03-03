<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

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

/* ---------------- Helpers ---------------- */
function badge_estado($estado) {
  $b = 'secondary';
  if ($estado === 'ACTIVO') $b = 'success';
  elseif ($estado === 'EN_MANTENIMIENTO') $b = 'warning';
  elseif ($estado === 'BAJA') $b = 'danger';
  return $b;
}
function badge_mant_estado($estado){
  $estado = (string)$estado;
  if ($estado === 'PROGRAMADO') return 'info';
  if ($estado === 'EN_PROCESO') return 'warning';
  if ($estado === 'CERRADO') return 'success';
  if ($estado === 'ANULADO') return 'danger';
  return 'secondary';
}
function fmt_bytes($bytes) {
  $bytes = (int)$bytes;
  if ($bytes <= 0) return '0 B';
  $units = array('B','KB','MB','GB','TB');
  $i = 0;
  while ($bytes >= 1024 && $i < count($units)-1) {
    $bytes /= 1024;
    $i++;
  }
  return round($bytes, 1) . ' ' . $units[$i];
}
function fmt_fecha_simple($v){
  if (!$v) return '—';
  $s = (string)$v;
  return substr($s, 0, 10);
}
function money0($v){
  $n = (float)$v;
  return '$ ' . number_format($n, 0, ',', '.');
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

/* ---------------- Componentes (PRO: tabla propia) ---------------- */
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

/* ---------------- Ubicación ---------------- */
$ubic = '';
if (!empty($activo['sede'])) $ubic .= $activo['sede'];
if (!empty($activo['area'])) $ubic .= ($ubic ? ' - ' : '') . $activo['area'];
if ($ubic === '') $ubic = '—';

/* ---------------- Red ---------------- */
$red = '';
$host = trim((string)($activo['hostname'] ?? ''));
$usaDhcp = (int)($activo['usa_dhcp'] ?? 1);
$ip = trim((string)($activo['ip_fija'] ?? ''));
$mac = trim((string)($activo['mac'] ?? ''));

if ($host !== '') $red .= e($host);
$red .= ($red ? ' · ' : '') . ($usaDhcp ? 'DHCP' : e($ip !== '' ? $ip : 'IP fija (sin dato)'));
if ($mac !== '') $red .= '<br><span class="text-muted text-sm">MAC: ' . e($mac) . '</span>';
if ($red === '') $red = '—';

/* ---------------- Tipo con prefijo ---------------- */
$tipoNombre = $activo['tipo'] ? (string)$activo['tipo'] : '';
$tipoCod    = $activo['tipo_codigo'] ? (string)$activo['tipo_codigo'] : '';
if ($tipoNombre === '') {
  $tipoTxt = '—';
} else {
  $tipoTxt = ($tipoCod !== '' ? ($tipoCod . ' · ') : '') . $tipoNombre;
}

/* ---------------- Software (listado en pestaña) ---------------- */
$swRows = array();
$swCount = 0;

if ($usaSoftware === 1) {
  $cnt = db()->prepare("
    SELECT COUNT(*) AS c
    FROM activos_software
    WHERE tenant_id=:t AND activo_id=:a
  ");
  $cnt->execute([':t'=>$tenantId, ':a'=>$id]);
  $rowCnt = $cnt->fetch();
  $swCount = $rowCnt ? (int)$rowCnt['c'] : 0;

  $sw = db()->prepare("
    SELECT id, nombre, version, licencia_tipo, fecha_vencimiento
    FROM activos_software
    WHERE tenant_id=:t AND activo_id=:a
    ORDER BY id DESC
    LIMIT 10
  ");
  $sw->execute([':t'=>$tenantId, ':a'=>$id]);
  $swRows = $sw->fetchAll();
}

/* ---------------- Adjuntos por activo ---------------- */
$adjRows = array();
$adjCount = 0;

$ac = db()->prepare("
  SELECT COUNT(*) AS c
  FROM activos_adjuntos
  WHERE tenant_id=:t AND activo_id=:a
");
$ac->execute([':t'=>$tenantId, ':a'=>$id]);
$acRow = $ac->fetch();
$adjCount = $acRow ? (int)$acRow['c'] : 0;

$al = db()->prepare("
  SELECT id, nombre_original, mime, tamano, creado_en
  FROM activos_adjuntos
  WHERE tenant_id=:t AND activo_id=:a
  ORDER BY id DESC
  LIMIT 30
");
$al->execute([':t'=>$tenantId, ':a'=>$id]);
$adjRows = $al->fetchAll();

/* =========================
   HOJA DE VIDA: KPIs del activo
========================= */
$k_total_mant = 0;
$k_pend_mant  = 0;
$k_costo_total = 0;
$k_ultimo_mant = null;
$k_prox_prog = null;

try {
  $q = db()->prepare("SELECT COUNT(*) c FROM mantenimientos WHERE tenant_id=:t AND activo_id=:a");
  $q->execute([':t'=>$tenantId, ':a'=>$id]);
  $k_total_mant = (int)($q->fetch()['c'] ?? 0);

  $q = db()->prepare("SELECT COUNT(*) c FROM mantenimientos WHERE tenant_id=:t AND activo_id=:a AND estado IN ('PROGRAMADO','EN_PROCESO')");
  $q->execute([':t'=>$tenantId, ':a'=>$id]);
  $k_pend_mant = (int)($q->fetch()['c'] ?? 0);

  $q = db()->prepare("
    SELECT
      SUM(IFNULL(costo_mano_obra,0) + IFNULL(costo_repuestos,0)) AS s
    FROM mantenimientos
    WHERE tenant_id=:t AND activo_id=:a
  ");
  $q->execute([':t'=>$tenantId, ':a'=>$id]);
  $k_costo_total = (float)($q->fetch()['s'] ?? 0);

  $q = db()->prepare("
    SELECT id, tipo, estado, fecha_fin, fecha_inicio, fecha_programada
    FROM mantenimientos
    WHERE tenant_id=:t AND activo_id=:a
    ORDER BY id DESC
    LIMIT 1
  ");
  $q->execute([':t'=>$tenantId, ':a'=>$id]);
  $k_ultimo_mant = $q->fetch();

  $q = db()->prepare("
    SELECT id, tipo, estado, fecha_programada
    FROM mantenimientos
    WHERE tenant_id=:t AND activo_id=:a AND estado='PROGRAMADO'
    ORDER BY fecha_programada ASC, id ASC
    LIMIT 1
  ");
  $q->execute([':t'=>$tenantId, ':a'=>$id]);
  $k_prox_prog = $q->fetch();

} catch (Exception $e) {}

/* =========================
   Hoja de vida: listado mantenimientos
========================= */
$mantRows = [];
try {
  $q = db()->prepare("
    SELECT
      m.id, m.tipo, m.estado, m.fecha_programada, m.fecha_inicio, m.fecha_fin, m.prioridad,
      m.falla_reportada,
      IFNULL(m.costo_mano_obra,0) AS costo_mano_obra,
      IFNULL(m.costo_repuestos,0) AS costo_repuestos,
      (IFNULL(m.costo_mano_obra,0)+IFNULL(m.costo_repuestos,0)) AS costo_total
    FROM mantenimientos m
    WHERE m.tenant_id=:t AND m.activo_id=:a
    ORDER BY m.id DESC
    LIMIT 50
  ");
  $q->execute([':t'=>$tenantId, ':a'=>$id]);
  $mantRows = $q->fetchAll();
} catch (Exception $e) { $mantRows = []; }

/* =========================
   FOTO DEL ACTIVO (principal)
========================= */
$foto = '';
/* FIX: columna real es foto_path */
if (isset($activo['foto_path'])) {
  $foto = trim((string)$activo['foto_path']);
}
$fotoUrl = '';
if ($foto !== '') {
  $fotoUrl = e(base_url()) . '/' . ltrim($foto, '/');
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<!-- HOJA DE VIDA (Header Pro) -->
<div class="card card-outline card-primary">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap">

      <div class="d-flex align-items-start">
        <!-- FOTO -->
        <div class="mr-3" style="width:110px;">
          <div style="width:110px;height:110px;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;background:#f8fafc;display:flex;align-items:center;justify-content:center;">
            <?php if ($fotoUrl): ?>
              <img id="imgActivoFoto" src="<?= $fotoUrl ?>" alt="Foto del activo" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <div id="imgActivoFotoPh" class="text-muted text-center" style="padding:6px;">
                <i class="fas fa-camera" style="font-size:28px;"></i><br>
                <span style="font-size:12px;">Sin foto</span>
              </div>
              <img id="imgActivoFoto" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;">
            <?php endif; ?>
          </div>

          <div class="mt-2">
            <button class="btn btn-xs btn-outline-primary btn-block" type="button" onclick="openFotoAct()">
              <i class="fas fa-upload"></i> <?= $fotoUrl ? 'Cambiar' : 'Subir' ?>
            </button>
          </div>

<?php if ($fotoUrl): ?>
  <div class="mt-1">
    <button class="btn btn-xs btn-outline-danger btn-block" type="button" onclick="delFotoAct()">
      <i class="fas fa-trash"></i> Eliminar
    </button>
  </div>
<?php endif; ?>


          <!-- input oculto -->
          <input type="file" id="inpFotoAct" accept="image/*" style="display:none;">
          <div id="fotoActMsg" class="text-muted text-xs mt-1" style="display:none;"></div>
        </div>

        <!-- DATOS -->
        <div>
          <div class="text-muted text-sm">Hoja de vida del activo</div>
          <h3 class="mb-1" style="font-weight:900;">
            <i class="fas fa-id-card text-primary mr-1"></i>
            <?= e($activo['codigo_interno'] ?: ('Activo #'.(int)$activo['id'])) ?>
            <span class="badge badge-<?= badge_estado($activo['estado']) ?> ml-2" style="vertical-align:middle;">
              <?= e($activo['estado']) ?>
            </span>
          </h3>
          <div class="text-muted">
            <?= e($activo['nombre'] ?: '—') ?>
            <span class="mx-1">·</span>
            <?= e($tipoTxt) ?>
          </div>

          <div class="mt-2 text-sm text-muted">
            <i class="fas fa-map-marker-alt"></i> <?= e($ubic) ?>
            <span class="mx-2">|</span>
            <i class="fas fa-hashtag"></i> Serial: <?= e($activo['serial'] ?: '—') ?>
            <span class="mx-2">|</span>
            <i class="fas fa-industry"></i> Marca: <?= e($activo['marca'] ?: '—') ?>
          </div>
        </div>
      </div>

      <div class="mt-2 mt-md-0">
        <a class="btn btn-sm btn-outline-dark"
           href="<?= e(base_url()) ?>/index.php?route=activo_hoja_vida&id=<?= (int)$activo['id'] ?>">
          <i class="fas fa-file-alt"></i> Hoja de vida completa
        </a>

        <a class="btn btn-sm btn-dark"
           target="_blank"
           href="<?= e(base_url()) ?>/index.php?route=activo_hoja_vida_print&id=<?= (int)$activo['id'] ?>">
          <i class="fas fa-print"></i> Imprimir / PDF
        </a>

        <a class="btn btn-sm btn-secondary" href="<?= e(base_url()) ?>/index.php?route=activos">
          <i class="fas fa-arrow-left"></i> Volver
        </a>

        <a class="btn btn-sm btn-primary"
           href="<?= e(base_url()) ?>/index.php?route=activos_form&id=<?= (int)$activo['id'] ?>">
          <i class="fas fa-edit"></i> Editar
        </a>

        <a class="btn btn-sm btn-outline-primary"
           target="_blank"
           href="<?= e(base_url()) ?>/index.php?route=activo_qr_etiqueta&id=<?= (int)$activo['id'] ?>&w=80&h=50">
          <i class="fas fa-qrcode"></i> Etiqueta QR
        </a>

        <a class="btn btn-sm btn-outline-warning"
           href="<?= e(base_url()) ?>/index.php?route=mantenimiento_form&activo_id=<?= (int)$activo['id'] ?>">
          <i class="fas fa-tools"></i> Nuevo mantenimiento
        </a>
      </div>
    </div>

    <!-- KPIs del activo -->
    <div class="row mt-4">
      <div class="col-md-3 col-sm-6 col-12">
        <div class="info-box">
          <span class="info-box-icon bg-info"><i class="fas fa-clipboard-list"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Mantenimientos</span>
            <span class="info-box-number"><?= (int)$k_total_mant ?></span>
            <span class="text-muted text-sm">Historial total</span>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-sm-6 col-12">
        <div class="info-box">
          <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Pendientes</span>
            <span class="info-box-number"><?= (int)$k_pend_mant ?></span>
            <span class="text-muted text-sm">Programado / En proceso</span>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-sm-6 col-12">
        <div class="info-box">
          <span class="info-box-icon bg-success"><i class="fas fa-dollar-sign"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Costo acumulado</span>
            <span class="info-box-number"><?= e(money0($k_costo_total)) ?></span>
            <span class="text-muted text-sm">Mano de obra + repuestos</span>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-sm-6 col-12">
        <div class="info-box">
          <span class="info-box-icon bg-primary"><i class="fas fa-calendar-alt"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Próximo</span>
            <span class="info-box-number" style="font-size:16px;">
              <?= $k_prox_prog ? e(fmt_fecha_simple($k_prox_prog['fecha_programada'])) : '—' ?>
            </span>
            <span class="text-muted text-sm">
              <?= $k_prox_prog ? ('#'.(int)$k_prox_prog['id'].' · '.e($k_prox_prog['tipo'] ?: '—')) : 'Sin programación' ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Último mantenimiento -->
    <div class="mt-2 text-sm text-muted">
      <i class="fas fa-history"></i>
      Último mantenimiento:
      <?php if ($k_ultimo_mant): ?>
        <b>#<?= (int)$k_ultimo_mant['id'] ?></b>
        · <?= e($k_ultimo_mant['tipo'] ?: '—') ?>
        · <span class="badge badge-<?= badge_mant_estado($k_ultimo_mant['estado'] ?? '') ?>"><?= e($k_ultimo_mant['estado'] ?? '—') ?></span>
        · Fecha:
        <?php
          $f = $k_ultimo_mant['fecha_fin'] ?: ($k_ultimo_mant['fecha_inicio'] ?: ($k_ultimo_mant['fecha_programada'] ?: null));
          echo e(fmt_fecha_simple($f));
        ?>
        · <a href="<?= e(base_url()) ?>/index.php?route=mantenimiento_detalle&id=<?= (int)$k_ultimo_mant['id'] ?>">Ver</a>
      <?php else: ?>
        — (Aún no hay mantenimientos)
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- TABS -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-layer-group"></i> Secciones de la hoja de vida
    </h3>
  </div>

  <div class="card-body">
    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item">
        <a class="nav-link active" data-toggle="tab" href="#info" role="tab">
          <i class="fas fa-info-circle"></i> Información
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#mantenimientos" role="tab">
          <i class="fas fa-tools"></i> Mantenimientos
          <span class="badge badge-light ml-1"><?= (int)$k_total_mant ?></span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#componentes" role="tab">
          <i class="fas fa-puzzle-piece"></i> Componentes
          <span class="badge badge-light ml-1"><?= (int)count($componentes) ?></span>
        </a>
      </li>

      <?php if ($usaSoftware === 1): ?>
        <li class="nav-item">
          <a class="nav-link" data-toggle="tab" href="#software" role="tab">
            <i class="fas fa-desktop"></i> Software
            <span class="badge badge-light ml-1"><?= (int)$swCount ?></span>
          </a>
        </li>
      <?php else: ?>
        <li class="nav-item">
          <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true"
             title="No aplica para este tipo de activo">
            <i class="fas fa-desktop"></i> Software
          </a>
        </li>
      <?php endif; ?>

      <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#adjuntos" role="tab">
          <i class="fas fa-paperclip"></i> Adjuntos
          <span class="badge badge-light ml-1"><?= (int)$adjCount ?></span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true" title="Próximamente">
          <i class="fas fa-sliders-h"></i> Campos del tipo
        </a>
      </li>
    </ul>

    <div class="tab-content pt-3">

      <!-- INFO -->
      <div class="tab-pane fade show active" id="info">
        <div class="row">
          <div class="col-md-6">
            <table class="table table-sm">
              <tr><th>Código</th><td><?= e($activo['codigo_interno']) ?></td></tr>
              <tr><th>Nombre</th><td><?= e($activo['nombre']) ?></td></tr>
              <tr><th>Categoría</th><td><?= e($activo['categoria']) ?></td></tr>
              <tr><th>Tipo</th><td><?= e($tipoTxt) ?></td></tr>
              <tr><th>Marca</th><td><?= e($activo['marca'] ?: '—') ?></td></tr>
              <tr><th>Proveedor</th><td><?= e($activo['proveedor'] ?: '—') ?></td></tr>
              <tr><th>Ubicación</th><td><?= e($ubic) ?></td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm">
              <tr><th>Modelo</th><td><?= e($activo['modelo'] ?: '—') ?></td></tr>
              <tr><th>Serial</th><td><?= e($activo['serial'] ?: '—') ?></td></tr>
              <tr><th>Placa</th><td><?= e($activo['placa'] ?: '—') ?></td></tr>
              <tr>
                <th>Red</th>
                <td><?= ($usaRed === 1 ? $red : '—') ?></td>
              </tr>
              <tr>
                <th>Estado</th>
                <td><span class="badge badge-<?= badge_estado($activo['estado']) ?>"><?= e($activo['estado']) ?></span></td>
              </tr>
              <tr><th>Observaciones</th><td><?= e($activo['observaciones'] ?: '—') ?></td></tr>
            </table>
          </div>
        </div>
      </div>

      <!-- MANTENIMIENTOS -->
      <div class="tab-pane fade" id="mantenimientos">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
          <div>
            <h5 class="mb-0"><i class="fas fa-tools"></i> Historial de mantenimientos</h5>
            <div class="text-muted text-sm">
              Registros asociados a este activo. Límite: últimos 50.
            </div>
          </div>
          <div class="mt-2 mt-md-0">
            <a class="btn btn-sm btn-outline-warning"
               href="<?= e(base_url()) ?>/index.php?route=mantenimiento_form&activo_id=<?= (int)$activo['id'] ?>">
              <i class="fas fa-plus"></i> Crear mantenimiento
            </a>
            <a class="btn btn-sm btn-outline-secondary"
               href="<?= e(base_url()) ?>/index.php?route=mantenimientos">
              <i class="fas fa-external-link-alt"></i> Ir al módulo
            </a>
          </div>
        </div>

        <?php if (!$mantRows): ?>
          <div class="alert alert-light border text-muted mb-0">
            Aún no hay mantenimientos registrados para este activo.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover text-nowrap">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Tipo</th>
                  <th>Estado</th>
                  <th>Fecha</th>
                  <th>Prioridad</th>
                  <th>Falla</th>
                  <th class="text-right">Costo</th>
                  <th style="width:120px" class="text-right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($mantRows as $m): ?>
                  <?php
                    $fecha = $m['fecha_fin'] ?: ($m['fecha_inicio'] ?: ($m['fecha_programada'] ?: null));
                    $costo = (float)($m['costo_total'] ?? 0);
                  ?>
                  <tr>
                    <td>#<?= (int)$m['id'] ?></td>
                    <td><?= e($m['tipo'] ?: '—') ?></td>
                    <td>
                      <span class="badge badge-<?= badge_mant_estado($m['estado'] ?? '') ?>">
                        <?= e($m['estado'] ?: '—') ?>
                      </span>
                    </td>
                    <td><?= e(fmt_fecha_simple($fecha)) ?></td>
                    <td><?= e($m['prioridad'] ?: '—') ?></td>
                    <td style="max-width:380px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      <?= e($m['falla_reportada'] ?: '—') ?>
                    </td>
                    <td class="text-right"><?= e(money0($costo)) ?></td>
                    <td class="text-right">
                      <a class="btn btn-sm btn-outline-info"
                         href="<?= e(base_url()) ?>/index.php?route=mantenimiento_detalle&id=<?= (int)$m['id'] ?>"
                         title="Ver">
                        <i class="fas fa-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- COMPONENTES -->
      <div class="tab-pane fade" id="componentes">
        <div class="mb-2 d-flex justify-content-between align-items-center flex-wrap">
          <div class="text-muted text-sm">
            <i class="fas fa-info-circle"></i>
            Componentes del activo (periféricos, piezas, partes). No se contabilizan como activos.
          </div>

          <a class="btn btn-sm btn-success"
             href="<?= e(base_url()) ?>/index.php?route=componente_form&activo_id=<?= (int)$activo['id'] ?>">
            <i class="fas fa-plus"></i> Agregar componente
          </a>
        </div>

        <?php if (!$componentes): ?>
          <div class="text-muted">Este activo no tiene componentes registrados.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Componente</th>
                  <th>Tipo</th>
                  <th>Marca/Modelo</th>
                  <th>Serial</th>
                  <th>Cant</th>
                  <th>Estado</th>
                  <th style="width:160px" class="text-right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($componentes as $c): ?>
                  <tr>
                    <td><b><?= e($c['nombre']) ?></b></td>
                    <td><?= e($c['tipo'] ?: '—') ?></td>
                    <td>
                      <?= e($c['marca'] ?: '—') ?>
                      <?php if (!empty($c['modelo'])): ?>
                        <span class="text-muted">· <?= e($c['modelo']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?= e($c['serial'] ?: '—') ?></td>
                    <td><?= (int)($c['cantidad'] ?? 1) ?></td>
                    <td><span class="badge badge-<?= badge_estado($c['estado']) ?>"><?= e($c['estado']) ?></span></td>
                    <td class="text-right">
                      <a class="btn btn-sm btn-outline-primary"
                         href="<?= e(base_url()) ?>/index.php?route=componente_form&id=<?= (int)$c['id'] ?>"
                         title="Editar">
                        <i class="fas fa-edit"></i>
                      </a>

                      <a class="btn btn-sm btn-outline-danger"
                         onclick="return confirm('¿Eliminar este componente?');"
                         href="<?= e(base_url()) ?>/index.php?route=componente_delete&id=<?= (int)$c['id'] ?>"
                         title="Eliminar">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- SOFTWARE -->
      <?php if ($usaSoftware === 1): ?>
        <div class="tab-pane fade" id="software">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <h5 class="mb-0"><i class="fas fa-desktop"></i> Software instalado</h5>
              <div class="text-muted text-sm">
                Registro manual de software/licencias asociado a este equipo.
              </div>
            </div>

            <a class="btn btn-sm btn-primary"
               href="<?= e(base_url()) ?>/index.php?route=activo_software&id=<?= (int)$activo['id'] ?>">
              <i class="fas fa-cog"></i> Administrar
            </a>
          </div>

          <?php if (!$swRows): ?>
            <div class="text-muted">No hay software registrado para este activo.</div>
            <div class="text-muted mt-2">
              Aquí registrarás sistemas operativos, licencias, antivirus, Office, AnyDesk, etc. (solo aplica a tipos que tengan <b>usa_software=1</b>).
            </div>
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

            <div class="text-muted text-sm">Mostrando los últimos 10 registros.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- ADJUNTOS (AJAX) -->
      <div class="tab-pane fade" id="adjuntos">
        <!-- (tu bloque adjuntos queda igual, sin cambios) -->
        <div class="row">
          <div class="col-md-5">
            <div class="card card-outline card-primary">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-upload"></i> Subir adjunto</h3>
              </div>
              <div class="card-body">

                <div id="adjAlertAct" class="alert alert-danger text-sm" style="display:none;"></div>

                <form id="formAdjAct" enctype="multipart/form-data" onsubmit="return false;">
                  <input type="hidden" name="activo_id" value="<?= (int)$activo['id'] ?>">

                  <div class="form-group">
                    <label>Archivo</label>
                    <input type="file" name="archivo" id="adjArchivoAct" class="form-control" required>
                    <small class="text-muted">PDF, imágenes, Office, ZIP. Tamaño recomendado: &lt; 10MB.</small>
                  </div>

                  <button class="btn btn-primary" id="btnAdjSubirAct" type="button">
                    <i class="fas fa-save"></i> Subir
                  </button>
                </form>

              </div>
            </div>
          </div>

          <div class="col-md-7">
            <div class="card card-outline card-info">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-paperclip"></i> Adjuntos del activo</h3>
              </div>

              <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                  <thead>
                    <tr>
                      <th>Archivo</th>
                      <th>Tipo</th>
                      <th>Tamaño</th>
                      <th>Fecha</th>
                      <th style="width:180px" class="text-right">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$adjRows): ?>
                      <tr><td colspan="5" class="text-center text-muted p-4">No hay adjuntos para este activo.</td></tr>
                    <?php endif; ?>

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
                               href="<?= e(base_url()) ?>/index.php?route=ajax_act_adj_preview&id=<?= (int)$a['id'] ?>"
                               title="Vista previa">
                              <i class="fas fa-eye"></i>
                            </a>
                          <?php else: ?>
                            <a class="btn btn-sm btn-outline-secondary disabled" href="#" tabindex="-1" aria-disabled="true"
                               title="Vista previa no disponible para este tipo">
                              <i class="fas fa-eye"></i>
                            </a>
                          <?php endif; ?>

                          <a class="btn btn-sm btn-outline-primary"
                             href="<?= e(base_url()) ?>/index.php?route=ajax_act_adj_download&id=<?= (int)$a['id'] ?>"
                             title="Descargar">
                            <i class="fas fa-download"></i>
                          </a>

                          <button class="btn btn-sm btn-outline-danger"
                                  type="button"
                                  onclick="delAdjAct(<?= (int)$a['id'] ?>)"
                                  title="Eliminar">
                            <i class="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>

                  </tbody>
                </table>
              </div>

              <div class="card-footer text-muted text-sm">
                Mostrando los últimos 30 adjuntos.
              </div>
            </div>
          </div>
        </div>

        <div id="js-activo-adj" data-base-url="<?= e(base_url()) ?>"></div>
        <script src="<?= e(base_url()) ?>/assets/js/activo-adjuntos.js"></script>
      </div>

    </div>
  </div>
</div>

<div id="js-activo-foto" data-activo-id="<?= (int)$activo['id'] ?>" data-base-url="<?= e(base_url()) ?>"></div>
<script src="<?= e(base_url()) ?>/assets/js/activo-foto.js"></script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
