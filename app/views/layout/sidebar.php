<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';

$current = $_GET['route'] ?? 'dashboard';
function is_active($r, $cur){ return $r === $cur ? 'active' : ''; }
function is_open($routes, $cur){ return in_array($cur, $routes, true) ? 'menu-open' : ''; }

function can_perm($code){
  // SUPERADMIN bypass (si está en sesión)
  $isSuper = (int)($_SESSION['user']['es_superadmin'] ?? 0);
  if ($isSuper === 1) return true;

  if (class_exists('Auth') && method_exists('Auth','can')) {
    return Auth::can($code);
  }
  return true;
}

$tenantNombre  = $_SESSION['user']['tenant_nombre'] ?? ($_SESSION['tenant']['nombre'] ?? 'Cliente');
$usuarioNombre = $_SESSION['user']['nombre'] ?? 'Administrador';

$dashboardRoutes = ['dashboard'];

$activosRoutes = ['activos','activos_form','activo_detalle'];
$papeleraRoutes = ['activos_eliminados','activos_restore','activos_purge'];

$mantenimientosRoutes = ['mantenimientos','mantenimiento_form','mantenimiento_detalle','mantenimiento_ver'];
$auditoriaRoutes = ['audit_log','activo_auditoria'];

$confRoutes = [
  'categorias','categoria_form',
  'marcas','marca_form',
  'sedes','sede_form',
  'areas','area_form',
  'proveedores','proveedor_form',
  'tipos_activo','tipo_activo_form'
];

$secRoutes = [
  'empresas','empresa_form',
  'usuarios','usuario_form',
  'roles','rol_form','rol_permisos','rol_delete'
];

// Calibraciones
$calibracionesRoutes = ['calibraciones','calibracion_form','calibracion_detalle','calibracion_print'];
$patronesRoutes = ['patrones','patron_form','patron_delete'];

$showConfig = can_perm('config.view') || can_perm('config.manage');
$showAuditoria = can_perm('auditoria.view') || can_perm('auditoria.manage') || can_perm('dashboard.view');

$showSecurity =
  can_perm('empresas.view') || can_perm('empresas.edit') ||
  can_perm('usuarios.view') || can_perm('usuarios.edit') ||
  can_perm('roles.view') || can_perm('roles.edit') || can_perm('roles.permisos');

$showCalibraciones =
  can_perm('calibraciones.view') || can_perm('calibraciones.edit') || can_perm('calibraciones.manage');

