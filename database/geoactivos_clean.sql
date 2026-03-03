-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 21-01-2026 a las 04:02:22
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `geoactivos_clean`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activos`
--

CREATE TABLE `activos` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `tipo_activo_id` int(11) DEFAULT NULL,
  `activo_padre_id` int(11) DEFAULT NULL,
  `es_componente` tinyint(1) NOT NULL DEFAULT 0,
  `marca_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `codigo_interno` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `hostname` varchar(120) DEFAULT NULL,
  `usa_dhcp` tinyint(1) NOT NULL DEFAULT 1,
  `ip_fija` varchar(45) DEFAULT NULL,
  `mac` varchar(32) DEFAULT NULL,
  `biomedico` tinyint(1) DEFAULT NULL,
  `requiere_calibracion` tinyint(1) DEFAULT NULL,
  `periodicidad_meses` int(11) DEFAULT NULL,
  `prox_calibracion` date DEFAULT NULL,
  `foto_path` varchar(255) DEFAULT NULL,
  `foto_mime` varchar(100) DEFAULT NULL,
  `foto_updated_en` datetime DEFAULT NULL,
  `modelo` varchar(120) DEFAULT NULL,
  `serial` varchar(120) DEFAULT NULL,
  `placa` varchar(80) DEFAULT NULL,
  `fecha_compra` date DEFAULT NULL,
  `fecha_instalacion` date DEFAULT NULL,
  `garantia_hasta` date DEFAULT NULL,
  `estado` enum('ACTIVO','EN_MANTENIMIENTO','BAJA') NOT NULL DEFAULT 'ACTIVO',
  `observaciones` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `eliminado_en` datetime DEFAULT NULL,
  `eliminado_por` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `activos`
--

