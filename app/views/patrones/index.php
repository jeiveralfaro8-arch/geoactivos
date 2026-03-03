<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$msg = '';

/* =========================
   Helpers: detección de columnas (robusto)
========================= */
function has_col($table, $col) {
  try {
    $st = db()->prepare("
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :t
        AND COLUMN_NAME = :c
      LIMIT 1
    ");
    $st->execute([':t'=>$table, ':c'=>$col]);
    return (bool)$st->fetchColumn();
  } catch (Exception $e) {
    return false;
  }
}

function fmt_date($d){
  if(!$d) return '—';
  $s = (string)$d;
  return substr($s, 0, 10);
}

function join_path($base, $rel) {
  $base = rtrim((string)$base, '/');
  $rel  = ltrim((string)$rel, '/');
  return $base . '/' . $rel;
}

/* =========================
   Columnas reales (según BD)
========================= */
$col_magnitudes = has_col('patrones', 'magnitudes');          // viejo
$col_magnitud   = has_col('patrones', 'magnitud');            // real
$col_unidad     = has_col('patrones', 'unidad');              // real
$col_rango      = has_col('patrones', 'rango');               // viejo
$col_rmin       = has_col('patrones', 'rango_min');           // real
$col_rmax       = has_col('patrones', 'rango_max');           // real

$col_cert_fecha       = has_col('patrones', 'certificado_fecha');          // viejo
$col_cert_fecha_emis  = has_col('patrones', 'certificado_fecha_emision');  // real
$col_cert_vig_hasta   = has_col('patrones', 'certificado_vigencia_hasta'); // real
$col_archivo_cert     = has_col('patrones', 'archivo_certificado');        // real
$col_tipo_patron      = has_col('patrones', 'tipo_patron');                // real

/* =========================
   Eliminar (soft delete)
   - Tu tabla patrones tiene: eliminado
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $id = (int)($_POST['id'] ?? 0);

  if ($id > 0) {
    $chk = db()->prepare("SELECT id FROM patrones WHERE id=:id AND tenant_id=:t LIMIT 1");
    $chk->execute([':id'=>$id, ':t'=>$tenantId]);

    if (!$chk->fetch()) {
      $msg = "Patrón no encontrado o no pertenece a este cliente.";
    } else {
      $up = db()->prepare("
        UPDATE patrones
        SET eliminado=1
        WHERE id=:id AND tenant_id=:t
        LIMIT 1
      ");
      $up->execute([':id'=>$id, ':t'=>$tenantId]);
      $msg = "Patrón eliminado.";
    }
  }
}

/* =========================
   Listado (robusto con columnas reales)
========================= */
$select = [];
$select[] = "p.id";
$select[] = "p.nombre";
$select[] = "p.marca";
$select[] = "p.modelo";
$select[] = "p.serial";
$select[] = "p.resolucion";
$select[] = "p.certificado_numero";
$select[] = "p.certificado_emisor";
$select[] = "p.estado";

if ($col_magnitudes) $select[] = "p.magnitudes";
if ($col_magnitud)   $select[] = "p.magnitud";
if ($col_unidad)     $select[] = "p.unidad";

if ($col_rango) $select[] = "p.rango";
if ($col_rmin)  $select[] = "p.rango_min";
if ($col_rmax)  $select[] = "p.rango_max";

if ($col_cert_fecha)      $select[] = "p.certificado_fecha";
if ($col_cert_fecha_emis) $select[] = "p.certificado_fecha_emision";
if ($col_cert_vig_hasta)  $select[] = "p.certificado_vigencia_hasta";
if ($col_archivo_cert)    $select[] = "p.archivo_certificado";

$join = "";
if ($col_tipo_patron) {
  $select[] = "p.tipo_patron";
  // opcional: traer nombre del tipo si existe la tabla patrones_tipos
  try {
    $test = db()->query("SELECT 1 FROM patrones_tipos LIMIT 1");
    if ($test) {
      $join = " LEFT JOIN patrones_tipos pt ON pt.id = p.tipo_patron ";
      $select[] = "pt.nombre AS tipo_patron_nombre";
    }
  } catch (Exception $e) {
    // si no existe la tabla, no hacemos join
  }
}

$sql = "
  SELECT " . implode(",\n    ", $select) . "
  FROM patrones p
  $join
  WHERE p.tenant_id = :t
    AND COALESCE(p.eliminado,0) = 0
  ORDER BY p.nombre ASC, p.id DESC
";

$rows = [];
try {
  $st = db()->prepare($sql);
  $st->execute([':t'=>$tenantId]);
  $rows = $st->fetchAll();
} catch (PDOException $e) {
  // Mostrar un mensaje útil sin tumbar la vista
  $msg = "Error consultando patrones: " . $e->getMessage();
  $rows = [];
}

/* =========================
   Render
========================= */
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';

/* Formateadores de columnas */
function build_magnitudes($r) {
  $m = '';
  if (!empty($r['magnitudes'])) $m = trim((string)$r['magnitudes']);
  if (!$m && !empty($r['magnitud'])) $m = trim((string)$r['magnitud']);
  // Si además hay tipo_patron_nombre, úsalo como complemento si magnitud está vacío
  if (!$m && !empty($r['tipo_patron_nombre'])) $m = trim((string)$r['tipo_patron_nombre']);
  if (!$m) return '—';

  if (!empty($r['unidad'])) {
    return $m . " (" . trim((string)$r['unidad']) . ")";
  }
  return $m;
}

function build_rango($r) {
  if (!empty($r['rango'])) return trim((string)$r['rango']);

  $min = isset($r['rango_min']) ? trim((string)$r['rango_min']) : '';
  $max = isset($r['rango_max']) ? trim((string)$r['rango_max']) : '';

  if ($min === '' && $max === '') return '—';

  if ($min !== '' && $max !== '') return $min . " a " . $max;
  if ($min !== '') return "Desde " . $min;
  return "Hasta " . $max;
}

function cert_fecha($r) {
  if (!empty($r['certificado_fecha_emision'])) return $r['certificado_fecha_emision'];
  if (!empty($r['certificado_fecha'])) return $r['certificado_fecha'];
  return null;
}
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-balance-scale"></i> Patrones</h3>
    <div class="card-tools">
      <a class="btn btn-primary btn-sm" href="<?= e(base_url()) ?>/index.php?route=patron_form">
        <i class="fas fa-plus"></i> Nuevo
      </a>
    </div>
  </div>

  <div class="card-body">
    <?php if ($msg): ?>
      <div class="alert alert-info text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if (!$rows): ?>
      <div class="text-muted">No hay patrones registrados.</div>
      <div class="mt-2">
        <a class="btn btn-primary btn-sm" href="<?= e(base_url()) ?>/index.php?route=patron_form">
          <i class="fas fa-plus"></i> Crear primer patrón
        </a>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover text-nowrap">
          <thead>
            <tr>
              <th style="width:70px">ID</th>
              <th>Nombre</th>
              <th>Marca/Modelo</th>
              <th style="width:140px">Serial</th>
              <th style="width:220px">Magnitud</th>
              <th style="width:180px">Rango</th>
              <th style="width:190px">Certificado</th>
              <th style="width:120px">Vigencia</th>
              <th style="width:110px">Estado</th>
              <th style="width:160px" class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <?php
              $marcaModelo = trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? ''));
              $vig = fmt_date($r['certificado_vigencia_hasta'] ?? null);
              $cf = fmt_date(cert_fecha($r));
              $certNum = $r['certificado_numero'] ?? '';
              $certEmi = $r['certificado_emisor'] ?? '';
              $archivo = $r['archivo_certificado'] ?? '';
              $archivo = is_string($archivo) ? trim($archivo) : '';
              $archivoUrl = '';
              if ($archivo !== '') {
                // si el valor ya viene como "uploads/..." lo publicamos con base_url()
                $archivoUrl = join_path(base_url(), $archivo);
              }
            ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td>
                <b><?= e($r['nombre'] ?? '') ?></b>
                <?php if (!empty($r['tipo_patron_nombre'])): ?>
                  <div class="text-muted text-sm"><?= e($r['tipo_patron_nombre']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($marcaModelo ?: '—') ?></td>
              <td><?= e(($r['serial'] ?? '') ?: '—') ?></td>
              <td><?= e(build_magnitudes($r)) ?></td>
              <td><?= e(build_rango($r)) ?></td>
              <td>
                <?= e($certNum ?: '—') ?>
                <?php if ($certEmi): ?>
                  <div class="text-muted text-sm"><?= e($certEmi) ?></div>
                <?php endif; ?>
                <?php if ($cf !== '—'): ?>
                  <div class="text-muted text-sm">Emisión: <?= e($cf) ?></div>
                <?php endif; ?>
                <?php if ($archivoUrl): ?>
                  <div class="mt-1">
                    <a class="btn btn-xs btn-outline-info" href="<?= e($archivoUrl) ?>" target="_blank" rel="noopener">
                      <i class="fas fa-file-pdf"></i> Ver certificado
                    </a>
                  </div>
                <?php endif; ?>
              </td>
              <td><?= e($vig) ?></td>
              <td>
                <?php if (($r['estado'] ?? '') === 'INACTIVO'): ?>
                  <span class="badge badge-secondary">INACTIVO</span>
                <?php else: ?>
                  <span class="badge badge-success">ACTIVO</span>
                <?php endif; ?>
              </td>
              <td class="text-right">
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= e(base_url()) ?>/index.php?route=patron_form&id=<?= (int)$r['id'] ?>"
                   title="Editar">
                  <i class="fas fa-edit"></i>
                </a>
                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este patrón?');">
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

<?php require __DIR__ . '/../layout/footer.php'; ?>
