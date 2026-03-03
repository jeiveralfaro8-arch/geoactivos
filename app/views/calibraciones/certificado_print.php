<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = (int)Auth::tenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }

/* =========================================================
   Helpers
========================================================= */
function e2($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_dt($d, $len=16){
  if(!$d) return '—';
  $s = (string)$d;
  return (strlen($s) >= $len) ? substr($s,0,$len) : $s;
}
function nvl($v, $dash='—'){
  if ($v === null) return $dash;
  $s = trim((string)$v);
  return ($s === '') ? $dash : $s;
}
/**
 * Convierte paths guardados en BD a URL pública.
 * - Si vienen con "public/", lo elimina.
 * - Respeta el estándar: public/uploads/firmas
 */
function public_url($relPath){
  $relPath = trim((string)$relPath);
  if ($relPath === '') return '';

  $relPath = str_replace('\\','/',$relPath);

  if (stripos($relPath, 'public/') === 0) {
    $relPath = substr($relPath, 7);
  }
  return rtrim(e2(base_url()),'/') . '/' . ltrim($relPath,'/');
}

/* =========================================================
   Helpers SQL robustos (evita columnas inexistentes)
========================================================= */
function db_name_current(){
  try {
    $q = db()->query("SELECT DATABASE() AS dbname");
    $r = $q ? $q->fetch() : null;
    return $r && !empty($r['dbname']) ? (string)$r['dbname'] : '';
  } catch (Throwable $e) {
    return '';
  }
}

function table_columns_cached($table){
  static $cache = [];
  $table = (string)$table;
  if (isset($cache[$table])) return $cache[$table];

  $cols = [];
  try{
    $dbn = db_name_current();
    if ($dbn === '') { $cache[$table] = $cols; return $cols; }

    $st = db()->prepare("
      SELECT COLUMN_NAME
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = :db
        AND TABLE_NAME = :t
    ");
    $st->execute([':db'=>$dbn, ':t'=>$table]);
    $rows = $st->fetchAll();
    foreach($rows as $r){
      if (!empty($r['COLUMN_NAME'])) $cols[(string)$r['COLUMN_NAME']] = true;
    }
  }catch(Throwable $e){
    $cols = [];
  }

  $cache[$table] = $cols;
  return $cols;
}

function col_exists($table, $col){
  $cols = table_columns_cached($table);
  return isset($cols[(string)$col]);
}

/**
 * select_if_exists (FIX)
 * - Permite prefijar con alias de tabla para evitar columnas ambiguas (nombre, etc)
 * - Ejemplo: select_if_exists('usuarios','nombre','u_tecnico_nombre',"''",'u')
 *   => u.nombre AS u_tecnico_nombre
 */
function select_if_exists($table, $col, $alias=null, $defaultExpr=null, $tblAlias=null){
  $alias = $alias ?: $col;

  $prefix = '';
  if ($tblAlias !== null && trim((string)$tblAlias) !== '') {
    $prefix = trim((string)$tblAlias) . '.';
  }

  if (col_exists($table, $col)) return "{$prefix}{$col} AS {$alias}";
  if ($defaultExpr !== null) return "{$defaultExpr} AS {$alias}";
  return "NULL AS {$alias}";
}

/* =========================================================
   Formatos permitidos
========================================================= */
$fmtWhitelist = array('general','balanza','termometro','manometro','electrico');

$FMT_LABELS = array(
  'general'    => 'General',
  'balanza'    => 'Balanza',
  'termometro' => 'Termómetro',
  'manometro'  => 'Manómetro',
  'electrico'  => 'Eléctrico'
);

/* =========================================================
   Empresa (Tenant)
   + LOGO (si existe) + SELLO (si existe)
========================================================= */
$tenant = null;
try {
  // Selección robusta: si columnas no existen, no rompe
  $sel = [];
  $sel[] = "t.id AS id";
  $sel[] = select_if_exists('tenants','nombre','nombre',"''",'t');
  $sel[] = select_if_exists('tenants','nit','nit',"''",'t');
  $sel[] = select_if_exists('tenants','email','email',"''",'t');
  $sel[] = select_if_exists('tenants','telefono','telefono',"''",'t');
  $sel[] = select_if_exists('tenants','direccion','direccion',"''",'t');
  $sel[] = select_if_exists('tenants','ciudad','ciudad',"''",'t');
  $sel[] = select_if_exists('tenants','logo_path','logo_path',"''",'t');
  $sel[] = select_if_exists('tenants','sello_path','sello_path',"''",'t');

  $tq = db()->prepare("
    SELECT ".implode(",\n           ", $sel)."
    FROM tenants t
    WHERE t.id=:t
    LIMIT 1
  ");
  $tq->execute([':t'=>$tenantId]);
  $tenant = $tq->fetch();
} catch (Throwable $e) { $tenant = null; }

$empresaNombre = $tenant && !empty($tenant['nombre']) ? (string)$tenant['nombre'] : '—';
$empresaNit    = $tenant && !empty($tenant['nit']) ? (string)$tenant['nit'] : '';
$empresaTel    = $tenant && !empty($tenant['telefono']) ? (string)$tenant['telefono'] : '';
$empresaEmail  = $tenant && !empty($tenant['email']) ? (string)$tenant['email'] : '';
$empresaDir    = $tenant && !empty($tenant['direccion']) ? (string)$tenant['direccion'] : '';
$empresaCiu    = $tenant && !empty($tenant['ciudad']) ? (string)$tenant['ciudad'] : '';

$logoUrl  = '';
$selloUrl = '';
if ($tenant) {
  if (!empty($tenant['logo_path']))  $logoUrl  = public_url($tenant['logo_path']);
  if (!empty($tenant['sello_path'])) $selloUrl = public_url($tenant['sello_path']);
}

/* =========================================================
   Calibración + Activo + Técnico (fallback desde usuarios)
========================================================= */
$usrSel = [];
$usrSel[] = select_if_exists('usuarios','nombre','u_tecnico_nombre',"''",'u');
$usrSel[] = select_if_exists('usuarios','cargo','u_tecnico_cargo',"''",'u');
$usrSel[] = select_if_exists('usuarios','tarjeta_profesional','u_tecnico_tp',"''",'u');
$usrSel[] = select_if_exists('usuarios','firma_path','u_firma_path',"''",'u');

$st = db()->prepare("
  SELECT
    c.*,
    a.codigo_interno,
    a.nombre AS activo_nombre,
    a.modelo AS activo_modelo,
    a.serial AS activo_serial,
    a.placa AS activo_placa,
    ar.nombre AS area_nombre,
    s.nombre AS sede_nombre,

    ".implode(",\n    ", $usrSel)."

  FROM calibraciones c
  LEFT JOIN activos a ON a.id=c.activo_id AND a.tenant_id=c.tenant_id
  LEFT JOIN areas ar ON ar.id=a.area_id AND ar.tenant_id=a.tenant_id
  LEFT JOIN sedes s ON s.id=ar.sede_id AND s.tenant_id=ar.tenant_id
  LEFT JOIN usuarios u ON u.id=c.tecnico_id AND u.tenant_id=c.tenant_id

  WHERE c.id=:id AND c.tenant_id=:t AND COALESCE(c.eliminado,0)=0
  LIMIT 1
");
$st->execute([':id'=>$id, ':t'=>$tenantId]);
$row = $st->fetch();

if (!$row) { http_response_code(404); echo "Calibración no encontrada"; exit; }

/* =========================================================
   Formato: GET o BD (cert_formato)
========================================================= */
$fmtGet = strtolower(trim((string)($_GET['fmt'] ?? '')));
$fmtDb  = strtolower(trim((string)($row['cert_formato'] ?? 'general')));

if (!in_array($fmtDb, $fmtWhitelist, true)) $fmtDb = 'general';

$fmt = $fmtDb;
if ($fmtGet !== '') {
  if (!in_array($fmtGet, $fmtWhitelist, true)) $fmtGet = 'general';
  $fmt = $fmtGet;
}

$setfmt = isset($_GET['setfmt']) ? (int)$_GET['setfmt'] : 0;
if ($setfmt === 1 && $fmtGet !== '' && $fmtGet !== $fmtDb) {
  try {
    $up = db()->prepare("
      UPDATE calibraciones
      SET cert_formato = :f
      WHERE id = :id AND tenant_id = :t AND COALESCE(eliminado,0)=0
      LIMIT 1
    ");
    $up->execute([':f'=>$fmtGet, ':id'=>$id, ':t'=>$tenantId]);

    $row['cert_formato'] = $fmtGet;
    $fmtDb = $fmtGet;
    $fmt = $fmtGet;
  } catch (Throwable $e) { }
}

$fmtLabel = isset($FMT_LABELS[$fmt]) ? $FMT_LABELS[$fmt] : 'General';

/* =========================================================
   Normalización de campos
========================================================= */
$cert  = trim((string)($row['numero_certificado'] ?? ''));
$token = trim((string)($row['token_verificacion'] ?? ''));

$activoCod  = trim((string)($row['codigo_interno'] ?? ''));
$activoNom  = trim((string)($row['activo_nombre'] ?? ''));

$sede = trim((string)($row['sede_nombre'] ?? ''));
$area = trim((string)($row['area_nombre'] ?? ''));
$ubic = '—';
if ($sede !== '' && $area !== '') $ubic = $sede.' - '.$area;
elseif ($sede !== '') $ubic = $sede;
elseif ($area !== '') $ubic = $area;

$tipo = strtoupper(trim((string)($row['tipo'] ?? '')));
$estado = strtoupper(trim((string)($row['estado'] ?? '')));
$resultado = strtoupper(trim((string)($row['resultado_global'] ?? '')));

$toneEstado = 'info';
if ($estado === 'CERRADA') $toneEstado = 'ok';
else if ($estado === 'EN_PROCESO') $toneEstado = 'warn';
else if ($estado === 'ANULADA') $toneEstado = 'bad';

$toneRes = 'info';
if ($resultado === 'CONFORME') $toneRes = 'ok';
else if ($resultado === 'NO_CONFORME') $toneRes = 'bad';

function badge_chip($text, $tone){
  $text = trim((string)$text);
  if ($text === '') $text = '—';

  $cls = 'chip';
  if ($tone === 'ok') $cls .= ' chip-ok';
  else if ($tone === 'warn') $cls .= ' chip-warn';
  else if ($tone === 'bad') $cls .= ' chip-bad';
  else if ($tone === 'info') $cls .= ' chip-info';

  return "<span class='{$cls}'>".htmlspecialchars($text,ENT_QUOTES,'UTF-8')."</span>";
}

/* =========================================================
   Técnico (fallback)
========================================================= */
$tecNombre = trim((string)($row['tecnico_nombre'] ?? ''));
$tecCargo  = trim((string)($row['tecnico_cargo'] ?? ''));
$tecTP     = trim((string)($row['tecnico_tarjeta_prof'] ?? ''));

if ($tecNombre === '') $tecNombre = trim((string)($row['u_tecnico_nombre'] ?? ''));
if ($tecCargo  === '') $tecCargo  = trim((string)($row['u_tecnico_cargo'] ?? ''));
if ($tecTP     === '') $tecTP     = trim((string)($row['u_tecnico_tp'] ?? ''));

$firmaPath = trim((string)($row['u_firma_path'] ?? ''));
$firmaUrl  = $firmaPath ? public_url($firmaPath) : '';

/* =========================================================
   Patrones asociados (robusto)
   - Evita inventar columnas: si no existen, salen como NULL
========================================================= */
$patrones = [];
try{

  // SELECT robusto (prefijo p.)
  $patSel = [];
  $patSel[] = "p.id";
  $patSel[] = select_if_exists('patrones','codigo','patron_codigo',"NULL",'p'); // si no existe, NULL
  $patSel[] = "p.nombre AS patron_nombre";
  $patSel[] = select_if_exists('patrones','marca','marca',"''",'p');
  $patSel[] = select_if_exists('patrones','modelo','modelo',"''",'p');
  $patSel[] = select_if_exists('patrones','serial','serial',"''",'p');
  $patSel[] = select_if_exists('patrones','magnitudes','magnitudes',"''",'p');
  $patSel[] = select_if_exists('patrones','rango','rango',"''",'p');
  $patSel[] = select_if_exists('patrones','resolucion','resolucion',"''",'p');

  $patSel[] = select_if_exists('patrones','certificado_numero','certificado_numero',"''",'p');
  $patSel[] = select_if_exists('patrones','certificado_num','certificado_num',"''",'p');
  $patSel[] = select_if_exists('patrones','certificado_emisor','certificado_emisor',"''",'p');

  $patSel[] = select_if_exists('patrones','certificado_fecha','certificado_fecha',"NULL",'p');
  $patSel[] = select_if_exists('patrones','fecha_certificado','fecha_certificado',"NULL",'p');

  $patSel[] = select_if_exists('patrones','certificado_vigencia_hasta','certificado_vigencia_hasta',"NULL",'p');
  $patSel[] = select_if_exists('patrones','fecha_vencimiento','fecha_vencimiento',"NULL",'p');

  $patSel[] = select_if_exists('patrones','estado','estado',"''",'p');

  $ps = db()->prepare("
    SELECT
      ".implode(",\n      ", $patSel).",
      cp.uso,
      cp.notas
    FROM calibraciones_patrones cp
    INNER JOIN patrones p
      ON p.id = cp.patron_id
     AND p.tenant_id = cp.tenant_id
    WHERE cp.tenant_id = :t
      AND cp.calibracion_id = :c
    ORDER BY cp.uso ASC, p.nombre ASC
  ");

  $ps->execute([':t'=>$tenantId, ':c'=>$id]);
  $patrones = $ps->fetchAll();
}catch(Throwable $e){
  $patrones = [];
}

/* =========================================================
   Puntos / resultados
========================================================= */
$puntos = [];
try{
  $qs = db()->prepare("
    SELECT
      id, orden, magnitud, unidad,
      punto_nominal, lectura_equipo, lectura_patron,
      error_abs, error_rel, tolerancia, conforme,
      incertidumbre_expandida, k, notas
    FROM calibraciones_puntos
    WHERE tenant_id = :t AND calibracion_id = :c
    ORDER BY magnitud ASC, orden ASC, id ASC
  ");
  $qs->execute([':t'=>$tenantId, ':c'=>$id]);
  $puntos = $qs->fetchAll();
}catch(Throwable $e){
  $puntos = [];
}

/* =========================================================
   FASE 2 – VALIDACIÓN Y FILTRO DEFINITIVO DE PUNTOS
========================================================= */
// ✅ Asegurar que existan siempre (evita Undefined variable)
$puntos_validos = [];
$blocked = false;
$validation = [
  'warnings' => [],
  'errors'   => []
];

/* Reglas por formato de certificado */
$formatRules = [
  'electrico'  => ['Voltaje DC','Voltaje AC','Corriente','Resistencia','Frecuencia','Continuidad'],
  'balanza'    => ['Masa'],
  'termometro' => ['Temperatura'],
  'manometro'  => ['Presión'],
  'general'    => []
];

foreach ($puntos as $pt) {

  $mag = trim((string)($pt['magnitud'] ?? ''));

  /* Regla 1: magnitud válida por formato */
  if (!empty($formatRules[$fmt]) && !in_array($mag, $formatRules[$fmt], true)) {
    $validation['warnings'][] =
      "Magnitud '{$mag}' no permitida para formato {$fmtLabel}.";
    continue; // ❌ NO entra al certificado
  }

  /* Regla 2: lecturas obligatorias (solo si el formato NO es balanza con nominal 0)
     Nota: si quieres permitir filas nominal=0 sin lecturas, dime y lo ajusto.
  */
  if ($pt['lectura_equipo'] === null || $pt['lectura_patron'] === null) {
    $validation['errors'][] =
      "Punto {$pt['orden']} ({$mag}) sin lecturas completas.";
    $blocked = true;
    continue;
  }

  /* Regla 3: tolerancia */
  if (
    $pt['error_abs'] !== null &&
    $pt['tolerancia'] !== null &&
    abs((float)$pt['error_abs']) > abs((float)$pt['tolerancia'])
  ) {
    $validation['warnings'][] =
      "Punto {$pt['orden']} ({$mag}) fuera de tolerancia.";
  }

  // ✅ ESTE es el arreglo final
  $puntos_validos[] = $pt;
}

// ✅ Reemplazar puntos por los válidos (esto afecta TODO el certificado)
$puntos = $puntos_validos;

/* =========================================================
   PASO 3: RESULTADO GLOBAL AUTOMÁTICO SEGÚN PUNTOS
========================================================= */
$calcResultado = '—';

if ($blocked) {
  $calcResultado = 'NO_EMITIBLE';
} else if (!$puntos_validos) {
  $calcResultado = 'SIN_PUNTOS';
} else {
  $allOk = true;
  foreach ($puntos_validos as $pt2) {
    if (isset($pt2['conforme']) && (int)$pt2['conforme'] === 0) {
      $allOk = false;
      break;
    }
  }
  $calcResultado = $allOk ? 'CONFORME' : 'NO_CONFORME';
}

// ✅ Forzar el resultado mostrado en el certificado
$resultado = $calcResultado;

// ✅ Ajustar color del chip según el resultado calculado
$toneRes = 'info';
if ($resultado === 'CONFORME') $toneRes = 'ok';
else if ($resultado === 'NO_CONFORME' || $resultado === 'NO_EMITIBLE') $toneRes = 'bad';

/* =========================================================
   URL de verificación (robusta)
========================================================= */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$base   = $scheme . '://' . $host;

$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/'); // /geoactivos/public
$baseReal  = $base . $scriptDir;

// Si base_url() ya retorna con subcarpeta, úsalo para consistencia
$baseFromApp = rtrim((string)base_url(),'/');
if ($baseFromApp !== '') {
  $verifyUrl = $baseFromApp . "/index.php?route=calibracion_verificar&token=" . urlencode($token);
} else {
  $verifyUrl = $baseReal . "/index.php?route=calibracion_verificar&token=" . urlencode($token);
}

$backUrl = e2(base_url()) . "/index.php?route=calibracion_detalle&id=".(int)$row['id'];

/* =========================================================
   URL base + links de formato
========================================================= */
$selfUrlBase = $_SERVER['REQUEST_URI'];
$selfUrlBase = preg_replace('/([&?])fmt=[^&]+/', '$1', $selfUrlBase);
$selfUrlBase = preg_replace('/([&?])setfmt=[^&]+/', '$1', $selfUrlBase);
$selfUrlBase = rtrim($selfUrlBase, '&?');

function fmt_link($base, $fmt){
  $u = $base . (strpos($base, '?') !== false ? '&' : '?') . 'fmt=' . urlencode($fmt) . '&setfmt=1';
  return $u;
}

/* =========================================================
   Utilidades para puntos (formato)
========================================================= */
function pick_error($pt){
  if (!is_array($pt)) return '—';
  if (isset($pt['error_abs']) && $pt['error_abs'] !== null && $pt['error_abs'] !== '') return (string)$pt['error_abs'];
  if (isset($pt['error_rel']) && $pt['error_rel'] !== null && $pt['error_rel'] !== '') return (string)$pt['error_rel'];
  return '—';
}
function pill_conforme($pt){
  $conf = isset($pt['conforme']) ? (int)$pt['conforme'] : -1;
  if ($conf === 1) return "<span class='pill ok'>SI</span>";
  if ($conf === 0) return "<span class='pill bad'>NO</span>";
  return "<span class='pill'>—</span>";
}

/* =======================================================================
   EJEMPLOS PRO POR FORMATO (OPCIÓN B)
======================================================================= */
$showExamples = isset($_GET['ej']) ? (int)$_GET['ej'] : 0;

/* Mini-tablas de ejemplo por formato (demo) */
$FMT_EXAMPLES_PRO = array(
  'general' => array(
    'titulo' => 'Plantilla recomendada (General)',
    'bullets' => array(
      "Encabezado: empresa, NIT, certificado, token y estado.",
      "Equipo: código, nombre, serial, ubicación.",
      "Condiciones: método, norma, temperatura/humedad.",
      "Trazabilidad: patrones, certificados y vencimientos.",
      "Resultados: tabla de puntos y conclusión.",
      "Verificación: URL + QR."
    ),
    'tabla_html' => "
      <table class='etable'>
        <thead>
          <tr>
            <th style='width:10%'>Orden</th>
            <th style='width:18%'>Magnitud</th>
            <th style='width:10%'>Unidad</th>
            <th style='width:14%'>Nominal</th>
            <th style='width:14%'>Equipo</th>
            <th style='width:14%'>Patrón</th>
            <th style='width:10%'>Error</th>
            <th style='width:10%'>OK</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>1</td><td>General</td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td><td><span class='pill ok'>SI</span></td></tr>
          <tr><td>2</td><td>General</td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td><td><span class='pill ok'>SI</span></td></tr>
        </tbody>
      </table>"
  ),
  'balanza' => array(
    'titulo' => 'Ejemplo PRO (Balanza)',
    'bullets' => array(
      "Mostrar por magnitud/carga: nominal, equipo, patrón.",
      "Error: abs/rel (según aplique).",
      "Tolerancia, incertidumbre expandida U y factor k.",
      "Conforme SI/NO por punto + notas si aplica."
    ),
    'tabla_html' => "
      <table class='etable'>
        <thead>
          <tr>
            <th style='width:8%'>Ord</th>
            <th style='width:10%'>Unidad</th>
            <th style='width:14%'>Nominal</th>
            <th style='width:14%'>Equipo</th>
            <th style='width:14%'>Patrón</th>
            <th style='width:12%'>Error</th>
            <th style='width:12%'>Tol</th>
            <th style='width:10%'>U</th>
            <th style='width:6%'>k</th>
            <th style='width:10%'>OK</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>1</td><td>g</td><td>0</td><td>0.01</td><td>0.00</td><td>0.01</td><td>±0.05</td><td>0.02</td><td>2</td><td><span class='pill ok'>SI</span></td></tr>
          <tr><td>2</td><td>g</td><td>500</td><td>500.03</td><td>500.00</td><td>0.03</td><td>±0.10</td><td>0.05</td><td>2</td><td><span class='pill ok'>SI</span></td></tr>
          <tr><td>3</td><td>g</td><td>1000</td><td>1000.15</td><td>1000.00</td><td>0.15</td><td>±0.10</td><td>0.05</td><td>2</td><td><span class='pill bad'>NO</span></td></tr>
        </tbody>
      </table>"
  ),
  'termometro' => array(
    'titulo' => 'Ejemplo PRO (Termómetro)',
    'bullets' => array(
      "Puntos típicos según rango (p.ej.: 0°C, 25°C, 80°C).",
      "Mostrar error (°C), tolerancia, U y k.",
      "Conforme por punto (y conclusión global)."
    ),
    'tabla_html' => "
      <table class='etable'>
        <thead>
          <tr>
            <th style='width:8%'>Ord</th>
            <th style='width:16%'>Magnitud</th>
            <th style='width:10%'>Unidad</th>
            <th style='width:14%'>Nominal</th>
            <th style='width:14%'>Equipo</th>
            <th style='width:14%'>Patrón</th>
            <th style='width:12%'>Error</th>
            <th style='width:12%'>Tol</th>
            <th style='width:10%'>U</th>
            <th style='width:6%'>k</th>
            <th style='width:10%'>OK</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>1</td><td>Temperatura</td><td>°C</td><td>0.0</td><td>0.2</td><td>0.0</td><td>0.2</td><td>±0.5</td><td>0.2</td><td>2</td><td><span class='pill ok'>SI</span></td></tr>
          <tr><td>2</td><td>Temperatura</td><td>°C</td><td>25.0</td><td>25.3</td><td>25.0</td><td>0.3</td><td>±0.5</td><td>0.2</td><td>2</td><td><span class='pill ok'>SI</span></td></tr>
          <tr><td>3</td><td>Temperatura</td><td>°C</td><td>80.0</td><td>80.7</td><td>80.0</td><td>0.7</td><td>±0.5</td><td>0.2</td><td>2</td><td><span class='pill bad'>NO</span></td></tr>
        </tbody>
      </table>"
  ),
  'manometro' => array(
    'titulo' => 'Ejemplo PRO (Manómetro)',
    'bullets' => array(
      "Verificar cero, punto medio y punto alto del rango.",
      "Mostrar error, tolerancia, U y k.",
      "Registrar unidad (psi / bar / kPa) y condiciones."
    ),
    'tabla_html' => "
      <table class='etable'>
        <thead>
          <tr>
            <th style='width:8%'>Ord</th>
            <th style='width:16%'>Magnitud</th>
            <th style='width:10%'>Unidad</th>
            <th style='width:14%'>Nominal</th>
            <th style='width:14%'>Equipo</th>
            <th style='width:14%'>Patrón</th>
            <th style='width:12%'>Error</th>
            <th style='width:12%'>Tol</th>
            <th style='width:10%'>U</th>
            <th style='width:6%'>k</th>
            <th style='width:10%'>OK</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>1</td><td>Presión</td><td>psi</td><td>0</td><td>0.2</td><td>0.0</td><td>0.2</td><td>±1.0</td><td>0.5</td><td>2</td><td><span class='pill ok'>SI</span></td></tr>
          <tr><td>2</td><td>Presión</td><td>psi</td><td>50</td><td>50.8</td><td>50.0</td><td>0.8</td><td>±1.0</td><td>0.5</td><td>2</td><td><span class='pill ok'>SI</span></td></tr>
          <tr><td>3</td><td>Presión</td><td>psi</td><td>100</td><td>102.2</td><td>100.0</td><td>2.2</td><td>±1.0</td><td>0.5</td><td>2</td><td><span class='pill bad'>NO</span></td></tr>
        </tbody>
      </table>"
  ),
  'electrico' => array(
    'titulo' => 'Ejemplo PRO (Eléctrico)',
    'bullets' => array(
      "Magnitud puede variar: V / A / Ω / Hz.",
      "Mostrar nominal, equipo, patrón, error.",
      "Registrar tolerancia, U y k.",
      "En notas: puntas, rango, configuración."
    ),
    'tabla_html' => "
      <table class='etable'>
        <thead>
          <tr>
            <th style='width:8%'>Ord</th>
            <th style='width:16%'>Magnitud</th>
            <th style='width:10%'>Unidad</th>
            <th style='width:14%'>Nominal</th>
            <th style='width:14%'>Equipo</th>
            <th style='width:14%'>Patrón</th>
            <th style='width:12%'>Error</th>
            <th style='width:12%'>Tol</th>
            <th style='width:10%'>U</th>
            <th style='width:6%'>k</th>
            <th style='width:10%'>OK</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>1</td><td>Voltaje DC</td><td>V</td><td>5.00</td><td>5.03</td><td>5.00</td><td>0.03</td><td>±0.10</td><td>0.05</td><td>2</td><td><span class='pill ok'>SI</span></td></tr>
          <tr><td>2</td><td>Voltaje DC</td><td>V</td><td>12.00</td><td>12.18</td><td>12.00</td><td>0.18</td><td>±0.10</td><td>0.05</td><td>2</td><td><span class='pill bad'>NO</span></td></tr>
        </tbody>
      </table>"
  ),
);

function ga_examples_render_pro($fmt, $fmtLabel, $examples){
  if (!isset($examples[$fmt])) return '';
  $ex = $examples[$fmt];

  $title = isset($ex['titulo']) ? (string)$ex['titulo'] : ('Ejemplo (' . $fmtLabel . ')');
  $bullets = (isset($ex['bullets']) && is_array($ex['bullets'])) ? $ex['bullets'] : array();
  $table = isset($ex['tabla_html']) ? (string)$ex['tabla_html'] : '';

  $out  = "<div class='wide note examples'>";
  $out .= "  <h3>Ejemplos por formato · " . htmlspecialchars($fmtLabel, ENT_QUOTES, 'UTF-8') . "</h3>";
  $out .= "  <div class='text'>";
  $out .= "    <div class='exgrid'>";

  $out .= "      <div class='exbox'>";
  $out .= "        <div class='extitle'>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</div>";
  $out .= "        <div class='exhint'>Guía interna (no altera tu certificado). Visible solo con <b>?ej=1</b>.</div>";
  if ($bullets){
    $out .= "        <ul class='exlist'>";
    foreach($bullets as $b){
      $out .= "          <li>" . htmlspecialchars((string)$b, ENT_QUOTES, 'UTF-8') . "</li>";
    }
    $out .= "        </ul>";
  } else {
    $out .= "        <div class='exhint'>Sin recomendaciones definidas para este formato.</div>";
  }
  $out .= "      </div>";

  $out .= "      <div class='exbox exbox-white'>";
  $out .= "        <div class='extitle'>Mini-tabla ejemplo</div>";
  $out .= "        <div class='exhint'>Valores demo para visualizar el formato recomendado.</div>";
  $out .=          $table;
  $out .= "      </div>";

  $out .= "    </div>";
  $out .= "  </div>";
  $out .= "</div>";

  return $out;
}

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificado de calibración #<?= (int)$row['id'] ?> · <?= e2($fmtLabel) ?></title>

<!-- TODO: TU HTML/CSS/JS QUEDA IGUAL AL TUYO DESDE AQUÍ -->
<style>
  :root{
    --ink:#0f172a;
    --muted:#475569;
    --line:#e2e8f0;
    --bg:#ffffff;
    --soft:#f8fafc;
    --soft2:#f1f5f9;
    --accent:#2563eb;
    --ok:#16a34a;
    --warn:#d97706;
    --bad:#dc2626;
  }
  *{ box-sizing:border-box; }
  body{ margin:0; font-family:Arial, Helvetica, sans-serif; color:var(--ink); background:var(--bg); }
  .page{ max-width:980px; margin:18px auto; padding:0 12px 24px; }

  .toolbar{ display:flex; gap:8px; justify-content:space-between; align-items:center; margin:10px 0 14px; flex-wrap:wrap; }
  .toolbar-left{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .toolbar-right{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }

  .btn{
    border:1px solid var(--line);
    background:var(--soft);
    padding:9px 12px;
    border-radius:10px;
    cursor:pointer;
    font-size:13px;
    color:var(--ink);
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:8px;
  }
  .btn:hover{ background:var(--soft2); }
  .btn-primary{ background:var(--accent); border-color:var(--accent); color:#fff; }
  .btn-primary:hover{ filter:brightness(.95); }

  .fmtbar{ display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
  .fmtlabel{ font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; font-weight:800; margin-right:4px; }
  .fmtpill{
    border:1px solid var(--line);
    background:#fff;
    border-radius:999px;
    padding:6px 10px;
    font-size:12px;
    font-weight:800;
    color:var(--ink);
    text-decoration:none;
  }
  .fmtpill:hover{ background:var(--soft); }
  .fmtpill.active{ background:rgba(37,99,235,.10); border-color:rgba(37,99,235,.35); color:#0b2f8a; }

  .sheet{ border:1px solid var(--line); border-radius:14px; overflow:hidden; }

  .header{
    padding:16px 18px;
    background:linear-gradient(135deg, #0b1220 0%, #111827 45%, #0b1220 100%);
    color:#fff;
    position:relative;
  }
  .topline{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap; }
  .brand{ display:flex; gap:12px; align-items:center; }
  .logo{
    width:46px; height:46px;
    border-radius:14px;
    background:rgba(255,255,255,.10);
    border:1px solid rgba(255,255,255,.18);
    display:flex; align-items:center; justify-content:center;
    font-weight:900; letter-spacing:.06em;
    overflow:hidden;
  }
  .logo img{ max-width:100%; max-height:100%; display:block; }
  .title{ font-size:18px; font-weight:900; margin:0; line-height:1.2; text-transform:uppercase; }
  .subtitle{ margin-top:4px; font-size:12px; opacity:.85; }

  .chips{ display:flex; gap:6px; flex-wrap:wrap; margin-top:10px; }
  .chip{
    display:inline-flex; align-items:center;
    padding:6px 10px; border-radius:999px; font-size:12px;
    border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.08);
    color:#fff; white-space:nowrap;
  }
  .chip-ok{ background:rgba(22,163,74,.20); border-color:rgba(22,163,74,.35); }
  .chip-warn{ background:rgba(217,119,6,.20); border-color:rgba(217,119,6,.35); }
  .chip-bad{ background:rgba(220,38,38,.20); border-color:rgba(220,38,38,.35); }
  .chip-info{ background:rgba(37,99,235,.20); border-color:rgba(37,99,235,.35); }

  .meta{ text-align:right; min-width:280px; }
  .meta .mrow{ font-size:12px; opacity:.95; margin:2px 0; }
  .meta .mrow b{ color:#fff; }

  .body{ padding:16px 18px 18px; background:#fff; }

  .grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .card{ border:1px solid var(--line); border-radius:14px; padding:12px 14px; background:var(--soft); }
  .card h3{ margin:0 0 10px; font-size:13px; letter-spacing:.02em; text-transform:uppercase; color:var(--muted); }

  .kv{ display:flex; justify-content:space-between; gap:10px; padding:8px 0; border-bottom:1px dashed #dbe2ea; }
  .kv:last-child{ border-bottom:0; }
  .k{ font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; }
  .v{ font-size:13px; font-weight:800; text-align:right; }

  .wide{ margin-top:12px; }
  .note{ border:1px solid var(--line); border-radius:14px; background:#fff; padding:12px 14px; }
  .note h3{ margin:0 0 8px; font-size:13px; letter-spacing:.02em; text-transform:uppercase; color:var(--muted); }
  .text{ font-size:13px; color:var(--ink); line-height:1.4; }
  .text .muted{ color:var(--muted); }

  .table{
    width:100%; border-collapse:collapse; font-size:12px;
    margin-top:8px; background:#fff;
    border:1px solid var(--line);
    border-radius:12px; overflow:hidden;
  }
  .table th, .table td{ border-bottom:1px solid var(--line); padding:8px 10px; vertical-align:top; }
  .table th{ background:var(--soft); color:var(--muted); text-transform:uppercase; letter-spacing:.04em; font-size:11px; text-align:left; }
  .table tr:last-child td{ border-bottom:0; }

  .pill{ display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:800; border:1px solid var(--line); background:var(--soft); }
  .pill.ok{ border-color: rgba(22,163,74,.35); background: rgba(22,163,74,.10); color:#0b6b2a; }
  .pill.bad{ border-color: rgba(220,38,38,.35); background: rgba(220,38,38,.10); color:#8a1212; }

  .verify{ display:flex; justify-content:space-between; gap:12px; align-items:stretch; margin-top:12px; }
  .verify .left{ flex:1; border:1px solid var(--line); border-radius:14px; padding:12px 14px; background:var(--soft); }
  .verify .left h3{ margin:0 0 6px; font-size:13px; text-transform:uppercase; color:var(--muted); }
  .verify .url{ font-size:12px; color:var(--ink); word-break:break-all; background:#fff; border:1px solid var(--line); border-radius:10px; padding:8px 10px; margin-top:8px; }

  .qr{
    width:220px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fff;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:12px;
    gap:8px;
  }
  .qrbox{
    width:200px;
    height:200px;
    background:#fff;
    padding:10px;
    border-radius:12px;
    border:1px dashed #cbd5e1;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  #qr img, #qr canvas{
    width:200px !important;
    height:200px !important;
    display:block;
  }
  .qrhint{ font-size:11px; color:var(--muted); text-align:center; }

  .sign{ margin-top:12px; display:grid; grid-template-columns:1fr 1fr; gap:12px; align-items:stretch; }
  .signbox{ border:1px solid var(--line); border-radius:14px; padding:12px 14px; background:var(--soft); }
  .signbox h3{ margin:0 0 10px; font-size:13px; text-transform:uppercase; color:var(--muted); }
  .sigimg{
    margin-top:10px; border:1px dashed #cbd5e1; border-radius:12px; background:#fff;
    height:110px; display:flex; align-items:center; justify-content:center; overflow:hidden;
    color:#64748b; font-size:12px; text-align:center; padding:10px;
  }
  .sigimg img{ max-height:100%; max-width:100%; display:block; }
  .sigline{ margin-top:10px; border-top:1px solid #0f172a; padding-top:6px; font-size:12px; color:var(--muted); }

  .footer{ margin-top:10px; font-size:11px; color:var(--muted); }

  .examples{ background:linear-gradient(180deg, #ffffff 0%, #fbfdff 100%); border-color:#dbe7ff; }
  .exgrid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .exbox{ border:1px solid #e6eefc; background:rgba(37,99,235,.05); border-radius:12px; padding:12px; }
  .exbox-white{ background:#fff; border:1px dashed #cbd5e1; }
  .extitle{ font-weight:900; margin-bottom:6px; }
  .exhint{ font-size:12px; color:var(--muted); margin-bottom:8px; line-height:1.35; }
  .exlist{ margin:0; padding-left:18px; font-size:12px; color:#0f172a; }
  .exlist li{ margin:6px 0; }
  .etable{ width:100%; border-collapse:collapse; font-size:12px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
  .etable th, .etable td{ border-bottom:1px solid #e2e8f0; padding:8px 10px; vertical-align:top; }
  .etable th{ background:#f8fafc; color:#475569; text-transform:uppercase; letter-spacing:.04em; font-size:11px; text-align:left; }
  .etable tr:last-child td{ border-bottom:0; }
  @media (max-width: 820px){
    .exgrid{ grid-template-columns:1fr; }
  }

  @media print{
    body{ background:#fff; }
    .toolbar{ display:none; }
    .page{ margin:0; padding:0; max-width:none; }
    .sheet{ border:0; border-radius:0; }
    .header{ border-radius:0; }
    .card, .note, .signbox, .verify .left, .qr{ break-inside:avoid; page-break-inside:avoid; }
    .examples{ display:none !important; }
  }
</style>
</head>

<body>
<div class="page">

  <div class="toolbar">
    <div class="toolbar-left">
      <a class="btn" href="<?= $backUrl ?>">← Volver</a>

      <div class="fmtbar">
        <span class="fmtlabel">Formato:</span>
        <a class="fmtpill <?= ($fmt==='general'?'active':'') ?>" href="<?= e2(fmt_link($selfUrlBase,'general')) ?>">General</a>
        <a class="fmtpill <?= ($fmt==='balanza'?'active':'') ?>" href="<?= e2(fmt_link($selfUrlBase,'balanza')) ?>">Balanza</a>
        <a class="fmtpill <?= ($fmt==='termometro'?'active':'') ?>" href="<?= e2(fmt_link($selfUrlBase,'termometro')) ?>">Termómetro</a>
        <a class="fmtpill <?= ($fmt==='manometro'?'active':'') ?>" href="<?= e2(fmt_link($selfUrlBase,'manometro')) ?>">Manómetro</a>
        <a class="fmtpill <?= ($fmt==='electrico'?'active':'') ?>" href="<?= e2(fmt_link($selfUrlBase,'electrico')) ?>">Eléctrico</a>
      </div>
    </div>

    <div class="toolbar-right">
<?php if ($blocked): ?>
  <button class="btn btn-primary" disabled
          style="opacity:.55; cursor:not-allowed;"
          title="No se puede imprimir: hay errores críticos en puntos">
    🖨️ Imprimir / Guardar PDF
  </button>
<?php else: ?>
  <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
<?php endif; ?>
      <button class="btn" onclick="window.close()">✖ Cerrar</button>
    </div>
  </div>

  <div class="sheet">

    <div class="header">
      <div class="topline">
        <div class="brand">
          <div class="logo">
            <?php if ($logoUrl !== ''): ?>
              <img src="<?= e2($logoUrl) ?>?v=<?= urlencode((string)time()) ?>" alt="Logo">
            <?php else: ?>
              GA
            <?php endif; ?>
          </div>
          <div>
            <div class="title">Certificado de calibración</div>
            <div class="subtitle"><?= e2($empresaNombre) ?><?= $empresaNit!=='' ? ' · NIT: '.e2($empresaNit) : '' ?></div>
            <div class="chips">
              <?= badge_chip($tipo ?: '—', 'info') ?>
              <?= badge_chip("FORMATO: ".$fmtLabel, 'info') ?>
              <?= badge_chip($estado ?: '—', $toneEstado) ?>
              <?= badge_chip($resultado ?: '—', $toneRes) ?>
            </div>
          </div>
        </div>

        <div class="meta">
          <div class="mrow"><b>Certificado:</b> <?= e2($cert ?: '—') ?></div>
          <div class="mrow"><b>Token:</b> <?= e2($token ?: '—') ?></div>
          <div class="mrow"><b>ID interno:</b> #<?= (int)$row['id'] ?></div>
          <div class="mrow"><b>Formato guardado:</b> <?= e2(isset($FMT_LABELS[$fmtDb]) ? $FMT_LABELS[$fmtDb] : 'General') ?></div>
          <div class="mrow"><b>Formato actual:</b> <?= e2($fmtLabel) ?></div>
          <div class="mrow" style="margin-top:6px;opacity:.9;">
            <?= $empresaCiu!=='' ? e2($empresaCiu).' · ' : '' ?><?= $empresaDir!=='' ? e2($empresaDir) : '' ?><br>
            <?= $empresaTel!=='' ? 'Tel: '.e2($empresaTel).' · ' : '' ?><?= $empresaEmail!=='' ? 'Email: '.e2($empresaEmail) : '' ?>
          </div>
        </div>
      </div>
    </div>

    <div class="body">

      <div class="grid">
        <div class="card">
          <h3>Datos del equipo</h3>
          <div class="kv"><div class="k">Código</div><div class="v"><?= e2($activoCod ?: '—') ?></div></div>
          <div class="kv"><div class="k">Nombre</div><div class="v"><?= e2($activoNom ?: '—') ?></div></div>
          <div class="kv"><div class="k">Modelo</div><div class="v"><?= e2($row['activo_modelo'] ?? '—') ?></div></div>
          <div class="kv"><div class="k">Serial</div><div class="v"><?= e2($row['activo_serial'] ?? '—') ?></div></div>
          <div class="kv"><div class="k">Placa</div><div class="v"><?= e2($row['activo_placa'] ?? '—') ?></div></div>
          <div class="kv"><div class="k">Ubicación</div><div class="v"><?= e2($ubic) ?></div></div>
        </div>

        <div class="card">
          <h3>Ejecución / condiciones</h3>
          <div class="kv"><div class="k">Programada</div><div class="v"><?= e2(fmt_dt($row['fecha_programada'] ?? null, 16)) ?></div></div>
          <div class="kv"><div class="k">Inicio</div><div class="v"><?= e2(fmt_dt($row['fecha_inicio'] ?? null, 16)) ?></div></div>
          <div class="kv"><div class="k">Fin</div><div class="v"><?= e2(fmt_dt($row['fecha_fin'] ?? null, 16)) ?></div></div>
          <div class="kv"><div class="k">Lugar</div><div class="v"><?= e2($row['lugar'] ?? '—') ?></div></div>
          <div class="kv"><div class="k">Método</div><div class="v"><?= e2($row['metodo'] ?? '—') ?></div></div>
          <div class="kv"><div class="k">Norma ref</div><div class="v"><?= e2($row['norma_ref'] ?? '—') ?></div></div>
          <div class="kv">
            <div class="k">Temp / Humedad</div>
            <div class="v"><?= e2(($row['temperatura_c'] ?? '—')) ?> °C · <?= e2(($row['humedad_rel'] ?? '—')) ?> %</div>
          </div>
        </div>
      </div>

      <div class="wide note">
        <h3>Observaciones / Recomendaciones</h3>
        <div class="text">
          <div><span class="muted"><b>Observaciones:</b></span> <?= nl2br(e2($row['observaciones'] ?? '—')) ?></div>
          <div style="margin-top:8px;"><span class="muted"><b>Recomendaciones:</b></span> <?= nl2br(e2($row['recomendaciones'] ?? '—')) ?></div>
        </div>
      </div>

      <div class="wide note">
        <h3>Patrones / Trazabilidad metrológica</h3>

        <?php if (!$patrones): ?>
          <div class="text"><span class="muted">No hay patrones asociados a esta calibración.</span></div>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th style="width:18%">Tipo</th>
                <th style="width:34%">Patrón</th>
                <th style="width:18%">Serie</th>
                <th style="width:20%">Certificado</th>
                <th style="width:10%">Vence</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($patrones as $p): ?>
                <?php
                  $certNum = !empty($p['certificado_numero']) ? (string)$p['certificado_numero'] : '';
                  $vence   = !empty($p['certificado_vigencia_hasta']) ? (string)$p['certificado_vigencia_hasta'] : '';
                  $tipoPat = !empty($p['tipo_patron']) ? (string)$p['tipo_patron'] : '';
                ?>
                <tr>
                  <td>
                    <?= e2($tipoPat !== '' ? $tipoPat : '—') ?><br>
                    <span class="muted" style="font-size:11px;"><?= e2($p['uso'] ?? '') ?></span>
                  </td>
                  <td>
                    <b><?= e2($p['nombre'] ?? '—') ?></b><br>
                    <span class="muted" style="font-size:11px;">
                      <?= e2($p['marca'] ?? '') ?><?= (!empty($p['modelo']) ? (' · '.e2($p['modelo'])) : '') ?>
                    </span>
                    <?php if (!empty($p['magnitudes']) || !empty($p['rango'])): ?>
                      <br><span class="muted" style="font-size:11px;">
                        <?= e2($p['magnitudes'] ?? '') ?><?= (!empty($p['rango']) ? (' · Rango: '.e2($p['rango'])) : '') ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td><?= e2($p['serial'] ?? '—') ?></td>
                  <td>
                    <?= e2($certNum !== '' ? $certNum : '—') ?>
                    <?php if (!empty($p['certificado_emisor'])): ?>
                      <br><span class="muted" style="font-size:11px;"><?= e2($p['certificado_emisor']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= e2(fmt_dt($vence ?: null, 10)) ?></td>
                </tr>
                <?php if (!empty($p['notas'])): ?>
                  <tr>
                    <td colspan="5" style="background:#fbfdff;">
                      <span class="muted" style="font-size:11px;"><b>Notas patrón:</b> <?= e2($p['notas']) ?></span>
                    </td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div class="footer">
            Nota: esta sección se alimenta desde <b>calibraciones_patrones</b> y <b>patrones</b>.
          </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($validation['errors']) || !empty($validation['warnings'])): ?>
  <div class="wide note">
    <h3>Validaciones automáticas</h3>

    <?php foreach ($validation['errors'] as $e): ?>
      <div style="color:#b91c1c;font-size:12px;">
        ❌ <?= e2($e) ?>
      </div>
    <?php endforeach; ?>

    <?php foreach ($validation['warnings'] as $w): ?>
      <div style="color:#92400e;font-size:12px;">
        ⚠️ <?= e2($w) ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>


    <div class="wide note">
  <h3>Resultados de medición</h3>

  <?php
// =========================================================
// PASO 4: RESUMEN PRO (antes de la tabla)
// =========================================================
$cntTotal = count($puntos_validos);
$cntOk = 0; $cntBad = 0;

foreach ($puntos_validos as $pt) {
  if ((int)($pt['conforme'] ?? -1) === 1) $cntOk++;
  if ((int)($pt['conforme'] ?? -1) === 0) $cntBad++;
}

$cntWarn = count($validation['warnings']);
$cntErr  = count($validation['errors']);
?>

<div class="wide note" style="margin-top:10px;">
  <h3>Resumen de resultados</h3>
  <div class="text">
    <b>Puntos válidos:</b> <?= (int)$cntTotal ?> ·
    <b>Conformes:</b> <?= (int)$cntOk ?> ·
    <b>No conformes:</b> <?= (int)$cntBad ?> ·
    <b>Alertas:</b> <?= (int)$cntWarn ?> ·
    <b>Errores:</b> <?= (int)$cntErr ?>
  </div>
</div>


  <?php if (!empty($validation['errors']) || !empty($validation['warnings'])): ?>
    <div class="note" style="margin-top:10px; border-color:#fde68a; background:#fffbeb;">
      <h3 style="margin:0 0 8px; color:#92400e;">Validación de puntos</h3>

      

      <?php if (!empty($validation['errors'])): ?>
        <div class="text" style="margin-bottom:10px;">
          <b style="color:#b91c1c;">Errores (requieren corrección):</b>
          <ul style="margin:6px 0 0 18px;">
            <?php foreach($validation['errors'] as $msg): ?>
              <li><?= e2($msg) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($validation['warnings'])): ?>
        <div class="text">
          <b style="color:#b45309;">Alertas (revisar):</b>
          <ul style="margin:6px 0 0 18px;">
            <?php foreach($validation['warnings'] as $msg): ?>
              <li><?= e2($msg) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  

  <?php if ($blocked): ?>
    <div class="text" style="margin-top:10px; color:#b91c1c; font-weight:800;">
      No se puede emitir el certificado: hay puntos sin lecturas completas.
      Corrija los puntos y vuelva a generar.
    </div>

  <?php else: ?>

    <?php if (!$puntos): ?>
      <div class="text"><span class="muted">No hay puntos de medición registrados.</span></div>
    <?php else: ?>

      <?php
        $byMag = array();
          foreach($puntos_validos as $pt){
          $mag = trim((string)($pt['magnitud'] ?? ''));
          if ($mag === '') $mag = '—';
          if (!isset($byMag[$mag])) $byMag[$mag] = array();
          $byMag[$mag][] = $pt;
        }
      ?>

      <?php if ($fmt === 'balanza'): ?>

        <?php foreach($byMag as $mag => $rowsMag): ?>
          <div class="footer" style="margin-top:6px;"><b>Magnitud:</b> <?= e2($mag) ?></div>

          <table class="table">
            <thead>
              <tr>
                <th style="width:8%">Ord</th>
                <th style="width:10%">Unidad</th>
                <th style="width:14%">Nominal</th>
                <th style="width:14%">Equipo</th>
                <th style="width:14%">Patrón</th>
                <th style="width:12%">Error</th>
                <th style="width:12%">Tol</th>
                <th style="width:10%">U</th>
                <th style="width:6%">k</th>
                <th style="width:10%">OK</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rowsMag as $pt): ?>
                <tr>
                  <td><?= (int)$pt['orden'] ?></td>
                  <td><?= e2($pt['unidad'] ?? '—') ?></td>
                  <td><?= e2($pt['punto_nominal'] ?? '—') ?></td>
                  <td><?= e2($pt['lectura_equipo'] ?? '—') ?></td>
                  <td><?= e2($pt['lectura_patron'] ?? '—') ?></td>
                  <td><?= e2(pick_error($pt)) ?></td>
                  <td><?= e2(nvl($pt['tolerancia'] ?? null)) ?></td>
                  <td><?= e2(nvl($pt['incertidumbre_expandida'] ?? null)) ?></td>
                  <td><?= e2(nvl($pt['k'] ?? null)) ?></td>
                  <td><?= pill_conforme($pt) ?></td>
                </tr>

                <?php if (!empty($pt['notas'])): ?>
                  <tr>
                    <td colspan="10" style="background:#fbfdff;">
                      <span class="muted" style="font-size:11px;"><b>Nota:</b> <?= e2($pt['notas']) ?></span>
                    </td>
                  </tr>
                <?php endif; ?>

              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endforeach; ?>

      <?php else: ?>

        <table class="table">
          <thead>
            <tr>
              <th style="width:8%">Orden</th>
              <th style="width:16%">Magnitud</th>
              <th style="width:10%">Unidad</th>
              <th style="width:14%">Nominal</th>
              <th style="width:14%">Equipo</th>
              <th style="width:14%">Patrón</th>
              <th style="width:12%">Error</th>

              <?php if ($fmt === 'termometro' || $fmt === 'manometro' || $fmt === 'electrico'): ?>
                <th style="width:12%">Tol</th>
                <th style="width:10%">U</th>
                <th style="width:6%">k</th>
              <?php endif; ?>

              <th style="width:10%">Conforme</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($puntos as $pt): ?>
              <tr>
                <td><?= (int)$pt['orden'] ?></td>
                <td><?= e2($pt['magnitud'] ?? '—') ?></td>
                <td><?= e2($pt['unidad'] ?? '—') ?></td>
                <td><?= e2($pt['punto_nominal'] ?? '—') ?></td>
                <td><?= e2($pt['lectura_equipo'] ?? '—') ?></td>
                <td><?= e2($pt['lectura_patron'] ?? '—') ?></td>
                <td><?= e2(pick_error($pt)) ?></td>

                <?php if ($fmt === 'termometro' || $fmt === 'manometro' || $fmt === 'electrico'): ?>
                  <td><?= e2(nvl($pt['tolerancia'] ?? null)) ?></td>
                  <td><?= e2(nvl($pt['incertidumbre_expandida'] ?? null)) ?></td>
                  <td><?= e2(nvl($pt['k'] ?? null)) ?></td>
                <?php endif; ?>

                <td><?= pill_conforme($pt) ?></td>
              </tr>

              <?php if (!empty($pt['notas'])): ?>
                <tr>
                  <td colspan="<?= ($fmt === 'termometro' || $fmt === 'manometro' || $fmt === 'electrico') ? 11 : 8 ?>" style="background:#fbfdff;">
                    <span class="muted" style="font-size:11px;"><b>Nota:</b> <?= e2($pt['notas']) ?></span>
                  </td>
                </tr>
              <?php endif; ?>

            <?php endforeach; ?>
          </tbody>
        </table>

      <?php endif; ?>

      <?php if (!empty($row['resultado_detalle'])): ?>
        <div class="footer" style="margin-top:8px;">
          <b>Resultado detallado:</b> <?= e2($row['resultado_detalle']) ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>

  <?php endif; ?>
</div>

     </div>

      <?php
        if ($showExamples === 1) {
          echo ga_examples_render_pro($fmt, $fmtLabel, $FMT_EXAMPLES_PRO);
        }
      ?>

      <div class="verify">
        <div class="left">
          <h3>Verificación</h3>
          <div class="text">Use el token (o el QR) para validar este certificado en el sistema.</div>
          <div class="url"><?= e2($verifyUrl) ?></div>
        </div>

        <div class="qr">
          <div class="qrbox"><div id="qr"></div></div>
          <div class="qrhint">Escanee para verificar</div>
        </div>
      </div>

      <div class="sign">
        <div class="signbox">
          <h3>Técnico / Responsable</h3>
          <div class="kv"><div class="k">Nombre</div><div class="v"><?= e2($tecNombre !== '' ? $tecNombre : '—') ?></div></div>
          <div class="kv"><div class="k">Cargo</div><div class="v"><?= e2($tecCargo !== '' ? $tecCargo : '—') ?></div></div>
          <div class="kv"><div class="k">TP</div><div class="v"><?= e2($tecTP !== '' ? $tecTP : '—') ?></div></div>

          <div class="sigimg">
            <?php if ($firmaUrl !== ''): ?>
              <img src="<?= e2($firmaUrl) ?>?v=<?= urlencode((string)time()) ?>" alt="Firma del técnico">
            <?php else: ?>
              No hay imagen de firma registrada.<br>
              Cargue la firma del usuario en el módulo de Usuarios.
            <?php endif; ?>
          </div>
          <div class="sigline">Firma</div>
        </div>

        <div class="signbox">
          <h3>Información del certificado</h3>
          <div class="kv"><div class="k">Certificado</div><div class="v"><?= e2($cert ?: '—') ?></div></div>
          <div class="kv"><div class="k">Fecha emisión</div><div class="v"><?= e2(fmt_dt($row['creado_en'] ?? null, 10)) ?></div></div>
          <div class="kv"><div class="k">Estado</div><div class="v"><?= e2($estado ?: '—') ?></div></div>
          <div class="kv"><div class="k">Resultado</div><div class="v"><?= e2($resultado ?: '—') ?></div></div>
          <div class="kv"><div class="k">Formato</div><div class="v"><?= e2($fmtLabel) ?></div></div>

          <div class="sigimg">
            <?php if ($selloUrl !== ''): ?>
              <img src="<?= e2($selloUrl) ?>?v=<?= urlencode((string)time()) ?>" alt="Sello/Laboratorio">
            <?php else: ?>
              <div>
                <b><?= e2($empresaNombre) ?></b><br>
                <span class="muted" style="font-size:12px;">Documento generado desde GeoActivos.</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="sigline">Sello / Laboratorio</div>
        </div>
      </div>

      <div class="footer">
        Generado: <?= e2(date('Y-m-d H:i')) ?> · Certificado #<?= (int)$row['id'] ?> · Formato: <?= e2($fmtLabel) ?> · GeoActivos (multi-cliente)
      </div>

    </div>
  </div>

</div>

<div id="js-cert-print-qr"
  data-scripts="<?= e(json_encode([
    rtrim(base_url(),'/').'/assets/js/qrcode.min.js',
    rtrim(base_url(),'/').'/js/qrcode.min.js',
    rtrim(base_url(),'/').'/vendor/qrcode.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'
  ])) ?>"
  data-verify-url="<?= e(htmlspecialchars_decode($verifyUrl, ENT_QUOTES)) ?>"
></div>
<script src="<?= e(base_url()) ?>/assets/js/certificado-print-qr.js"></script>

</body>
</html>
