<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

$tenantId = Auth::tenantId();

function column_exists($table, $column) {
  $st = db()->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1");
  $st->execute([':t' => $table, ':c' => $column]);
  return (bool)$st->fetchColumn();
}

$hasSoftDelete = column_exists('activos', 'eliminado');

$sql = "
  SELECT 
    a.id,
    a.codigo_interno,
    a.nombre,
    a.hostname,
    a.usa_dhcp,
    a.ip_fija,
    a.mac,
    a.modelo,
    a.serial,
    a.estado,
    c.nombre AS categoria,
    ta.nombre AS tipo_activo,
    ta.codigo AS tipo_codigo,
    m.nombre AS marca,
    p.nombre AS proveedor,
    ar.nombre AS area,
    s.nombre AS sede
  FROM activos a
  INNER JOIN categorias_activo c 
    ON c.id = a.categoria_id AND c.tenant_id = a.tenant_id
  LEFT JOIN tipos_activo ta
    ON ta.id = a.tipo_activo_id AND ta.tenant_id = a.tenant_id
  LEFT JOIN marcas m
    ON m.id = a.marca_id AND m.tenant_id = a.tenant_id
  LEFT JOIN proveedores p
    ON p.id = a.proveedor_id AND p.tenant_id = a.tenant_id
  LEFT JOIN areas ar
    ON ar.id = a.area_id AND ar.tenant_id = a.tenant_id
  LEFT JOIN sedes s
    ON s.id = ar.sede_id AND s.tenant_id = a.tenant_id
  WHERE a.tenant_id = :t
";

if ($hasSoftDelete) {
  $sql .= " AND a.eliminado = 0 ";
}

$sql .= " ORDER BY a.id DESC LIMIT 300 ";

$st = db()->prepare($sql);
$st->execute([':t' => $tenantId]);
$rows = $st->fetchAll();

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-laptop-medical"></i> Activos</h3>
    <div class="card-tools">
      <a class="btn btn-primary btn-sm" href="<?= e(base_url()) ?>/index.php?route=activos_form">
        <i class="fas fa-plus"></i> Nuevo
      </a>
    </div>
  </div>

  <div class="card-body table-responsive p-0">
    <table class="table table-hover text-nowrap">
      <thead>
        <tr>
          <th>Código</th>
          <th>Nombre</th>
          <th>Categoría</th>
          <th>Tipo</th>
          <th>Estado</th>
          <th style="width:180px" class="text-right">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="6" class="text-center text-muted p-4">No hay activos registrados</td>
          </tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
          <?php
            $badge = 'secondary';
            if ($r['estado'] === 'ACTIVO') $badge = 'success';
            elseif ($r['estado'] === 'EN_MANTENIMIENTO') $badge = 'warning';
            elseif ($r['estado'] === 'BAJA') $badge = 'danger';

            $tipoNombre = $r['tipo_activo'] ? (string)$r['tipo_activo'] : '';
            $tipoCod    = $r['tipo_codigo'] ? (string)$r['tipo_codigo'] : '';

            if ($tipoNombre === '') {
              $tipo = '—';
            } else {
              $tipo = ($tipoCod !== '' ? ($tipoCod . ' · ') : '') . $tipoNombre;
            }
          ?>
          <tr>
            <td><?= e($r['codigo_interno']) ?></td>
            <td><?= e($r['nombre']) ?></td>
            <td><span class="badge badge-info"><?= e($r['categoria']) ?></span></td>
            <td><?= e($tipo) ?></td>
            <td><span class="badge badge-<?= $badge ?>"><?= e($r['estado']) ?></span></td>

            <td class="text-right">
              <a class="btn btn-sm btn-outline-info"
                 href="<?= e(base_url()) ?>/index.php?route=activo_detalle&id=<?= (int)$r['id'] ?>"
                 title="Detalle">
                <i class="fas fa-eye"></i>
              </a>

              <a class="btn btn-sm btn-outline-primary"
                 href="<?= e(base_url()) ?>/index.php?route=activos_form&id=<?= (int)$r['id'] ?>"
                 title="Editar">
                <i class="fas fa-edit"></i>
              </a>

              <a class="btn btn-sm btn-outline-danger"
                 href="<?= e(base_url()) ?>/index.php?route=activos_delete&id=<?= (int)$r['id'] ?>"
                 title="Eliminar"
                 onclick="return confirm('¿Eliminar este activo?\\n\\nSe eliminará en modo seguro (soft delete) e incluirá mantenimientos y adjuntos asociados.');">
                <i class="fas fa-trash"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
