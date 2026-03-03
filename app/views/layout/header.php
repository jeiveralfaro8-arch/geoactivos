<?php
// app/views/layout/header.php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';

Auth::requireLogin();

$route = (string)($_GET['route'] ?? 'dashboard');

$userNombre  = (string)($_SESSION['user']['nombre'] ?? 'Usuario');
$userEmail   = (string)($_SESSION['user']['email'] ?? '');
$rolNombre   = (string)($_SESSION['rol_nombre'] ?? ($_SESSION['user']['rol_nombre'] ?? ''));
$tenantNombre= (string)($_SESSION['tenant']['nombre'] ?? ($_SESSION['user']['tenant_nombre'] ?? 'Cliente'));

$brandMain   = 'GeoActivos';
$brandSub    = 'GeSaProv Project Design';
$badgePlan   = 'PRO'; // si luego manejas planes por tenant, aquí lo conectas

// Breadcrumb simple por ruta (puedes ampliar)
$map = [
  'dashboard'            => ['Dashboard'],
  'activos'              => ['Activos'],
  'activos_form'         => ['Activos', 'Formulario'],
  'activo_detalle'       => ['Activos', 'Hoja de vida'],
  'mantenimientos'       => ['Mantenimientos'],
  'mantenimiento_form'   => ['Mantenimientos', 'Formulario'],
  'mantenimiento_detalle'=> ['Mantenimientos', 'Detalle'],
  'mantenimiento_ver'    => ['Mantenimientos', 'Detalle'],
  'categorias'           => ['Configuración', 'Categorías'],
  'categoria_form'       => ['Configuración', 'Categorías', 'Formulario'],
  'marcas'               => ['Configuración', 'Marcas'],
  'marca_form'           => ['Configuración', 'Marcas', 'Formulario'],
  'sedes'                => ['Configuración', 'Sedes'],
  'sede_form'            => ['Configuración', 'Sedes', 'Formulario'],
  'areas'                => ['Configuración', 'Áreas'],
  'area_form'            => ['Configuración', 'Áreas', 'Formulario'],
  'proveedores'          => ['Configuración', 'Proveedores'],
  'proveedor_form'       => ['Configuración', 'Proveedores', 'Formulario'],
  'tipos_activo'         => ['Configuración', 'Tipos de activo'],
  'tipo_activo_form'     => ['Configuración', 'Tipos de activo', 'Formulario'],
  'empresas'             => ['Administración', 'Empresas'],
  'empresa_form'         => ['Administración', 'Empresas', 'Formulario'],
  'usuarios'             => ['Administración', 'Usuarios'],
  'usuario_form'         => ['Administración', 'Usuarios', 'Formulario'],
  'roles'                => ['Administración', 'Roles y permisos'],
  'rol_form'             => ['Administración', 'Roles y permisos', 'Formulario'],
  'rol_permisos'         => ['Administración', 'Roles y permisos', 'Permisos'],
];

$crumbs = $map[$route] ?? ['Módulo'];
$pageTitle = end($crumbs);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · <?= e($brandMain) ?></title>

  <!-- AdminLTE 3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
  <link rel="stylesheet" href="<?= e(base_url()) ?>/assets/css/custom.css">
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light navbar-pro">

    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button" title="Menú">
          <i class="fas fa-bars"></i>
        </a>
      </li>

      <li class="nav-item d-none d-sm-inline-block">
        <span class="nav-link" style="padding-top:.35rem;padding-bottom:.35rem;">
          <span class="brand-chip">
            <span class="logo"><i class="fas fa-cubes"></i></span>
            <span class="txt">
              <div class="main"><?= e($brandMain) ?></div>
              <div class="sub"><?= e($brandSub) ?></div>
            </span>
            <span class="plan-badge"><?= e($badgePlan) ?></span>
          </span>
        </span>
      </li>
    </ul>

    <!-- Center: Tenant -->
    <div class="navbar-nav ml-auto mr-auto d-none d-md-flex">
      <div class="tenant-pill" title="Cliente / Tenant">
        <span class="dot"></span>
        <span class="name"><?= e($tenantNombre) ?></span>
      </div>
    </div>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

      <!-- Fullscreen -->
      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button" title="Pantalla completa">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>

      <!-- Notifications (placeholder PRO) -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#" title="Notificaciones">
          <i class="far fa-bell"></i>
          <span class="badge badge-warning navbar-badge" style="opacity:.75;">!</span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-item dropdown-header">Notificaciones</span>
          <div class="dropdown-divider"></div>
          <span class="dropdown-item text-sm text-muted">
            <i class="far fa-clock mr-2"></i> Próximamente: alertas de mantenimientos
          </span>
          <div class="dropdown-divider"></div>
          <a href="<?= e(base_url()) ?>/index.php?route=dashboard" class="dropdown-item dropdown-footer">
            Ir al dashboard
          </a>
        </div>
      </li>

      <!-- User -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#" style="padding-top:.25rem;padding-bottom:.25rem;">
          <span class="user-mini">
            <span class="avatar"><i class="fas fa-user"></i></span>
            <span class="meta">
              <div class="n"><?= e($userNombre) ?></div>
              <div class="r"><?= e($rolNombre ?: '—') ?></div>
            </span>
            <i class="fas fa-angle-down" style="opacity:.65;"></i>
          </span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <span class="dropdown-item-text text-sm">
            <div style="font-weight:900;"><?= e($userNombre) ?></div>
            <?php if ($userEmail !== ''): ?>
              <div class="text-muted"><?= e($userEmail) ?></div>
            <?php endif; ?>
            <div class="text-muted">Tenant: <b><?= e($tenantNombre) ?></b></div>
          </span>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="<?= e(base_url()) ?>/index.php?route=dashboard">
            <i class="fas fa-home mr-2"></i> Dashboard
          </a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item text-danger" href="<?= e(base_url()) ?>/index.php?route=logout">
            <i class="fas fa-sign-out-alt mr-2"></i> Cerrar sesión
          </a>
        </div>
      </li>

    </ul>
  </nav>
  <!-- /.navbar -->

  <!-- Content Wrapper -->
  <div class="content-wrapper">

    <!-- Content Header (Page header) -->
    <div class="content-header content-header-pro">
      <div class="container-fluid">
        <div class="d-flex align-items-start align-items-md-center justify-content-between flex-wrap">
          <div class="mb-2 mb-md-0">
            <h1 class="page-title"><?= e($pageTitle) ?></h1>

            <ol class="breadcrumb breadcrumb-pro">
              <li class="breadcrumb-item">
                <a href="<?= e(base_url()) ?>/index.php?route=dashboard">
                  <i class="fas fa-home"></i>
                </a>
              </li>
              <?php
                // render crumbs
                if ($crumbs) {
                  $last = count($crumbs) - 1;
                  foreach ($crumbs as $i => $c) {
                    if ($i === $last) {
                      echo '<li class="breadcrumb-item active">' . e($c) . '</li>';
                    } else {
                      echo '<li class="breadcrumb-item">' . e($c) . '</li>';
                    }
                  }
                }
              ?>
            </ol>
          </div>

          <div class="text-muted text-sm">
            <i class="fas fa-shield-alt mr-1"></i> <?= e($brandSub) ?> ·
            <span class="ml-1"><i class="fas fa-check-circle text-success"></i> Sistema estable</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
