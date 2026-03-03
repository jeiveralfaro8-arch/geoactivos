<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$userId   = Auth::userId(); // ✅ correcto en tu sistema
$activoId = (int)($_GET['id'] ?? 0);

if ($activoId <= 0) {
  header('Location: ' . base_url() . '/index.php?route=activos&err=ID inválido');
  exit;
}

/* =========================================================
   Helpers: DB schema detect
========================================================= */
function table_exists($table) {
  $st = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $st->execute([':t' => $table]);
  return (bool)$st->fetchColumn();
}

function table_columns($table) {
  $st = db()->prepare("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = :t
  ");
  $st->execute([':t' => $table]);
  $cols = [];
  foreach ($st->fetchAll() as $r) $cols[] = $r['column_name'];
  return $cols;
}

function column_exists($table, $column) {
  $st = db()->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1");
  $st->execute([':t' => $table, ':c' => $column]);
  return (bool)$st->fetchColumn();
}

function client_ip() {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    return trim($parts[0]);
  }
  return $_SERVER['REMOTE_ADDR'] ?? '';
}

/* =========================================================
   Auditoría robusta (NO asume columnas)
========================================================= */
function audit_log_safe($tenantId, $userId, $action, $entity, $entityId, $message) {
  if (!table_exists('audit_log')) return;

  $cols = table_columns('audit_log');

  $colTenant = in_array('tenant_id', $cols, true) ? 'tenant_id' : null;
  $colUser   = in_array('user_id', $cols, true) ? 'user_id' : null;
  $colAction = in_array('action', $cols, true) ? 'action' : (in_array('evento', $cols, true) ? 'evento' : null);
  $colEntity = in_array('entity', $cols, true) ? 'entity' : (in_array('tabla', $cols, true) ? 'tabla' : null);
  $colEid    = in_array('entity_id', $cols, true) ? 'entity_id' : (in_array('registro_id', $cols, true) ? 'registro_id' : null);
  $colMsg    = in_array('message', $cols, true) ? 'message' : (in_array('descripcion', $cols, true) ? 'descripcion' : null);
  $colIp     = in_array('ip', $cols, true) ? 'ip' : null;
  $colUa     = in_array('user_agent', $cols, true) ? 'user_agent' : null;
  $colWhen   = in_array('created_at', $cols, true) ? 'created_at' : (in_array('creado_en', $cols, true) ? 'creado_en' : null);

  // mínimo viable
  if (!$colTenant || !$colAction || !$colEntity || !$colEid || !$colMsg) return;

  $ip = substr((string)client_ip(), 0, 45);
  $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

  $fields = [];
  $vals   = [];
  $params = [];

  $fields[] = "`$colTenant`"; $vals[] = ":t";   $params[":t"] = (int)$tenantId;

  if ($colUser) {
    $fields[] = "`$colUser`"; $vals[] = ":u";   $params[":u"] = ((int)$userId > 0 ? (int)$userId : null);
  }

  $fields[] = "`$colAction`"; $vals[] = ":a";   $params[":a"] = (string)$action;
  $fields[] = "`$colEntity`"; $vals[] = ":e";   $params[":e"] = (string)$entity;
  $fields[] = "`$colEid`";    $vals[] = ":i";   $params[":i"] = ((int)$entityId > 0 ? (int)$entityId : null);

  // limitar msg para no romper si el campo es corto
  $msg = (string)$message;
  if (strlen($msg) > 1000) $msg = substr($msg, 0, 1000);

  $fields[] = "`$colMsg`";    $vals[] = ":m";   $params[":m"] = ($msg !== '' ? $msg : null);

  if ($colIp) { $fields[] = "`$colIp`"; $vals[] = ":ip"; $params[":ip"] = ($ip !== '' ? $ip : null); }
  if ($colUa) { $fields[] = "`$colUa`"; $vals[] = ":ua"; $params[":ua"] = ($ua !== '' ? $ua : null); }

  if ($colWhen) {
    $fields[] = "`$colWhen`";
    $vals[]   = ":w";
    $params[":w"] = date('Y-m-d H:i:s');
  }

  $sql = "INSERT INTO audit_log (" . implode(',', $fields) . ") VALUES (" . implode(',', $vals) . ")";
  try {
    $st = db()->prepare($sql);
    $st->execute($params);
  } catch (Exception $e) {
    // nunca romper UX por auditoría
  }
}

