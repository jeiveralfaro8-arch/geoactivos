<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();

/* =========================
   KPIs
========================= */
$st = db()->prepare("SELECT COUNT(*) c FROM activos WHERE tenant_id=:t");
$st->execute([':t'=>$tenantId]);
$totalActivos = (int)($st->fetch()['c'] ?? 0);

$st = db()->prepare("SELECT COUNT(*) c FROM mantenimientos WHERE tenant_id=:t AND estado IN ('PROGRAMADO','EN_PROCESO')");
$st->execute([':t'=>$tenantId]);
$totalMantPend = (int)($st->fetch()['c'] ?? 0);

$st = db()->prepare("SELECT COUNT(*) c FROM activos WHERE tenant_id=:t AND estado='EN_MANTENIMIENTO'");
$st->execute([':t'=>$tenantId]);
$totalActEnMant = (int)($st->fetch()['c'] ?? 0);

$st = db()->prepare("SELECT COUNT(*) c FROM activos WHERE tenant_id=:t AND estado='BAJA'");
$st->execute([':t'=>$tenantId]);
$totalActBaja = (int)($st->fetch()['c'] ?? 0);

/* =========================
   Últimos registros
========================= */
$ultActivos = [];
try {
  $q = db()->prepare("
    SELECT id, codigo_interno, nombre, estado
    FROM activos
    WHERE tenant_id=:t
    ORDER BY id DESC
    LIMIT 6
  ");
  $q->execute([':t'=>$tenantId]);
  $ultActivos = $q->fetchAll();
} catch (Exception $e) { $ultActivos = []; }

$ultMants = [];
try {
  $q2 = db()->prepare("
    SELECT m.id, m.tipo, m.estado, m.fecha_programada, m.fecha_inicio, a.codigo_interno, a.nombre AS activo_nombre
    FROM mantenimientos m
    INNER JOIN activos a ON a.id = m.activo_id AND a.tenant_id = m.tenant_id
    WHERE m.tenant_id=:t
    ORDER BY m.id DESC
    LIMIT 6
  ");
  $q2->execute([':t'=>$tenantId]);
  $ultMants = $q2->fetchAll();
} catch (Exception $e) { $ultMants = []; }

function badge_activo_estado($estado){
  $estado = (string)$estado;
  if ($estado === 'ACTIVO') return 'success';
  if ($estado === 'EN_MANTENIMIENTO') return 'warning';
  if ($estado === 'BAJA') return 'danger';
  return 'secondary';
}
function badge_mant_estado($estado){
  $estado = (string)$estado;
  if ($estado === 'PROGRAMADO') return 'info';
  if ($estado === 'EN_PROCESO') return 'warning';
  if ($estado === 'CERRADO') return 'success';
  if ($estado === 'ANULADO') return 'danger';
  return 'secondary';
}
function fmt_fecha_simple($v){
  if (!$v) return '—';
  $s = (string)$v;
  return substr($s, 0, 10);
}

$tenantNombre = $_SESSION['tenant']['nombre'] ?? ($_SESSION['user']['tenant_nombre'] ?? 'Cliente');
$usuarioNombre = $_SESSION['user']['nombre'] ?? 'Usuario';

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<style>
/* =========================
   DASHBOARD PRO UI (sin romper AdminLTE)
========================= */
.hero-pro{
  border:1px solid rgba(0,0,0,.08);
  border-radius:.85rem;
  background: linear-gradient(135deg, rgba(13,110,253,.12), rgba(23,162,184,.08));
  overflow:hidden;
  position:relative;
}
.hero-pro:before{
  content:"";
  position:absolute;
  right:-80px; top:-80px;
  width:220px; height:220px;
  background: radial-gradient(circle, rgba(13,110,253,.20), rgba(13,110,253,0));
  border-radius:50%;
}
.hero-pro .title{
  font-weight:900;
  letter-spacing:.2px;
}
.kpi-card{
  border:1px solid rgba(0,0,0,.06);
  border-radius:.85rem;
  overflow:hidden;
}
.kpi-card .kpi-label{font-size:.82rem;color:#6c757d}
.kpi-card .kpi-value{font-size:32px;font-weight:900;line-height:1}
.kpi-card .kpi-sub{font-size:.8rem;color:#6c757d;margin-top:.25rem}
.kpi-bar{
  height:4px;
  background:rgba(0,0,0,.06);
  border-radius:20px;
  overflow:hidden;
}
.kpi-bar > span{
  display:block;height:100%;
  background:rgba(13,110,253,.55);
  width:0%;
}
.list-pro .list-group-item{
  border-left:0;border-right:0;
}
.list-pro .code{
  font-weight:900;
  letter-spacing:.15px;
}
.soft-muted{color:#6c757d}
</style>

<!-- HERO PRO -->
<div class="row mb-3">
  <div class="col-12">
    <div class="card hero-pro">
      <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
          <div style="min-width:0">
            <div class="text-muted text-sm">
              <i class="fas fa-building mr-1"></i> <?= e($tenantNombre) ?>
              <span class="ml-2 badge badge-success">MULTI-TENANT</span>
              <span class="ml-1 badge badge-warning">PRO</span>
            </div>

            <h3 class="mb-0 title">
              <i class="fas fa-chart-line text-primary mr-1"></i> Dashboard
            </h3>

            <div class="text-muted text-sm mt-1">
              Bienvenido, <b><?= e($usuarioNombre) ?></b>. Resumen operativo del sistema.
            </div>
          </div>

          <div class="mt-2 mt-md-0">
            <a class="btn btn-sm btn-primary mr-1" href="<?= e(base_url()) ?>/index.php?route=activos_form">
              <i class="fas fa-plus"></i> Nuevo activo
            </a>
            <a class="btn btn-sm btn-outline-secondary mr-1" href="<?= e(base_url()) ?>/index.php?route=activos">
              <i class="fas fa-th-list"></i> Ver activos
            </a>
            <a class="btn btn-sm btn-outline-warning" href="<?= e(base_url()) ?>/index.php?route=mantenimientos">
              <i class="fas fa-tools"></i> Mantenimientos
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- KPIs PRO -->
<div class="row">

  <?php
    // Progresos visuales (no matemáticos exactos, solo UX)
    $p1 = $totalActivos > 0 ? 100 : 8;
    $p2 = $totalActivos > 0 ? min(100, (int)round(($totalMantPend / max(1,$totalActivos)) * 140)) : 10;
    $p3 = $totalActivos > 0 ? min(100, (int)round(($totalActEnMant / max(1,$totalActivos)) * 100)) : 6;
    $p4 = $totalActivos > 0 ? min(100, (int)round(($totalActBaja / max(1,$totalActivos)) * 100)) : 4;
  ?>

  <div class="col-md-3 col-sm-6 col-12 mb-3">
    <div class="card kpi-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Activos registrados</div>
            <div class="kpi-value"><?= (int)$totalActivos ?></div>
            <div class="kpi-sub"><i class="fas fa-layer-group text-success"></i> Inventario total</div>
          </div>
          <div class="text-primary" style="font-size:36px;opacity:.22;">
            <i class="fas fa-laptop"></i>
          </div>
        </div>

        <div class="mt-3 kpi-bar"><span style="width: <?= (int)$p1 ?>%"></span></div>

        <div class="mt-3">
          <a href="<?= e(base_url()) ?>/index.php?route=activos" class="text-sm">
            Ver listado <i class="fas fa-arrow-right ml-1"></i>
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3 col-sm-6 col-12 mb-3">
    <div class="card kpi-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Mantenimientos pendientes</div>
            <div class="kpi-value"><?= (int)$totalMantPend ?></div>
            <div class="kpi-sub"><i class="fas fa-clock text-warning"></i> Programado / En proceso</div>
          </div>
          <div class="text-warning" style="font-size:36px;opacity:.22;">
            <i class="fas fa-tools"></i>
          </div>
        </div>

        <div class="mt-3 kpi-bar"><span style="width: <?= (int)$p2 ?>%; background: rgba(255,193,7,.75);"></span></div>

        <div class="mt-3">
          <a href="<?= e(base_url()) ?>/index.php?route=mantenimientos" class="text-sm">
            Ir al módulo <i class="fas fa-arrow-right ml-1"></i>
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3 col-sm-6 col-12 mb-3">
    <div class="card kpi-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Activos en mantenimiento</div>
            <div class="kpi-value"><?= (int)$totalActEnMant ?></div>
            <div class="kpi-sub"><i class="fas fa-wrench text-warning"></i> Estado del activo</div>
          </div>
          <div class="text-secondary" style="font-size:36px;opacity:.18;">
            <i class="fas fa-screwdriver"></i>
          </div>
        </div>

        <div class="mt-3 kpi-bar"><span style="width: <?= (int)$p3 ?>%; background: rgba(108,117,125,.60);"></span></div>

        <div class="mt-3">
          <a href="<?= e(base_url()) ?>/index.php?route=activos" class="text-sm">
            Ver activos <i class="fas fa-arrow-right ml-1"></i>
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3 col-sm-6 col-12 mb-3">
    <div class="card kpi-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Activos de baja</div>
            <div class="kpi-value"><?= (int)$totalActBaja ?></div>
            <div class="kpi-sub"><i class="fas fa-exclamation-triangle text-danger"></i> Fuera de servicio</div>
          </div>
          <div class="text-danger" style="font-size:36px;opacity:.18;">
            <i class="fas fa-ban"></i>
          </div>
        </div>

        <div class="mt-3 kpi-bar"><span style="width: <?= (int)$p4 ?>%; background: rgba(220,53,69,.70);"></span></div>

        <div class="mt-3">
          <a href="<?= e(base_url()) ?>/index.php?route=activos" class="text-sm">
            Revisar <i class="fas fa-arrow-right ml-1"></i>
          </a>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Accesos + Listas -->
<div class="row">

  <div class="col-lg-4 mb-3">
    <div class="card card-outline card-primary h-100">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bolt"></i> Accesos rápidos</h3>
      </div>
      <div class="card-body">
        <a class="btn btn-block btn-outline-primary" href="<?= e(base_url()) ?>/index.php?route=activos_form">
          <i class="fas fa-plus mr-1"></i> Registrar nuevo activo
        </a>
        <a class="btn btn-block btn-outline-secondary" href="<?= e(base_url()) ?>/index.php?route=activos">
          <i class="fas fa-th-list mr-1"></i> Ver todos los activos
        </a>
        <a class="btn btn-block btn-outline-warning" href="<?= e(base_url()) ?>/index.php?route=mantenimientos">
          <i class="fas fa-tools mr-1"></i> Ir a mantenimientos
        </a>
        <a class="btn btn-block btn-outline-info" href="<?= e(base_url()) ?>/index.php?route=mantenimiento_form">
          <i class="fas fa-plus-circle mr-1"></i> Crear mantenimiento
        </a>

        <div class="mt-3 p-2" style="border:1px dashed rgba(0,0,0,.10);border-radius:.75rem;">
          <div class="text-muted text-sm">
            <i class="fas fa-shield-alt mr-1"></i> Operación PRO
          </div>
          <div class="soft-muted text-sm">
            Auditoría activa · Eliminación segura · Multi-cliente
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Últimos activos -->
  <div class="col-lg-4 mb-3">
    <div class="card h-100">
      <div class="card-header border-0">
        <h3 class="card-title"><i class="fas fa-cube"></i> Últimos activos</h3>
        <div class="card-tools">
          <a href="<?= e(base_url()) ?>/index.php?route=activos" class="btn btn-tool" title="Ver todos">
            <i class="fas fa-external-link-alt"></i>
          </a>
        </div>
      </div>

      <div class="card-body p-0 list-pro">
        <?php if (!$ultActivos): ?>
          <div class="p-3 text-muted">No hay registros recientes.</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($ultActivos as $a): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div style="min-width:0">
                  <div class="code">
                    <?= e($a['codigo_interno'] ?: ('ID #'.(int)$a['id'])) ?>
                  </div>
                  <div class="text-muted text-sm" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px;">
                    <?= e($a['nombre'] ?: '—') ?>
                  </div>
                </div>
                <div class="text-right">
                  <span class="badge badge-<?= badge_activo_estado($a['estado'] ?? '') ?>">
                    <?= e($a['estado'] ?? '—') ?>
                  </span>
                  <div>
                    <a class="text-sm" href="<?= e(base_url()) ?>/index.php?route=activo_detalle&id=<?= (int)$a['id'] ?>">
                      Ver <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Últimos mantenimientos -->
  <div class="col-lg-4 mb-3">
    <div class="card h-100">
      <div class="card-header border-0">
        <h3 class="card-title"><i class="fas fa-history"></i> Actividad reciente</h3>
        <div class="card-tools">
          <a href="<?= e(base_url()) ?>/index.php?route=mantenimientos" class="btn btn-tool" title="Ver módulo">
            <i class="fas fa-external-link-alt"></i>
          </a>
        </div>
      </div>

      <div class="card-body p-0 list-pro">
        <?php if (!$ultMants): ?>
          <div class="p-3 text-muted">Aún no hay mantenimientos registrados.</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($ultMants as $m): ?>
              <?php
                $estado = (string)($m['estado'] ?? '');
                $tipo   = (string)($m['tipo'] ?? '');
                $fecha  = $m['fecha_inicio'] ?: ($m['fecha_programada'] ?: null);
                $mantId = (int)($m['id'] ?? 0);
              ?>
              <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                  <div style="min-width:0">
                    <div class="code" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px;">
                      <?= e($m['codigo_interno'] ?? '—') ?> · <?= e($m['activo_nombre'] ?? 'Activo') ?>
                    </div>
                    <div class="text-muted text-sm">
                      <?= e($tipo ?: '—') ?> · <?= e(fmt_fecha_simple($fecha)) ?>
                    </div>
                  </div>
                  <div class="text-right">
                    <span class="badge badge-<?= badge_mant_estado($estado) ?>"><?= e($estado ?: '—') ?></span>
                    <div class="mt-1">
                      <a class="text-sm" href="<?= e(base_url()) ?>/index.php?route=mantenimiento_detalle&id=<?= $mantId ?>">
                        Ver <i class="fas fa-arrow-right ml-1"></i>
                      </a>
                    </div>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
