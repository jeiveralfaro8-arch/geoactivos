<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = (int)Auth::tenantId();
$user = Auth::user();
$userId = isset($user['id']) ? (int)$user['id'] : null;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }

/* =========================
   Helpers
========================= */
function f10($d){ return $d ? substr((string)$d,0,10) : ''; }

function dt_local_value($d){
  if (!$d) return '';
  $s = (string)$d;
  // soporta "YYYY-mm-dd HH:ii:ss" o "YYYY-mm-ddTHH:ii"
  $s = str_replace('T', ' ', $s);
  $s = substr($s, 0, 16); // YYYY-mm-dd HH:ii
  if (strlen($s) < 16) return '';
  return str_replace(' ', 'T', $s);
}

function gen_token_geo($len=18){
  $bytesLen = (int)ceil($len/2);
  if (function_exists('random_bytes')) {
    $raw = random_bytes($bytesLen);
  } elseif (function_exists('openssl_random_pseudo_bytes')) {
    $raw = openssl_random_pseudo_bytes($bytesLen);
  } else {
    $raw = '';
    for($i=0;$i<$bytesLen;$i++) $raw .= chr(mt_rand(0,255));
  }
  return strtoupper(substr(bin2hex($raw), 0, $len));
}

function chip_tone_estado($estado){
  $estado = strtoupper(trim((string)$estado));
  if ($estado === 'CERRADA') return 'success';
  if ($estado === 'EN_PROCESO') return 'warning';
  if ($estado === 'ANULADA') return 'danger';
  return 'info';
}
function chip_tone_resultado($res){
  $res = strtoupper(trim((string)$res));
  if ($res === 'CONFORME') return 'success';
  if ($res === 'NO_CONFORME') return 'danger';
  if ($res === 'OBSERVADO') return 'warning';
  return 'secondary';
}

