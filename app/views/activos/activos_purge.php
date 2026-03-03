<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$tenantId = Auth::tenantId();
$userId   = Auth::userId();
$activoId = (int)($_GET['id'] ?? 0);

if ($activoId <= 0) {
  header('Location: ' . base_url() . '/index.php?route=activos_eliminados&err=ID inválido');
  exit;
}

function table_exists($table) {
  $st = db()->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
  $st->execute([':t' => $table]);
  return (bool)$st->fetchColumn();
}

function column_exists($table, $column) {
  $st = db()->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1");
  $st->execute([':t' => $table, ':c' => $column]);
  return (bool)$st->fetchColumn();
}

function audit_log_safe($tenantId, $userId, $action, $entity, $entityId, $message) {
  if (!table_exists('audit_log')) return;

  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $ip = substr((string)$ip, 0, 45);
  $ua = substr((string)$ua, 0, 255);

  $st = db()->prepare("
    INSERT INTO audit_log (tenant_id, user_id, action, entity, entity_id, message, ip, user_agent)
    VALUES (:t, :u, :a, :e, :eid, :m, :ip, :ua)
  ");
  $st->execute([
    ':t' => (int)$tenantId,
    ':u' => ((int)$userId > 0 ? (int)$userId : null),
    ':a' => (string)$action,
    ':e' => (string)$entity,
    ':eid' => ((int)$entityId > 0 ? (int)$entityId : null),
    ':m' => ($message !== '' ? substr((string)$message, 0, 255) : null),
    ':ip' => ($ip !== '' ? $ip : null),
    ':ua' => ($ua !== '' ? $ua : null),
  ]);
}

/**
 * Intenta resolver un path de archivo desde una fila de adjunto, soportando nombres comunes.
 * Si no encuentra nada, retorna ''.
 */
function resolve_attachment_path($row) {
  $pathCandidates = ['stored_path', 'file_path', 'path', 'ruta', 'ruta_archivo', 'storage_path'];
  foreach ($pathCandidates as $k) {
    if (!empty($row[$k])) return (string)$row[$k];
  }

  $dirCandidates  = ['dir_path', 'folder', 'carpeta', 'ruta_base', 'rel_path'];
  $nameCandidates = ['stored_name', 'nombre_archivo', 'filename', 'archivo', 'name'];

  $dir = '';
  foreach ($dirCandidates as $k) {
    if (!empty($row[$k])) { $dir = rtrim((string)$row[$k], '/\\'); break; }
  }

  $name = '';
  foreach ($nameCandidates as $k) {
    if (!empty($row[$k])) { $name = (string)$row[$k]; break; }
  }

  if ($dir !== '' && $name !== '') return $dir . DIRECTORY_SEPARATOR . $name;
  return '';
}

/**
 * Borra archivo físico si existe. Soporta rutas relativas desde raíz del proyecto.
 */
function unlink_if_exists($path) {
  $path = trim((string)$path);
  if ($path === '') return;

  $path = str_replace(['\\'], '/', $path);

  $abs = $path;
  if (strpos($path, ':') === false && substr($path, 0, 1) !== '/') {
    $projectRoot = realpath(__DIR__ . '/../../'); // ajusta si tu estructura difiere
    if ($projectRoot) {
      $abs = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/' . ltrim($path, '/');
    }
  }

  if (is_file($abs)) {
    @unlink($abs);
  }
}

$tbl_mantenimientos = 'mantenimientos';
$tbl_adjuntos       = 'mantenimientos_adjuntos';

/** Confirmar que el activo existe y es del tenant (puede estar eliminado o no) */
$st = db()->prepare("SELECT id, codigo_interno, nombre FROM activos WHERE id = :id AND tenant_id = :t LIMIT 1");
$st->execute([':id' => $activoId, ':t' => $tenantId]);
$activo = $st->fetch();

if (!$activo) {
  header('Location: ' . base_url() . '/index.php?route=activos_eliminados&err=Activo no encontrado');
  exit;
}

try {
  db()->beginTransaction();

  /** 1) Obtener IDs de mantenimientos del activo */
  $mantenimientoIds = [];
  if (table_exists($tbl_mantenimientos) && column_exists($tbl_mantenimientos, 'activo_id') && column_exists($tbl_mantenimientos, 'tenant_id')) {
    $st = db()->prepare("SELECT id FROM {$tbl_mantenimientos} WHERE tenant_id = :t AND activo_id = :a");
    $st->execute([':t' => $tenantId, ':a' => $activoId]);
    $mantenimientoIds = $st->fetchAll(PDO::FETCH_COLUMN, 0);
  }

  /** 2) Adjuntos: borrar archivos físicos y luego BD */
  if ($mantenimientoIds && table_exists($tbl_adjuntos) && column_exists($tbl_adjuntos, 'mantenimiento_id') && column_exists($tbl_adjuntos, 'tenant_id')) {
    $in = implode(',', array_fill(0, count($mantenimientoIds), '?'));

    // Leer filas para borrar archivos físicos
    $sql = "SELECT * FROM {$tbl_adjuntos} WHERE tenant_id = ? AND mantenimiento_id IN ($in)";
    $params = array_merge([(int)$tenantId], $mantenimientoIds);

    $st = db()->prepare($sql);
    $st->execute($params);
    $adjRows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($adjRows as $row) {
      $p = resolve_attachment_path($row);
      if ($p !== '') unlink_if_exists($p);
    }

    // Borrar registros adjuntos (duro)
    $sql = "DELETE FROM {$tbl_adjuntos} WHERE tenant_id = ? AND mantenimiento_id IN ($in)";
    $st = db()->prepare($sql);
    $st->execute($params);
  }

  /** 3) Mantenimientos: borrar duro */
  if (table_exists($tbl_mantenimientos) && column_exists($tbl_mantenimientos, 'activo_id') && column_exists($tbl_mantenimientos, 'tenant_id')) {
    $st = db()->prepare("DELETE FROM {$tbl_mantenimientos} WHERE tenant_id = :t AND activo_id = :a");
    $st->execute([':t' => (int)$tenantId, ':a' => (int)$activoId]);
  }

  /** 4) Activo: borrar duro */
  $st = db()->prepare("DELETE FROM activos WHERE id = :id AND tenant_id = :t LIMIT 1");
  $st->execute([':id' => (int)$activoId, ':t' => (int)$tenantId]);

  /** 5) Auditoría */
  $cod = isset($activo['codigo_interno']) ? (string)$activo['codigo_interno'] : '';
  $nom = isset($activo['nombre']) ? (string)$activo['nombre'] : '';
  $msg = "Activo eliminado DEFINITIVO (purge). Código: {$cod} · Nombre: {$nom}. Incluye mantenimientos y adjuntos + archivos físicos.";
  audit_log_safe($tenantId, $userId, 'PURGE', 'activo', $activoId, $msg);

  db()->commit();

  header('Location: ' . base_url() . '/index.php?route=activos_eliminados&ok=Eliminación definitiva realizada');
  exit;

} catch (Exception $e) {
  if (db()->inTransaction()) db()->rollBack();
  header('Location: ' . base_url() . '/index.php?route=activos_eliminados&err=No se pudo eliminar definitivamente');
  exit;
}
