<?php
require_once __DIR__ . '/../../../core/Helpers.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../config/db.php';

$tenantId = Auth::tenantId();

function col_exists($table, $col){
  try {
    $st = db()->prepare("
      SELECT COUNT(*) c
      FROM information_schema.columns
      WHERE table_schema = DATABASE()
        AND table_name = :t
        AND column_name = :c
    ");
    $st->execute([':t'=>$table, ':c'=>$col]);
    return ((int)($st->fetch()['c'] ?? 0) > 0);
  } catch (Exception $e) {
    return false;
  }
}

$hasFamilia = col_exists('tipos_activo', 'familia');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $id = (int)($_POST['id'] ?? 0);

  if ($id > 0) {
    // No borrar si hay activos con ese tipo
    $chk = db()->prepare("SELECT COUNT(*) c FROM activos WHERE tenant_id=:t AND tipo_activo_id=:id");
    $chk->execute([':t'=>$tenantId, ':id'=>$id]);
    $usada = (int)($chk->fetch()['c'] ?? 0);

    if ($usada > 0) {
      $msg = "No se puede eliminar: este tipo está usado en $usada activo(s).";
    } else {
      // Limpieza segura: reglas del tipo (por si no hay FK CASCADE)
      try {
        $delR = db()->prepare("DELETE FROM tipo_activo_reglas WHERE tenant_id=:t AND tipo_activo_id=:id");
        $delR->execute([':t'=>$tenantId, ':id'=>$id]);
      } catch (Exception $e) {
        // si falla, no rompemos
      }

      $del = db()->prepare("DELETE FROM tipos_activo WHERE id=:id AND tenant_id=:t");
      $del->execute([':id'=>$id, ':t'=>$tenantId]);
      $msg = "Tipo eliminado.";
    }
  }
}

if ($hasFamilia) {
  $st = db()->prepare("
    SELECT
      ta.id, ta.nombre, ta.codigo, ta.familia,
      COALESCE(r.usa_red,0) AS usa_red,
      COALESCE(r.usa_software,0) AS usa_software,
      COALESCE(r.es_biomedico,0) AS es_biomedico,
      COALESCE(r.requiere_calibracion,0) AS requiere_calibracion,
      r.periodicidad_meses
    FROM tipos_activo ta
    LEFT JOIN tipo_activo_reglas r
      ON r.tipo_activo_id = ta.id AND r.tenant_id = ta.tenant_id
    WHERE ta.tenant_id=:t
    ORDER BY ta.nombre ASC
  ");
} else {
  $st = db()->prepare("
    SELECT
      ta.id, ta.nombre, ta.codigo,
      COALESCE(r.usa_red,0) AS usa_red,
      COALESCE(r.usa_software,0) AS usa_software,
      COALESCE(r.es_biomedico,0) AS es_biomedico,
      COALESCE(r.requiere_calibracion,0) AS requiere_calibracion,
      r.periodicidad_meses
    FROM tipos_activo ta
    LEFT JOIN tipo_activo_reglas r
      ON r.tipo_activo_id = ta.id AND r.tenant_id = ta.tenant_id
    WHERE ta.tenant_id=:t
    ORDER BY ta.nombre ASC
  ");
}

$st->execute([':t'=>$tenantId]);
$rows = $st->fetchAll();

/* =========================
   Helpers visuales
========================= */
function familia_badge($f){
  $f = strtoupper(trim((string)$f));
  if ($f === 'BIOMED') return "<span class='badge badge-info'>BIOMED</span>";
  if ($f === 'INFRA')  return "<span class='badge badge-primary'>INFRA</span>";
  return "<span class='badge badge-secondary'>TI</span>";
}

function rule_badge($on, $icon, $label, $colorOn='success'){
  $on = ((int)$on === 1);

  if ($on) {
    $cls = "badge badge-$colorOn";
  } else {
    // OFF: visual pro (claro, con borde)
    $cls = "badge badge-light";
  }

  $title = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
  $txt   = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

  $style = $on ? "" : "style='border:1px solid #e5e7eb;color:#6b7280;'";
  return "<span class='$cls mr-1' $style title='$title'><i class='fas $icon'></i> $txt</span>";
}

require __DIR__ . '/../../layout/header.php';
require __DIR__ . '/../../layout/sidebar.php';
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-layer-group"></i> Tipos de activo</h3>
    <div class="card-tools">
      <a class="btn btn-primary btn-sm" href="<?= e(base_url()) ?>/index.php?route=tipo_activo_form">
        <i class="fas fa-plus"></i> Nuevo
      </a>
    </div>
  </div>

  <div class="card-body">
    <?php if ($msg): ?>
      <div class="alert alert-info text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if (!$rows): ?>
      <div class="text-muted">Aún no hay tipos. Crea el primero.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover text-nowrap">
          <thead>
            <tr>
              <th style="width:90px">ID</th>
              <th>Nombre</th>
              <th style="width:120px">Prefijo</th>
              <?php if ($hasFamilia): ?>
                <th style="width:110px">Familia</th>
              <?php endif; ?>
              <th>Reglas</th>
              <th style="width:160px" class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <?php
              $usaRed  = (int)($r['usa_red'] ?? 0);
              $usaSoft = (int)($r['usa_software'] ?? 0);
              $bio     = (int)($r['es_biomedico'] ?? 0);
              $cal     = (int)($r['requiere_calibracion'] ?? 0);
              $per     = $r['periodicidad_meses'];

              $perTxt = '';
              if ($cal === 1) {
                $n = ($per === null ? 0 : (int)$per);
                $perTxt = ($n > 0)
                  ? ("<span class='badge badge-light' style='border:1px solid #e5e7eb;' title='Periodicidad'><i class='fas fa-calendar-alt'></i> {$n}m</span>")
                  : ("<span class='badge badge-light' style='border:1px solid #e5e7eb;color:#6b7280;' title='Periodicidad'><i class='fas fa-calendar-alt'></i> —</span>");
              }
            ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['nombre']) ?></td>
              <td>
                <?php if (!empty($r['codigo'])): ?>
                  <span class="badge badge-info"><?= e($r['codigo']) ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>

              <?php if ($hasFamilia): ?>
              <td><?= familia_badge($r['familia'] ?? 'TI') ?></td>
              <?php endif; ?>

              <td>
                <?= rule_badge($usaRed,  'fa-network-wired', 'Red',        'success') ?>
                <?= rule_badge($usaSoft, 'fa-boxes',        'Software',   'warning') ?>
                <?= rule_badge($bio,     'fa-heartbeat',    'Biomédico',  'primary') ?>
                <?= rule_badge($cal,     'fa-ruler-combined','Calibración','info') ?>
                <?= $perTxt ?>
              </td>

              <td class="text-right">
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= e(base_url()) ?>/index.php?route=tipo_activo_form&id=<?= (int)$r['id'] ?>"
                   title="Editar">
                  <i class="fas fa-edit"></i>
                </a>

                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este tipo?');">
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

    <div class="mt-3 text-muted text-sm">
      Sugerencia: crea tipos como <b>Computador</b>, <b>Servidor</b>, <b>Impresora</b>, <b>Switch</b>, <b>Cámara IP</b>, <b>DVR/NVR</b>, <b>Biomédico</b>, <b>Aire acondicionado</b>, <b>Televisor</b>.
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../layout/footer.php'; ?>
