<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }

$st = db()->prepare("
  SELECT
    c.*,
    a.codigo_interno,
    a.nombre AS activo_nombre,
    a.modelo AS activo_modelo,
    a.serial AS activo_serial
  FROM calibraciones c
  LEFT JOIN activos a ON a.id=c.activo_id AND a.tenant_id=c.tenant_id
  WHERE c.id=:id AND c.tenant_id=:t AND COALESCE(c.eliminado,0)=0
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$row = $st->fetch();
if (!$row) { http_response_code(404); echo "No encontrado"; exit; }

/* =========================
   Helpers robustos
========================= */
function fmt_dt($d){
  if(!$d) return '—';
  $s = (string)$d;
  return (strlen($s) >= 16) ? substr($s,0,16) : $s;
}
function has_table($table){
  try{
    $st = db()->prepare("
      SELECT 1
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :t
      LIMIT 1
    ");
    $st->execute([':t'=>$table]);
    return (bool)$st->fetchColumn();
  } catch(Exception $e){
    return false;
  }
}
function has_col($table, $col){
  try{
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
  } catch(Exception $e){
    return false;
  }
}

/* ===== Badges (estilo PRO) ===== */
function badge_estado_det($e){
  $e = strtoupper(trim((string)$e));
  if ($e === 'CERRADA') return "<span class='badge badge-success'><i class='fas fa-check-circle'></i> CERRADA</span>";
  if ($e === 'EN_PROCESO') return "<span class='badge badge-warning'><i class='fas fa-spinner'></i> EN PROCESO</span>";
  if ($e === 'ANULADA') return "<span class='badge badge-danger'><i class='fas fa-ban'></i> ANULADA</span>";
  return "<span class='badge badge-info'><i class='fas fa-calendar'></i> PROGRAMADA</span>";
}
function badge_tipo_det($t){
  $t = strtoupper(trim((string)$t));
  if ($t === 'EXTERNA') return "<span class='badge badge-primary'><i class='fas fa-truck'></i> EXTERNA</span>";
  return "<span class='badge badge-secondary'><i class='fas fa-building'></i> INTERNA</span>";
}
function badge_result_det($r){
  $r = strtoupper(trim((string)$r));
  if ($r === 'CONFORME') return "<span class='badge badge-success'><i class='fas fa-thumbs-up'></i> CONFORME</span>";
  if ($r === 'NO_CONFORME') return "<span class='badge badge-danger'><i class='fas fa-thumbs-down'></i> NO CONFORME</span>";
  return "<span class='badge badge-light'>—</span>";
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';

/* ===== Datos derivados para header ===== */
$codigo = trim((string)($row['codigo_interno'] ?? ''));
if ($codigo === '') $codigo = '#'.(int)$row['activo_id'];

$nombre = trim((string)($row['activo_nombre'] ?? ''));
$modelo = trim((string)($row['activo_modelo'] ?? ''));
$serial = trim((string)($row['activo_serial'] ?? ''));

$ms = [];
if ($modelo !== '') $ms[] = 'Modelo: '.$modelo;
if ($serial !== '') $ms[] = 'Serial: '.$serial;
$msTxt = $ms ? implode(' · ', $ms) : '—';

/* =========================
   NUEVO: Patrones usados (si existe tabla)
   - calibraciones_patrones (calibracion_id, patron_id, etc.)
========================= */
$patrones = [];
$hayPatrones = false;

if (has_table('calibraciones_patrones') && has_table('patrones')) {
  $hayPatrones = true;

  $col_cal_id = has_col('calibraciones_patrones', 'calibracion_id');
  $col_pat_id = has_col('calibraciones_patrones', 'patron_id');

  if ($col_cal_id && $col_pat_id) {
    // columnas opcionales que a veces traen detalle del uso
    $selExtra = [];
    if (has_col('calibraciones_patrones', 'observacion')) $selExtra[] = "cp.observacion";
    if (has_col('calibraciones_patrones', 'factor')) $selExtra[] = "cp.factor";
    if (has_col('calibraciones_patrones', 'condicion')) $selExtra[] = "cp.condicion";

    $sql = "
      SELECT
        p.id,
        p.nombre,
        p.marca,
        p.modelo,
        p.serial,
        p.certificado_numero,
        p.certificado_emisor
        ".($selExtra ? (",\n        ".implode(",\n        ", $selExtra)) : "")."
      FROM calibraciones_patrones cp
      INNER JOIN patrones p
        ON p.id = cp.patron_id AND p.tenant_id = :t
      WHERE cp.calibracion_id = :id
      ORDER BY p.nombre ASC, p.id DESC
    ";

    try{
      $stp = db()->prepare($sql);
      $stp->execute([':t'=>$tenantId, ':id'=>$id]);
      $patrones = $stp->fetchAll();
    } catch(Exception $e){
      $patrones = [];
    }
  }
}

/* =========================
   NUEVO: Resultados / Puntos (si existe tabla)
   - calibraciones_puntos (calibracion_id, punto, valor_ref, valor_med, error, tolerancia, conforme, etc.)
========================= */
$puntos = [];
$hayPuntos = false;

if (has_table('calibraciones_puntos')) {
  $hayPuntos = true;

  $col_cal_id2 = has_col('calibraciones_puntos', 'calibracion_id');
  if ($col_cal_id2) {

    // Elegimos columnas típicas si existen
    $cols = [];
    $cols[] = "id";
    if (has_col('calibraciones_puntos','punto')) $cols[] = "punto";
    if (has_col('calibraciones_puntos','unidad')) $cols[] = "unidad";
    if (has_col('calibraciones_puntos','valor_ref')) $cols[] = "valor_ref";
    if (has_col('calibraciones_puntos','valor_med')) $cols[] = "valor_med";
    if (has_col('calibraciones_puntos','error')) $cols[] = "error";
    if (has_col('calibraciones_puntos','tolerancia')) $cols[] = "tolerancia";
    if (has_col('calibraciones_puntos','incertidumbre')) $cols[] = "incertidumbre";
    if (has_col('calibraciones_puntos','conforme')) $cols[] = "conforme";
    if (has_col('calibraciones_puntos','observacion')) $cols[] = "observacion";

    $sql2 = "
      SELECT ".implode(",", $cols)."
      FROM calibraciones_puntos
      WHERE calibracion_id = :id
      ORDER BY id ASC
    ";

    try{
      $st2 = db()->prepare($sql2);
      $st2->execute([':id'=>$id]);
      $puntos = $st2->fetchAll();
    } catch(Exception $e){
      $puntos = [];
    }
  }
}
?>

<style>
.badge{border-radius:999px; padding:6px 10px; font-weight:600;}
.small-muted{font-size:12px; color:#6c757d;}
.kv{display:flex; justify-content:space-between; gap:10px; padding:10px 0; border-bottom:1px solid #eef1f4;}
.kv:last-child{border-bottom:0;}
.kv .k{color:#6c757d; font-size:12px; text-transform:uppercase; letter-spacing:.04em;}
.kv .v{font-weight:600;}
.card-soft{border:1px solid #e5e7eb; border-radius:12px;}
.hero-line{display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;}
.hero-left{display:flex; flex-direction:column;}
.hero-right{display:flex; gap:8px; align-items:center; flex-wrap:wrap;}
.pill{display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:#f4f6f9; border:1px solid #e5e7eb; font-size:12px;}
.note-box{background:#f8fafc; border:1px dashed #d8dee6; border-radius:12px; padding:12px;}
.note-title{font-weight:700; margin-bottom:6px;}
.table-pro{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;}
.table-pro table{margin:0;}
.table-pro thead th{background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6c757d;}
.badge-mini{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;border:1px solid #e5e7eb;background:#fff;font-weight:700;font-size:12px;}
</style>

<div class="content">
  <div class="container-fluid">

    <div class="card card-outline card-primary">
      <div class="card-header">
        <div class="hero-line">
          <div class="hero-left">
            <h3 class="card-title mb-0">
              <i class="fas fa-ruler-combined"></i> Detalle calibración #<?= (int)$row['id'] ?>
            </h3>
            <div class="small-muted">
              <?= e($codigo) ?><?= ($nombre !== '' ? ' · '.e($nombre) : '') ?>
            </div>
          </div>

          <div class="hero-right">
            <?= badge_tipo_det($row['tipo'] ?? 'INTERNA') ?>
            <?= badge_estado_det($row['estado'] ?? 'PROGRAMADA') ?>
            <?= badge_result_det($row['resultado_global'] ?? null) ?>

            <a class="btn btn-outline-secondary btn-sm"
               href="<?= e(base_url()) ?>/index.php?route=calibracion_certificado_edit&id=<?= (int)$row['id'] ?>">
              <i class="fas fa-pen"></i> Editar certificado
            </a>

<a class="btn btn-outline-primary btn-sm"
   href="<?= e(base_url()) ?>/index.php?route=calibracion_puntos&id=<?= (int)$row['id'] ?>">
  <i class="fas fa-list-ol"></i> Puntos / Resultados
</a>


            <a class="btn btn-outline-info btn-sm" target="_blank"
               href="<?= e(base_url()) ?>/index.php?route=calibracion_certificado&id=<?= (int)$row['id'] ?>">
              <i class="fas fa-print"></i> Imprimir certificado
            </a>

            <a class="btn btn-secondary btn-sm" href="<?= e(base_url()) ?>/index.php?route=calibraciones">
              <i class="fas fa-arrow-left"></i> Volver
            </a>
          </div>
        </div>
      </div>

      <div class="card-body">

        <!-- Tarjetas resumen -->
        <div class="row">
          <div class="col-lg-6 mb-3">
            <div class="card-soft p-3">
              <div class="d-flex align-items-start">
                <div style="width:44px;height:44px;border-radius:12px;background:#f4f6f9;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;margin-right:12px;">
                  <i class="fas fa-microchip text-muted"></i>
                </div>
                <div style="flex:1;">
                  <div class="small-muted">Equipo</div>
                  <div style="font-weight:800; font-size:18px; line-height:1.2;"><?= e($codigo) ?></div>
                  <?php if($nombre !== ''): ?><div class="small-muted"><?= e($nombre) ?></div><?php endif; ?>
                  <div class="pill mt-2"><i class="fas fa-tag"></i> <?= e($msTxt) ?></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6 mb-3">
            <div class="card-soft p-3">
              <div class="d-flex align-items-start">
                <div style="width:44px;height:44px;border-radius:12px;background:#f4f6f9;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;margin-right:12px;">
                  <i class="fas fa-certificate text-muted"></i>
                </div>
                <div style="flex:1;">
                  <div class="small-muted">Certificado</div>
                  <div style="font-weight:800; font-size:18px; line-height:1.2;"><?= e($row['numero_certificado'] ?: '—') ?></div>
                  <div class="pill mt-2"><i class="fas fa-fingerprint"></i> Token: <?= e($row['token_verificacion'] ?: '—') ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Datos en 2 columnas -->
        <div class="row">
          <div class="col-lg-6 mb-3">
            <div class="card-soft p-3">
              <div class="d-flex align-items-center mb-2" style="gap:8px;">
                <i class="fas fa-calendar-alt text-muted"></i>
                <div style="font-weight:800;">Fechas y estado</div>
              </div>

              <div class="kv">
                <div class="k">Fecha programada</div>
                <div class="v"><?= e(fmt_dt($row['fecha_programada'])) ?></div>
              </div>
              <div class="kv">
                <div class="k">Inicio</div>
                <div class="v"><?= e(fmt_dt($row['fecha_inicio'])) ?></div>
              </div>
              <div class="kv">
                <div class="k">Fin</div>
                <div class="v"><?= e(fmt_dt($row['fecha_fin'])) ?></div>
              </div>
              <div class="kv">
                <div class="k">Lugar</div>
                <div class="v"><?= e($row['lugar'] ?: '—') ?></div>
              </div>
            </div>
          </div>

          <div class="col-lg-6 mb-3">
            <div class="card-soft p-3">
              <div class="d-flex align-items-center mb-2" style="gap:8px;">
                <i class="fas fa-clipboard-check text-muted"></i>
                <div style="font-weight:800;">Método y referencias</div>
              </div>

              <div class="kv">
                <div class="k">Método</div>
                <div class="v"><?= e($row['metodo'] ?: '—') ?></div>
              </div>
              <div class="kv">
                <div class="k">Norma ref</div>
                <div class="v"><?= e($row['norma_ref'] ?: '—') ?></div>
              </div>
              <div class="kv">
                <div class="k">Procedimiento ref</div>
                <div class="v"><?= e($row['procedimiento_ref'] ?: '—') ?></div>
              </div>
              <div class="kv">
                <div class="k">Resultado global</div>
                <div class="v"><?= e($row['resultado_global'] ?: '—') ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Patrones usados + Resultados -->
        <div class="row">
          <div class="col-lg-6 mb-3">
            <div class="card-soft p-3">
              <div class="d-flex align-items-center mb-2" style="gap:8px;">
                <i class="fas fa-balance-scale text-muted"></i>
                <div style="font-weight:800;">Patrones usados</div>
              </div>

              <?php if(!$hayPatrones): ?>
                <div class="text-muted">Aún no está configurada la tabla de relación de patrones (calibraciones_patrones).</div>
              <?php else: ?>
                <?php if(!$patrones): ?>
                  <div class="text-muted">No hay patrones vinculados a esta calibración.</div>
                <?php else: ?>
                  <div class="table-pro">
                    <table class="table table-sm table-hover">
                      <thead>
                        <tr>
                          <th>Patrón</th>
                          <th>Serial</th>
                          <th>Certificado</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach($patrones as $p): ?>
                          <tr>
                            <td>
                              <b><?= e($p['nombre'] ?? '') ?></b>
                              <div class="small-muted">
                                <?= e(trim(($p['marca'] ?? '').' '.($p['modelo'] ?? '')) ?: '—') ?>
                              </div>
                            </td>
                            <td><?= e(($p['serial'] ?? '') ?: '—') ?></td>
                            <td>
                              <?= e(($p['certificado_numero'] ?? '') ?: '—') ?>
                              <?php if(!empty($p['certificado_emisor'])): ?>
                                <div class="small-muted"><?= e($p['certificado_emisor']) ?></div>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-lg-6 mb-3">
            <div class="card-soft p-3">
              <div class="d-flex align-items-center mb-2" style="gap:8px;">
                <i class="fas fa-chart-line text-muted"></i>
                <div style="font-weight:800;">Resultados / Puntos</div>
              </div>

              <?php if(!$hayPuntos): ?>
                <div class="text-muted">Aún no está configurada la tabla de puntos/mediciones (calibraciones_puntos).</div>
              <?php else: ?>
                <?php if(!$puntos): ?>
                  <div class="text-muted">No hay puntos registrados para esta calibración.</div>
                <?php else: ?>
                  <div class="table-pro">
                    <table class="table table-sm table-hover">
                      <thead>
                        <tr>
                          <th>#</th>
                          <?php if(isset($puntos[0]['punto'])): ?><th>Punto</th><?php endif; ?>
                          <?php if(isset($puntos[0]['valor_ref'])): ?><th>Ref</th><?php endif; ?>
                          <?php if(isset($puntos[0]['valor_med'])): ?><th>Med</th><?php endif; ?>
                          <?php if(isset($puntos[0]['error'])): ?><th>Error</th><?php endif; ?>
                          <?php if(isset($puntos[0]['tolerancia'])): ?><th>Tol</th><?php endif; ?>
                          <?php if(isset($puntos[0]['conforme'])): ?><th>OK</th><?php endif; ?>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach($puntos as $i=>$pt): ?>
                          <?php
                            $ok = null;
                            if (array_key_exists('conforme', $pt)) {
                              $v = strtoupper(trim((string)$pt['conforme']));
                              $ok = ($v==='1' || $v==='SI' || $v==='S' || $v==='YES' || $v==='CONFORME');
                            }
                          ?>
                          <tr>
                            <td><?= (int)($i+1) ?></td>
                            <?php if(isset($pt['punto'])): ?><td><?= e($pt['punto']) ?><?= isset($pt['unidad']) && $pt['unidad']!=='' ? ' '.e($pt['unidad']) : '' ?></td><?php endif; ?>
                            <?php if(isset($pt['valor_ref'])): ?><td><?= e($pt['valor_ref']) ?></td><?php endif; ?>
                            <?php if(isset($pt['valor_med'])): ?><td><?= e($pt['valor_med']) ?></td><?php endif; ?>
                            <?php if(isset($pt['error'])): ?><td><?= e($pt['error']) ?></td><?php endif; ?>
                            <?php if(isset($pt['tolerancia'])): ?><td><?= e($pt['tolerancia']) ?></td><?php endif; ?>
                            <?php if(isset($pt['conforme'])): ?>
                              <td>
                                <?php if ($ok === true): ?>
                                  <span class="badge-mini"><i class="fas fa-check text-success"></i> OK</span>
                                <?php elseif ($ok === false): ?>
                                  <span class="badge-mini"><i class="fas fa-times text-danger"></i> NO</span>
                                <?php else: ?>
                                  —
                                <?php endif; ?>
                              </td>
                            <?php endif; ?>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Observaciones / Recomendaciones -->
        <div class="row">
          <div class="col-lg-6 mb-3">
            <div class="note-box">
              <div class="note-title"><i class="fas fa-comment-dots"></i> Observaciones</div>
              <div><?= nl2br(e($row['observaciones'] ?: '—')) ?></div>
            </div>
          </div>
          <div class="col-lg-6 mb-3">
            <div class="note-box">
              <div class="note-title"><i class="fas fa-lightbulb"></i> Recomendaciones</div>
              <div><?= nl2br(e($row['recomendaciones'] ?: '—')) ?></div>
            </div>
          </div>
        </div>

        <div class="small-muted mt-2">
          <i class="fas fa-info-circle"></i>
          Consejo PRO: el certificado imprimible usa el token de verificación y marca de agua para anti-falsificación.
        </div>

      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
