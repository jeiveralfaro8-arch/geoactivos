<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$msg = '';

/* =========================
   Filtros
========================= */
$q = trim($_GET['q'] ?? '');
$estado = trim($_GET['estado'] ?? '');
$tipo = trim($_GET['tipo'] ?? '');

/* =========================
   Eliminar (soft delete)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    $chk = db()->prepare("SELECT id FROM calibraciones WHERE id=:id AND tenant_id=:t LIMIT 1");
    $chk->execute([':id'=>$id, ':t'=>$tenantId]);
    if (!$chk->fetch()) {
      $msg = 'Calibración no encontrada.';
    } else {
      $up = db()->prepare("
        UPDATE calibraciones
        SET eliminado=1, eliminado_en=NOW(), eliminado_por=NULL
        WHERE id=:id AND tenant_id=:t
        LIMIT 1
      ");
      $up->execute([':id'=>$id, ':t'=>$tenantId]);
      $msg = 'Calibración eliminada.';
    }
  }
}

/* =========================
   Helpers
========================= */
function fmt_dt($d){
  if(!$d) return '—';
  $s = (string)$d;
  return (strlen($s) >= 16) ? substr($s,0,16) : $s;
}
function badge_estado($e){
  $e = strtoupper(trim((string)$e));
  if ($e === 'CERRADA') return "<span class='badge badge-success'><i class='fas fa-check-circle'></i> CERRADA</span>";
  if ($e === 'EN_PROCESO') return "<span class='badge badge-warning'><i class='fas fa-spinner'></i> EN PROCESO</span>";
  if ($e === 'ANULADA') return "<span class='badge badge-danger'><i class='fas fa-ban'></i> ANULADA</span>";
  return "<span class='badge badge-info'><i class='fas fa-calendar'></i> PROGRAMADA</span>";
}
function badge_tipo($t){
  $t = strtoupper(trim((string)$t));
  if ($t === 'EXTERNA') return "<span class='badge badge-primary'><i class='fas fa-truck'></i> EXTERNA</span>";
  return "<span class='badge badge-secondary'><i class='fas fa-building'></i> INTERNA</span>";
}
function badge_result($r){
  $r = strtoupper(trim((string)$r));
  if ($r === 'CONFORME') return "<span class='badge badge-success'><i class='fas fa-thumbs-up'></i> CONFORME</span>";
  if ($r === 'NO_CONFORME') return "<span class='badge badge-danger'><i class='fas fa-thumbs-down'></i> NO CONFORME</span>";
  return "<span class='badge badge-light'>—</span>";
}

/* =========================
   Conteos (KPI)
========================= */
$kpi = [
  'total' => 0,
  'programada' => 0,
  'en_proceso' => 0,
  'cerrada' => 0,
  'anulada' => 0
];

$kst = db()->prepare("
  SELECT
    COUNT(*) total,
    SUM(CASE WHEN estado='PROGRAMADA' THEN 1 ELSE 0 END) programada,
    SUM(CASE WHEN estado='EN_PROCESO' THEN 1 ELSE 0 END) en_proceso,
    SUM(CASE WHEN estado='CERRADA' THEN 1 ELSE 0 END) cerrada,
    SUM(CASE WHEN estado='ANULADA' THEN 1 ELSE 0 END) anulada
  FROM calibraciones
  WHERE tenant_id=:t AND COALESCE(eliminado,0)=0
");
$kst->execute([':t'=>$tenantId]);
$k = $kst->fetch();
if ($k) {
  foreach ($kpi as $kk => $vv) {
    $kpi[$kk] = (int)($k[$kk] ?? 0);
  }
}

/* =========================
   Listado + filtros
========================= */
$where = " c.tenant_id=:t AND COALESCE(c.eliminado,0)=0 ";
$params = [':t'=>$tenantId];

if ($estado !== '' && in_array($estado, ['PROGRAMADA','EN_PROCESO','CERRADA','ANULADA'], true)) {
  $where .= " AND c.estado=:estado ";
  $params[':estado'] = $estado;
}
if ($tipo !== '' && in_array($tipo, ['INTERNA','EXTERNA'], true)) {
  $where .= " AND c.tipo=:tipo ";
  $params[':tipo'] = $tipo;
}
if ($q !== '') {
  $where .= " AND (
    a.codigo_interno LIKE :q
    OR a.nombre LIKE :q
    OR c.numero_certificado LIKE :q
    OR c.token_verificacion LIKE :q
  ) ";
  $params[':q'] = '%'.$q.'%';
}