/* =========================================================
   Borrado físico seguro (solo dentro de uploads)
========================================================= */
function safe_unlink_any($relativeOrPath) {
  $p = trim((string)$relativeOrPath);
  if ($p === '') return false;

  // normaliza separadores
  $p = str_replace(['\\'], '/', $p);

  // carpetas permitidas (ajusta si tu proyecto usa otra)
  $base1 = realpath(__DIR__ . '/../../..'); // raíz app (geoactivos/)
  if (!$base1) $base1 = __DIR__ . '/../../..';

  $allowed = [
    realpath($base1 . '/public/uploads'),
    realpath($base1 . '/uploads'),
    realpath($base1 . '/storage'),
    realpath($base1 . '/public/storage'),
  ];

  // limpia nulls
  $allowed = array_values(array_filter($allowed));

  // candidatos:
  $cands = [];

  // si viene absoluto y existe, lo intentamos
  if (file_exists($p)) $cands[] = $p;

  // si es relativo, probamos contra bases comunes
  $rel = ltrim($p, '/');
  $cands[] = $base1 . '/public/' . $rel;
  $cands[] = $base1 . '/' . $rel;
  $cands[] = $base1 . '/public/uploads/' . $rel;
  $cands[] = $base1 . '/uploads/' . $rel;
  $cands[] = $base1 . '/storage/' . $rel;
  $cands[] = $base1 . '/public/storage/' . $rel;

  foreach ($cands as $cand) {
    if (!file_exists($cand)) continue;

    $real = realpath($cand);
    if (!$real) continue;

    // solo si está dentro de allowed
    $ok = false;
    foreach ($allowed as $base) {
      if ($base && strpos($real, $base) === 0) { $ok = true; break; }
    }
    if (!$ok) continue;

    @unlink($real);
    return true;
  }

  return false;
}

/* =========================================================
   Verifica que el activo exista y sea del tenant
========================================================= */
$st = db()->prepare("SELECT id, tenant_id, codigo_interno, nombre FROM activos WHERE id = :id AND tenant_id = :t LIMIT 1");
$st->execute([':id' => $activoId, ':t' => $tenantId]);
$activo = $st->fetch();

if (!$activo) {
  header('Location: ' . base_url() . '/index.php?route=activos&err=Activo no encontrado');
  exit;
}

/* =========================================================
   Detección de tablas reales de adjuntos
   - mant_adjuntos o mantenimientos_adjuntos
========================================================= */
$tbl_mantenimientos = 'mantenimientos';

$tbl_adjuntos = null;
if (table_exists('mant_adjuntos')) $tbl_adjuntos = 'mant_adjuntos';
elseif (table_exists('mantenimientos_adjuntos')) $tbl_adjuntos = 'mantenimientos_adjuntos';

$softReadyActivos = column_exists('activos', 'eliminado') && column_exists('activos', 'eliminado_en') && column_exists('activos', 'eliminado_por');
$softReadyMant    = table_exists($tbl_mantenimientos) && column_exists($tbl_mantenimientos, 'eliminado') && column_exists($tbl_mantenimientos, 'eliminado_en') && column_exists($tbl_mantenimientos, 'eliminado_por');

$softReadyAdj     = false;
$adjCols          = [];
$adjColArchivo    = null;

if ($tbl_adjuntos) {
  $adjCols = table_columns($tbl_adjuntos);
  $softReadyAdj = in_array('eliminado', $adjCols, true) && in_array('eliminado_en', $adjCols, true) && in_array('eliminado_por', $adjCols, true);

  // detecta la columna de archivo/ruta
  foreach (['archivo','ruta','path','filename','nombre_archivo','stored_name'] as $cname) {
    if (in_array($cname, $adjCols, true)) { $adjColArchivo = $cname; break; }
  }
}

