-- GeoActivos - Esquema base (Multi-tenant) para MySQL/MariaDB
-- Importar en phpMyAdmin (XAMPP) en una BD llamada: geoactivos
-- Charset recomendado: utf8mb4

SET NAMES utf8mb4;
SET time_zone = "+00:00";

-- ==========================================
-- 0) Tenants (Clientes)
-- ==========================================
CREATE TABLE IF NOT EXISTS tenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  nit VARCHAR(30) DEFAULT NULL,
  email VARCHAR(120) DEFAULT NULL,
  telefono VARCHAR(40) DEFAULT NULL,
  direccion VARCHAR(180) DEFAULT NULL,
  ciudad VARCHAR(100) DEFAULT NULL,
  estado ENUM('ACTIVO','SUSPENDIDO') NOT NULL DEFAULT 'ACTIVO',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 1) Usuarios y Roles
-- ==========================================
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  nombre VARCHAR(50) NOT NULL,
  UNIQUE KEY uk_role (tenant_id, nombre),
  CONSTRAINT fk_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  rol_id INT NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  pass_hash VARCHAR(255) NOT NULL,
  estado ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_email (tenant_id, email),
  CONSTRAINT fk_usuarios_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 2) Catálogos básicos
-- ==========================================
CREATE TABLE IF NOT EXISTS sedes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  direccion VARCHAR(180) DEFAULT NULL,
  UNIQUE KEY uk_sede (tenant_id, nombre),
  CONSTRAINT fk_sedes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  sede_id INT DEFAULT NULL,
  nombre VARCHAR(120) NOT NULL,
  UNIQUE KEY uk_area (tenant_id, nombre),
  CONSTRAINT fk_areas_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_areas_sede FOREIGN KEY (sede_id) REFERENCES sedes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categorias_activo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  UNIQUE KEY uk_cat_act (tenant_id, nombre),
  CONSTRAINT fk_cat_act_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marcas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  UNIQUE KEY uk_marca (tenant_id, nombre),
  CONSTRAINT fk_marcas_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS proveedores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  nit VARCHAR(30) DEFAULT NULL,
  email VARCHAR(120) DEFAULT NULL,
  telefono VARCHAR(40) DEFAULT NULL,
  UNIQUE KEY uk_prov (tenant_id, nombre),
  CONSTRAINT fk_proveedores_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 3) Activos (Hoja de vida)
-- ==========================================
CREATE TABLE IF NOT EXISTS activos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  categoria_id INT NOT NULL,
  marca_id INT DEFAULT NULL,
  area_id INT DEFAULT NULL,
  proveedor_id INT DEFAULT NULL,

  codigo_interno VARCHAR(50) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  modelo VARCHAR(120) DEFAULT NULL,
  serial VARCHAR(120) DEFAULT NULL,
  placa VARCHAR(80) DEFAULT NULL,

  fecha_compra DATE DEFAULT NULL,
  fecha_instalacion DATE DEFAULT NULL,
  garantia_hasta DATE DEFAULT NULL,

  estado ENUM('ACTIVO','EN_MANTENIMIENTO','BAJA') NOT NULL DEFAULT 'ACTIVO',
  observaciones TEXT DEFAULT NULL,

  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_activo_codigo (tenant_id, codigo_interno),
  KEY idx_activo_serial (tenant_id, serial),

  CONSTRAINT fk_activos_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_activos_cat FOREIGN KEY (categoria_id) REFERENCES categorias_activo(id),
  CONSTRAINT fk_activos_marca FOREIGN KEY (marca_id) REFERENCES marcas(id),
  CONSTRAINT fk_activos_area FOREIGN KEY (area_id) REFERENCES areas(id),
  CONSTRAINT fk_activos_prov FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 4) Mantenimientos
-- ==========================================
CREATE TABLE IF NOT EXISTS mantenimientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  activo_id INT NOT NULL,
  tipo ENUM('PREVENTIVO','CORRECTIVO','PREDICTIVO') NOT NULL,
  estado ENUM('PROGRAMADO','EN_PROCESO','CERRADO','ANULADO') NOT NULL DEFAULT 'PROGRAMADO',

  fecha_programada DATE DEFAULT NULL,
  fecha_inicio DATETIME DEFAULT NULL,
  fecha_fin DATETIME DEFAULT NULL,

  prioridad ENUM('BAJA','MEDIA','ALTA','CRITICA') NOT NULL DEFAULT 'MEDIA',

  falla_reportada TEXT DEFAULT NULL,
  diagnostico TEXT DEFAULT NULL,
  actividades TEXT DEFAULT NULL,
  recomendaciones TEXT DEFAULT NULL,

  costo_mano_obra DECIMAL(12,2) NOT NULL DEFAULT 0,
  costo_repuestos DECIMAL(12,2) NOT NULL DEFAULT 0,

  creado_por INT DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_mant (tenant_id, activo_id, estado),
  CONSTRAINT fk_mant_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_mant_activo FOREIGN KEY (activo_id) REFERENCES activos(id),
  CONSTRAINT fk_mant_user FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mantenimiento_archivos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  mantenimiento_id INT NOT NULL,
  nombre_archivo VARCHAR(200) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  mime VARCHAR(80) DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mantarch_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_mantarch_mant FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 5) Repuestos e Inventario (kardex)
-- ==========================================
CREATE TABLE IF NOT EXISTS repuestos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  proveedor_id INT DEFAULT NULL,
  nombre VARCHAR(160) NOT NULL,
  referencia VARCHAR(120) DEFAULT NULL,
  unidad VARCHAR(40) DEFAULT NULL,
  stock_minimo DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock_actual DECIMAL(12,2) NOT NULL DEFAULT 0,
  costo_promedio DECIMAL(12,2) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_rep (tenant_id, nombre, referencia),
  CONSTRAINT fk_rep_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_rep_prov FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kardex (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  repuesto_id INT NOT NULL,
  tipo ENUM('ENTRADA','SALIDA') NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL,
  costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
  referencia_doc VARCHAR(120) DEFAULT NULL,
  nota VARCHAR(255) DEFAULT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_id INT DEFAULT NULL,
  KEY idx_kardex (tenant_id, repuesto_id, fecha),
  CONSTRAINT fk_kardex_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_kardex_rep FOREIGN KEY (repuesto_id) REFERENCES repuestos(id),
  CONSTRAINT fk_kardex_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mantenimiento_repuestos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  mantenimiento_id INT NOT NULL,
  repuesto_id INT NOT NULL,
  cantidad DECIMAL(12,2) NOT NULL,
  costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
  KEY idx_mr (tenant_id, mantenimiento_id),
  CONSTRAINT fk_mr_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_mr_mant FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id),
  CONSTRAINT fk_mr_rep FOREIGN KEY (repuesto_id) REFERENCES repuestos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
