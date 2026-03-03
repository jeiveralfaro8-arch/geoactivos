<?php
require_once __DIR__ . '/../config/db.php';

function e($str) {
  return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * base_url:
 * - Si config.php trae base_url lo respeta
 * - Si no, lo calcula como: scheme://host + directorio de /public
 */
function base_url() {
  $configFile = __DIR__ . '/../config/config.php';
  if (is_file($configFile)) {
    $config = require $configFile;
    if (!empty($config['base_url'])) return rtrim($config['base_url'], '/');
  }

  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

  // SCRIPT_NAME suele ser /geoactivos/public/index.php
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  $dir = str_replace('\\', '/', dirname($script));

  // si dirname devuelve ".", lo dejamos en vacío
  if ($dir === '.' || $dir === '/') $dir = '';

  // base quedaría .../geoactivos/public
  return rtrim($scheme . '://' . $host . $dir, '/');
}

function redirect($path) {
  header('Location: ' . base_url() . '/' . ltrim($path, '/'));
  exit;
}

/**
 * Retorna true si el activo es calibrable (según vw_activos_calibrables).
 */
function activo_es_calibrable($tenantId, $activoId) {
  $tenantId = (int)$tenantId;
  $activoId = (int)$activoId;
  if ($tenantId <= 0 || $activoId <= 0) return false;

  $st = db()->prepare("
    SELECT requiere_calibracion_eff
    FROM vw_activos_calibrables
    WHERE tenant_id = :t AND id = :a
    LIMIT 1
  ");
  $st->execute([':t'=>$tenantId, ':a'=>$activoId]);
  $r = $st->fetch();
  return ($r && (int)$r['requiere_calibracion_eff'] === 1);
}

/**
 * Guard reusable: corta con 403 si NO es calibrable.
 * Úsalo SOLO dentro de rutas/acciones de calibración.
 */
function require_activo_calibrable($tenantId, $activoId, $msg = 'Este activo no está marcado para calibración.') {
  if (!activo_es_calibrable($tenantId, $activoId)) {
    http_response_code(403);
    echo $msg;
    exit;
  }
}