$showPatrones =
  can_perm('patrones.view') || can_perm('patrones.edit') || can_perm('patrones.manage');
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">

  <a href="<?= e(base_url()) ?>/index.php?route=dashboard" class="brand-link" style="padding:.9rem 1rem;">
    <span class="brand-image img-circle elevation-2 d-inline-flex align-items-center justify-content-center"
          style="width:34px;height:34px;background:rgba(0,123,255,.15);">
      <i class="fas fa-cubes text-primary" style="font-size:16px;"></i>
    </span>

    <span class="brand-text font-weight-bold" style="letter-spacing:.25px;display:block;line-height:1.05;">
      GeoActivos
    </span>

    <span class="brand-text font-weight-light text-sm d-block text-muted" style="line-height:1.2;margin-top:2px;">
      GeSaProv Project Design · Multi-tenant
    </span>
  </a>

  <div class="sidebar">

    <div class="user-panel mt-3 pb-3 mb-2 d-flex align-items-center">
      <div class="image">
        <span class="img-circle elevation-2 d-inline-flex align-items-center justify-content-center"
              style="width:36px;height:36px;background:rgba(255,255,255,.08);">
          <i class="fas fa-user text-light" style="font-size:16px;opacity:.9;"></i>
        </span>
      </div>

      <div class="info" style="line-height:1.15;min-width:0;">
        <a href="#" class="d-block" style="font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:175px;">
          <?= e($usuarioNombre) ?>
        </a>
        <div class="text-muted text-sm" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:175px;">
          <i class="fas fa-circle text-success" style="font-size:8px;"></i>
          <span class="ml-1"><?= e($tenantNombre) ?></span>
        </div>
      </div>
    </div>

    <div style="height:1px;background:rgba(255,255,255,.06);margin:0 12px 10px;"></div>

    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column nav-compact nav-legacy nav-flat"
          data-widget="treeview" role="menu" data-accordion="false">

        <li class="nav-header">PRINCIPAL</li>

        <?php if (can_perm('dashboard.view')): ?>
        <li class="nav-item">
          <a href="<?= e(base_url()) ?>/index.php?route=dashboard"
             class="nav-link <?= in_array($current,$dashboardRoutes,true) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-chart-pie"></i>
            <p>Dashboard</p>
          </a>
        </li>
        <?php endif; ?>

        <?php if (can_perm('activos.view')): ?>
        <li class="nav-item">
          <a href="<?= e(base_url()) ?>/index.php?route=activos"
             class="nav-link <?= in_array($current,$activosRoutes,true) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-laptop"></i>
            <p>Activos <span class="right badge badge-light" style="opacity:.9;">INV</span></p>
          </a>
        </li>
        <?php endif; ?>

        <?php if (can_perm('activos.view')): ?>
        <li class="nav-item">
          <a href="<?= e(base_url()) ?>/index.php?route=activos_eliminados"
             class="nav-link <?= in_array($current,$papeleraRoutes,true) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-trash-restore"></i>
            <p>Papelera</p>
          </a>
        </li>
        <?php endif; ?>

        <li class="nav-header">OPERACIÓN</li>

        <?php if (can_perm('mantenimientos.view')): ?>
        <li class="nav-item">
          <a href="<?= e(base_url()) ?>/index.php?route=mantenimientos"
             class="nav-link <?= in_array($current,$mantenimientosRoutes,true) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-tools"></i>
            <p>Mantenimientos <span class="right badge badge-warning" style="opacity:.95;">PRO</span></p>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($showCalibraciones): ?>
        <li class="nav-item">
          <a href="<?= e(base_url()) ?>/index.php?route=calibraciones"
             class="nav-link <?= in_array($current,$calibracionesRoutes,true) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-ruler-combined"></i>
            <p>Calibraciones <span class="right badge badge-info" style="opacity:.95;">BIO</span></p>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($showPatrones): ?>
        <li class="nav-item">
          <a href="<?= e(base_url()) ?>/index.php?route=patrones"
             class="nav-link <?= in_array($current,$patronesRoutes,true) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-balance-scale"></i>
            <p>Patrones <span class="right badge badge-light" style="opacity:.95;">LAB</span></p>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($showAuditoria): ?>
        <li class="nav-item">
          <a href="<?= e(base_url()) ?>/index.php?route=audit_log"
             class="nav-link <?= in_array($current,$auditoriaRoutes,true) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-clipboard-list"></i>
            <p>Auditoría</p>
          </a>
        </li>
        <?php endif; ?>

        <?php if ($showConfig): ?>
          <li class="nav-header">CONFIGURACIÓN</li>

          <li class="nav-item <?= is_open($confRoutes,$current) ?>">
            <a href="#" class="nav-link <?= in_array($current,$confRoutes,true) ? 'active' : '' ?>">
              <i class="nav-icon fas fa-sliders-h"></i>
              <p>Parámetros técnicos <i class="right fas fa-angle-left"></i></p>
            </a>

            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="<?= e(base_url()) ?>/index.php?route=categorias"
                   class="nav-link <?= is_active('categorias',$current) ?> <?= is_active('categoria_form',$current) ?>">
                  <i class="nav-icon far fa-dot-circle"></i>
                  <p>Categorías</p>
                </a>
              </li>

              <li class="nav-item">
                <a href="<?= e(base_url()) ?>/index.php?route=marcas"
                   class="nav-link <?= is_active('marcas',$current) ?> <?= is_active('marca_form',$current) ?>">
                  <i class="nav-icon far fa-dot-circle"></i>
                  <p>Marcas</p>
                </a>
              </li>

              <li class="nav-item">
                <a href="<?= e(base_url()) ?>/index.php?route=tipos_activo"
                   class="nav-link <?= is_active('tipos_activo',$current) ?> <?= is_active('tipo_activo_form',$current) ?>">
                  <i class="nav-icon far fa-dot-circle"></i>
                  <p>Tipos de activo</p>
                </a>
              </li>

              <li class="nav-item">
                <a href="<?= e(base_url()) ?>/index.php?route=sedes"
                   class="nav-link <?= is_active('sedes',$current) ?> <?= is_active('sede_form',$current) ?>">
                  <i class="nav-icon far fa-dot-circle"></i>
                  <p>Sedes</p>
                </a>
              </li>

              <li class="nav-item">
                <a href="<?= e(base_url()) ?>/index.php?route=areas"
                   class="nav-link <?= is_active('areas',$current) ?> <?= is_active('area_form',$current) ?>">
                  <i class="nav-icon far fa-dot-circle"></i>
                  <p>Áreas</p>
                </a>
              </li>

              <li class="nav-item">
                <a href="<?= e(base_url()) ?>/index.php?route=proveedores"
                   class="nav-link <?= is_active('proveedores',$current) ?> <?= is_active('proveedor_form',$current) ?>">
                  <i class="nav-icon far fa-dot-circle"></i>
                  <p>Proveedores</p>
                </a>
              </li>
            </ul>
          </li>
        <?php endif; ?>

        <?php if ($showSecurity): ?>
          <li class="nav-header">ADMINISTRACIÓN</li>

          <li class="nav-item <?= is_open($secRoutes,$current) ?>">
            <a href="#" class="nav-link <?= in_array($current,$secRoutes,true) ? 'active' : '' ?>">
              <i class="nav-icon fas fa-user-shield"></i>
              <p>Seguridad <i class="right fas fa-angle-left"></i></p>
            </a>

            <ul class="nav nav-treeview">

              <?php if (can_perm('empresas.view') || can_perm('empresas.edit')): ?>
              <li class="nav-item">
                <a href="<?= e(base_url()) ?>/index.php?route=empresas"
                   class="nav-link <?= is_active('empresas',$current) ?> <?= is_active('empresa_form',$current) ?>">
                  <i class="nav-icon far fa-dot-circle"></i>
                  <p>Empresas</p>
                </a>
              </li>
              <?php endif; ?>

              <?php if (can_perm('usuarios.view') || can_perm('usuarios.edit')): ?>
              <li class="nav-item">
                <a href="<?= e(base_url()) ?>/index.php?route=usuarios"
                   class="nav-link <?= is_active('usuarios',$current) ?> <?= is_active('usuario_form',$current) ?>">
                  <i class="nav-icon far fa-dot-circle"></i>
                  <p>Usuarios</p>
                </a>
              </li>
              <?php endif; ?>

              <?php if (can_perm('roles.view') || can_perm('roles.edit') || can_perm('roles.permisos')): ?>
              <li class="nav-item">
                <a href="<?= e(base_url()) ?>/index.php?route=roles"
                   class="nav-link <?= is_active('roles',$current) ?> <?= is_active('rol_form',$current) ?> <?= is_active('rol_permisos',$current) ?>">
                  <i class="nav-icon far fa-dot-circle"></i>
                  <p>Roles y permisos</p>
                </a>
              </li>
              <?php endif; ?>

            </ul>
          </li>
        <?php endif; ?>

        <li class="nav-item mt-2 mb-1">
          <div style="height:1px;background:rgba(255,255,255,.08);margin:0 12px;"></div>
        </li>

        <li class="nav-item">
          <a href="<?= e(base_url()) ?>/index.php?route=logout" class="nav-link text-danger">
            <i class="nav-icon fas fa-sign-out-alt"></i>
            <p>Cerrar sesión</p>
          </a>
        </li>

        <li class="nav-item mt-2" style="padding:0 12px;">
          <div class="text-muted text-xs" style="opacity:.85;line-height:1.2;">
            <div><b>GeoActivos</b> · PRO</div>
            <div>GeSaProv Project Design · v1</div>
          </div>
        </li>

      </ul>
    </nav>
  </div>
</aside>