/* =========================
   Cargar calibración/certificado
========================= */
$st = db()->prepare("
  SELECT
    c.*,
    a.codigo_interno,
    a.nombre AS activo_nombre,
    a.modelo AS activo_modelo,
    a.serial AS activo_serial,
    ar.nombre AS area_nombre,
    s.nombre AS sede_nombre
  FROM calibraciones c
  LEFT JOIN activos a ON a.id=c.activo_id AND a.tenant_id=c.tenant_id
  LEFT JOIN areas ar ON ar.id=a.area_id AND ar.tenant_id=a.tenant_id
  LEFT JOIN sedes s ON s.id=ar.sede_id AND s.tenant_id=ar.tenant_id
  WHERE c.id=:id AND c.tenant_id=:t AND COALESCE(c.eliminado,0)=0
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$row = $st->fetch();
if (!$row) { http_response_code(404); echo "No encontrado"; exit; }

/* =========================
   Defaults seguros
========================= */
$numero_certificado = (string)($row['numero_certificado'] ?? '');
$token_verificacion = (string)($row['token_verificacion'] ?? '');
$tipo              = strtoupper(trim((string)($row['tipo'] ?? 'INTERNA')));
$estado            = strtoupper(trim((string)($row['estado'] ?? 'PROGRAMADA')));
$resultado_global  = strtoupper(trim((string)($row['resultado_global'] ?? '')));
$fecha_programada  = (string)($row['fecha_programada'] ?? '');
$fecha_inicio      = (string)($row['fecha_inicio'] ?? '');
$fecha_fin         = (string)($row['fecha_fin'] ?? '');

$lugar             = (string)($row['lugar'] ?? '');
$metodo            = (string)($row['metodo'] ?? '');
$norma_ref         = (string)($row['norma_ref'] ?? '');
$procedimiento_ref = (string)($row['procedimiento_ref'] ?? '');
$observaciones     = (string)($row['observaciones'] ?? '');
$recomendaciones   = (string)($row['recomendaciones'] ?? '');

$cert_formato      = strtolower(trim((string)($row['cert_formato'] ?? 'general')));
if ($cert_formato === '') $cert_formato = 'general';

/* =========================
   POST (guardar certificado)
========================= */
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $numero_certificado = trim((string)($_POST['numero_certificado'] ?? ''));
  $token_verificacion = trim((string)($_POST['token_verificacion'] ?? ''));

  if ($token_verificacion === '') {
    $token_verificacion = 'TOK-' . gen_token_geo(18);
  }

  $tipo = strtoupper(trim((string)($_POST['tipo'] ?? 'INTERNA')));
  if (!in_array($tipo, ['INTERNA','EXTERNA'], true)) $tipo = 'INTERNA';

  $estado = strtoupper(trim((string)($_POST['estado'] ?? 'PROGRAMADA')));
  if (!in_array($estado, ['PROGRAMADA','EN_PROCESO','CERRADA','ANULADA'], true)) $estado = 'PROGRAMADA';

  $resultado_global = strtoupper(trim((string)($_POST['resultado_global'] ?? '')));
  if ($resultado_global !== '' && !in_array($resultado_global, ['CONFORME','NO_CONFORME','OBSERVADO'], true)) {
    $resultado_global = '';
  }

  $fecha_programada = trim((string)($_POST['fecha_programada'] ?? ''));
  $fecha_inicio     = trim((string)($_POST['fecha_inicio'] ?? ''));
  $fecha_fin        = trim((string)($_POST['fecha_fin'] ?? ''));

  // convertir datetime-local a formato DB si viene "YYYY-mm-ddTHH:ii"
  $fecha_programada = str_replace('T', ' ', $fecha_programada);
  $fecha_inicio     = str_replace('T', ' ', $fecha_inicio);
  $fecha_fin        = str_replace('T', ' ', $fecha_fin);

  $lugar             = trim((string)($_POST['lugar'] ?? ''));
  $metodo            = trim((string)($_POST['metodo'] ?? ''));
  $norma_ref         = trim((string)($_POST['norma_ref'] ?? ''));
  $procedimiento_ref = trim((string)($_POST['procedimiento_ref'] ?? ''));
  $observaciones     = trim((string)($_POST['observaciones'] ?? ''));
  $recomendaciones   = trim((string)($_POST['recomendaciones'] ?? ''));

  if ($estado === 'CERRADA' && $numero_certificado === '') {
    $err = 'Si el estado es CERRADA, se recomienda asignar un número de certificado.';
  }

  if ($err === '') {
    $up = db()->prepare("
      UPDATE calibraciones SET
        numero_certificado=:nc,
        token_verificacion=:tv,
        tipo=:tipo,
        estado=:estado,
        resultado_global=:rg,
        fecha_programada=:fp,
        fecha_inicio=:fi,
        fecha_fin=:ff,
        lugar=:lugar,
        metodo=:metodo,
        norma_ref=:nr,
        procedimiento_ref=:pr,
        observaciones=:obs,
        recomendaciones=:rec
      WHERE id=:id AND tenant_id=:t AND COALESCE(eliminado,0)=0
      LIMIT 1
    ");

    $up->execute([
      ':nc'=>($numero_certificado !== '' ? $numero_certificado : null),
      ':tv'=>($token_verificacion !== '' ? $token_verificacion : null),
      ':tipo'=>$tipo,
      ':estado'=>$estado,
      ':rg'=>($resultado_global !== '' ? $resultado_global : null),

      ':fp'=>($fecha_programada !== '' ? $fecha_programada : null),
      ':fi'=>($fecha_inicio !== '' ? $fecha_inicio : null),
      ':ff'=>($fecha_fin !== '' ? $fecha_fin : null),

      ':lugar'=>($lugar !== '' ? $lugar : null),
      ':metodo'=>($metodo !== '' ? $metodo : null),
      ':nr'=>($norma_ref !== '' ? $norma_ref : null),
      ':pr'=>($procedimiento_ref !== '' ? $procedimiento_ref : null),
      ':obs'=>($observaciones !== '' ? $observaciones : null),
      ':rec'=>($recomendaciones !== '' ? $recomendaciones : null),

      ':id'=>$id,
      ':t'=>$tenantId
    ]);

    header('Location: '.base_url().'/index.php?route=calibracion_detalle&id='.$id.'&ok=cert');
    exit;
  }
}

require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';

$codigo = trim((string)($row['codigo_interno'] ?? ''));
if ($codigo === '') $codigo = '#'.(int)$row['activo_id'];
$nombre = trim((string)($row['activo_nombre'] ?? ''));

$sede = trim((string)($row['sede_nombre'] ?? ''));
$area = trim((string)($row['area_nombre'] ?? ''));
if ($sede && $area) $ubic = $sede.' - '.$area;
else if ($sede) $ubic = $sede;
else if ($area) $ubic = $area;
else $ubic = '—';

$estadoTone = chip_tone_estado($estado);
$resTone    = chip_tone_resultado($resultado_global);

$backUrl = base_url().'/index.php?route=calibracion_detalle&id='.(int)$id;

// ✅ Ruta del certificado (según tu archivo de impresión PRO que me pasaste)
$certUrlBase = base_url().'/index.php?route=calibracion_certificado&id='.(int)$id;
$certUrlFmt  = $certUrlBase . '&fmt=' . urlencode($cert_formato) . '&setfmt=1';

$formats = [
  'general'    => 'General',
  'balanza'    => 'Balanza',
  'termometro' => 'Termómetro',
  'manometro'  => 'Manómetro',
  'electrico'  => 'Eléctrico',
];
if (!isset($formats[$cert_formato])) $cert_formato = 'general';
?>

<style>
/* ======= PRO UI (compatible AdminLTE) ======= */
.ga-pro-top{
  display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;
}
.ga-pro-sub{ color:#6c757d; font-size:12px; margin-top:3px; }
.ga-pro-chips{ display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
.ga-chip{
  display:inline-flex; align-items:center; gap:8px;
  padding:6px 10px; border-radius:999px;
  background:#f6f7fb; border:1px solid #e5e7eb;
  font-size:12px; font-weight:700; color:#111827;
}
.ga-chip i{ opacity:.85; }
.ga-chip.success{ background:rgba(22,163,74,.10); border-color:rgba(22,163,74,.25); color:#0b6b2a; }
.ga-chip.warning{ background:rgba(217,119,6,.10); border-color:rgba(217,119,6,.25); color:#8a4b08; }
.ga-chip.danger{  background:rgba(220,38,38,.10); border-color:rgba(220,38,38,.25); color:#8a1212; }
.ga-chip.info{    background:rgba(37,99,235,.10); border-color:rgba(37,99,235,.25); color:#0b2f8a; }
.ga-chip.secondary{ background:#f3f4f6; border-color:#e5e7eb; color:#374151; }

.card-soft{border:1px solid #e5e7eb; border-radius:12px; background:#fff;}
.card-soft .head{
  display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
  padding:12px 14px; border-bottom:1px solid #eef0f4;
}
.card-soft .head .ttl{ font-weight:800; letter-spacing:.02em; }
.card-soft .body{ padding:12px 14px; }

.small-muted{font-size:12px; color:#6c757d;}
.help-tip{ font-size:12px; color:#6b7280; margin-top:6px; }

.fmtbar{
  display:flex; gap:6px; flex-wrap:wrap;
}
.fmtpill{
  display:inline-flex; align-items:center; gap:6px;
  padding:6px 10px; border-radius:999px;
  background:#fff; border:1px solid #e5e7eb;
  font-size:12px; font-weight:800; color:#111827; text-decoration:none;
}
.fmtpill.active{ background:rgba(37,99,235,.10); border-color:rgba(37,99,235,.30); color:#0b2f8a; }
.fmtpill:hover{ background:#f9fafb; }

.ga-sticky{
  position:sticky; bottom:0; z-index:30;
  background:rgba(255,255,255,.92);
  border-top:1px solid #e5e7eb;
  padding:10px 12px;
  backdrop-filter: blur(6px);
}
.ga-sticky .inner{
  display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
}
.ga-sticky .left{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.ga-sticky .right{ display:flex; gap:8px; flex-wrap:wrap; }
.ga-mini{
  display:flex; align-items:center; gap:8px;
  padding:8px 10px; border-radius:10px;
  background:#f9fafb; border:1px dashed #e5e7eb;
  font-size:12px; color:#374151;
}
</style>

<div class="content">
  <div class="container-fluid">

    <div class="card card-outline card-primary">
      <div class="card-header">
        <div class="ga-pro-top">
          <div>
            <h3 class="card-title mb-0">
              <i class="fas fa-pen"></i> Editar certificado · Calibración #<?= (int)$id ?>
            </h3>
            <div class="ga-pro-sub">
              <?= e($codigo) ?><?= ($nombre !== '' ? ' · '.e($nombre) : '') ?> · <?= e($ubic) ?>
            </div>

            <div class="ga-pro-chips">
              <span class="ga-chip info"><i class="fas fa-tag"></i> Tipo: <?= e($tipo) ?></span>
              <span class="ga-chip <?= e($estadoTone) ?>"><i class="fas fa-traffic-light"></i> Estado: <?= e($estado) ?></span>
              <span class="ga-chip <?= e($resTone) ?>"><i class="fas fa-clipboard-check"></i> Resultado: <?= e($resultado_global !== '' ? $resultado_global : '—') ?></span>
              <span class="ga-chip secondary"><i class="fas fa-layer-group"></i> Formato: <?= e(isset($formats[$cert_formato]) ? $formats[$cert_formato] : 'General') ?></span>
            </div>
          </div>

          <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-start;">
            <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">
              <i class="fas fa-arrow-left"></i> Volver
            </a>

            <a class="btn btn-outline-primary" href="<?= e($certUrlFmt) ?>" target="_blank" rel="noopener">
              <i class="fas fa-file-pdf"></i> Ver certificado
            </a>

            <a class="btn btn-primary" href="<?= e($certUrlFmt) ?>" target="_blank" rel="noopener" onclick="setTimeout(function(){ try{ window.open('<?= e($certUrlFmt) ?>','_blank'); }catch(e){} }, 50);">
              <i class="fas fa-print"></i> Imprimir (PDF)
            </a>
          </div>
        </div>
      </div>

      <form method="post" id="form-cert">
        <div class="card-body">

          <?php if ($err): ?>
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-triangle"></i> <?= e($err) ?>
            </div>
          <?php endif; ?>

          <!-- ===== Formato guardado (atajos) ===== -->
          <div class="card-soft mb-3">
            <div class="head">
              <div class="ttl"><i class="fas fa-sliders-h"></i> Formato del certificado</div>
              <div class="small-muted">Se usa al imprimir / ver el certificado.</div>
            </div>
            <div class="body">
              <div class="fmtbar">
                <?php foreach($formats as $k=>$lbl): ?>
                  <?php
                    $u = base_url().'/index.php?route=calibracion_certificado&id='.(int)$id.'&fmt='.urlencode($k).'&setfmt=1';
                  ?>
                  <a class="fmtpill <?= ($cert_formato===$k?'active':'') ?>" href="<?= e($u) ?>" target="_blank" rel="noopener">
                    <?= e($lbl) ?>
                  </a>
                <?php endforeach; ?>
              </div>
              <div class="help-tip">
                Tip: al abrir un formato desde aquí, el certificado quedará “guardado” en ese formato (según tu lógica de <b>setfmt</b>).
              </div>
            </div>
          </div>

          <div class="row">
            <!-- Número / Token / Resultado -->
            <div class="col-lg-4">
              <div class="card-soft mb-3">
                <div class="head">
                  <div class="ttl"><i class="fas fa-hashtag"></i> Número de certificado</div>
                  <div class="small-muted">Impreso</div>
                </div>
                <div class="body">
                  <input class="form-control" name="numero_certificado" value="<?= e($numero_certificado) ?>" placeholder="Ej: CERT-2026-000123">
                  <div class="help-tip">Recomendado cuando el estado esté en <b>CERRADA</b>.</div>
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <div class="card-soft mb-3">
                <div class="head">
                  <div class="ttl"><i class="fas fa-shield-alt"></i> Token de verificación</div>
                  <button type="button" class="btn btn-xs btn-outline-primary" id="btn-gen-token">
                    <i class="fas fa-random"></i> Generar
                  </button>
                </div>
                <div class="body">
                  <input class="form-control" name="token_verificacion" id="token_verificacion" value="<?= e($token_verificacion) ?>" placeholder="Ej: TOK-ABC123...">
                  <div class="help-tip">Si lo dejas vacío, el sistema lo genera automáticamente al guardar.</div>
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <div class="card-soft mb-3">
                <div class="head">
                  <div class="ttl"><i class="fas fa-clipboard-check"></i> Resultado global</div>
                  <div class="small-muted">Encabezado</div>
                </div>
                <div class="body">
                  <select class="form-control" name="resultado_global">
                    <option value="" <?= ($resultado_global===''?'selected':'') ?>>—</option>
                    <option value="CONFORME" <?= ($resultado_global==='CONFORME'?'selected':'') ?>>CONFORME</option>
                    <option value="NO_CONFORME" <?= ($resultado_global==='NO_CONFORME'?'selected':'') ?>>NO CONFORME</option>
                    <option value="OBSERVADO" <?= ($resultado_global==='OBSERVADO'?'selected':'') ?>>OBSERVADO</option>
                  </select>
                  <div class="help-tip">Se recomienda definirlo cuando se cierre la calibración.</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tipo / Estado / Fechas -->
          <div class="row">
            <div class="col-lg-3">
              <div class="card-soft mb-3">
                <div class="head">
                  <div class="ttl"><i class="fas fa-tag"></i> Tipo</div>
                </div>
                <div class="body">
                  <select class="form-control" name="tipo">
                    <option value="INTERNA" <?= ($tipo==='INTERNA'?'selected':'') ?>>INTERNA</option>
                    <option value="EXTERNA" <?= ($tipo==='EXTERNA'?'selected':'') ?>>EXTERNA</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="col-lg-3">
              <div class="card-soft mb-3">
                <div class="head">
                  <div class="ttl"><i class="fas fa-traffic-light"></i> Estado</div>
                </div>
                <div class="body">
                  <select class="form-control" name="estado" id="estado">
                    <option value="PROGRAMADA" <?= ($estado==='PROGRAMADA'?'selected':'') ?>>PROGRAMADA</option>
                    <option value="EN_PROCESO" <?= ($estado==='EN_PROCESO'?'selected':'') ?>>EN PROCESO</option>
                    <option value="CERRADA" <?= ($estado==='CERRADA'?'selected':'') ?>>CERRADA</option>
                    <option value="ANULADA" <?= ($estado==='ANULADA'?'selected':'') ?>>ANULADA</option>
                  </select>
                  <div class="help-tip" id="hint-estado"></div>
                </div>
              </div>
            </div>

            <div class="col-lg-2">
              <div class="card-soft mb-3">
                <div class="head">
                  <div class="ttl"><i class="far fa-calendar-alt"></i> Programada</div>
                </div>
                <div class="body">
                  <input type="datetime-local" class="form-control" name="fecha_programada"
                         value="<?= e(dt_local_value($fecha_programada)) ?>">
                </div>
              </div>
            </div>

            <div class="col-lg-2">
              <div class="card-soft mb-3">
                <div class="head">
                  <div class="ttl"><i class="fas fa-play"></i> Inicio</div>
                </div>
                <div class="body">
                  <input type="datetime-local" class="form-control" name="fecha_inicio"
                         value="<?= e(dt_local_value($fecha_inicio)) ?>">
                </div>
              </div>
            </div>

            <div class="col-lg-2">
              <div class="card-soft mb-3">
                <div class="head">
                  <div class="ttl"><i class="fas fa-stop"></i> Fin</div>
                </div>
                <div class="body">
                  <input type="datetime-local" class="form-control" name="fecha_fin"
                         value="<?= e(dt_local_value($fecha_fin)) ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Condiciones -->
          <div class="card-soft mb-3">
            <div class="head">
              <div class="ttl"><i class="fas fa-flask"></i> Ejecución / Condiciones</div>
              <div class="small-muted">Método, norma y referencia</div>
            </div>
            <div class="body">
              <div class="row">
                <div class="col-lg-4">
                  <div class="form-group mb-2">
                    <label class="small-muted">Lugar</label>
                    <input class="form-control" name="lugar" value="<?= e($lugar) ?>" placeholder="Ej: Taller principal / Sede Norte">
                  </div>
                </div>
                <div class="col-lg-4">
                  <div class="form-group mb-2">
                    <label class="small-muted">Método</label>
                    <input class="form-control" name="metodo" value="<?= e($metodo) ?>" placeholder="Ej: Comparación directa">
                  </div>
                </div>
                <div class="col-lg-4">
                  <div class="form-group mb-2">
                    <label class="small-muted">Norma de referencia</label>
                    <input class="form-control" name="norma_ref" value="<?= e($norma_ref) ?>" placeholder="Ej: ISO/IEC 17025 / NTC...">
                  </div>
                </div>
              </div>

              <div class="form-group mb-0">
                <label class="small-muted">Procedimiento de referencia</label>
                <input class="form-control" name="procedimiento_ref" value="<?= e($procedimiento_ref) ?>" placeholder="Ej: PROC-CAL-001">
              </div>
            </div>
          </div>

          <!-- Observaciones / Recomendaciones -->
          <div class="row">
            <div class="col-lg-6">
              <div class="card-soft mb-3">
                <div class="head">
                  <div class="ttl"><i class="fas fa-comment-dots"></i> Observaciones</div>
                </div>
                <div class="body">
                  <textarea class="form-control" name="observaciones" rows="5" placeholder="Condiciones, hallazgos, notas..."><?= e($observaciones) ?></textarea>
                </div>
              </div>
            </div>

            <div class="col-lg-6">
              <div class="card-soft mb-3">
                <div class="head">
                  <div class="ttl"><i class="fas fa-lightbulb"></i> Recomendaciones</div>
                </div>
                <div class="body">
                  <textarea class="form-control" name="recomendaciones" rows="5" placeholder="Acciones sugeridas, periodicidad, mantenimiento..."><?= e($recomendaciones) ?></textarea>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Sticky footer PRO -->
        <div class="ga-sticky">
          <div class="inner">
            <div class="left">
              <div class="ga-mini">
                <i class="fas fa-info-circle"></i>
                Aquí editas solo datos del certificado (no modifica puntos/patrones).
              </div>
              <div class="ga-mini">
                <i class="fas fa-link"></i>
                Token requerido para verificación por QR en el certificado.
              </div>
            </div>
            <div class="right">
              <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">
                <i class="fas fa-arrow-left"></i> Volver
              </a>
              <button class="btn btn-primary" type="submit">
                <i class="fas fa-save"></i> Guardar cambios
              </button>
            </div>
          </div>
        </div>

      </form>

    </div>

  </div>
</div>

<script src="<?= e(base_url()) ?>/assets/js/certificado-edit.js"></script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
