<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();

$st = db()->prepare("
  SELECT
    m.id,
    m.tipo,
    m.estado,
    m.prioridad,
    m.fecha_programada,
    m.fecha_inicio,
    m.fecha_fin,
    (m.costo_mano_obra + m.costo_repuestos) AS costo_total,
    a.id AS activo_id,
    a.codigo_interno,
    a.nombre AS activo_nombre
  FROM mantenimientos m
  INNER JOIN activos a
    ON a.id = m.activo_id AND a.tenant_id = m.tenant_id
  WHERE m.tenant_id = :t
  ORDER BY m.id DESC
  LIMIT 300
");
$st->execute([':t'=>$tenantId]);
$rows = $st->fetchAll();

function badge_estado_mant($estado){
  $b = 'secondary';
  if ($estado === 'PROGRAMADO') $b = 'info';
  elseif ($estado === 'EN_PROCESO') $b = 'warning';
  elseif ($estado === 'CERRADO') $b = 'success';
  elseif ($estado === 'ANULADO') $b = 'danger';
  return $b;
}

function badge_prio($p){
  $b = 'secondary';
  if ($p === 'BAJA') $b = 'secondary';
  elseif ($p === 'MEDIA') $b = 'info';
  elseif ($p === 'ALTA') $b = 'warning';
  elseif ($p === 'CRITICA') $b = 'danger';
  return $b;
}

function fecha_ref($r){
  // Prioridad: inicio -> programada -> creado_en (si algún día lo agregas a la consulta)
  if (!empty($r['fecha_inicio'])) return substr((string)$r['fecha_inicio'], 0, 16);
  if (!empty($r['fecha_programada'])) return (string)$r['fecha_programada'];
  return '—';
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-tools"></i> Mantenimientos</h3>
    <div class="card-tools">
      <a class="btn btn-primary btn-sm" href="<?= e(base_url()) ?>/index.php?route=mantenimiento_form">
        <i class="fas fa-plus"></i> Nuevo
      </a>
    </div>
  </div>

  <div class="card-body table-responsive p-0">
    <table class="table table-hover text-nowrap">
      <thead>
        <tr>
          <th style="width:90px">ID</th>
          <th>Activo</th>
          <th>Tipo</th>
          <th>Prioridad</th>
          <th>Fecha</th>
          <th>Estado</th>
          <th class="text-right">Costo</th>
          <th style="width:170px" class="text-right">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="8" class="text-center text-muted p-4">No hay mantenimientos registrados</td>
          </tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td>
              <div><b><?= e($r['codigo_interno']) ?></b></div>
              <div class="text-muted text-sm"><?= e($r['activo_nombre']) ?></div>
            </td>
            <td><?= e($r['tipo']) ?></td>
            <td><span class="badge badge-<?= badge_prio($r['prioridad']) ?>"><?= e($r['prioridad']) ?></span></td>
            <td><?= e(fecha_ref($r)) ?></td>
            <td><span class="badge badge-<?= badge_estado_mant($r['estado']) ?>"><?= e($r['estado']) ?></span></td>
            <td class="text-right">$ <?= number_format((float)$r['costo_total'], 0, ',', '.') ?></td>
            <td class="text-right">
              <a class="btn btn-sm btn-outline-info"
                 href="<?= e(base_url()) ?>/index.php?route=mantenimiento_detalle&id=<?= (int)$r['id'] ?>"
                 title="Detalle">
                <i class="fas fa-eye"></i>
              </a>
              <a class="btn btn-sm btn-outline-primary"
                 href="<?= e(base_url()) ?>/index.php?route=mantenimiento_form&id=<?= (int)$r['id'] ?>"
                 title="Editar">
                <i class="fas fa-edit"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
