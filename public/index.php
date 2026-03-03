<?php
session_start();

/* ===================== DEV ===================== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ===================== CORE ===================== */
require_once __DIR__ . '/../app/core/Helpers.php';
require_once __DIR__ . '/../app/core/Auth.php';

/* ===================== ROUTE ===================== */
$route = $_GET['route'] ?? 'dashboard';

/* ===================== RUTAS PÚBLICAS (NO LOGIN) ===================== 
   FIX REAL: el QR debe abrir verificación SIN pedir login
   - calibracion_verificar: página pública por token
   Puedes agregar más rutas públicas aquí cuando lo necesites
=================================================== */
$publicRoutes = [
  'login',
  'logout', // (logout realmente redirige, pero lo dejamos)
  'calibracion_verificar',
];

/* ===================== AUTH ===================== */
if ($route === 'login') {
  require __DIR__ . '/../app/views/auth/login.php';
  exit;
}

if ($route === 'logout') {
  Auth::logout();
  redirect('index.php?route=login');
}

/* ===================== PROTECCIÓN GLOBAL ===================== 
   FIX REAL: solo pedimos login si NO es ruta pública
=================================================== */
if (!in_array($route, $publicRoutes, true)) {
  Auth::requireLogin();
}

/* ===================== ROUTER ===================== */
switch ($route) {

  /* ================= DASHBOARD ================= */
  case 'dashboard':
    Auth::requirePerm('dashboard.view');
    require __DIR__ . '/../app/views/dashboard/index.php';
    break;

  /* ================= ACTIVOS ================= */
  case 'activos':
    Auth::requirePerm('activos.view');
    require __DIR__ . '/../app/views/activos/index.php';
    break;

  case 'activos_form':
    Auth::requirePerm('activos.edit');
    require __DIR__ . '/../app/views/activos/form.php';
    break;

  case 'activo_detalle':
    Auth::requirePerm('activos.view');
    require __DIR__ . '/../app/views/activos/detalle.php';
    break;

  case 'activo_software':
    Auth::requirePerm('activos.edit');
    require __DIR__ . '/../app/views/activos/software.php';
    break;

  case 'activo_software_delete':
    Auth::requirePerm('activos.edit');
    require __DIR__ . '/../app/views/activos/software_delete.php';
    break;

  case 'activo_hoja_vida':
    Auth::requirePerm('activos.edit');
    require __DIR__ . '/../app/views/activos/activo_hoja_vida.php';
    break;

  case 'activo_hoja_vida_print':
    Auth::requirePerm('activos.edit');
    require __DIR__ . '/../app/views/activos/activo_hoja_vida_print.php';
    break;

  case 'activos_delete':
    require __DIR__ . '/../app/views/activos/activos_delete.php';
    break;

  case 'activos_eliminados':
    require __DIR__ . '/../app/views/activos/activos_eliminados.php';
    break;

  case 'activos_restore':
    require __DIR__ . '/../app/views/activos/activos_restore.php';
    break;

  case 'activos_purge':
    require __DIR__ . '/../app/views/activos/activos_purge.php';
    break;

  case 'audit_log':
    require __DIR__ . '/../app/views/auditoria/audit_log.php';
    break;

  case 'activo_auditoria':
    require __DIR__ . '/../app/views/auditoria/activo_auditoria.php';
    break;

  case 'activo_auditoria':
    require __DIR__ . '/../app/views/auditoria/timeline.php';
    break;

  case 'auditoria_usuario':
    require __DIR__ . '/../app/views/auditoria/auditoria_usuario.php';
    break;

  case 'activo_auditoria':
    require __DIR__ . '/../app/views/activos/activo_auditoria.php';
    break;

  case 'mantenimiento_auditoria':
    require __DIR__ . '/../app/views/mantenimientos/mantenimiento_auditoria.php';
    break;

  case 'componente_form':
    require __DIR__ . '/../app/views/componentes/componente_form.php';
    break;

  case 'componente_delete':
    require __DIR__ . '/../app/actions/componentes/delete.php';
    break;

  // Etiqueta QR imprimible (sticker)
  case 'activo_qr_etiqueta':
    require __DIR__ . '/../app/views/activos/activo_qr_etiqueta.php';
    break;

  // (Opcional) Alias rápido para abrir hoja de vida desde QR
  case 'activo_qr':
    // redirige a hoja de vida (o detalle)
    $id = (int)($_GET['id'] ?? 0);
    header("Location: index.php?route=activo_hoja_vida&id=".$id);
    exit;

  case 'mantenimiento_print':
    require __DIR__ . '/../app/views/mantenimientos/mantenimiento_print.php';
    break;

  case 'ajax_act_foto_upload':
    require __DIR__ . '/../app/ajax/act_foto_upload.php';
    exit;

  case 'ajax_act_foto_delete':
    require_once __DIR__ . '/../app/ajax/act_foto_delete.php';
    exit;

  case 'ajax_act_adj_download':
    require __DIR__ . '/../app/ajax/act_adj_download.php';
    exit;

  case 'ajax_act_adj_preview':
    require __DIR__ . '/../app/ajax/act_adj_preview.php';
    exit;

  // ===== Calibraciones =====
  case 'calibraciones':
    require_once __DIR__ . '/../app/views/calibraciones/index.php';
    exit;

  case 'calibracion_form':
    require_once __DIR__ . '/../app/views/calibraciones/form.php';
    exit;

  case 'calibracion_detalle':
    require_once __DIR__ . '/../app/views/calibraciones/detalle.php';
    exit;

  case 'calibracion_certificado':
    require __DIR__ . '/../app/views/calibraciones/certificado_print.php';
    break;

  // ✅ NUEVO: Editar certificado (corrección rápida)
  case 'calibracion_certificado_edit':
    require __DIR__ . '/../app/views/calibraciones/certificado_edit.php';
    break;


  // ===== Calibraciones: puntos =====
case 'calibracion_puntos':
  require __DIR__ . '/../app/views/calibraciones/puntos.php';
  break;

case 'calibracion_punto_form':
  require __DIR__ . '/../app/views/calibraciones/punto_form.php';
  break;
  
  // ===== Patrones =====
  case 'patrones':
    require_once __DIR__ . '/../app/views/patrones/index.php';
    exit;

  case 'patron_form':
    require_once __DIR__ . '/../app/views/patrones/form.php';
    exit;

  case 'patron_delete':
    require_once __DIR__ . '/../app/views/patrones/delete.php';
    exit;

  // ===== AJAX Calibraciones (puntos + patrones + cierre) =====
  case 'ajax_cal_punto_add':
    require_once __DIR__ . '/../app/ajax/ajax_cal_punto_add.php';
    exit;

  case 'ajax_cal_punto_delete':
    require_once __DIR__ . '/../app/ajax/ajax_cal_punto_delete.php';
    exit;

  case 'ajax_cal_patron_add':
    require_once __DIR__ . '/../app/ajax/ajax_cal_patron_add.php';
    exit;

  case 'ajax_cal_patron_delete':
    require_once __DIR__ . '/../app/ajax/ajax_cal_patron_delete.php';
    exit;

  case 'ajax_cal_cerrar':
    require_once __DIR__ . '/../app/ajax/ajax_cal_cerrar.php';
    exit;

  case 'ajax_cal_anular':
    require_once __DIR__ . '/../app/ajax/ajax_cal_anular.php';
    exit;

  /* ✅ ESTE ES EL FIX REAL: ejecutar TU archivo real app/ajax/patron_puntos.php */
  case 'patron_puntos_ajax':
    require __DIR__ . '/../app/ajax/patron_puntos.php';
    exit;

  // ===== Calibraciones (pública) =====
  case 'calibracion_verificar':
    // FIX REAL: esta ruta es pública (sin login) por token
    require_once __DIR__ . '/../app/views/calibraciones/verificar.php';
    exit;

  // ===== Adjuntos calibración (AJAX) =====
  case 'ajax_cal_adj_upload':
    require_once __DIR__ . '/../app/ajax/ajax_cal_adj_upload.php';
    exit;

  case 'ajax_cal_adj_preview':
    require_once __DIR__ . '/../app/ajax/ajax_cal_adj_preview.php';
    exit;

  case 'ajax_cal_adj_download':
    require_once __DIR__ . '/../app/ajax/ajax_cal_adj_download.php';
    exit;

  case 'ajax_cal_adj_delete':
    require_once __DIR__ . '/../app/ajax/ajax_cal_adj_delete.php';
    exit;

  /* ================= MANTENIMIENTOS ================= */
  case 'mantenimientos':
    Auth::requirePerm('mantenimientos.view');
    require __DIR__ . '/../app/views/mantenimientos/index.php';
    break;

  case 'mantenimiento_form':
    Auth::requirePerm('mantenimientos.edit');
    require __DIR__ . '/../app/views/mantenimientos/form.php';
    break;

  case 'mantenimiento_ver':
    Auth::requirePerm('mantenimientos.view');
    require __DIR__ . '/../app/views/mantenimientos/ver.php';
    break;

  case 'mantenimiento_log_add':
    Auth::requirePerm('mantenimientos.edit');
    require __DIR__ . '/../app/views/mantenimientos/log_add.php';
    break;

  case 'mantenimiento_detalle':
    Auth::requirePerm('mantenimientos.view');
    require __DIR__ . '/../app/views/mantenimientos/detalle.php';
    break;

  /* ================= CONFIGURACIÓN ================= */
  case 'categorias':
  case 'categoria_form':
  case 'marcas':
  case 'marca_form':
  case 'sedes':
  case 'sede_form':
  case 'areas':
  case 'area_form':
  case 'proveedores':
  case 'proveedor_form':
  case 'tipos_activo':
  case 'tipo_activo_form':
    Auth::requirePerm('config.view');
    require __DIR__ . '/../app/views/' . match($route) {
      'categorias'        => 'config/categorias/index.php',
      'categoria_form'    => 'config/categorias/form.php',
      'marcas'            => 'config/marcas/index.php',
      'marca_form'        => 'config/marcas/form.php',
      'sedes'             => 'config/sedes/index.php',
      'sede_form'         => 'config/sedes/form.php',
      'areas'             => 'config/areas/index.php',
      'area_form'         => 'config/areas/form.php',
      'proveedores'       => 'config/proveedores/index.php',
      'proveedor_form'    => 'config/proveedores/form.php',
      'tipos_activo'      => 'config/tipos_activo/index.php',
      'tipo_activo_form'  => 'config/tipos_activo/form.php',
    };
    break;

  /* ================= EMPRESAS ================= */
  case 'empresas':
    Auth::requirePerm('empresas.view');
    require __DIR__ . '/../app/views/empresas/index.php';
    break;

  case 'empresa_form':
    Auth::requirePerm('empresas.view');
    require __DIR__ . '/../app/views/empresas/form.php';
    break;

  /* ================= USUARIOS ================= */
  case 'usuarios':
    Auth::requirePerm('usuarios.view');
    require __DIR__ . '/../app/views/usuarios/index.php';
    break;

  case 'usuario_form':
    Auth::requirePerm('usuarios.edit');
    require __DIR__ . '/../app/views/usuarios/form.php';
    break;

  /* ================= ROLES ================= */
  case 'roles':
    Auth::requirePerm('roles.view');
    require __DIR__ . '/../app/views/roles/index.php';
    break;

  case 'rol_form':
  case 'rol_delete':
  case 'rol_permisos':
    Auth::requirePerm('roles.edit');
    require __DIR__ . '/../app/views/roles/' . str_replace('rol_', '', $route) . '.php';
    break;

  /* ================= AJAX ================= */
  case 'ajax_next_codigo_activo':
    Auth::requirePerm('activos.edit');
    require __DIR__ . '/../app/ajax/next_codigo_activo.php';
    break;

  case 'ajax_tipo_reglas':
    Auth::requirePerm('config.view');
    require __DIR__ . '/../app/ajax/tipo_reglas.php';
    break;

  case 'ajax_act_adj_upload':
    Auth::requirePerm('activos.edit');
    require __DIR__ . '/../app/ajax/activos_adj_upload.php';
    break;

  case 'ajax_act_adj_download':
  case 'ajax_act_adj_preview':
    Auth::requirePerm('activos.view');
    require __DIR__ . '/../app/ajax/' . str_replace('ajax_', '', $route) . '.php';
    break;

  case 'ajax_act_adj_delete':
    Auth::requirePerm('activos.edit');
    require __DIR__ . '/../app/ajax/activos_adj_delete.php';
    break;

  case 'ajax_mant_adj_upload':
    Auth::requirePerm('mantenimientos.edit');
    require __DIR__ . '/../app/ajax/mant_adj_upload.php';
    break;

  case 'ajax_mant_adj_download':
    Auth::requirePerm('mantenimientos.view');
    require __DIR__ . '/../app/ajax/mant_adj_download.php';
    break;

  case 'ajax_mant_adj_delete':
    Auth::requirePerm('mantenimientos.edit');
    require __DIR__ . '/../app/ajax/mant_adj_delete.php';
    break;

  /* ================= 404 ================= */
  default:
    http_response_code(404);
    echo "404 - Ruta no encontrada";
}
