<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$tenantId = (int)Auth::tenantId();
$user     = Auth::user();
$userId   = isset($user['id']) ? (int)$user['id'] : null;

$id = (int)($_GET['id'] ?? 0);

/* =========================
   Helpers
========================= */
function e2($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function f10($d){ return $d ? substr((string)$d,0,10) : ''; }
function in_list($v, $arr){ return in_array($v, $arr, true); }

function table_exists_geo($table) {
  $q = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $q->execute([':t'=>$table]);
  return (bool)$q->fetch();
}
function column_exists_geo($table, $col){
  $q = db()->prepare("
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = :t
      AND column_name = :c
    LIMIT 1
  ");
  $q->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$q->fetch();
}
function ensure_column_geo($table, $col, $ddl){
  if (!table_exists_geo($table)) return false;
  if (column_exists_geo($table, $col)) return true;
  db()->exec("ALTER TABLE `$table` ADD COLUMN `$col` $ddl");
  return true;
}
function safe_json($raw){
  if (!$raw) return array();
  if (is_array($raw)) return $raw;
  $s = trim((string)$raw);
  if ($s === '') return array();
  $j = json_decode($s, true);
  return is_array($j) ? $j : array();
}

/* =========================================================
   ✅ Asegurar columnas mínimas en calibraciones_puntos
========================================================= */
if (table_exists_geo('calibraciones_puntos')) {
  ensure_column_geo('calibraciones_puntos', 'orden',            'INT NULL DEFAULT NULL');
  ensure_column_geo('calibraciones_puntos', 'magnitud',         'VARCHAR(100) NULL DEFAULT NULL');
  ensure_column_geo('calibraciones_puntos', 'unidad',           'VARCHAR(40) NULL DEFAULT NULL');
  ensure_column_geo('calibraciones_puntos', 'valor_referencia', 'DECIMAL(18,6) NULL DEFAULT NULL');
  ensure_column_geo('calibraciones_puntos', 'valor_equipo',     'DECIMAL(18,6) NULL DEFAULT NULL');
  ensure_column_geo('calibraciones_puntos', 'tolerancia',       'DECIMAL(18,6) NULL DEFAULT NULL');
  ensure_column_geo('calibraciones_puntos', 'error',            'DECIMAL(18,6) NULL DEFAULT NULL');
  ensure_column_geo('calibraciones_puntos', 'cumple',           'TINYINT(1) NULL DEFAULT NULL');
}

/* =========================================================
   ✅ Técnico desde usuario logueado
========================================================= */
$tecNombre  = '';
$tecCargo   = '';
$tecTarjeta = '';

function pick_first_geo($arr, $keys) {
  foreach ($keys as $k) {
    if (isset($arr[$k]) && trim((string)$arr[$k]) !== '') return trim((string)$arr[$k]);
  }
  return '';
}

if ($tecNombre === '') {
  $tecNombre = pick_first_geo($user, ['nombre_completo','full_name','name']);
  if ($tecNombre === '') {
    $n = pick_first_geo($user, ['nombre','nombres']);
    $a = pick_first_geo($user, ['apellido','apellidos']);
    $tecNombre = trim($n.' '.$a);
  }
  if ($tecNombre === '') $tecNombre = pick_first_geo($user, ['usuario','username','email']);
  if ($tecNombre === '') $tecNombre = 'Técnico';
}
if ($tecCargo === '')   $tecCargo   = pick_first_geo($user, ['cargo','rol','perfil','puesto']);
if ($tecTarjeta === '') $tecTarjeta = pick_first_geo($user, ['tarjeta_prof','tarjeta_profesional','registro_profesional','tp','matricula']);

/* =========================================================
   Defaults (columnas reales + extras al JSON)
========================================================= */
$cal = [
  'id' => 0,
  'activo_id' => 0,
  'plantilla_id' => null,

  'cert_formato' => 'general',
  'numero_certificado' => '',
  'token_verificacion' => '',

  'tipo' => 'INTERNA',
  'estado' => 'PROGRAMADA',

  'fecha_programada' => date('Y-m-d'),
  'fecha_inicio' => null,
  'fecha_fin' => null,

  'lugar' => '',
  'metodo' => '',
  'procedimiento_ref' => '',
  'norma_ref' => '',

  'temperatura_c' => null,
  'humedad_rel' => null,

  'resultado_global' => 'CONFORME',
  'observaciones' => '',
  'recomendaciones' => '',

  'tecnico_id' => $userId,
  'tecnico_nombre' => $tecNombre,
  'tecnico_cargo' => $tecCargo,
  'tecnico_tarjeta_prof' => $tecTarjeta,

  'recibido_por_nombre' => '',
  'recibido_por_cargo' => '',

  'detalle_json' => ''
];

$DJ = [];      // detalle_json como array
$patSel = [];  // patron_id seleccionados
$puntos = [];  // puntos existentes

/* =========================================================
   Cargar calibración si edita
========================================================= */
if ($id > 0) {
  $st = db()->prepare("SELECT * FROM calibraciones WHERE id=:id AND tenant_id=:t AND COALESCE(eliminado,0)=0 LIMIT 1");
  $st->execute([':id'=>$id, ':t'=>$tenantId]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); echo "Calibración no encontrada"; exit; }

  $cal = array_merge($cal, $row);
  $DJ  = safe_json($cal['detalle_json'] ?? '');

  // Técnico fallback si viene vacío en registro viejo
  if (empty($cal['tecnico_id'])) $cal['tecnico_id'] = $userId;
  if (trim((string)$cal['tecnico_nombre']) === '') $cal['tecnico_nombre'] = $tecNombre;
  if (trim((string)$cal['tecnico_cargo']) === '')  $cal['tecnico_cargo']  = $tecCargo;
  if (trim((string)$cal['tecnico_tarjeta_prof']) === '') $cal['tecnico_tarjeta_prof'] = $tecTarjeta;

  $ps = db()->prepare("SELECT patron_id FROM calibraciones_patrones WHERE tenant_id=:t AND calibracion_id=:c");
  $ps->execute([':t'=>$tenantId, ':c'=>$id]);
  $patSel = array_map('intval', array_column($ps->fetchAll(), 'patron_id'));

  $pt = db()->prepare("
    SELECT orden, magnitud, unidad, valor_referencia, valor_equipo, tolerancia
    FROM calibraciones_puntos
    WHERE tenant_id=:t AND calibracion_id=:c
    ORDER BY orden ASC, id ASC
  ");
  $pt->execute([':t'=>$tenantId, ':c'=>$id]);
  $puntos = $pt->fetchAll();
} else {
  $DJ = [];
}

/* =========================================================
   Catálogos
========================================================= */
$activos = [];
$stA = db()->prepare("
  SELECT id, codigo_interno, nombre, serial, modelo
  FROM vw_activos_calibrables
  WHERE tenant_id=:t AND COALESCE(eliminado,0)=0 AND requiere_calibracion_eff=1
  ORDER BY nombre ASC, id DESC
  LIMIT 300
");
$stA->execute([':t'=>$tenantId]);
$activos = $stA->fetchAll();

$patrones = [];
$stP = db()->prepare("
  SELECT id, nombre, marca, modelo, serial, certificado_vigencia_hasta, estado
  FROM patrones
  WHERE tenant_id=:t AND COALESCE(eliminado,0)=0 AND estado='ACTIVO'
  ORDER BY nombre ASC, id DESC
  LIMIT 300
");
$stP->execute([':t'=>$tenantId]);
$patrones = $stP->fetchAll();

/* =========================================================
   Guardar (POST)
========================================================= */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $activoId = (int)($_POST['activo_id'] ?? 0);

  // Validar activo calibrable
  $va = db()->prepare("
    SELECT id
    FROM vw_activos_calibrables
    WHERE tenant_id=:t AND id=:a AND COALESCE(eliminado,0)=0 AND requiere_calibracion_eff=1
    LIMIT 1
  ");
  $va->execute([':t'=>$tenantId, ':a'=>$activoId]);
  if (!$va->fetch()) {
    $error = 'Este activo no está marcado para calibración (o no existe en tu empresa).';
  }

  // Enums reales de tu tabla
  $tipo = strtoupper(trim((string)($_POST['tipo'] ?? ($cal['tipo'] ?? 'INTERNA'))));
  if (!in_list($tipo, ['INTERNA','EXTERNA'])) $tipo = 'INTERNA';

  // OJO: tu columna estado es enum largo, usamos los básicos más comunes
  $estado = strtoupper(trim((string)($_POST['estado'] ?? ($cal['estado'] ?? 'PROGRAMADA'))));
  if (!in_list($estado, ['PROGRAMADA','EN_PROCESO','CERRADA','ANULADA'])) $estado = 'PROGRAMADA';

  $resultado = strtoupper(trim((string)($_POST['resultado_global'] ?? ($cal['resultado_global'] ?? 'CONFORME'))));
  if (!in_list($resultado, ['CONFORME','NO_CONFORME'])) $resultado = 'CONFORME';

  $certFormato = strtolower(trim((string)($_POST['cert_formato'] ?? ($cal['cert_formato'] ?? 'general'))));
  if (!in_list($certFormato, ['general','balanza','termometro','manometro','electrico'])) $certFormato = 'general';

  $numeroCert = trim((string)($_POST['numero_certificado'] ?? ($cal['numero_certificado'] ?? '')));
  $token = trim((string)($_POST['token_verificacion'] ?? ($cal['token_verificacion'] ?? '')));
  if ($token === '') {
    // token simple/robusto sin libs
    $token = substr(sha1($tenantId.'|'.$activoId.'|'.microtime(true).'|'.rand(1000,9999)), 0, 32);
  }

  $fechaProgramada = trim((string)($_POST['fecha_programada'] ?? ''));
  if ($fechaProgramada === '') $fechaProgramada = date('Y-m-d');

  $fechaInicio = trim((string)($_POST['fecha_inicio'] ?? ''));
  $fechaFin    = trim((string)($_POST['fecha_fin'] ?? ''));
  $fechaInicio = ($fechaInicio === '') ? null : str_replace('T',' ',$fechaInicio).':00';
  $fechaFin    = ($fechaFin === '') ? null : str_replace('T',' ',$fechaFin).':00';

  $temp = trim((string)($_POST['temperatura_c'] ?? ''));
  $hum  = trim((string)($_POST['humedad_rel'] ?? ''));
  $temp = ($temp === '') ? null : (float)$temp;
  $hum  = ($hum === '')  ? null : (float)$hum;

  // ✅ EXTRAS al detalle_json (porque NO existen como columnas)
  $DJ = safe_json($cal['detalle_json'] ?? '');
  $DJ['ubicacion'] = trim((string)($_POST['ubicacion'] ?? ($DJ['ubicacion'] ?? '')));
  $DJ['condiciones_ambientales'] = trim((string)($_POST['condiciones_ambientales'] ?? ($DJ['condiciones_ambientales'] ?? '')));
  $DJ['proxima_calibracion'] = trim((string)($_POST['proxima_calibracion'] ?? ($DJ['proxima_calibracion'] ?? '')));
  $DJ['norma_referencia'] = trim((string)($_POST['norma_referencia'] ?? ($DJ['norma_referencia'] ?? '')));
  $detalleJson = json_encode($DJ, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

  // ✅ Data SOLO con placeholders reales (esto evita HY093)
  $data = [
    ':activo_id' => $activoId,
    ':plantilla_id' => (isset($_POST['plantilla_id']) && $_POST['plantilla_id'] !== '') ? (int)$_POST['plantilla_id'] : null,

    ':cert_formato' => $certFormato,
    ':numero_certificado' => $numeroCert !== '' ? $numeroCert : null,
    ':token_verificacion' => $token,

    ':tipo' => $tipo,
    ':estado' => $estado,

    ':fecha_programada' => $fechaProgramada,
    ':fecha_inicio' => $fechaInicio,
    ':fecha_fin' => $fechaFin,

    ':lugar' => trim((string)($_POST['lugar'] ?? '')),
    ':metodo' => trim((string)($_POST['metodo'] ?? '')),
    ':procedimiento_ref' => trim((string)($_POST['procedimiento_ref'] ?? '')),
    ':norma_ref' => trim((string)($_POST['norma_ref'] ?? '')),

    ':temperatura_c' => $temp,
    ':humedad_rel' => $hum,

    ':resultado_global' => $resultado,
    ':observaciones' => trim((string)($_POST['observaciones'] ?? '')),
    ':recomendaciones' => trim((string)($_POST['recomendaciones'] ?? '')),

    // Técnico SIEMPRE desde login
    ':tecnico_id' => $userId,
    ':tecnico_nombre' => $tecNombre,
    ':tecnico_cargo' => $tecCargo,
    ':tecnico_tarjeta_prof' => $tecTarjeta,

    ':recibido_por_nombre' => trim((string)($_POST['recibido_por_nombre'] ?? '')),
    ':recibido_por_cargo' => trim((string)($_POST['recibido_por_cargo'] ?? '')),

    ':detalle_json' => $detalleJson
  ];

  if ($error === '') {

    if ($id > 0) {

      $sql = "
        UPDATE calibraciones SET
          activo_id = :activo_id,
          plantilla_id = :plantilla_id,

          cert_formato = :cert_formato,
          numero_certificado = :numero_certificado,
          token_verificacion = :token_verificacion,

          tipo = :tipo,
          estado = :estado,

          fecha_programada = :fecha_programada,
          fecha_inicio = :fecha_inicio,
          fecha_fin = :fecha_fin,

          lugar = :lugar,
          metodo = :metodo,
          procedimiento_ref = :procedimiento_ref,
          norma_ref = :norma_ref,

          temperatura_c = :temperatura_c,
          humedad_rel = :humedad_rel,

          resultado_global = :resultado_global,
          observaciones = :observaciones,
          recomendaciones = :recomendaciones,

          tecnico_id = :tecnico_id,
          tecnico_nombre = :tecnico_nombre,
          tecnico_cargo = :tecnico_cargo,
          tecnico_tarjeta_prof = :tecnico_tarjeta_prof,

          recibido_por_nombre = :recibido_por_nombre,
          recibido_por_cargo = :recibido_por_cargo,

          detalle_json = :detalle_json
        WHERE id = :id AND tenant_id = :t AND COALESCE(eliminado,0)=0
        LIMIT 1
      ";
      $up = db()->prepare($sql);
      $data[':id'] = $id;
      $data[':t']  = $tenantId;
      $up->execute($data);

    } else {

      $sql = "
        INSERT INTO calibraciones (
          tenant_id, activo_id, plantilla_id,
          cert_formato, numero_certificado, token_verificacion,
          tipo, estado,
          fecha_programada, fecha_inicio, fecha_fin,
          lugar, metodo, procedimiento_ref, norma_ref,
          temperatura_c, humedad_rel,
          resultado_global, observaciones, recomendaciones,
          tecnico_id, tecnico_nombre, tecnico_cargo, tecnico_tarjeta_prof,
          recibido_por_nombre, recibido_por_cargo,
          detalle_json,
          creado_por
        ) VALUES (
          :t, :activo_id, :plantilla_id,
          :cert_formato, :numero_certificado, :token_verificacion,
          :tipo, :estado,
          :fecha_programada, :fecha_inicio, :fecha_fin,
          :lugar, :metodo, :procedimiento_ref, :norma_ref,
          :temperatura_c, :humedad_rel,
          :resultado_global, :observaciones, :recomendaciones,
          :tecnico_id, :tecnico_nombre, :tecnico_cargo, :tecnico_tarjeta_prof,
          :recibido_por_nombre, :recibido_por_cargo,
          :detalle_json,
          :u
        )
      ";
      $ins = db()->prepare($sql);
      $data[':t'] = $tenantId;
      $data[':u'] = $userId;
      $ins->execute($data);
      $id = (int)db()->lastInsertId();
    }

    /* =========================
       Guardar patrones
    ========================= */
    $patIds = $_POST['patrones'] ?? [];
    if (!is_array($patIds)) $patIds = [];
    $patIds = array_values(array_unique(array_map('intval', $patIds)));

    db()->prepare("DELETE FROM calibraciones_patrones WHERE tenant_id=:t AND calibracion_id=:c")
      ->execute([':t'=>$tenantId, ':c'=>$id]);

    if ($patIds) {
      $chk = db()->prepare("SELECT id FROM patrones WHERE tenant_id=:t AND id=:id AND COALESCE(eliminado,0)=0 LIMIT 1");
      $insP = db()->prepare("
        INSERT INTO calibraciones_patrones (tenant_id, calibracion_id, patron_id, uso, notas)
        VALUES (:t,:c,:p,:uso,:notas)
      ");
      $first = true;
      foreach ($patIds as $pid) {
        if ($pid <= 0) continue;
        $chk->execute([':t'=>$tenantId, ':id'=>$pid]);
        if (!$chk->fetch()) continue;

        $insP->execute([
          ':t'=>$tenantId,
          ':c'=>$id,
          ':p'=>$pid,
          ':uso'=> ($first ? 'PRINCIPAL' : 'SECUNDARIO'),
          ':notas'=> ($first ? 'Patrón principal seleccionado en la calibración.' : 'Patrón adicional seleccionado en la calibración.')
        ]);
        $first = false;
      }
    }

    /* =========================
       Guardar puntos
    ========================= */
    db()->prepare("DELETE FROM calibraciones_puntos WHERE tenant_id=:t AND calibracion_id=:c")
      ->execute([':t'=>$tenantId, ':c'=>$id]);

    $ord = $_POST['p_orden'] ?? [];
    $mag = $_POST['p_magnitud'] ?? [];
    $uni = $_POST['p_unidad'] ?? [];
    $ref = $_POST['p_ref'] ?? [];
    $eq  = $_POST['p_eq'] ?? [];
    $tol = $_POST['p_tol'] ?? [];

    if (is_array($ref)) {
      $insPt = db()->prepare("
        INSERT INTO calibraciones_puntos (
          tenant_id, calibracion_id, orden, magnitud, unidad,
          valor_referencia, valor_equipo, error, tolerancia, cumple
        ) VALUES (
          :t, :c, :o, :m, :u,
          :vr, :ve, :er, :to, :cu
        )
      ");

      $n = count($ref);
      for ($i=0; $i<$n; $i++) {
        $vr = trim((string)($ref[$i] ?? ''));
        $ve = trim((string)($eq[$i] ?? ''));
        if ($vr === '' && $ve === '') continue;

        $vrn = ($vr === '' ? null : (float)$vr);
        $ven = ($ve === '' ? null : (float)$ve);
        $ern = (is_null($vrn) || is_null($ven)) ? null : ($ven - $vrn);

        $ton = trim((string)($tol[$i] ?? ''));
        $ton = ($ton === '' ? null : (float)$ton);

        $cum = null;
        if (!is_null($ton) && !is_null($ern)) $cum = (abs($ern) <= abs($ton)) ? 1 : 0;

        $insPt->execute([
          ':t'=>$tenantId,
          ':c'=>$id,
          ':o'=>(int)($ord[$i] ?? ($i+1)),
          ':m'=>trim((string)($mag[$i] ?? '')),
          ':u'=>trim((string)($uni[$i] ?? '')),
          ':vr'=>$vrn,
          ':ve'=>$ven,
          ':er'=>$ern,
          ':to'=>$ton,
          ':cu'=>$cum
        ]);
      }
    }

    header('Location: '.base_url().'/index.php?route=calibraciones');
    exit;
  }
}
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';

$ajaxPatronPuntosUrl = rtrim(base_url(), '/').'/index.php?route=patron_puntos_ajax';
?>
<div class="card card-outline card-primary">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-ruler-combined"></i>
      <?= $id>0 ? 'Editar calibración' : 'Nueva calibración' ?>
    </h3>
  </div>

  <form method="post">
    <div class="card-body">

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e2($error) ?></div>
      <?php endif; ?>

      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label>Activo calibrable *</label>
            <select name="activo_id" class="form-control" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($activos as $a): ?>
                <?php
                  $aid = (int)$a['id'];
                  $txt = trim((string)$a['nombre']);
                  $sub = [];
                  if (!empty($a['codigo_interno'])) $sub[] = $a['codigo_interno'];
                  if (!empty($a['serial'])) $sub[] = 'S/N '.$a['serial'];
                  if (!empty($a['modelo'])) $sub[] = 'Modelo '.$a['modelo'];
                  if ($sub) $txt .= ' · '.implode(' · ', $sub);
                ?>
                <option value="<?= $aid ?>" <?= ((int)$cal['activo_id']===$aid)?'selected':'' ?>>
                  <?= e2($txt) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Aquí solo aparecen activos con <b>requiere calibración</b>.</small>
          </div>
        </div>

        <div class="col-md-2">
          <div class="form-group">
            <label>Formato certificado</label>
            <select name="cert_formato" class="form-control">
              <?php
                $fmts = ['general'=>'General','balanza'=>'Balanza','termometro'=>'Termómetro','manometro'=>'Manómetro','electrico'=>'Eléctrico'];
                $cur = strtolower(trim((string)($cal['cert_formato'] ?? 'general')));
                if (!isset($fmts[$cur])) $cur = 'general';
              ?>
              <?php foreach($fmts as $k=>$lab): ?>
                <option value="<?= e2($k) ?>" <?= $cur===$k?'selected':'' ?>><?= e2($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="col-md-2">
          <div class="form-group">
            <label>Tipo</label>
            <select name="tipo" class="form-control">
              <option value="INTERNA" <?= strtoupper($cal['tipo'])==='INTERNA'?'selected':'' ?>>INTERNA</option>
              <option value="EXTERNA" <?= strtoupper($cal['tipo'])==='EXTERNA'?'selected':'' ?>>EXTERNA</option>
            </select>
          </div>
        </div>

        <div class="col-md-2">
          <div class="form-group">
            <label>Estado</label>
            <?php $es = strtoupper(trim((string)$cal['estado'])); ?>
            <select name="estado" class="form-control">
              <option value="PROGRAMADA" <?= $es==='PROGRAMADA'?'selected':'' ?>>PROGRAMADA</option>
              <option value="EN_PROCESO" <?= $es==='EN_PROCESO'?'selected':'' ?>>EN PROCESO</option>
              <option value="CERRADA" <?= $es==='CERRADA'?'selected':'' ?>>CERRADA</option>
              <option value="ANULADA" <?= $es==='ANULADA'?'selected':'' ?>>ANULADA</option>
            </select>
          </div>
        </div>

        <div class="col-md-2">
          <div class="form-group">
            <label>Resultado</label>
            <?php $rg = strtoupper(trim((string)$cal['resultado_global'])); ?>
            <select name="resultado_global" class="form-control">
              <option value="CONFORME" <?= $rg==='CONFORME'?'selected':'' ?>>CONFORME</option>
              <option value="NO_CONFORME" <?= $rg==='NO_CONFORME'?'selected':'' ?>>NO CONFORME</option>
            </select>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-3">
          <div class="form-group">
            <label>Fecha programada *</label>
            <input type="date" name="fecha_programada" class="form-control" required
                   value="<?= e2(f10($cal['fecha_programada'])) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Inicio (opcional)</label>
            <input type="datetime-local" name="fecha_inicio" class="form-control"
                   value="<?= e2($cal['fecha_inicio'] ? str_replace(' ','T',substr((string)$cal['fecha_inicio'],0,16)) : '') ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Fin (opcional)</label>
            <input type="datetime-local" name="fecha_fin" class="form-control"
                   value="<?= e2($cal['fecha_fin'] ? str_replace(' ','T',substr((string)$cal['fecha_fin'],0,16)) : '') ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Lugar</label>
            <input type="text" name="lugar" class="form-control" value="<?= e2($cal['lugar']) ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-3">
          <div class="form-group">
            <label>Número certificado</label>
            <input type="text" name="numero_certificado" class="form-control" value="<?= e2($cal['numero_certificado']) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Token verificación</label>
            <input type="text" name="token_verificacion" class="form-control" value="<?= e2($cal['token_verificacion']) ?>">
            <small class="text-muted">Si lo dejas vacío, se genera automático.</small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Temperatura (°C)</label>
            <input type="number" step="0.01" name="temperatura_c" class="form-control" value="<?= e2($cal['temperatura_c']) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label>Humedad (%)</label>
            <input type="number" step="0.01" name="humedad_rel" class="form-control" value="<?= e2($cal['humedad_rel']) ?>">
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-3"><div class="form-group"><label>Método</label><input type="text" name="metodo" class="form-control" value="<?= e2($cal['metodo']) ?>"></div></div>
        <div class="col-md-3"><div class="form-group"><label>Procedimiento ref</label><input type="text" name="procedimiento_ref" class="form-control" value="<?= e2($cal['procedimiento_ref']) ?>"></div></div>
        <div class="col-md-3"><div class="form-group"><label>Norma ref</label><input type="text" name="norma_ref" class="form-control" value="<?= e2($cal['norma_ref']) ?>"></div></div>
        <div class="col-md-3"><div class="form-group"><label>Próxima calibración (extra)</label><input type="date" name="proxima_calibracion" class="form-control" value="<?= e2(isset($DJ['proxima_calibracion']) ? f10($DJ['proxima_calibracion']) : '') ?>"></div></div>
      </div>

      <div class="row">
        <div class="col-md-4"><div class="form-group"><label>Ubicación (extra)</label><input type="text" name="ubicacion" class="form-control" value="<?= e2($DJ['ubicacion'] ?? '') ?>"></div></div>
        <div class="col-md-4"><div class="form-group"><label>Condiciones ambientales (extra)</label><input type="text" name="condiciones_ambientales" class="form-control" value="<?= e2($DJ['condiciones_ambientales'] ?? '') ?>"></div></div>
        <div class="col-md-4"><div class="form-group"><label>Norma referencia (extra)</label><input type="text" name="norma_referencia" class="form-control" value="<?= e2($DJ['norma_referencia'] ?? '') ?>"></div></div>
      </div>

      <hr>

      <div class="d-flex align-items-center justify-content-between flex-wrap">
        <h5 class="mb-2"><i class="fas fa-balance-scale"></i> Patrones utilizados</h5>
        <button type="button" id="btnCargarPts" class="btn btn-sm btn-outline-primary mb-2" onclick="cargarPuntosPatron()">
          <i class="fas fa-download"></i> Cargar puntos desde patrón
        </button>
      </div>

      <div class="form-group">
        <select id="selPatrones" name="patrones[]" class="form-control" multiple size="10" style="height:260px; overflow:auto;">
          <?php foreach ($patrones as $p): ?>
            <?php
              $pid = (int)$p['id'];
              $t = (string)$p['nombre'];
              $sub = [];
              if (!empty($p['marca'])) $sub[] = $p['marca'];
              if (!empty($p['modelo'])) $sub[] = $p['modelo'];
              if (!empty($p['serial'])) $sub[] = 'S/N '.$p['serial'];
              $vig = !empty($p['certificado_vigencia_hasta']) ? substr((string)$p['certificado_vigencia_hasta'],0,10) : '';
              if ($vig) $sub[] = 'Vig: '.$vig;
              if ($sub) $t .= ' · '.implode(' · ', $sub);
            ?>
            <option value="<?= $pid ?>" <?= in_array($pid, $patSel, true) ? 'selected' : '' ?>>
              <?= e2($t) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <small class="text-muted">
          Selecciona uno o varios patrones (Ctrl/Shift). Para cargar puntos: selecciona al menos 1 y pulsa el botón.
          <br><b>Tip:</b> el sistema cargará los puntos del <u>primer patrón seleccionado</u>.
        </small>

        <div id="patronMsg" class="mt-2" style="display:none;"></div>
      </div>

      <hr>

      <h5 class="mb-2"><i class="fas fa-list-ol"></i> Puntos de calibración</h5>

      <div class="table-responsive">
        <table class="table table-sm table-hover" id="tblPts">
          <thead>
            <tr>
              <th style="width:70px">Orden</th>
              <th style="width:160px">Magnitud</th>
              <th style="width:90px">Unidad</th>
              <th style="width:160px">Referencia</th>
              <th style="width:160px">Equipo</th>
              <th style="width:160px">Tolerancia</th>
              <th style="width:70px" class="text-right">X</th>
            </tr>
          </thead>
          <tbody>
            <?php
              if (!$puntos) {
                $puntos = [
                  ['orden'=>1,'magnitud'=>'','unidad'=>'','valor_referencia'=>'','valor_equipo'=>'','tolerancia'=>'']
                ];
              }
            ?>
            <?php foreach ($puntos as $i => $pt): ?>
              <tr>
                <td><input class="form-control" name="p_orden[]" type="number" value="<?= e2($pt['orden'] ?? ($i+1)) ?>"></td>
                <td><input class="form-control" name="p_magnitud[]" value="<?= e2($pt['magnitud'] ?? '') ?>"></td>
                <td><input class="form-control" name="p_unidad[]" value="<?= e2($pt['unidad'] ?? '') ?>"></td>
                <td><input class="form-control" name="p_ref[]" type="number" step="0.0001" value="<?= e2($pt['valor_referencia'] ?? '') ?>"></td>
                <td><input class="form-control" name="p_eq[]" type="number" step="0.0001" value="<?= e2($pt['valor_equipo'] ?? '') ?>"></td>
                <td><input class="form-control" name="p_tol[]" type="number" step="0.0001" value="<?= e2($pt['tolerancia'] ?? '') ?>"></td>
                <td class="text-right">
                  <button type="button" class="btn btn-sm btn-outline-danger" onclick="rmPt(this)">
                    <i class="fas fa-times"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPt()">
        <i class="fas fa-plus"></i> Agregar punto
      </button>

      <hr>

      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label>Técnico (nombre)</label>
            <input class="form-control" value="<?= e2($tecNombre) ?>" readonly>
            <small class="text-muted">Se toma del usuario logueado.</small>
          </div>
        </div>
        <div class="col-md-4"><div class="form-group"><label>Técnico (cargo)</label><input class="form-control" value="<?= e2($tecCargo) ?>" readonly></div></div>
        <div class="col-md-4"><div class="form-group"><label>Técnico (tarjeta profesional)</label><input class="form-control" value="<?= e2($tecTarjeta) ?>" readonly></div></div>
      </div>

      <div class="row">
        <div class="col-md-6"><div class="form-group"><label>Recibido por (nombre)</label><input class="form-control" name="recibido_por_nombre" value="<?= e2($cal['recibido_por_nombre']) ?>"></div></div>
        <div class="col-md-6"><div class="form-group"><label>Recibido por (cargo)</label><input class="form-control" name="recibido_por_cargo" value="<?= e2($cal['recibido_por_cargo']) ?>"></div></div>
      </div>

      <div class="form-group"><label>Observaciones</label><textarea class="form-control" name="observaciones" rows="3"><?= e2($cal['observaciones']) ?></textarea></div>
      <div class="form-group"><label>Recomendaciones</label><textarea class="form-control" name="recomendaciones" rows="3"><?= e2($cal['recomendaciones']) ?></textarea></div>

    </div>

    <div class="card-footer">
      <button class="btn btn-primary" type="submit">
        <i class="fas fa-save"></i> Guardar
      </button>
      <a class="btn btn-secondary" href="<?= e2(base_url()) ?>/index.php?route=calibraciones">
        <i class="fas fa-arrow-left"></i> Volver
      </a>
    </div>
  </form>
</div>
<script>var AJAX_PATRON_PUNTOS_URL = <?= json_encode($ajaxPatronPuntosUrl) ?>;</script>
<script src="<?= e(base_url()) ?>/assets/js/calibracion-form.js"></script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

<?php
echo '<div class="alert alert-warning" style="margin:10px 15px;">';
echo '<b>DEBUG:</b> archivo=' . e2(__FILE__);
echo ' | DB=' . e2(db()->query("SELECT DATABASE()")->fetchColumn());
echo ' | tenantId=' . e2($tenantId);
echo ' | patrones=' . (int)count($patrones);
echo ' | activos=' . (int)count($activos);
echo '</div>';
?>