/* =========================================================
   Ejecución: cascada + auditoría
========================================================= */
try {
  db()->beginTransaction();

  /* 1) Obtener IDs de mantenimientos del activo */
  $mantenimientoIds = [];

  if (table_exists($tbl_mantenimientos) && column_exists($tbl_mantenimientos, 'activo_id') && column_exists($tbl_mantenimientos, 'tenant_id')) {
    if ($softReadyMant) {
      $st = db()->prepare("SELECT id FROM {$tbl_mantenimientos} WHERE tenant_id = :t AND activo_id = :a AND eliminado = 0");
      $st->execute([':t' => $tenantId, ':a' => $activoId]);
    } else {
      $st = db()->prepare("SELECT id FROM {$tbl_mantenimientos} WHERE tenant_id = :t AND activo_id = :a");
      $st->execute([':t' => $tenantId, ':a' => $activoId]);
    }
    $mantenimientoIds = $st->fetchAll(PDO::FETCH_COLUMN, 0);
  }

  /* 2) Adjuntos (DB + archivo físico) */
  $adjCount = 0;
  $adjFilesDeleted = 0;

  if ($mantenimientoIds && $tbl_adjuntos && column_exists($tbl_adjuntos, 'mantenimiento_id') && column_exists($tbl_adjuntos, 'tenant_id')) {

    $in = implode(',', array_fill(0, count($mantenimientoIds), '?'));

    // 2.1) Tomar lista de archivos para borrado físico (ANTES de soft/hard en DB)
    if ($adjColArchivo) {
      $sqlList = "SELECT id, `$adjColArchivo` AS archivo FROM {$tbl_adjuntos} WHERE tenant_id = ? AND mantenimiento_id IN ($in)";
      if ($softReadyAdj) $sqlList .= " AND eliminado = 0";
      $stL = db()->prepare($sqlList);
      $paramsL = array_merge([(int)$tenantId], $mantenimientoIds);
      $stL->execute($paramsL);
      $rows = $stL->fetchAll();

      foreach ($rows as $r) {
        $adjCount++;
        $path = $r['archivo'] ?? '';
        if ($path) {
          if (safe_unlink_any($path)) $adjFilesDeleted++;
        }
      }
    } else {
      // no hay columna de archivo, igual contamos adjuntos por DB
      $sqlC = "SELECT COUNT(*) FROM {$tbl_adjuntos} WHERE tenant_id = ? AND mantenimiento_id IN ($in)";
      if ($softReadyAdj) $sqlC .= " AND eliminado = 0";
      $stC = db()->prepare($sqlC);
      $paramsC = array_merge([(int)$tenantId], $mantenimientoIds);
      $stC->execute($paramsC);
      $adjCount = (int)$stC->fetchColumn();
    }

    // 2.2) Soft o hard en DB
    if ($softReadyAdj) {
      $sql = "UPDATE {$tbl_adjuntos}
              SET eliminado = 1, eliminado_en = NOW(), eliminado_por = ?
              WHERE tenant_id = ? AND mantenimiento_id IN ($in) AND eliminado = 0";
      $params = array_merge([(int)$userId > 0 ? (int)$userId : null, (int)$tenantId], $mantenimientoIds);
      $st = db()->prepare($sql);
      $st->execute($params);
    } else {
      $sql = "DELETE FROM {$tbl_adjuntos} WHERE tenant_id = ? AND mantenimiento_id IN ($in)";
      $params = array_merge([(int)$tenantId], $mantenimientoIds);
      $st = db()->prepare($sql);
      $st->execute($params);
    }
  }

  /* 3) Mantenimientos */
  $mantCount = 0;

  if (table_exists($tbl_mantenimientos) && column_exists($tbl_mantenimientos, 'activo_id') && column_exists($tbl_mantenimientos, 'tenant_id')) {
    // contar antes (para auditoría)
    $stC = db()->prepare("SELECT COUNT(*) FROM {$tbl_mantenimientos} WHERE tenant_id = :t AND activo_id = :a" . ($softReadyMant ? " AND eliminado = 0" : ""));
    $stC->execute([':t' => (int)$tenantId, ':a' => (int)$activoId]);
    $mantCount = (int)$stC->fetchColumn();

    if ($softReadyMant) {
      $st = db()->prepare("
        UPDATE {$tbl_mantenimientos}
        SET eliminado = 1, eliminado_en = NOW(), eliminado_por = :u
        WHERE tenant_id = :t AND activo_id = :a AND eliminado = 0
      ");
      $st->execute([
        ':u' => ((int)$userId > 0 ? (int)$userId : null),
        ':t' => (int)$tenantId,
        ':a' => (int)$activoId
      ]);
    } else {
      $st = db()->prepare("DELETE FROM {$tbl_mantenimientos} WHERE tenant_id = :t AND activo_id = :a");
      $st->execute([':t' => (int)$tenantId, ':a' => (int)$activoId]);
    }
  }

  /* 4) Activo */
  $modo = 'hard';
  if ($softReadyActivos) {
    $st = db()->prepare("
      UPDATE activos
      SET eliminado = 1, eliminado_en = NOW(), eliminado_por = :u
      WHERE id = :id AND tenant_id = :t AND eliminado = 0
      LIMIT 1
    ");
    $st->execute([
      ':u'  => ((int)$userId > 0 ? (int)$userId : null),
      ':id' => (int)$activoId,
      ':t'  => (int)$tenantId
    ]);
    $modo = 'soft';
  } else {
    $st = db()->prepare("DELETE FROM activos WHERE id = :id AND tenant_id = :t LIMIT 1");
    $st->execute([':id' => (int)$activoId, ':t' => (int)$tenantId]);
    $modo = 'hard';
  }

  /* 5) Auditoría */
  $cod = isset($activo['codigo_interno']) ? (string)$activo['codigo_interno'] : '';
  $nom = isset($activo['nombre']) ? (string)$activo['nombre'] : '';

  $msg = "Activo eliminado ({$modo}). Código: {$cod} · Nombre: {$nom}. ";
  $msg .= "Mantenimientos: {$mantCount}. ";

  if ($tbl_adjuntos) {
    $msg .= "Adjuntos: {$adjCount}";
    if ($adjColArchivo) $msg .= " · Archivos borrados: {$adjFilesDeleted}";
    $msg .= ".";
  } else {
    $msg .= "Adjuntos: (sin tabla de adjuntos detectada).";
  }

  audit_log_safe($tenantId, $userId, 'DELETE', 'activo', $activoId, $msg);

  db()->commit();

  header('Location: ' . base_url() . '/index.php?route=activos&ok=Activo eliminado (modo ' . $modo . ')');
  exit;

} catch (Exception $e) {
  if (db()->inTransaction()) db()->rollBack();
  header('Location: ' . base_url() . '/index.php?route=activos&err=No se pudo eliminar el activo');
  exit;
}
