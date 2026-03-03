<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin(); // recomendado en vistas protegidas

$tenantId = Auth::tenantId();

function column_exists($table, $column) {
  $st = db()->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1");
  $st->execute([':t' => $table, ':c' => $column]);
  return (bool)$st->fetchColumn();
}

$hasSoft = column_exists('activos', 'eliminado');

$rows = [];
if ($hasSoft) {
  $st = db()->prepare("
    SELECT
      a.id,
      a.codigo_interno,
      a.nombre,
      a.estado,
      a.eliminado_en,
      c.nombre AS categoria,
      ta.nombre AS tipo_activo,
      ta.codigo AS tipo_codigo
    FROM activos a
    INNER JOIN categorias_activo c
      ON c.id = a.categoria_id AND c.tenant_id = a.tenant_id
    LEFT JOIN tipos_activo ta
      ON ta.id = a.tipo_activo_id AND ta.tenant_id = a.tenant_id
    WHERE a.tenant_id = :t
      AND a.eliminado = 1
    ORDER BY a.eliminado_en DESC, a.id DESC
    LIMIT 300
  ");
  $st->execute([':t' => $tenantId]);
  $rows = $st->fetchAll();
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-trash-restore"></i> Papelera · Activos eliminados</h3>
    <div class="card-tools">
      <a class="btn btn-secondary btn-sm" href="<?= e(base_url()) ?>/index.php?route=activos">
        <i class="fas fa-arrow-left"></i> Volver
      </a>
    </div>
  </div>

  <div class="card-body">
    <?php if (!$hasSoft): ?>
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        La papelera requiere Soft Delete (columna <b>activos.eliminado</b>). Ejecuta el script SQL de soft delete.
      </div>
    <?php else: ?>
      <div class="alert alert-info text-sm mb-3">
        <i class="fas fa-info-circle mr-1"></i>
        Aquí aparecen activos eliminados en modo seguro. Puedes <b>Restaurar</b> o <b>Eliminar definitivo</b>.
      </div>

      <div class="table-responsive p-0">
        <table class="table table-hover text-nowrap">
          <thead>
            <tr>
              <th>Código</th>
              <th>Nombre</th>
              <th>Categoría</th>
              <th>Tipo</th>
              <th>Estado</th>
              <th>Eliminado el</th>
              <th style="width:220px" class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="7" class="text-center text-muted p-4">No hay activos eliminados</td>
              </tr>
            <?php endif; ?>

            <?php foreach ($rows as $r): ?>
              <?php
                $tipoNombre = $r['tipo_activo'] ? (string)$r['tipo_activo'] : '';
                $tipoCod    = $r['tipo_codigo'] ? (string)$r['tipo_codigo'] : '';
                $tipo = ($tipoNombre === '') ? '—' : (($tipoCod !== '' ? ($tipoCod . ' · ') : '') . $tipoNombre);

                $badge = 'secondary';
                if ($r['estado'] === 'ACTIVO') $badge = 'success';
                elseif ($r['estado'] === 'EN_MANTENIMIENTO') $badge = 'warning';
                elseif ($r['estado'] === 'BAJA') $badge = 'danger';

                $elimEn = !empty($r['eliminado_en']) ? (string)$r['eliminado_en'] : '—';
              ?>
              <tr>
                <td><?= e($r['codigo_interno']) ?></td>
                <td><?= e($r['nombre']) ?></td>
                <td><span class="badge badge-info"><?= e($r['categoria']) ?></span></td>
                <td><?= e($tipo) ?></td>
                <td><span class="badge badge-<?= $badge ?>"><?= e($r['estado']) ?></span></td>
                <td><?= e($elimEn) ?></td>

                <td class="text-right">
                  <a class="btn btn-sm btn-success"
                     href="<?= e(base_url()) ?>/index.php?route=activos_restore&id=<?= (int)$r['id'] ?>"
                     title="Restaurar"
                     onclick="return confirm('¿Restaurar este activo?\\n\\nTambién se restaurarán sus mantenimientos y adjuntos eliminados.');">
                    <i class="fas fa-undo"></i> Restaurar
                  </a>

                  <a class="btn btn-sm btn-danger"
                     href="<?= e(base_url()) ?>/index.php?route=activos_purge&id=<?= (int)$r['id'] ?>"
                     title="Eliminar definitivo"
                     onclick="return confirm('⚠️ ELIMINACIÓN DEFINITIVA\\n\\nEsto borrará el activo y sus mantenimientos/adjuntos de forma permanente.\\n\\n¿Continuar?');">
                    <i class="fas fa-times"></i> Definitivo
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

<?php require __DIR__ . '/../layout/footer.php'; ?>