$st = db()->prepare("
  SELECT
    c.id,
    c.activo_id,
    c.numero_certificado,
    c.token_verificacion,
    c.tipo,
    c.estado,
    c.fecha_programada,
    c.fecha_inicio,
    c.fecha_fin,
    c.resultado_global,
    a.codigo_interno,
    a.nombre AS activo_nombre,
    a.modelo AS activo_modelo,
    a.serial AS activo_serial,
    ar.nombre AS area_nombre,
    s.nombre AS sede_nombre
  FROM calibraciones c
  LEFT JOIN activos a
    ON a.id=c.activo_id AND a.tenant_id=c.tenant_id
  LEFT JOIN areas ar
    ON ar.id=a.area_id AND ar.tenant_id=a.tenant_id
  LEFT JOIN sedes s
    ON s.id=ar.sede_id AND s.tenant_id=ar.tenant_id
  WHERE $where
  ORDER BY c.id DESC
  LIMIT 300
");
$st->execute($params);
$rows = $st->fetchAll();

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<style>
/* Evita “saltos” y descuadres */
.kpi-wrap .small-box{border-radius:14px; overflow:hidden; box-shadow:0 1px 0 rgba(0,0,0,.03);}
.kpi-wrap .small-box .inner{padding:14px 16px;}
.kpi-wrap .small-box .icon{top:10px; right:12px; opacity:.18; font-size:44px;}

.badge{border-radius:999px; padding:6px 10px; font-weight:600;}
.small-muted{font-size:12px; color:#6c757d;}
.chip{display:inline-block; padding:6px 10px; border-radius:999px; background:#f4f6f9; border:1px solid #e5e7eb; font-size:12px;}
.searchbar{border-radius:999px;}

/* Tabla más estable */
.table td{vertical-align: middle;}
.table thead th{white-space:nowrap;}
</style>

<!-- ✅ OJO: No usamos <div class="content">, usamos section.content (AdminLTE) -->
<section class="content">
  <div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
      <div>
        <h3 class="mb-0"><i class="fas fa-ruler-combined"></i> Calibraciones</h3>
        <div class="small-muted">Gestión de certificados, programación y resultados por equipo.</div>
      </div>

      <div class="mt-2 mt-md-0">
        <a class="btn btn-primary mr-2" href="<?= e(base_url()) ?>/index.php?route=calibracion_form">
          <i class="fas fa-plus"></i> Nueva calibración
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url()) ?>/index.php?route=patrones">
          <i class="fas fa-balance-scale"></i> Patrones
        </a>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-info"><?= e($msg) ?></div>
    <?php endif; ?>

    <!-- KPI (RESPONSIVO Y PRO) -->
    <div class="row kpi-wrap">
      <div class="col-lg-3 col-md-6 col-12">
        <div class="small-box bg-light">
          <div class="inner">
            <h3><?= (int)$kpi['total'] ?></h3>
            <p class="mb-0">Total</p>
          </div>
          <div class="icon"><i class="fas fa-list"></i></div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 col-12">
        <div class="small-box bg-info">
          <div class="inner">
            <h3><?= (int)$kpi['programada'] ?></h3>
            <p class="mb-0">Programadas</p>
          </div>
          <div class="icon"><i class="fas fa-calendar"></i></div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 col-12">
        <div class="small-box bg-warning">
          <div class="inner">
            <h3><?= (int)$kpi['en_proceso'] ?></h3>
            <p class="mb-0">En proceso</p>
          </div>
          <div class="icon"><i class="fas fa-spinner"></i></div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 col-12">
        <div class="small-box bg-success">
          <div class="inner">
            <h3><?= (int)$kpi['cerrada'] ?></h3>
            <p class="mb-0">Cerradas</p>
          </div>
          <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
      </div>
    </div>

    <!-- Filtros PRO -->
    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter"></i> Filtros</h3>
      </div>
      <div class="card-body">
        <form method="get" action="<?= e(base_url()) ?>/index.php" class="row">
          <input type="hidden" name="route" value="calibraciones">

          <div class="col-lg-5 col-md-12">
            <label class="small-muted">Buscar (equipo / certificado / token)</label>
            <input class="form-control searchbar" name="q" value="<?= e($q) ?>" placeholder="Ej: PC-0001, CERT-..., TOK-...">
          </div>

          <div class="col-lg-3 col-md-6">
            <label class="small-muted">Estado</label>
            <select class="form-control" name="estado">
              <option value="">— Todos —</option>
              <?php foreach (['PROGRAMADA','EN_PROCESO','CERRADA','ANULADA'] as $op): ?>
                <option value="<?= e($op) ?>" <?= ($estado===$op?'selected':'') ?>><?= e($op) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-lg-2 col-md-6">
            <label class="small-muted">Tipo</label>
            <select class="form-control" name="tipo">
              <option value="">— Todos —</option>
              <?php foreach (['INTERNA','EXTERNA'] as $op): ?>
                <option value="<?= e($op) ?>" <?= ($tipo===$op?'selected':'') ?>><?= e($op) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-lg-2 col-md-12 d-flex align-items-end">
            <button class="btn btn-primary btn-block"><i class="fas fa-search"></i> Aplicar</button>
          </div>

          <div class="col-12 mt-2">
            <a class="chip" href="<?= e(base_url()) ?>/index.php?route=calibraciones">
              <i class="fas fa-undo"></i> Limpiar
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-table"></i> Listado</h3>
      </div>
      <div class="card-body">
        <?php if (!$rows): ?>
          <div class="text-muted">No hay calibraciones para los filtros seleccionados.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover text-nowrap">
              <thead>
                <tr>
                  <th style="width:75px">#</th>
                  <th>Equipo</th>
                  <th style="width:170px">Certificado</th>
                  <th style="width:160px">Estado</th>
                  <th style="width:160px">Programada</th>
                  <th style="width:190px" class="text-right">Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <?php
                  $ubic = '';
                  $sede = trim((string)($r['sede_nombre'] ?? ''));
                  $area = trim((string)($r['area_nombre'] ?? ''));
                  if($sede && $area) $ubic = $sede.' - '.$area;
                  else if($sede) $ubic = $sede;
                  else if($area) $ubic = $area;
                  else $ubic = '—';

                  $cod = trim((string)($r['codigo_interno'] ?? ''));
                  if($cod === '') $cod = 'Activo #'.(int)$r['activo_id'];
                  $nom = trim((string)($r['activo_nombre'] ?? ''));

                  $modelo = trim((string)($r['activo_modelo'] ?? ''));
                  $serial = trim((string)($r['activo_serial'] ?? ''));
                  $ms = [];
                  if($modelo !== '') $ms[] = 'Modelo: '.$modelo;
                  if($serial !== '') $ms[] = 'S/N '.$serial;
                  $msTxt = $ms ? implode(' · ', $ms) : '';
                ?>
                <tr>
                  <td><span class="badge badge-light">#<?= (int)$r['id'] ?></span></td>

                  <td>
                    <div class="d-flex align-items-center">
                      <div style="width:38px;height:38px;border-radius:10px;background:#f4f6f9;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;margin-right:10px;">
                        <i class="fas fa-microchip text-muted"></i>
                      </div>
                      <div>
                        <div><b><?= e($cod) ?></b></div>
                        <?php if($nom !== ''): ?><div class="small-muted"><?= e($nom) ?></div><?php endif; ?>
                        <div class="small-muted"><?= e($ubic) ?></div>
                        <?php if($msTxt !== ''): ?><div class="small-muted"><?= e($msTxt) ?></div><?php endif; ?>
                      </div>
                    </div>
                  </td>

                  <td>
                    <div><b><?= e($r['numero_certificado'] ?: '—') ?></b></div>
                    <?php if(!empty($r['token_verificacion'])): ?>
                      <div class="small-muted">Token: <?= e($r['token_verificacion']) ?></div>
                    <?php endif; ?>
                  </td>

                  <td>
                    <?= badge_tipo($r['tipo'] ?? 'INTERNA') ?>
                    <div class="mt-1"><?= badge_estado($r['estado'] ?? 'PROGRAMADA') ?></div>
                    <?php if(!empty($r['resultado_global'])): ?>
                      <div class="mt-1"><?= badge_result($r['resultado_global']) ?></div>
                    <?php endif; ?>
                  </td>

                  <td><?= e(fmt_dt($r['fecha_programada'] ?? null)) ?></td>

                  <td class="text-right">
                    <a class="btn btn-sm btn-outline-primary"
                       href="<?= e(base_url()) ?>/index.php?route=calibracion_detalle&id=<?= (int)$r['id'] ?>"
                       title="Ver detalle">
                      <i class="fas fa-eye"></i>
                    </a>

                    <a class="btn btn-sm btn-outline-info"
                       target="_blank"
                       href="<?= e(base_url()) ?>/index.php?route=calibracion_certificado&id=<?= (int)$r['id'] ?>"
                       title="Imprimir certificado">
                      <i class="fas fa-print"></i>
                    </a>

                    <a class="btn btn-sm btn-outline-secondary"
                       href="<?= e(base_url()) ?>/index.php?route=calibracion_form&id=<?= (int)$r['id'] ?>"
                       title="Editar">
                      <i class="fas fa-edit"></i>
                    </a>

                    <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar esta calibración?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit" title="Eliminar">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
