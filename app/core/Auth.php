<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Helpers.php';

class Auth {

  public static function check() {
    return !empty($_SESSION['user']);
  }

  public static function user() {
    return $_SESSION['user'] ?? null;
  }

  public static function userId() {
    if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    return (int)($_SESSION['user_id'] ?? 0);
  }

  public static function tenantId() {
    // fallback: user.tenant_id -> tenant.id -> tenant_id
    if (!empty($_SESSION['user']['tenant_id'])) return (int)$_SESSION['user']['tenant_id'];
    if (!empty($_SESSION['tenant']['id'])) return (int)$_SESSION['tenant']['id'];
    return (int)($_SESSION['tenant_id'] ?? 0);
  }

  public static function rolId() {
    // fallback: user.rol_id -> rol_id
    if (!empty($_SESSION['user']['rol_id'])) return (int)$_SESSION['user']['rol_id'];
    return (int)($_SESSION['rol_id'] ?? 0);
  }

  public static function requireLogin() {
    if (!self::check()) redirect('index.php?route=login');

    // Siempre asegura cache de permisos (evita 403 falsos)
    if (!isset($_SESSION['perms']) || !is_array($_SESSION['perms'])) {
      self::loadPerms(true);
    }
  }

  /* =========================================================
     SUPERADMIN (PRO)
     - Preferido: roles.es_superadmin = 1
     - Fallback: rol_nombre por texto (legacy)
  ========================================================= */
  public static function isSuperadmin() {
    if (!self::check()) return false;

    // cache
    if (isset($_SESSION['is_superadmin'])) {
      return (bool)$_SESSION['is_superadmin'];
    }

    $tenantId = self::tenantId();
    $rolId    = self::rolId();

    $is = false;

    // 1) Intentar por columna roles.es_superadmin (si existe)
    try {
      $c = db()->prepare("
        SELECT COUNT(*) c
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'roles'
          AND column_name = 'es_superadmin'
      ");
      $c->execute();
      $hasCol = (int)($c->fetch()['c'] ?? 0);

      if ($hasCol > 0 && $tenantId > 0 && $rolId > 0) {
        $q = db()->prepare("
          SELECT es_superadmin
          FROM roles
          WHERE tenant_id = :t AND id = :r
          LIMIT 1
        ");
        $q->execute([':t'=>$tenantId, ':r'=>$rolId]);
        $row = $q->fetch();
        if ($row && (int)$row['es_superadmin'] === 1) $is = true;
      }

    } catch (Exception $e) {
      // si falla, seguimos a fallback legacy
    }

    // 2) Fallback legacy (por nombre)
    if (!$is) {
      $rolNombre = strtolower(trim((string)($_SESSION['rol_nombre'] ?? ($_SESSION['user']['rol_nombre'] ?? ''))));
      if ($rolNombre === 'admin' || $rolNombre === 'superadmin' || $rolNombre === 'root') {
        $is = true;
      }
    }

    $_SESSION['is_superadmin'] = $is ? 1 : 0;

    // También lo dejamos en user (para sidebar u otros)
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
      $_SESSION['user']['es_superadmin'] = $is ? 1 : 0;
    }

    return $is;
  }

  /* =========================================================
     LOGIN
  ========================================================= */
  public static function attempt($email, $password) {

    $sql = "
      SELECT
        u.id,
        u.tenant_id,
        u.rol_id,
        u.nombre,
        u.email,
        u.pass_hash,
        u.estado,
        r.nombre AS rol_nombre
      FROM usuarios u
      INNER JOIN roles r
        ON r.id = u.rol_id
       AND r.tenant_id = u.tenant_id
      WHERE u.email = :email
      LIMIT 1
    ";
    $st = db()->prepare($sql);
    $st->execute([':email' => $email]);
    $u = $st->fetch();

    if (!$u) return [false, 'Usuario o contraseña incorrectos.'];
    if (($u['estado'] ?? '') !== 'ACTIVO') return [false, 'Usuario inactivo.'];

    $hash = (string)($u['pass_hash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
      return [false, 'Usuario o contraseña incorrectos.'];
    }

    // Tenant activo
    $t = db()->prepare("SELECT id, nombre, estado FROM tenants WHERE id = :id LIMIT 1");
    $t->execute([':id' => $u['tenant_id']]);
    $tenant = $t->fetch();
    if (!$tenant || ($tenant['estado'] ?? '') !== 'ACTIVO') {
      return [false, 'Cliente suspendido o no disponible.'];
    }

    // Guardar sesión consistente
    unset($u['pass_hash']);
    $_SESSION['user'] = $u;

    $_SESSION['user_id']    = (int)$u['id'];
    $_SESSION['tenant']     = $tenant;
    $_SESSION['tenant_id']  = (int)$tenant['id'];

    $_SESSION['rol_id']     = (int)$u['rol_id']; // CLAVE para evitar 403
    $_SESSION['rol_nombre'] = (string)($u['rol_nombre'] ?? '');

    // limpiar caches por seguridad
    unset($_SESSION['is_superadmin']);
    unset($_SESSION['perms']);

    // Detectar y cachear superadmin (y guardarlo también en user.es_superadmin)
    self::isSuperadmin();

    // Cargar permisos del rol
    self::loadPerms(true);

    return [true, 'OK'];
  }

  /* =========================================================
     PERMISOS
     rol_permisos: tenant_id, rol_id, permiso_codigo
  ========================================================= */
  public static function loadPerms($force = false) {
    if (!$force && isset($_SESSION['perms']) && is_array($_SESSION['perms'])) {
      return $_SESSION['perms'];
    }

    $tenantId = self::tenantId();
    $rolId    = self::rolId();

    if ($tenantId <= 0 || $rolId <= 0) {
      $_SESSION['perms'] = [];
      return $_SESSION['perms'];
    }

    $q = db()->prepare("
      SELECT permiso_codigo
      FROM rol_permisos
      WHERE tenant_id = :t AND rol_id = :r
    ");
    $q->execute([':t' => $tenantId, ':r' => $rolId]);

    $perms = [];
    foreach ($q->fetchAll() as $row) {
      $code = (string)($row['permiso_codigo'] ?? '');
      if ($code !== '') $perms[$code] = true;
    }

    $_SESSION['perms'] = $perms;
    return $perms;
  }

  public static function reloadPermissions() {
    return self::loadPerms(true);
  }

  public static function can($permisoCodigo) {
    if (!self::check()) return false;

    // ✅ Superadmin = acceso total (incluye menús y rutas)
    if (self::isSuperadmin()) return true;

    $permisoCodigo = (string)$permisoCodigo;
    if ($permisoCodigo === '') return false;

    $perms = self::loadPerms(false);
    return !empty($perms[$permisoCodigo]);
  }

  // Alias para tu router/vistas
  public static function requireCan($permisoCodigo) {
    return self::requirePerm($permisoCodigo);
  }

  public static function requirePerm($permisoCodigo) {
    if (self::can($permisoCodigo)) return;

    http_response_code(403);

    $perm = e((string)$permisoCodigo);
    $tid  = (int)self::tenantId();
    $rid  = (int)self::rolId();
    $cnt  = isset($_SESSION['perms']) && is_array($_SESSION['perms']) ? count($_SESSION['perms']) : 0;
    $sup  = self::isSuperadmin() ? 1 : 0;

    echo "
      <div style='max-width:720px;margin:40px auto;font-family:Arial,sans-serif'>
        <div style='border:1px solid #e5e7eb;border-left:6px solid #dc3545;padding:18px;border-radius:10px;background:#fff'>
          <h2 style='margin:0 0 10px 0'>403 - Acceso denegado</h2>
          <p style='margin:0 0 6px 0'>Tu rol no tiene permiso para acceder a esta sección.</p>
          <p style='margin:0;color:#6b7280'>Permiso requerido: <b>{$perm}</b></p>
          <hr style='border:none;border-top:1px solid #eee;margin:14px 0'>
          <p style='margin:0;color:#6b7280;font-size:12px'>
            Debug: tenant_id={$tid} · rol_id={$rid} · superadmin={$sup} · perms_cache={$cnt}
          </p>
          <div style='margin-top:14px'>
            <a href='index.php?route=logout' style='display:inline-block;padding:10px 14px;background:#6c757d;color:#fff;text-decoration:none;border-radius:8px;margin-right:8px'>
              Cerrar sesión
            </a>
            <a href='index.php?route=dashboard' style='display:inline-block;padding:10px 14px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:8px'>
              Volver al dashboard
            </a>
          </div>
        </div>
      </div>
    ";
    exit;
  }

  public static function logout() {
    $_SESSION = [];
    if (session_id() !== '' || isset($_COOKIE[session_name()])) {
      setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
  }
}