INSERT INTO `activos` (`id`, `tenant_id`, `categoria_id`, `tipo_activo_id`, `activo_padre_id`, `es_componente`, `marca_id`, `area_id`, `proveedor_id`, `codigo_interno`, `nombre`, `hostname`, `usa_dhcp`, `ip_fija`, `mac`, `biomedico`, `requiere_calibracion`, `periodicidad_meses`, `prox_calibracion`, `foto_path`, `foto_mime`, `foto_updated_en`, `modelo`, `serial`, `placa`, `fecha_compra`, `fecha_instalacion`, `garantia_hasta`, `estado`, `observaciones`, `creado_en`, `eliminado`, `eliminado_en`, `eliminado_por`) VALUES
(13, 1, 1, 16, NULL, 0, 1, 1, 1, 'PC-0001', 'PC Recepción', 'PC-REC-01', 0, '192.168.1.20', 'AA:BB:CC:DD:EE:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ProDesk', 'HP-SN-001', 'INV-0001', '2025-10-10', '2025-10-15', '2026-10-10', 'ACTIVO', 'Equipo de atención al usuario', '2026-01-14 21:30:37', 0, NULL, NULL),
(14, 1, 1, 18, 13, 1, 2, 1, 1, 'MON-0001', 'Monitor 22\"', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '22\"', 'DELL-MON-001', NULL, '2025-10-10', NULL, NULL, 'ACTIVO', 'Componente de PC-0001', '2026-01-14 21:30:37', 0, NULL, NULL),
(15, 1, 1, 19, 13, 1, 3, 1, 1, 'TEC-0001', 'Teclado', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'USB', 'LEN-TEC-001', NULL, '2025-10-10', NULL, NULL, 'ACTIVO', 'Componente de PC-0001', '2026-01-14 21:30:37', 0, NULL, NULL),
(16, 1, 1, 20, 13, 1, 3, 1, 1, 'MOU-0001', 'Mouse', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'USB', 'LEN-MOU-001', NULL, '2025-10-10', NULL, NULL, 'ACTIVO', 'Componente de PC-0001', '2026-01-14 21:30:37', 0, NULL, NULL),
(17, 1, 6, 17, NULL, 0, 2, 1, 1, 'SRV-0001', 'Servidor Principal', 'SRV-CCJN-01', 0, '192.168.1.10', 'AA:BB:CC:DD:EE:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PowerEdge', 'DELL-SRV-001', 'INV-0100', '2024-08-01', '2024-08-05', '2027-08-01', 'ACTIVO', 'Servidor de aplicaciones', '2026-01-14 21:30:38', 0, NULL, NULL),
(18, 1, 6, 25, 17, 1, NULL, 1, 1, 'UPS-0001', 'UPS Servidor', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1500VA', 'UPS-SN-001', NULL, '2024-08-01', NULL, NULL, 'ACTIVO', 'Respaldo eléctrico del SRV-0001', '2026-01-14 21:30:38', 0, NULL, NULL),
(19, 1, 1, 21, NULL, 0, 4, 1, 1, 'IMP-0001', 'Impresora Recepción', 'IMP-REC-01', 0, '192.168.1.30', 'AA:BB:CC:DD:EE:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'L3150', 'EPS-IMP-001', 'INV-0200', '2025-11-01', '2025-11-02', '2026-11-01', 'ACTIVO', 'Impresora en red', '2026-01-14 21:30:38', 0, NULL, NULL),
(20, 1, 2, 22, NULL, 0, NULL, 1, 1, 'SW-0001', 'Switch Principal 24p', 'SW-PRIN-01', 0, '192.168.1.2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '24 Puertos', 'SW-SN-001', 'INV-0300', '2024-09-01', '2024-09-01', NULL, 'ACTIVO', 'Core de red', '2026-01-14 21:30:38', 0, NULL, NULL),
(21, 1, 3, 31, NULL, 0, 5, 1, 1, 'NVR-0001', 'NVR Seguridad', 'NVR-SEC-01', 0, '192.168.1.50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'NVR 8CH', 'HIK-NVR-001', 'INV-0400', '2025-07-01', '2025-07-02', NULL, 'ACTIVO', 'Grabador de cámaras', '2026-01-14 21:30:38', 0, NULL, NULL),
(22, 1, 3, 23, 21, 1, 5, 1, 1, 'CAM-0001', 'Cámara IP Entrada', 'CAM-ENT-01', 0, '192.168.1.60', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2MP', 'HIK-CAM-001', NULL, '2025-07-01', '2025-07-02', NULL, 'ACTIVO', 'Componente del NVR-0001', '2026-01-14 21:30:38', 0, NULL, NULL),
(23, 1, 1, 16, NULL, 0, 1, 1, 1, 'PC-001', 'Computador Oficina 1', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ACTIVO', NULL, '2026-01-14 21:37:32', 0, NULL, NULL),
(24, 1, 1, 16, NULL, 0, 2, 1, 1, 'PC-002', 'Computador Oficina 2', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ACTIVO', NULL, '2026-01-14 21:37:32', 0, NULL, NULL),
(25, 1, 6, 17, NULL, 0, 2, 1, 1, 'SRV-001', 'Servidor Principal', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ACTIVO', NULL, '2026-01-14 21:37:32', 0, NULL, NULL),
(26, 1, 2, 22, NULL, 0, 5, 1, 1, 'SW-001', 'Switch Core', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', NULL, NULL, NULL, 'BAJA', '', '2026-01-14 21:37:32', 0, NULL, NULL),
(27, 1, 3, 23, NULL, 0, 5, 1, 1, 'CAM-001', 'Cámara Entrada', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ACTIVO', NULL, '2026-01-14 21:37:32', 0, NULL, NULL),
(29, 1, 1, 20, NULL, 0, 3, 1, 1, 'MOU-0002', 'Coordinacion_cimell', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', NULL, NULL, NULL, 'ACTIVO', '', '2026-01-15 22:39:24', 0, NULL, NULL),
(30, 1, 1, 16, NULL, 0, 3, 1, 1, 'PC-0004', 'Coordinacion_cimell', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/activos/1/activo_30.png', 'image/png', '2026-01-20 21:54:23', '', '', '', NULL, NULL, NULL, 'ACTIVO', '', '2026-01-15 22:40:24', 0, NULL, NULL);

--
-- Disparadores `activos`
--
DELIMITER $$
CREATE TRIGGER `trg_activos_set_componente_ins` BEFORE INSERT ON `activos` FOR EACH ROW BEGIN
  IF NEW.activo_padre_id IS NOT NULL THEN
    SET NEW.es_componente = 1;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_activos_set_componente_upd` BEFORE UPDATE ON `activos` FOR EACH ROW BEGIN
  IF NEW.activo_padre_id IS NOT NULL THEN
    SET NEW.es_componente = 1;
  ELSE
    /* si le quitan el padre, vuelve a ser activo principal */
    SET NEW.es_componente = 0;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activos_adjuntos`
--

CREATE TABLE `activos_adjuntos` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `activo_id` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_guardado` varchar(255) NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `mime` varchar(120) NOT NULL,
  `tamano` int(11) NOT NULL DEFAULT 0,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `activos_adjuntos`
--

INSERT INTO `activos_adjuntos` (`id`, `tenant_id`, `activo_id`, `nombre_original`, `nombre_guardado`, `ruta`, `mime`, `tamano`, `creado_por`, `creado_en`) VALUES
(2, 1, 13, 'BOLETIN 4 PERIODO (1).pdf', 'adj_696a3f71c65dc8.64160628.pdf', 'uploads/activos/1/13/adj_696a3f71c65dc8.64160628.pdf', 'application/pdf', 53922, NULL, '2026-01-16 08:38:57'),
(5, 1, 30, 'pse_20260111001351.pdf', 'adj_696d9b1341b192.78404839.pdf', 'uploads/activos/1/30/adj_696d9b1341b192.78404839.pdf', 'application/pdf', 158180, 1, '2026-01-18 21:46:43'),
(6, 1, 30, 'pse_20260111001351.pdf', 'adj_696fd6267ec237.74236113.pdf', 'uploads/activos/1/30/adj_696fd6267ec237.74236113.pdf', 'application/pdf', 158180, 1, '2026-01-20 14:23:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activos_componentes`
--

CREATE TABLE `activos_componentes` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `activo_id` int(11) NOT NULL,
  `nombre` varchar(190) NOT NULL,
  `tipo` varchar(60) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(120) DEFAULT NULL,
  `serial` varchar(120) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `estado` enum('ACTIVO','EN_MANTENIMIENTO','BAJA') NOT NULL DEFAULT 'ACTIVO',
  `observaciones` text DEFAULT NULL,
  `eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `eliminado_en` datetime DEFAULT NULL,
  `eliminado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `activos_componentes`
--

INSERT INTO `activos_componentes` (`id`, `tenant_id`, `activo_id`, `nombre`, `tipo`, `marca`, `modelo`, `serial`, `cantidad`, `estado`, `observaciones`, `eliminado`, `eliminado_en`, `eliminado_por`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 30, 'Teclado', 'Periferico', 'genius', NULL, NULL, 1, 'ACTIVO', NULL, 0, NULL, NULL, '2026-01-17 21:44:14', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activos_software`
--

CREATE TABLE `activos_software` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `activo_id` int(11) NOT NULL,
  `software_id` int(11) DEFAULT NULL,
  `nombre` varchar(180) NOT NULL,
  `version` varchar(80) DEFAULT NULL,
  `licencia_tipo` enum('FREE','OEM','VOLUMEN','SUSCRIPCION','OTRA') NOT NULL DEFAULT 'OTRA',
  `licencia_clave` varchar(200) DEFAULT NULL,
  `fecha_instalacion` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `activos_software`
--

INSERT INTO `activos_software` (`id`, `tenant_id`, `activo_id`, `software_id`, `nombre`, `version`, `licencia_tipo`, `licencia_clave`, `fecha_instalacion`, `fecha_vencimiento`, `observaciones`, `creado_en`) VALUES
(3, 1, 13, NULL, 'Windows', '11', 'OEM', NULL, NULL, NULL, NULL, '2026-01-16 00:28:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activo_software`
--

CREATE TABLE `activo_software` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `activo_id` int(11) NOT NULL,
  `software_id` int(11) NOT NULL,
  `version` varchar(80) DEFAULT NULL,
  `instalado` tinyint(1) NOT NULL DEFAULT 1,
  `licencia_clave` varchar(255) DEFAULT NULL,
  `licencia_vencimiento` date DEFAULT NULL,
  `fecha_instalacion` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sede_id` int(11) DEFAULT NULL,
  `nombre` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `areas`
--

INSERT INTO `areas` (`id`, `tenant_id`, `sede_id`, `nombre`) VALUES
(1, 1, 1, 'Sistemas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity` varchar(50) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `audit_log`
--

INSERT INTO `audit_log` (`id`, `tenant_id`, `user_id`, `action`, `entity`, `entity_id`, `message`, `ip`, `user_agent`, `created_at`) VALUES
(1, 1, 1, 'DELETE', 'activo', 13, 'Activo eliminado (soft). Código: PC-0001 · Nombre: PC Recepción. Incluye mantenimientos y adjuntos.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 17:38:04'),
(2, 1, 1, 'RESTORE', 'activo', 13, 'Activo restaurado. Código: PC-0001 · Nombre: PC Recepción. Incluye mantenimientos y adjuntos.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 18:00:14'),
(3, 1, 1, 'UPDATE', 'mantenimiento', 9, 'Actualización de mantenimiento #9. Cambios: Fecha inicio: \'2026-01-08 16:38:00\' → \'2026-01-08T16:38\' | Fecha fin: \'2026-01-08 16:38:00\' → \'2026-01-08T16:38\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 19:10:37'),
(4, 1, 1, 'UPDATE', 'mantenimiento', 9, 'Actualización de mantenimiento #9.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 21:51:00'),
(5, 1, 1, 'UPDATE', 'mantenimiento', 9, 'Actualización de mantenimiento #9. (Firma actualizada)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 22:01:31'),
(6, 1, 1, 'UPDATE', 'mantenimiento', 9, 'Actualización de mantenimiento #9.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-19 22:02:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calibraciones`
--

CREATE TABLE `calibraciones` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `activo_id` int(11) NOT NULL,
  `numero_certificado` varchar(40) DEFAULT NULL,
  `token_verificacion` varchar(80) DEFAULT NULL,
  `tipo` enum('INTERNA','EXTERNA') NOT NULL DEFAULT 'INTERNA',
  `estado` enum('PROGRAMADA','EN_PROCESO','CERRADA','ANULADA') NOT NULL DEFAULT 'PROGRAMADA',
  `fecha_programada` date DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `lugar` varchar(180) DEFAULT NULL,
  `metodo` varchar(180) DEFAULT NULL,
  `procedimiento_ref` varchar(180) DEFAULT NULL,
  `norma_ref` varchar(180) DEFAULT NULL,
  `temperatura_c` decimal(6,2) DEFAULT NULL,
  `humedad_rel` decimal(6,2) DEFAULT NULL,
  `resultado_global` enum('CONFORME','NO_CONFORME') DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `tecnico_id` int(11) DEFAULT NULL,
  `tecnico_nombre` varchar(180) DEFAULT NULL,
  `tecnico_cargo` varchar(120) DEFAULT NULL,
  `tecnico_tarjeta_prof` varchar(80) DEFAULT NULL,
  `firma_tecnico_id` int(11) DEFAULT NULL,
  `firma_hash` varchar(64) DEFAULT NULL,
  `firma_png` mediumblob DEFAULT NULL,
  `recibido_por_nombre` varchar(180) DEFAULT NULL,
  `recibido_por_cargo` varchar(120) DEFAULT NULL,
  `recibido_firma_hash` varchar(64) DEFAULT NULL,
  `recibido_firma_png` mediumblob DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `pdf_hash` varchar(64) DEFAULT NULL,
  `pdf_updated_en` datetime DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `cerrado_por` int(11) DEFAULT NULL,
  `cerrado_en` datetime DEFAULT NULL,
  `anulado_por` int(11) DEFAULT NULL,
  `anulado_en` datetime DEFAULT NULL,
  `anulado_motivo` varchar(255) DEFAULT NULL,
  `eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `eliminado_en` datetime DEFAULT NULL,
  `eliminado_por` int(11) DEFAULT NULL,
  `public_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calibraciones_adjuntos`
--

CREATE TABLE `calibraciones_adjuntos` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `calibracion_id` int(11) NOT NULL,
  `ruta` varchar(255) NOT NULL,
  `mime` varchar(120) NOT NULL,
  `tamano` bigint(20) NOT NULL DEFAULT 0,
  `nombre_original` varchar(255) NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL,
  `eliminado` tinyint(4) NOT NULL DEFAULT 0,
  `eliminado_en` datetime DEFAULT NULL,
  `eliminado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calibraciones_patrones`
--

CREATE TABLE `calibraciones_patrones` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `calibracion_id` int(11) NOT NULL,
  `patron_id` int(11) NOT NULL,
  `uso` enum('PRINCIPAL','SECUNDARIO') NOT NULL DEFAULT 'PRINCIPAL',
  `notas` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calibraciones_puntos`
--

CREATE TABLE `calibraciones_puntos` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `calibracion_id` int(11) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 1,
  `magnitud` varchar(80) NOT NULL,
  `unidad` varchar(20) NOT NULL,
  `punto_nominal` decimal(18,6) DEFAULT NULL,
  `lectura_equipo` decimal(18,6) DEFAULT NULL,
  `lectura_patron` decimal(18,6) DEFAULT NULL,
  `error_abs` decimal(18,6) DEFAULT NULL,
  `error_rel` decimal(18,6) DEFAULT NULL,
  `tolerancia` decimal(18,6) DEFAULT NULL,
  `conforme` tinyint(1) DEFAULT NULL,
  `incertidumbre_expandida` decimal(18,6) DEFAULT NULL,
  `k` decimal(10,4) DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_activo`
--

CREATE TABLE `categorias_activo` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias_activo`
--

INSERT INTO `categorias_activo` (`id`, `tenant_id`, `nombre`) VALUES
(4, 1, 'Biomédico'),
(5, 1, 'Climatización'),
(1, 1, 'Computo'),
(2, 1, 'Redes'),
(3, 1, 'Seguridad'),
(6, 1, 'Servidor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kardex`
--

CREATE TABLE `kardex` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `repuesto_id` int(11) NOT NULL,
  `tipo` enum('ENTRADA','SALIDA') NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `costo_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  `referencia_doc` varchar(120) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimientos`
--

CREATE TABLE `mantenimientos` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `activo_id` int(11) NOT NULL,
  `tipo` enum('PREVENTIVO','CORRECTIVO','PREDICTIVO') NOT NULL,
  `estado` enum('PROGRAMADO','EN_PROCESO','CERRADO','ANULADO') NOT NULL DEFAULT 'PROGRAMADO',
  `cerrado_por` int(11) DEFAULT NULL,
  `cerrado_en` datetime DEFAULT NULL,
  `firma_tecnico_id` int(11) DEFAULT NULL,
  `firma_hash` varchar(64) DEFAULT NULL,
  `tecnico_nombre` varchar(160) DEFAULT NULL,
  `tecnico_cargo` varchar(120) DEFAULT NULL,
  `tecnico_tarjeta_prof` varchar(60) DEFAULT NULL,
  `fecha_programada` date DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `prioridad` enum('BAJA','MEDIA','ALTA','CRITICA') NOT NULL DEFAULT 'MEDIA',
  `falla_reportada` text DEFAULT NULL,
  `diagnostico` text DEFAULT NULL,
  `actividades` text DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `costo_mano_obra` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_repuestos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `eliminado_en` datetime DEFAULT NULL,
  `eliminado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `recibido_por_nombre` varchar(160) DEFAULT NULL,
  `recibido_por_cargo` varchar(120) DEFAULT NULL,
  `recibido_firma_png` longtext DEFAULT NULL,
  `recibido_firma_hash` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mantenimientos`
--

INSERT INTO `mantenimientos` (`id`, `tenant_id`, `activo_id`, `tipo`, `estado`, `cerrado_por`, `cerrado_en`, `firma_tecnico_id`, `firma_hash`, `tecnico_nombre`, `tecnico_cargo`, `tecnico_tarjeta_prof`, `fecha_programada`, `fecha_inicio`, `fecha_fin`, `prioridad`, `falla_reportada`, `diagnostico`, `actividades`, `recomendaciones`, `costo_mano_obra`, `costo_repuestos`, `creado_por`, `creado_en`, `eliminado`, `eliminado_en`, `eliminado_por`, `recibido_por_nombre`, `recibido_por_cargo`, `recibido_firma_png`, `recibido_firma_hash`) VALUES
(2, 1, 13, 'PREVENTIVO', 'CERRADO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-01-05', '2025-01-05 09:00:00', '2025-01-05 10:30:00', 'MEDIA', 'Lentitud general', 'Se encontró disco al 95% de uso', 'Limpieza, desfragmentación y revisión antivirus', 'Revisar almacenamiento cada 30 días', 30000.00, 0.00, 1, '2026-01-15 07:43:01', 0, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 1, 17, 'PREVENTIVO', 'PROGRAMADO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-02-01', NULL, NULL, 'ALTA', 'Mantenimiento preventivo del servidor', NULL, 'Revisión de logs, limpieza, verificación RAID', 'Programar ventana de mantenimiento', 0.00, 0.00, 1, '2026-01-15 07:43:01', 0, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 1, 20, 'CORRECTIVO', 'EN_PROCESO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-01-12', '2025-01-12 14:00:00', NULL, 'CRITICA', 'Puertos intermitentes', 'Posible falla fuente / sobrecalentamiento', 'Verificar fuente, limpiar y probar puertos', 'Instalar UPS y mejorar ventilación', 50000.00, 0.00, 1, '2026-01-15 07:43:01', 0, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 1, 21, 'PREVENTIVO', 'CERRADO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-01', '2024-12-01 08:00:00', '2024-12-01 08:40:00', 'BAJA', 'Chequeo grabación', 'Normal', 'Actualizar firmware, revisar discos', 'Revisar SMART mensual', 0.00, 0.00, 1, '2026-01-15 07:43:01', 0, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 1, 13, 'PREVENTIVO', 'CERRADO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-01-05', '2025-01-05 09:00:00', '2025-01-05 10:30:00', 'MEDIA', 'Lentitud general', 'Se realizó limpieza y optimización', 'Limpieza interna, revisión, test SMART', 'Revisar cada 6 meses', 30000.00, 0.00, NULL, '2026-01-15 08:16:26', 0, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 1, 17, 'CORRECTIVO', 'CERRADO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-01-10', '2025-01-10 14:00:00', '2025-01-10 16:00:00', 'ALTA', 'Servidor no inicia', 'Disco dañado', 'Cambio de disco + restauración backup', 'Monitorear RAID/SMART', 80000.00, 220000.00, NULL, '2026-01-15 08:16:26', 0, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 1, 20, 'PREVENTIVO', 'PROGRAMADO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-02-01', NULL, NULL, 'BAJA', 'Mantenimiento programado', NULL, NULL, 'Actualizar firmware y revisar puertos', 0.00, 0.00, NULL, '2026-01-15 08:16:26', 0, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 1, 30, 'CORRECTIVO', 'PROGRAMADO', NULL, NULL, 1, 'a6139bc77024e286921186d5692cb54efac335be560d1f6f77c052ea5cedeb27', 'Administrador', 'Ingeniero de Sistemas', '73198932', '2026-01-08', '2026-01-08 16:38:00', '2026-01-08 16:38:00', 'CRITICA', 'Se reporta que el equipo de computo pesenta un mensaje y la pantalla negra', 'Se revisa equipo efectivamente el mensaje que arroja es de disco dañado se reporta y se notifica que se debe realizar cambio de disco', 'Se realiza cambio de disco duro ssd se procede a montar sistema operativo y programas com office para dejarlo listo para el usuario', 'Se recomienda intalar ups al equipo para evitar daños por cortes de energia', 180000.00, 0.00, NULL, '2026-01-17 16:51:27', 0, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimientos_adjuntos`
--

CREATE TABLE `mantenimientos_adjuntos` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `mantenimiento_id` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `mime` varchar(120) DEFAULT NULL,
  `tamano` int(11) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `eliminado_en` datetime DEFAULT NULL,
  `eliminado_por` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mantenimientos_adjuntos`
--

INSERT INTO `mantenimientos_adjuntos` (`id`, `tenant_id`, `mantenimiento_id`, `nombre_original`, `nombre_archivo`, `mime`, `tamano`, `nota`, `creado_por`, `creado_en`, `eliminado`, `eliminado_en`, `eliminado_por`) VALUES
(3, 1, 2, 'ChatGPT Image 17 ene 2026, 16_20_25.png', 'uploads/mantenimientos/1/2/adj_696bfe8f432c44.80504275.png', 'image/png', 1777601, NULL, 1, '2026-01-17 16:26:39', 0, NULL, NULL),
(4, 1, 9, 'pse_20260111001351.pdf', 'uploads/mantenimientos/1/9/adj_696d9ad6773926.54441236.pdf', 'application/pdf', 158180, NULL, 1, '2026-01-18 21:45:42', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marcas`
--

CREATE TABLE `marcas` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `marcas`
--

INSERT INTO `marcas` (`id`, `tenant_id`, `nombre`) VALUES
(2, 1, 'Dell'),
(4, 1, 'Epson'),
(5, 1, 'Hikvision'),
(1, 1, 'HP'),
(3, 1, 'Lenovo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `patrones`
--

CREATE TABLE `patrones` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `marca` varchar(120) DEFAULT NULL,
  `modelo` varchar(120) DEFAULT NULL,
  `serial` varchar(120) DEFAULT NULL,
  `magnitudes` varchar(255) DEFAULT NULL,
  `rango` varchar(120) DEFAULT NULL,
  `resolucion` varchar(120) DEFAULT NULL,
  `certificado_numero` varchar(120) DEFAULT NULL,
  `certificado_emisor` varchar(180) DEFAULT NULL,
  `certificado_fecha` date DEFAULT NULL,
  `certificado_vigencia_hasta` date DEFAULT NULL,
  `incertidumbre_ref` varchar(255) DEFAULT NULL,
  `archivo_certificado_path` varchar(255) DEFAULT NULL,
  `archivo_certificado_mime` varchar(120) DEFAULT NULL,
  `archivo_certificado_updated_en` datetime DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `eliminado_en` datetime DEFAULT NULL,
  `eliminado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `codigo` varchar(80) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `grupo` varchar(60) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`codigo`, `nombre`, `grupo`) VALUES
('activos.edit', 'Crear/Editar activos', 'Activos'),
('activos.view', 'Ver activos', 'Activos'),
('areas.view', 'Ver áreas', 'Configuración'),
('calibraciones.edit', 'Crear/Editar calibraciones', 'Calibraciones'),
('calibraciones.manage', 'Administrar calibraciones', 'Calibraciones'),
('calibraciones.view', 'Ver calibraciones', 'Calibraciones'),
('categorias.view', 'Ver categorías', 'Configuración'),
('config.view', 'Ver configuración', 'Configuración'),
('dashboard.view', 'Ver dashboard', 'General'),
('empresas.view', 'Ver empresas (tenants)', 'Configuración'),
('mantenimientos.edit', 'Crear/Editar mantenimientos', 'Mantenimientos'),
('mantenimientos.view', 'Ver mantenimientos', 'Mantenimientos'),
('marcas.view', 'Ver marcas', 'Configuración'),
('patrones.edit', 'Crear/Editar patrones', 'Calibraciones'),
('patrones.manage', 'Administrar patrones', 'Calibraciones'),
('patrones.view', 'Ver patrones de calibración', 'Calibraciones'),
('proveedores.view', 'Ver proveedores', 'Configuración'),
('roles.edit', 'Crear/Editar roles', 'Seguridad'),
('roles.permisos', 'Asignar permisos a roles', 'Seguridad'),
('roles.view', 'Ver roles', 'Seguridad'),
('sedes.view', 'Ver sedes', 'Configuración'),
('tipos_activo.view', 'Ver tipos de activo', 'Configuración'),
('usuarios.edit', 'Crear/Editar usuarios', 'Seguridad'),
('usuarios.view', 'Ver usuarios', 'Seguridad');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `nit` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `tenant_id`, `nombre`, `nit`, `email`, `telefono`) VALUES
(1, 1, 'Proveedor Demo', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `repuestos`
--

CREATE TABLE `repuestos` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `nombre` varchar(160) NOT NULL,
  `referencia` varchar(120) DEFAULT NULL,
  `unidad` varchar(40) DEFAULT NULL,
  `stock_minimo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_actual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_promedio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `es_superadmin` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `tenant_id`, `nombre`, `es_superadmin`) VALUES
(1, 1, 'ADMIN', 1),
(2, 1, 'TECNICO', 0),
(3, 1, 'LECTOR', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permisos`
--

CREATE TABLE `rol_permisos` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `permiso_codigo` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol_permisos`
--

INSERT INTO `rol_permisos` (`id`, `tenant_id`, `rol_id`, `permiso_codigo`) VALUES
(1, 1, 1, 'activos.edit'),
(2, 1, 1, 'activos.view'),
(3, 1, 1, 'areas.view'),
(33, 1, 1, 'calibraciones.edit'),
(34, 1, 1, 'calibraciones.manage'),
(32, 1, 1, 'calibraciones.view'),
(4, 1, 1, 'categorias.view'),
(5, 1, 1, 'config.view'),
(6, 1, 1, 'dashboard.view'),
(7, 1, 1, 'empresas.view'),
(8, 1, 1, 'mantenimientos.edit'),
(9, 1, 1, 'mantenimientos.view'),
(10, 1, 1, 'marcas.view'),
(36, 1, 1, 'patrones.edit'),
(37, 1, 1, 'patrones.manage'),
(35, 1, 1, 'patrones.view'),
(11, 1, 1, 'proveedores.view'),
(12, 1, 1, 'roles.edit'),
(13, 1, 1, 'roles.permisos'),
(14, 1, 1, 'roles.view'),
(15, 1, 1, 'sedes.view'),
(16, 1, 1, 'tipos_activo.view'),
(17, 1, 1, 'usuarios.edit'),
(18, 1, 1, 'usuarios.view');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sedes`
--

CREATE TABLE `sedes` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `direccion` varchar(180) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sedes`
--

INSERT INTO `sedes` (`id`, `tenant_id`, `nombre`, `direccion`) VALUES
(1, 1, 'Sede Principal', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `software_catalogo`
--

CREATE TABLE `software_catalogo` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `fabricante` varchar(180) DEFAULT NULL,
  `tipo_licencia` enum('FREE','OEM','VOLUMEN','SUSCRIPCION','OTRA') NOT NULL DEFAULT 'OTRA',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `nit` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `direccion` varchar(180) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `estado` enum('ACTIVO','SUSPENDIDO') NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tenants`
--

INSERT INTO `tenants` (`id`, `nombre`, `nit`, `email`, `telefono`, `direccion`, `ciudad`, `estado`, `creado_en`) VALUES
(1, 'GesaProv', '73198932', 'alexgure@gmail.com', '3207786087', 'MANZANA E Casa 1 VILLAS DEL MEDITERRANEO,', 'Acacias', 'ACTIVO', '2026-01-14 09:56:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_activo`
--

CREATE TABLE `tipos_activo` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `codigo` varchar(10) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `familia` enum('TI','INFRA','BIOMED') NOT NULL DEFAULT 'TI'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipos_activo`
--

INSERT INTO `tipos_activo` (`id`, `tenant_id`, `nombre`, `codigo`, `creado_en`, `familia`) VALUES
(16, 1, 'Computador', 'PC', '2026-01-14 09:57:16', 'BIOMED'),
(17, 1, 'Servidor', 'SRV', '2026-01-14 09:57:16', 'TI'),
(18, 1, 'Monitor', 'MON', '2026-01-14 09:57:16', 'TI'),
(19, 1, 'Teclado', 'TEC', '2026-01-14 09:57:16', 'TI'),
(20, 1, 'Mouse', 'MOU', '2026-01-14 09:57:16', 'TI'),
(21, 1, 'Impresora', 'IMP', '2026-01-14 09:57:16', 'TI'),
(22, 1, 'Switch', 'SW', '2026-01-14 09:57:16', 'TI'),
(23, 1, 'Cámara IP', 'CAM', '2026-01-14 09:57:16', 'TI'),
(24, 1, 'DVR', 'DVR', '2026-01-14 09:57:16', 'TI'),
(25, 1, 'UPS', 'UPS', '2026-01-14 09:57:16', 'TI'),
(26, 1, 'Router', 'RTR', '2026-01-14 09:57:16', 'TI'),
(27, 1, 'Access Point', 'AP', '2026-01-14 09:57:16', 'TI'),
(28, 1, 'Biomédico', 'BIO', '2026-01-14 09:57:16', 'TI'),
(29, 1, 'Aire acondicionado', 'AIR', '2026-01-14 09:57:16', 'TI'),
(30, 1, 'Televisor', 'TV', '2026-01-14 09:57:16', 'TI'),
(31, 1, 'NVR', 'NVR', '2026-01-14 17:58:03', 'TI');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_activo_reglas`
--

CREATE TABLE `tipo_activo_reglas` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `tipo_activo_id` int(11) NOT NULL,
  `usa_red` tinyint(1) NOT NULL DEFAULT 0,
  `usa_software` tinyint(1) NOT NULL DEFAULT 0,
  `es_biomedico` tinyint(1) NOT NULL DEFAULT 0,
  `requiere_calibracion` tinyint(1) NOT NULL DEFAULT 0,
  `periodicidad_meses` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipo_activo_reglas`
--

INSERT INTO `tipo_activo_reglas` (`id`, `tenant_id`, `tipo_activo_id`, `usa_red`, `usa_software`, `es_biomedico`, `requiere_calibracion`, `periodicidad_meses`, `creado_en`) VALUES
(20, 1, 16, 1, 1, 0, 0, NULL, '2026-01-15 23:08:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `cargo` varchar(120) DEFAULT NULL,
  `email` varchar(120) NOT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `tipo_documento` varchar(10) DEFAULT NULL,
  `num_documento` varchar(30) DEFAULT NULL,
  `tarjeta_profesional` varchar(60) DEFAULT NULL,
  `entidad_tarjeta` varchar(120) DEFAULT NULL,
  `firma_habilitada` tinyint(1) NOT NULL DEFAULT 1,
  `firma_png` mediumtext DEFAULT NULL,
  `firma_hash` varchar(64) DEFAULT NULL,
  `pass_hash` varchar(255) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `firma_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `tenant_id`, `rol_id`, `nombre`, `cargo`, `email`, `telefono`, `tipo_documento`, `num_documento`, `tarjeta_profesional`, `entidad_tarjeta`, `firma_habilitada`, `firma_png`, `firma_hash`, `pass_hash`, `estado`, `creado_en`, `firma_path`) VALUES
(1, 1, 1, 'Administrador', 'Ingeniero de Sistemas', 'admin@demo.com', '3207786087', 'CC', '73198932', '73198932', 'COPNIA', 1, NULL, 'a6139bc77024e286921186d5692cb54efac335be560d1f6f77c052ea5cedeb27', '$2y$10$rwQmbLNjkbkkiiJtKWDeEO6BbjjMqmlA1Zs3/fJnNy8XDCjA0NuT6', 'ACTIVO', '2026-01-14 10:07:09', 'uploads/firmas/1/user_1.png');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_firma`
--

CREATE TABLE `usuarios_firma` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `firma_png` longtext DEFAULT NULL,
  `firma_mime` varchar(50) DEFAULT NULL,
  `firma_hash` varchar(64) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_firmas`
--

CREATE TABLE `usuarios_firmas` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `firma_png` longtext NOT NULL,
  `firma_hash` varchar(64) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_activos_calibrables`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_activos_calibrables` (
`id` int(11)
,`tenant_id` int(11)
,`categoria_id` int(11)
,`tipo_activo_id` int(11)
,`activo_padre_id` int(11)
,`es_componente` tinyint(1)
,`marca_id` int(11)
,`area_id` int(11)
,`proveedor_id` int(11)
,`codigo_interno` varchar(50)
,`nombre` varchar(150)
,`hostname` varchar(120)
,`usa_dhcp` tinyint(1)
,`ip_fija` varchar(45)
,`mac` varchar(32)
,`biomedico` tinyint(1)
,`requiere_calibracion` tinyint(1)
,`periodicidad_meses` int(11)
,`prox_calibracion` date
,`foto_path` varchar(255)
,`foto_mime` varchar(100)
,`foto_updated_en` datetime
,`modelo` varchar(120)
,`serial` varchar(120)
,`placa` varchar(80)
,`fecha_compra` date
,`fecha_instalacion` date
,`garantia_hasta` date
,`estado` enum('ACTIVO','EN_MANTENIMIENTO','BAJA')
,`observaciones` text
,`creado_en` datetime
,`eliminado` tinyint(1)
,`eliminado_en` datetime
,`eliminado_por` bigint(20) unsigned
,`es_biomedico_eff` int(4)
,`requiere_calibracion_eff` int(4)
,`periodicidad_meses_eff` int(11)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_activos_calibrables`
--
DROP TABLE IF EXISTS `vw_activos_calibrables`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_activos_calibrables`  AS SELECT `a`.`id` AS `id`, `a`.`tenant_id` AS `tenant_id`, `a`.`categoria_id` AS `categoria_id`, `a`.`tipo_activo_id` AS `tipo_activo_id`, `a`.`activo_padre_id` AS `activo_padre_id`, `a`.`es_componente` AS `es_componente`, `a`.`marca_id` AS `marca_id`, `a`.`area_id` AS `area_id`, `a`.`proveedor_id` AS `proveedor_id`, `a`.`codigo_interno` AS `codigo_interno`, `a`.`nombre` AS `nombre`, `a`.`hostname` AS `hostname`, `a`.`usa_dhcp` AS `usa_dhcp`, `a`.`ip_fija` AS `ip_fija`, `a`.`mac` AS `mac`, `a`.`biomedico` AS `biomedico`, `a`.`requiere_calibracion` AS `requiere_calibracion`, `a`.`periodicidad_meses` AS `periodicidad_meses`, `a`.`prox_calibracion` AS `prox_calibracion`, `a`.`foto_path` AS `foto_path`, `a`.`foto_mime` AS `foto_mime`, `a`.`foto_updated_en` AS `foto_updated_en`, `a`.`modelo` AS `modelo`, `a`.`serial` AS `serial`, `a`.`placa` AS `placa`, `a`.`fecha_compra` AS `fecha_compra`, `a`.`fecha_instalacion` AS `fecha_instalacion`, `a`.`garantia_hasta` AS `garantia_hasta`, `a`.`estado` AS `estado`, `a`.`observaciones` AS `observaciones`, `a`.`creado_en` AS `creado_en`, `a`.`eliminado` AS `eliminado`, `a`.`eliminado_en` AS `eliminado_en`, `a`.`eliminado_por` AS `eliminado_por`, CASE WHEN `a`.`biomedico` is null THEN ifnull(`r`.`es_biomedico`,0) ELSE `a`.`biomedico` END AS `es_biomedico_eff`, CASE WHEN `a`.`requiere_calibracion` is null THEN ifnull(`r`.`requiere_calibracion`,0) ELSE `a`.`requiere_calibracion` END AS `requiere_calibracion_eff`, CASE WHEN `a`.`periodicidad_meses` is null THEN `r`.`periodicidad_meses` ELSE `a`.`periodicidad_meses` END AS `periodicidad_meses_eff` FROM (`activos` `a` left join `tipo_activo_reglas` `r` on(`r`.`tenant_id` = `a`.`tenant_id` and `r`.`tipo_activo_id` = `a`.`tipo_activo_id`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `activos`
--
ALTER TABLE `activos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_activo_codigo` (`tenant_id`,`codigo_interno`),
  ADD KEY `idx_activo_serial` (`tenant_id`,`serial`),
  ADD KEY `fk_activos_cat` (`categoria_id`),
  ADD KEY `fk_activos_marca` (`marca_id`),
  ADD KEY `fk_activos_area` (`area_id`),
  ADD KEY `fk_activos_prov` (`proveedor_id`),
  ADD KEY `idx_activos_tipo` (`tenant_id`,`tipo_activo_id`),
  ADD KEY `fk_activos_tipo` (`tipo_activo_id`),
  ADD KEY `idx_activos_padre` (`tenant_id`,`activo_padre_id`),
  ADD KEY `fk_activos_padre` (`activo_padre_id`);

--
-- Indices de la tabla `activos_adjuntos`
--
ALTER TABLE `activos_adjuntos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_adj_tenant_activo` (`tenant_id`,`activo_id`),
  ADD KEY `idx_adj_activo` (`activo_id`);

--
-- Indices de la tabla `activos_componentes`
--
ALTER TABLE `activos_componentes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_activo` (`tenant_id`,`activo_id`),
  ADD KEY `idx_tenant_elim` (`tenant_id`,`eliminado`),
  ADD KEY `fk_comp_activo` (`activo_id`);

--
-- Indices de la tabla `activos_software`
--
ALTER TABLE `activos_software`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_as_tenant` (`tenant_id`),
  ADD KEY `idx_as_activo` (`activo_id`),
  ADD KEY `idx_as_softid` (`software_id`);

--
-- Indices de la tabla `activo_software`
--
ALTER TABLE `activo_software`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_activo_software` (`tenant_id`,`activo_id`,`software_id`),
  ADD KEY `idx_tenant_activo` (`tenant_id`,`activo_id`),
  ADD KEY `idx_tenant_software` (`tenant_id`,`software_id`),
  ADD KEY `fk_activo_software_activo` (`activo_id`),
  ADD KEY `fk_activo_software_catalogo` (`software_id`);

--
-- Indices de la tabla `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_area` (`tenant_id`,`nombre`),
  ADD KEY `fk_areas_sede` (`sede_id`);

--
-- Indices de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_tenant` (`tenant_id`,`created_at`),
  ADD KEY `idx_audit_entity` (`tenant_id`,`entity`,`entity_id`),
  ADD KEY `idx_audit_user` (`tenant_id`,`user_id`,`created_at`);

--
-- Indices de la tabla `calibraciones`
--
ALTER TABLE `calibraciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cal_cert` (`tenant_id`,`numero_certificado`),
  ADD KEY `idx_cal_tenant` (`tenant_id`),
  ADD KEY `idx_cal_activo` (`tenant_id`,`activo_id`),
  ADD KEY `idx_cal_estado` (`tenant_id`,`estado`),
  ADD KEY `idx_cal_fechas` (`tenant_id`,`fecha_programada`,`fecha_fin`),
  ADD KEY `idx_cal_public_token` (`tenant_id`,`public_token`);

--
-- Indices de la tabla `calibraciones_adjuntos`
--
ALTER TABLE `calibraciones_adjuntos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cal_adj_tenant_cal` (`tenant_id`,`calibracion_id`),
  ADD KEY `idx_cal_adj_tenant` (`tenant_id`),
  ADD KEY `idx_cal_adj_cal` (`calibracion_id`);

--
-- Indices de la tabla `calibraciones_patrones`
--
ALTER TABLE `calibraciones_patrones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_calx_tenant` (`tenant_id`),
  ADD KEY `idx_calx_cal` (`tenant_id`,`calibracion_id`),
  ADD KEY `idx_calx_pat` (`tenant_id`,`patron_id`),
  ADD KEY `fk_calx_cal` (`calibracion_id`),
  ADD KEY `fk_calx_pat` (`patron_id`);

--
-- Indices de la tabla `calibraciones_puntos`
--
ALTER TABLE `calibraciones_puntos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_calp_tenant` (`tenant_id`),
  ADD KEY `idx_calp_cal` (`tenant_id`,`calibracion_id`),
  ADD KEY `idx_calp_mag` (`tenant_id`,`magnitud`),
  ADD KEY `fk_calp_cal` (`calibracion_id`);

--
-- Indices de la tabla `categorias_activo`
--
ALTER TABLE `categorias_activo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cat_act` (`tenant_id`,`nombre`);

--
-- Indices de la tabla `kardex`
--
ALTER TABLE `kardex`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kardex` (`tenant_id`,`repuesto_id`,`fecha`),
  ADD KEY `fk_kardex_rep` (`repuesto_id`),
  ADD KEY `fk_kardex_user` (`usuario_id`);

--
-- Indices de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mant` (`tenant_id`,`activo_id`,`estado`),
  ADD KEY `fk_mant_activo` (`activo_id`),
  ADD KEY `fk_mant_user` (`creado_por`),
  ADD KEY `idx_mant_tenant` (`tenant_id`),
  ADD KEY `idx_mant_activo` (`tenant_id`,`activo_id`),
  ADD KEY `idx_mant_estado` (`tenant_id`,`estado`);

--
-- Indices de la tabla `mantenimientos_adjuntos`
--
ALTER TABLE `mantenimientos_adjuntos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mant` (`tenant_id`,`mantenimiento_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `fk_mant_adj_mantenimiento` (`mantenimiento_id`);

--
-- Indices de la tabla `marcas`
--
ALTER TABLE `marcas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_marca` (`tenant_id`,`nombre`);

--
-- Indices de la tabla `patrones`
--
ALTER TABLE `patrones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patrones_tenant` (`tenant_id`),
  ADD KEY `idx_patrones_estado` (`tenant_id`,`estado`),
  ADD KEY `idx_patrones_elim` (`tenant_id`,`eliminado`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`codigo`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_prov` (`tenant_id`,`nombre`);

--
-- Indices de la tabla `repuestos`
--
ALTER TABLE `repuestos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_rep` (`tenant_id`,`nombre`,`referencia`),
  ADD KEY `fk_rep_prov` (`proveedor_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role` (`tenant_id`,`nombre`);

--
-- Indices de la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rol_perm` (`tenant_id`,`rol_id`,`permiso_codigo`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_rol` (`rol_id`),
  ADD KEY `fk_rolperm_perm` (`permiso_codigo`);

--
-- Indices de la tabla `sedes`
--
ALTER TABLE `sedes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sede` (`tenant_id`,`nombre`);

--
-- Indices de la tabla `software_catalogo`
--
ALTER TABLE `software_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_soft_tenant` (`tenant_id`);

--
-- Indices de la tabla `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tipos_activo`
--
ALTER TABLE `tipos_activo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_tipos_tenant_codigo` (`tenant_id`,`codigo`),
  ADD KEY `idx_tipos_tenant_nombre` (`tenant_id`,`nombre`);

--
-- Indices de la tabla `tipo_activo_reglas`
--
ALTER TABLE `tipo_activo_reglas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_reglas_tenant_tipo` (`tenant_id`,`tipo_activo_id`),
  ADD KEY `fk_reglas_tipo` (`tipo_activo_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_email` (`tenant_id`,`email`),
  ADD KEY `fk_usuarios_rol` (`rol_id`);

--
-- Indices de la tabla `usuarios_firma`
--
ALTER TABLE `usuarios_firma`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tenant_usuario` (`tenant_id`,`usuario_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_usuario` (`usuario_id`);

--
-- Indices de la tabla `usuarios_firmas`
--
ALTER TABLE `usuarios_firmas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_usuario` (`tenant_id`,`usuario_id`),
  ADD KEY `idx_hash` (`firma_hash`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `activos`
--
ALTER TABLE `activos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `activos_adjuntos`
--
ALTER TABLE `activos_adjuntos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `activos_componentes`
--
ALTER TABLE `activos_componentes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `activos_software`
--
ALTER TABLE `activos_software`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `activo_software`
--
ALTER TABLE `activo_software`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `calibraciones`
--
ALTER TABLE `calibraciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calibraciones_adjuntos`
--
ALTER TABLE `calibraciones_adjuntos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calibraciones_patrones`
--
ALTER TABLE `calibraciones_patrones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calibraciones_puntos`
--
ALTER TABLE `calibraciones_puntos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categorias_activo`
--
ALTER TABLE `categorias_activo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `kardex`
--
ALTER TABLE `kardex`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `mantenimientos_adjuntos`
--
ALTER TABLE `mantenimientos_adjuntos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `marcas`
--
ALTER TABLE `marcas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `patrones`
--
ALTER TABLE `patrones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `repuestos`
--
ALTER TABLE `repuestos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `sedes`
--
ALTER TABLE `sedes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `software_catalogo`
--
ALTER TABLE `software_catalogo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tipos_activo`
--
ALTER TABLE `tipos_activo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de la tabla `tipo_activo_reglas`
--
ALTER TABLE `tipo_activo_reglas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios_firma`
--
ALTER TABLE `usuarios_firma`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios_firmas`
--
ALTER TABLE `usuarios_firmas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `activos`
--
ALTER TABLE `activos`
  ADD CONSTRAINT `fk_activos_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  ADD CONSTRAINT `fk_activos_cat` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_activo` (`id`),
  ADD CONSTRAINT `fk_activos_marca` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`),
  ADD CONSTRAINT `fk_activos_padre` FOREIGN KEY (`activo_padre_id`) REFERENCES `activos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_activos_prov` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  ADD CONSTRAINT `fk_activos_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_activos_tipo` FOREIGN KEY (`tipo_activo_id`) REFERENCES `tipos_activo` (`id`);

--
-- Filtros para la tabla `activos_componentes`
--
ALTER TABLE `activos_componentes`
  ADD CONSTRAINT `fk_comp_activo` FOREIGN KEY (`activo_id`) REFERENCES `activos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `activos_software`
--
ALTER TABLE `activos_software`
  ADD CONSTRAINT `fk_as_activo` FOREIGN KEY (`activo_id`) REFERENCES `activos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_as_softid` FOREIGN KEY (`software_id`) REFERENCES `software_catalogo` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_as_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `activo_software`
--
ALTER TABLE `activo_software`
  ADD CONSTRAINT `fk_activo_software_activo` FOREIGN KEY (`activo_id`) REFERENCES `activos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_activo_software_catalogo` FOREIGN KEY (`software_id`) REFERENCES `software_catalogo` (`id`);

--
-- Filtros para la tabla `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `fk_areas_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`),
  ADD CONSTRAINT `fk_areas_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `calibraciones_patrones`
--
ALTER TABLE `calibraciones_patrones`
  ADD CONSTRAINT `fk_calx_cal` FOREIGN KEY (`calibracion_id`) REFERENCES `calibraciones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_calx_pat` FOREIGN KEY (`patron_id`) REFERENCES `patrones` (`id`);

--
-- Filtros para la tabla `calibraciones_puntos`
--
ALTER TABLE `calibraciones_puntos`
  ADD CONSTRAINT `fk_calp_cal` FOREIGN KEY (`calibracion_id`) REFERENCES `calibraciones` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `categorias_activo`
--
ALTER TABLE `categorias_activo`
  ADD CONSTRAINT `fk_cat_act_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `kardex`
--
ALTER TABLE `kardex`
  ADD CONSTRAINT `fk_kardex_rep` FOREIGN KEY (`repuesto_id`) REFERENCES `repuestos` (`id`),
  ADD CONSTRAINT `fk_kardex_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_kardex_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD CONSTRAINT `fk_mant_activo` FOREIGN KEY (`activo_id`) REFERENCES `activos` (`id`),
  ADD CONSTRAINT `fk_mant_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_mant_user` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `mantenimientos_adjuntos`
--
ALTER TABLE `mantenimientos_adjuntos`
  ADD CONSTRAINT `fk_mant_adj_mantenimiento` FOREIGN KEY (`mantenimiento_id`) REFERENCES `mantenimientos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `marcas`
--
ALTER TABLE `marcas`
  ADD CONSTRAINT `fk_marcas_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD CONSTRAINT `fk_proveedores_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `repuestos`
--
ALTER TABLE `repuestos`
  ADD CONSTRAINT `fk_rep_prov` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  ADD CONSTRAINT `fk_rep_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `fk_roles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD CONSTRAINT `fk_rolperm_perm` FOREIGN KEY (`permiso_codigo`) REFERENCES `permisos` (`codigo`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `sedes`
--
ALTER TABLE `sedes`
  ADD CONSTRAINT `fk_sedes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `software_catalogo`
--
ALTER TABLE `software_catalogo`
  ADD CONSTRAINT `fk_softcat_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `tipos_activo`
--
ALTER TABLE `tipos_activo`
  ADD CONSTRAINT `fk_tipos_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `tipo_activo_reglas`
--
ALTER TABLE `tipo_activo_reglas`
  ADD CONSTRAINT `fk_reglas_tipo` FOREIGN KEY (`tipo_activo_id`) REFERENCES `tipos_activo` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_usuarios_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
