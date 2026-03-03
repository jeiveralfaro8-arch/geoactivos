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

$tbl_mantenimientos = 'mantenimientos';
$tbl_adjuntos       = 'mantenimientos_adjuntos';

$softReadyActivos = column_exists('activos', 'eliminado');
$softReadyMant    = table_exists($tbl_mantenimientos) && column_exists($tbl_mantenimientos, 'eliminado');
$softReadyAdj     = table_exists($tbl_adjuntos) && column_exists($tbl_adjuntos, 'eliminado');

if (!$softReadyActivos) {
  header('Location: ' . base_url() . '/index.php?route=activos&err=Soft delete no está habilitado');
  exit;
}

/** Confirmar que el activo existe, es del tenant y está eliminado */
$st = db()->prepare("SELECT id, codigo_interno, nombre FROM activos WHERE id = :id AND tenant_id = :t AND eliminado = 1 LIMIT 1");
$st->execute([':id' => $activoId, ':t' => $tenantId]);
$activo = $st->fetch();

if (!$activo) {
  header('Location: ' . base_url() . '/index.php?route=activos_eliminados&err=Activo no encontrado o no está eliminado');
  exit;
}

try {
  db()->beginTransaction();

  /** 1) Restaurar activo */
  $st = db()->prepare("
    UPDATE activos
    SET eliminado = 0, eliminado_en = NULL, eliminado_por = NULL
    WHERE id = :id AND tenant_id = :t
    LIMIT 1
  ");
  $st->execute([':id' => $activoId, ':t' => $tenantId]);

  /** 2) Restaurar mantenimientos del activo */
  $mantenimientoIds = [];
  if ($softReadyMant && column_exists($tbl_mantenimientos, 'activo_id') && column_exists($tbl_mantenimientos, 'tenant_id')) {
    $st = db()->prepare("
      SELECT id
      FROM {$tbl_mantenimientos}
      WHERE tenant_id = :t AND activo_id = :a AND eliminado = 1
    ");
    $st->execute([':t' => $tenantId, ':a' => $activoId]);
    $mantenimientoIds = $st->fetchAll(PDO::FETCH_COLUMN, 0);

    $st = db()->prepare("
      UPDATE {$tbl_mantenimientos}
      SET eliminado = 0, eliminado_en = NULL, eliminado_por = NULL
      WHERE tenant_id = :t AND activo_id = :a
    ");
    $st->execute([':t' => $tenantId, ':a' => $activoId]);
  }

  /** 3) Restaurar adjuntos asociados a esos mantenimientos */
  if ($mantenimientoIds && $softReadyAdj && column_exists($tbl_adjuntos, 'mantenimiento_id') && column_exists($tbl_adjuntos, 'tenant_id')) {
    $in = implode(',', array_fill(0, count($mantenimientoIds), '?'));
    $sql = "UPDATE {$tbl_adjuntos}
            SET eliminado = 0, eliminado_en = NULL, eliminado_por = NULL
            WHERE tenant_id = ? AND mantenimiento_id IN ($in)";
    $params = array_merge([(int)$tenantId], $mantenimientoIds);
    $st = db()->prepare($sql);
    $st->execute($params);
  }

  /** 4) Auditoría */
  $cod = isset($activo['codigo_interno']) ? (string)$activo['codigo_interno'] : '';
  $nom = isset($activo['nombre']) ? (string)$activo['nombre'] : '';
  $msg = "Activo restaurado. Código: {$cod} · Nombre: {$nom}. Incluye mantenimientos y adjuntos.";
  audit_log_safe($tenantId, $userId, 'RESTORE', 'activo', $activoId, $msg);

  db()->commit();

  header('Location: ' . base_url() . '/index.php?route=activos&ok=Activo restaurado correctamente');
  exit;

} catch (Exception $e) {
  if (db()->inTransaction()) db()->rollBack();
  header('Location: ' . base_url() . '/index.php?route=activos_eliminados&err=No se pudo restaurar el activo');
  exit;
}
