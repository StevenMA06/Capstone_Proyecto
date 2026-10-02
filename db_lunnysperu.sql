-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 25-09-2026 a las 07:58:26
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
-- Base de datos: `db_lunnysperu`
--
CREATE DATABASE IF NOT EXISTS `db_lunnysperu` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_lunnysperu`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas_inventario`
--

CREATE TABLE `alertas_inventario` (
  `alerta_id` bigint(20) UNSIGNED NOT NULL,
  `material_id` bigint(20) UNSIGNED NOT NULL,
  `tipo` enum('STOCK_MINIMO','SIN_STOCK') NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `generada_en` datetime NOT NULL DEFAULT current_timestamp(),
  `atendida_en` datetime DEFAULT NULL,
  `atendida_por` bigint(20) UNSIGNED DEFAULT NULL,
  `estado` enum('PENDIENTE','ATENDIDA') NOT NULL DEFAULT 'PENDIENTE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria_cambios`
--

CREATE TABLE `auditoria_cambios` (
  `auditoria_id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tabla_afectada` varchar(80) NOT NULL,
  `registro_id` varchar(80) NOT NULL,
  `accion` enum('INSERT','UPDATE','INHABILITAR','ANULAR','CAMBIO_ESTADO') NOT NULL,
  `valores_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`valores_anteriores`)),
  `valores_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`valores_nuevos`)),
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `cliente_id` bigint(20) UNSIGNED NOT NULL,
  `tipo_documento` enum('DNI','CE','RUC','OTRO') NOT NULL DEFAULT 'DNI',
  `numero_documento` varchar(20) NOT NULL,
  `nombres_razon_social` varchar(180) NOT NULL,
  `apellidos` varchar(120) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `direccion` varchar(255) NOT NULL,
  `referencia_direccion` varchar(255) DEFAULT NULL,
  `distrito` varchar(100) DEFAULT NULL,
  `provincia` varchar(100) DEFAULT NULL,
  `departamento` varchar(100) DEFAULT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_por` bigint(20) UNSIGNED NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`cliente_id`, `tipo_documento`, `numero_documento`, `nombres_razon_social`, `apellidos`, `correo`, `telefono`, `direccion`, `referencia_direccion`, `distrito`, `provincia`, `departamento`, `latitud`, `longitud`, `estado`, `creado_por`, `creado_en`, `actualizado_en`) VALUES
(1, 'OTRO', '76445486555545555555', 'Fabricio Ronaldo', 'Gil Cabanillas', 'fabricioros@gmail.com', '7798964646464664664646646', 'ukk', 'fweeeeeee', 'eee', 'eee', 'redes', 0.0000077, 0.0000077, 'INACTIVO', 1, '2026-09-12 15:05:54', '2026-09-12 15:08:32'),
(2, 'DNI', '45665525', 'Fabricio Ro', 'Gil Ca', 'antuamenu@gmail.com', '7798964646464664664646646', 'ukk', 'fweeeeeee', 'eee', 'eee', 'redes', 0.0000077, 0.0000077, 'ACTIVO', 1, '2026-09-12 15:08:10', '2026-09-23 12:36:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `codigos_acceso_2fa`
--

CREATE TABLE `codigos_acceso_2fa` (
  `codigo_2fa_id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `codigo_hash` varchar(255) NOT NULL,
  `solicitado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `vence_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  `invalidado_en` datetime DEFAULT NULL,
  `intentos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `estado` enum('VIGENTE','USADO','VENCIDO','INVALIDADO') NOT NULL DEFAULT 'VIGENTE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `codigos_acceso_2fa`
--

INSERT INTO `codigos_acceso_2fa` (`codigo_2fa_id`, `usuario_id`, `codigo_hash`, `solicitado_en`, `vence_en`, `usado_en`, `invalidado_en`, `intentos`, `estado`) VALUES
(1, 4, '$2y$12$xC9OKIRZiEzVtKVzmZm/EOkW2PfjWXxEVPApfUA1tzRHuxLjpcvLi', '2026-09-17 14:44:51', '2026-09-17 14:49:51', NULL, '2026-09-17 14:44:56', 0, 'INVALIDADO'),
(2, 5, '$2y$12$.zNjv6cI9IOBfPbuIvz/YehiISk47g2j8L0AACMN4gQiA3/4PgxNC', '2026-09-17 14:47:55', '2026-09-17 14:52:55', '2026-09-17 14:48:25', NULL, 0, 'USADO'),
(3, 6, '$2y$12$rKG8boIHA94iWkmQ/bCO4uMhkN9OudZUva478OBalNlgsnU9j1bgO', '2026-09-17 21:48:15', '2026-09-17 21:53:15', '2026-09-17 21:48:35', NULL, 0, 'USADO'),
(4, 6, '$2y$12$dru5aUoraFGtgiufaCbFO.CuNk8TWlFhkX8ju27tclb1FztwGrfQW', '2026-09-17 23:31:26', '2026-09-17 23:36:26', '2026-09-17 23:31:55', NULL, 0, 'USADO'),
(5, 6, '$2y$12$gbyP3Kz7cv0Vwe/532e80.FuasBnx9dt43zrLKT/oFkg3XVU1NJj2', '2026-09-18 08:39:09', '2026-09-18 08:44:09', '2026-09-18 08:42:15', NULL, 1, 'USADO'),
(6, 6, '$2y$12$NP2o4qxG9uPf7/fYhlMG8O2ke7mmWjEPcnMuQDK9VZ8AXPcNKOebW', '2026-09-18 19:26:07', '2026-09-18 19:31:07', '2026-09-18 19:26:53', NULL, 0, 'USADO'),
(7, 6, '$2y$12$0.icb0XaLfThD3rV7OCHKONzfGDOahp.QCkN4jJtA6jcIZfZU7wsa', '2026-09-20 00:12:11', '2026-09-20 00:17:11', '2026-09-20 00:12:24', NULL, 0, 'USADO'),
(8, 6, '$2y$12$46Okgrhj5PYxoPECWvFmb.0elNXpJ7kUae80e4pS.jQZLFcDe4QVu', '2026-09-21 22:09:06', '2026-09-21 22:14:06', '2026-09-21 22:09:28', NULL, 0, 'USADO'),
(9, 6, '$2y$12$ges14Rpcmzio76Xc4wzHoO6zKGcLxdN6o6bZ1PBHIlW3rrrzl.tQi', '2026-09-22 08:11:29', '2026-09-22 08:16:29', '2026-09-22 08:11:50', NULL, 0, 'USADO'),
(10, 6, '$2y$12$YNjqJ5vC1uiSTYNVLKodf.cpDZPMhHuKfEmuu0MKaKFx.sKql94JS', '2026-09-23 09:48:49', '2026-09-23 09:53:49', '2026-09-23 09:49:13', NULL, 0, 'USADO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contratos`
--

CREATE TABLE `contratos` (
  `contrato_id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `cliente_id` bigint(20) UNSIGNED NOT NULL,
  `plan_id` bigint(20) UNSIGNED NOT NULL,
  `zona_id` bigint(20) UNSIGNED NOT NULL,
  `responsable_id` bigint(20) UNSIGNED NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_inicio` date NOT NULL,
  `duracion_meses` smallint(5) UNSIGNED NOT NULL,
  `fecha_fin` date NOT NULL,
  `precio_contratado` decimal(12,2) NOT NULL,
  `direccion_servicio` varchar(255) NOT NULL,
  `estado` enum('PENDIENTE','ACTIVO','FINALIZADO','CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
  `periodo_actual` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'FK al período contractual activo actual',
  `pdf_contrato_ruta` varchar(500) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `firma_revision` varchar(20) DEFAULT NULL,
  `firma_validada_en` datetime DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `contratos`
--

INSERT INTO `contratos` (`contrato_id`, `codigo`, `cliente_id`, `plan_id`, `zona_id`, `responsable_id`, `fecha_registro`, `fecha_inicio`, `duracion_meses`, `fecha_fin`, `precio_contratado`, `direccion_servicio`, `estado`, `periodo_actual`, `pdf_contrato_ruta`, `observaciones`, `actualizado_en`, `firma_revision`, `firma_validada_en`) VALUES
(3, 'CON-TEST-20260923-001', 2, 1, 13, 1, '2026-09-23 11:30:23', '2026-10-01', 3, '2027-01-01', 80.00, 'ukk', 'CANCELADO', 2, NULL, '\n[2026-09-23 13:15:22] Cancelado automáticamente por falta de pago (pago PAG-20260923-B5F7F8 IMPAGO).', '2026-09-23 13:15:22', NULL, NULL),
(5, 'CON-TEST-20260923113122-001', 2, 1, 13, 1, '2026-09-23 11:31:22', '2026-10-01', 3, '2027-01-01', 80.00, 'ukk', 'CANCELADO', 3, NULL, '\n[2026-09-23 13:15:22] Cancelado automáticamente por falta de pago (pago PAG-20260923-3F6AA5 IMPAGO).', '2026-09-23 13:15:22', NULL, NULL),
(6, 'CON-TEST-20260923113122-002', 2, 1, 13, 1, '2026-09-23 11:31:22', '2026-10-01', 6, '2027-04-01', 120.00, 'ukk', 'ACTIVO', 4, NULL, NULL, '2026-09-23 11:31:22', NULL, NULL),
(7, 'CON-TEST-20260923113122-003', 2, 1, 13, 1, '2026-09-23 11:31:22', '2026-10-01', 12, '2027-10-01', 150.00, 'ukk', 'ACTIVO', 5, NULL, NULL, '2026-09-23 11:31:22', NULL, NULL),
(8, 'CON-TEST-20260923124153-001', 2, 1, 13, 1, '2026-09-23 12:41:53', '2026-10-01', 3, '2027-01-01', 80.00, 'ukk', 'ACTIVO', 6, NULL, NULL, '2026-09-23 12:41:53', NULL, NULL),
(9, 'CON-TEST-20260923124153-004', 2, 1, 13, 1, '2026-09-23 12:41:53', '2026-09-01', 1, '2026-10-01', 50.00, 'ukk', 'ACTIVO', 7, NULL, NULL, '2026-09-23 12:41:53', NULL, NULL),
(10, 'CON-TEST-20260923124153-002', 2, 1, 13, 1, '2026-09-23 12:41:53', '2026-10-01', 6, '2027-04-01', 120.00, 'ukk', 'ACTIVO', 8, NULL, NULL, '2026-09-23 12:41:53', NULL, NULL),
(11, 'CON-TEST-20260923124153-003', 2, 1, 13, 1, '2026-09-23 12:41:53', '2026-10-01', 12, '2027-10-01', 150.00, 'ukk', 'ACTIVO', 9, NULL, NULL, '2026-09-23 12:41:53', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conversaciones_ia`
--

CREATE TABLE `conversaciones_ia` (
  `conversacion_id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `titulo` varchar(200) DEFAULT NULL,
  `estado` enum('ACTIVA','CERRADA') NOT NULL DEFAULT 'ACTIVA',
  `creada_en` datetime NOT NULL DEFAULT current_timestamp(),
  `cerrada_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `datos_facturacion`
--

CREATE TABLE `datos_facturacion` (
  `dato_facturacion_id` bigint(20) UNSIGNED NOT NULL,
  `cliente_id` bigint(20) UNSIGNED NOT NULL,
  `tipo_documento` enum('DNI','CE','RUC','OTRO') NOT NULL,
  `numero_documento` varchar(20) NOT NULL,
  `nombres_razon_social` varchar(180) NOT NULL,
  `direccion_facturacion` varchar(255) NOT NULL,
  `correo_facturacion` varchar(150) DEFAULT NULL,
  `datos_adicionales_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Campos adicionales aprobados por Lunnys Perú para facturación' CHECK (json_valid(`datos_adicionales_json`)),
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_por` bigint(20) UNSIGNED NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_facturacion`
--

CREATE TABLE `documentos_facturacion` (
  `documento_facturacion_id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `tipo_documento` varchar(60) NOT NULL COMMENT 'Tipo definido dentro del alcance aprobado por Lunnys Perú',
  `formato_facturacion_id` bigint(20) UNSIGNED NOT NULL,
  `dato_facturacion_id` bigint(20) UNSIGNED NOT NULL,
  `cliente_id` bigint(20) UNSIGNED NOT NULL,
  `contrato_id` bigint(20) UNSIGNED NOT NULL,
  `pago_id` bigint(20) UNSIGNED NOT NULL,
  `fecha_emision` datetime NOT NULL DEFAULT current_timestamp(),
  `contenido_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Datos utilizados para generar el documento según el formato aprobado' CHECK (json_valid(`contenido_json`)),
  `ruta_documento` varchar(500) NOT NULL,
  `nombre_archivo` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `estado` enum('GENERADO','ANULADO') NOT NULL DEFAULT 'GENERADO',
  `generado_por` bigint(20) UNSIGNED NOT NULL,
  `anulado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `anulado_en` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(500) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ejecuciones_instalacion`
--

CREATE TABLE `ejecuciones_instalacion` (
  `ejecucion_id` bigint(20) UNSIGNED NOT NULL,
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `tecnico_id` bigint(20) UNSIGNED NOT NULL,
  `inicio_en` datetime DEFAULT NULL,
  `fin_en` datetime DEFAULT NULL,
  `estado` enum('PENDIENTE','EN_PROCESO','COMPLETADA') NOT NULL DEFAULT 'PENDIENTE',
  `observacion_tecnica` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleados`
--

CREATE TABLE `empleados` (
  `empleado_id` bigint(20) UNSIGNED NOT NULL,
  `tipo_documento` enum('DNI','CE','OTRO') NOT NULL DEFAULT 'DNI',
  `numero_documento` varchar(20) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(120) NOT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `cargo` varchar(80) NOT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `fecha_cese` date DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `empleados`
--

INSERT INTO `empleados` (`empleado_id`, `tipo_documento`, `numero_documento`, `nombres`, `apellidos`, `correo`, `telefono`, `cargo`, `fecha_ingreso`, `fecha_cese`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 'DNI', '99999999', 'Responsable', 'Prueba', NULL, NULL, 'Asistente de Ventas', NULL, NULL, 'ACTIVO', '2026-09-23 11:27:40', '2026-09-23 11:27:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipos`
--

CREATE TABLE `equipos` (
  `equipo_id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `nombre` varchar(120) NOT NULL,
  `tipo` varchar(80) NOT NULL,
  `marca` varchar(80) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `condicion` enum('NUEVO','BUENO','REGULAR','MALO','MALOGRADO') NOT NULL DEFAULT 'BUENO',
  `estado` enum('DISPONIBLE','ASIGNADO','MANTENIMIENTO','INACTIVO') NOT NULL DEFAULT 'DISPONIBLE',
  `observaciones` varchar(500) DEFAULT NULL,
  `creado_por` bigint(20) UNSIGNED NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evidencias_contrato`
--

CREATE TABLE `evidencias_contrato` (
  `evidencia_contrato_id` bigint(20) UNSIGNED NOT NULL,
  `contrato_id` bigint(20) UNSIGNED NOT NULL,
  `tipo` enum('CONTRATO_FIRMADO','FOTO_VALIDACION','OTRA') NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `nombre_archivo` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `subido_por` bigint(20) UNSIGNED NOT NULL,
  `subido_en` datetime NOT NULL DEFAULT current_timestamp(),
  `observacion` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evidencias_instalacion`
--

CREATE TABLE `evidencias_instalacion` (
  `evidencia_id` bigint(20) UNSIGNED NOT NULL,
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `ejecucion_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tipo` enum('FOTOGRAFIA_INSTALACION','OTRA') NOT NULL DEFAULT 'FOTOGRAFIA_INSTALACION',
  `ruta_archivo` varchar(500) NOT NULL,
  `nombre_archivo` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `subido_por` bigint(20) UNSIGNED NOT NULL,
  `subido_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `formatos_facturacion`
--

CREATE TABLE `formatos_facturacion` (
  `formato_facturacion_id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `version` varchar(30) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `plantilla_ruta` varchar(500) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `aprobado_por` bigint(20) UNSIGNED NOT NULL,
  `aprobado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fuentes_respuesta_ia`
--

CREATE TABLE `fuentes_respuesta_ia` (
  `fuente_id` bigint(20) UNSIGNED NOT NULL,
  `mensaje_id` bigint(20) UNSIGNED NOT NULL,
  `tipo_fuente` enum('CLIENTES','CONTRATOS','VENTAS','PAGOS','FACTURACION','EQUIPOS','MATERIALES','KARDEX','INSTALACIONES','INDICADORES','BI','OTRA') NOT NULL,
  `referencia_interna` varchar(255) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_accesos`
--

CREATE TABLE `historial_accesos` (
  `acceso_id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nombre_usuario_intentado` varchar(60) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `resultado` enum('EXITOSO','CREDENCIALES_INCORRECTAS','2FA_INCORRECTO','2FA_VENCIDO','CUENTA_INACTIVA','BLOQUEADO') NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `detalle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `historial_accesos`
--

INSERT INTO `historial_accesos` (`acceso_id`, `usuario_id`, `nombre_usuario_intentado`, `fecha_hora`, `resultado`, `ip`, `user_agent`, `detalle`) VALUES
(1, NULL, 'admin', '2026-09-12 14:54:54', 'CREDENCIALES_INCORRECTAS', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL),
(2, NULL, 'antuamenu@gmail.com', '2026-09-12 14:57:35', 'CREDENCIALES_INCORRECTAS', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL),
(3, 1, 'personal01', '2026-09-12 14:58:20', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL),
(4, 1, 'personal01', '2026-09-12 17:49:37', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(5, 2, 'admin', '2026-09-12 18:08:41', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(6, 3, 'admin2', '2026-09-12 18:11:30', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(7, 3, 'admin2', '2026-09-12 18:11:40', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(8, 3, 'admin2', '2026-09-14 04:41:19', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(9, 3, 'admin2', '2026-09-14 15:18:17', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(10, 3, 'admin2', '2026-09-14 15:18:28', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(11, 3, 'admin2', '2026-09-17 14:26:35', 'CUENTA_INACTIVA', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(12, 3, 'admin2', '2026-09-17 14:26:41', 'CUENTA_INACTIVA', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(13, 2, 'admin', '2026-09-17 14:26:55', 'CUENTA_INACTIVA', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(14, NULL, 'persona1', '2026-09-17 14:28:24', 'CREDENCIALES_INCORRECTAS', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(15, 3, 'admin2', '2026-09-17 14:32:27', 'CUENTA_INACTIVA', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(16, 3, 'admin2', '2026-09-17 14:32:35', 'CUENTA_INACTIVA', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(17, 3, 'admin2', '2026-09-17 14:40:18', 'CUENTA_INACTIVA', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(18, 3, 'admin2', '2026-09-17 14:40:26', 'CUENTA_INACTIVA', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(19, 3, 'admin2', '2026-09-17 14:40:56', 'CUENTA_INACTIVA', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(20, 3, 'admin2', '2026-09-17 14:40:57', 'CUENTA_INACTIVA', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(21, 5, 'articuno2', '2026-09-17 14:48:26', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(22, 5, 'articuno2', '2026-09-17 21:32:22', 'CREDENCIALES_INCORRECTAS', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(23, 6, 'admin3', '2026-09-17 21:48:37', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(24, NULL, 'iglesiascortegana.nayeli@gmail.com', '2026-09-17 23:31:13', 'CREDENCIALES_INCORRECTAS', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', NULL),
(25, 6, 'admin3', '2026-09-17 23:31:55', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', NULL),
(26, 6, 'admin3', '2026-09-18 08:42:09', '2FA_INCORRECTO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(27, 6, 'admin3', '2026-09-18 08:42:15', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(28, 6, 'admin3', '2026-09-18 19:26:55', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(29, 6, 'admin3', '2026-09-20 00:12:25', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(30, 6, 'admin3', '2026-09-21 22:09:30', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(31, 6, 'admin3', '2026-09-22 08:11:51', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL),
(32, 6, 'admin3', '2026-09-23 09:49:15', 'EXITOSO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_facturacion`
--

CREATE TABLE `historial_facturacion` (
  `historial_facturacion_id` bigint(20) UNSIGNED NOT NULL,
  `documento_facturacion_id` bigint(20) UNSIGNED NOT NULL,
  `accion` enum('GENERACION','ANULACION') NOT NULL,
  `estado_anterior` enum('GENERADO','ANULADO') DEFAULT NULL,
  `estado_nuevo` enum('GENERADO','ANULADO') NOT NULL,
  `detalle` varchar(500) DEFAULT NULL,
  `realizado_por` bigint(20) UNSIGNED NOT NULL,
  `realizado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales`
--

CREATE TABLE `materiales` (
  `material_id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(60) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `unidad_base` varchar(30) NOT NULL,
  `unidad_empaque` varchar(30) DEFAULT NULL,
  `factor_conversion` decimal(10,3) DEFAULT NULL,
  `stock_actual` decimal(12,3) NOT NULL DEFAULT 0.000,
  `stock_minimo` decimal(12,3) NOT NULL DEFAULT 0.000,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_por` bigint(20) UNSIGNED NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes_ia`
--

CREATE TABLE `mensajes_ia` (
  `mensaje_id` bigint(20) UNSIGNED NOT NULL,
  `conversacion_id` bigint(20) UNSIGNED NOT NULL,
  `rol_mensaje` enum('USUARIO','ASISTENTE') NOT NULL,
  `contenido` longtext NOT NULL,
  `respuesta_suficiente` tinyint(1) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2026_09_10_000001_import_lunnys_schema', 1),
(2, '2026_09_14_000002_normalize_user_roles', 2),
(3, '2026_09_17_000001_add_contract_signature_verification', 2),
(4, '2026_09_17_000003_plan_sequence_and_peru_time', 2),
(5, '2026_09_21_000001_create_periodos_contractuales_table', 3),
(6, '2026_09_21_000002_add_payment_notification_tracking', 3),
(7, '2026_09_23_102142_add_no_pagado_status_to_pagos_table', 4),
(9, '2026_09_23_122220_rename_no_pagado_to_resuelto_in_pagos_table', 5),
(10, '2026_09_23_125409_rename_resuelto_to_impago_in_pagos_table', 6),
(11, '2026_09_18_000001_backfill_confirmed_contract_sales', 7);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_inventario`
--

CREATE TABLE `movimientos_inventario` (
  `movimiento_id` bigint(20) UNSIGNED NOT NULL,
  `tipo_movimiento` enum('ENTRADA','SALIDA','ASIGNACION','CONSUMO','DEVOLUCION','AJUSTE') NOT NULL,
  `equipo_id` bigint(20) UNSIGNED DEFAULT NULL,
  `material_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pedido_id` bigint(20) UNSIGNED DEFAULT NULL,
  `cantidad` decimal(12,3) NOT NULL DEFAULT 1.000,
  `cantidad_anterior` decimal(12,3) DEFAULT NULL,
  `cantidad_posterior` decimal(12,3) DEFAULT NULL,
  `justificacion` varchar(500) DEFAULT NULL,
  `responsable_id` bigint(20) UNSIGNED NOT NULL,
  `fecha_movimiento` datetime NOT NULL DEFAULT current_timestamp(),
  `observacion` varchar(500) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `pago_id` bigint(20) UNSIGNED NOT NULL,
  `contrato_id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `periodo_servicio` char(7) DEFAULT NULL COMMENT 'Formato YYYY-MM',
  `fecha_vencimiento` date NOT NULL,
  `fecha_pago` datetime DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `metodo_pago` varchar(60) DEFAULT NULL,
  `referencia_externa` varchar(120) DEFAULT NULL,
  `estado` enum('PENDIENTE','REALIZADO','VENCIDO','IMPAGO','ANULADO') NOT NULL DEFAULT 'PENDIENTE',
  `registrado_por` bigint(20) UNSIGNED NOT NULL,
  `constancia_ruta` varchar(500) DEFAULT NULL,
  `recordatorio_previo_enviado_en` datetime DEFAULT NULL COMMENT 'Fecha de envío del correo 7 días antes del vencimiento',
  `recordatorio_vencido_enviado_en` datetime DEFAULT NULL COMMENT 'Fecha de envío del correo 5 días después del vencimiento',
  `notificacion_anulacion_enviada_en` datetime DEFAULT NULL COMMENT 'Fecha de envío del correo de anulación/cancelación',
  `observaciones` varchar(500) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`pago_id`, `contrato_id`, `codigo`, `periodo_servicio`, `fecha_vencimiento`, `fecha_pago`, `monto`, `metodo_pago`, `referencia_externa`, `estado`, `registrado_por`, `constancia_ruta`, `recordatorio_previo_enviado_en`, `recordatorio_vencido_enviado_en`, `notificacion_anulacion_enviada_en`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(1, 3, 'PAG-20260923-B5F7F8', '2026-10', '2026-09-10', NULL, 80.00, NULL, NULL, 'ANULADO', 1, NULL, NULL, NULL, '2026-09-23 13:18:06', 'Pago generado automáticamente para período 2026-10\nMarcado como RESUELTO automáticamente por superar 7 días de vencimiento.\n[2026-09-23 13:15:22] Anulado automáticamente por cancelación del contrato.', '2026-09-23 11:30:23', '2026-09-23 13:18:06'),
(2, 3, 'PAG-20260923-2E1E40', '2026-11', '2026-11-01', NULL, 80.00, NULL, NULL, 'ANULADO', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-11\n[2026-09-23 13:15:22] Anulado automáticamente por cancelación del contrato.', '2026-09-23 11:30:23', '2026-09-23 13:15:22'),
(3, 3, 'PAG-20260923-9B7DC0', '2026-12', '2026-12-01', NULL, 80.00, NULL, NULL, 'ANULADO', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-12\n[2026-09-23 13:15:22] Anulado automáticamente por cancelación del contrato.', '2026-09-23 11:30:23', '2026-09-23 13:15:22'),
(4, 5, 'PAG-20260923-3F6AA5', '2026-10', '2026-09-10', NULL, 80.00, NULL, NULL, 'ANULADO', 1, NULL, NULL, NULL, '2026-09-23 13:18:07', 'Pago generado automáticamente para período 2026-10\nMarcado como IMPAGO automáticamente por superar 7 días de vencimiento.\n[2026-09-23 13:15:22] Anulado automáticamente por cancelación del contrato.', '2026-09-23 11:31:22', '2026-09-23 13:18:07'),
(5, 5, 'PAG-20260923-41E60B', '2026-11', '2026-11-01', NULL, 80.00, NULL, NULL, 'ANULADO', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-11\n[2026-09-23 13:15:22] Anulado automáticamente por cancelación del contrato.', '2026-09-23 11:31:22', '2026-09-23 13:15:22'),
(6, 5, 'PAG-20260923-772461', '2026-12', '2026-12-01', NULL, 80.00, NULL, NULL, 'ANULADO', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-12\n[2026-09-23 13:15:22] Anulado automáticamente por cancelación del contrato.', '2026-09-23 11:31:22', '2026-09-23 13:15:22'),
(7, 6, 'PAG-20260923-410E9B', '2026-10', '2026-10-01', '2026-09-23 00:00:00', 120.00, 'YAPE', '999999999999', 'REALIZADO', 1, 'storage/comprobantes/comprobante_pago_PAG-20260923-410E9B_20260923140843.pdf', NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-10', '2026-09-23 11:31:22', '2026-09-23 14:08:44'),
(8, 6, 'PAG-20260923-594690', '2026-11', '2026-11-01', NULL, 120.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-11', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(9, 6, 'PAG-20260923-2E4B35', '2026-12', '2026-12-01', NULL, 120.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-12', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(10, 6, 'PAG-20260923-CD2806', '2027-01', '2027-01-01', NULL, 120.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-01', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(11, 6, 'PAG-20260923-541F87', '2027-02', '2027-02-01', NULL, 120.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-02', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(12, 6, 'PAG-20260923-064D1C', '2027-03', '2027-03-01', NULL, 120.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-03', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(13, 7, 'PAG-20260923-303559', '2026-10', '2026-10-01', '2026-09-23 00:00:00', 1.00, 'EFECTIVO', '798988', 'REALIZADO', 1, 'storage/comprobantes/comprobante_pago_PAG-20260923-303559_20260923134415.pdf', NULL, NULL, NULL, 'wertyuiop´psdfghjkldfghjkerfvb 4rfvb dfv', '2026-09-23 11:31:22', '2026-09-23 13:44:16'),
(14, 7, 'PAG-20260923-E2B723', '2026-11', '2026-11-01', '2026-09-23 00:00:00', 150.00, 'TARJETA', '3333333333333333', 'REALIZADO', 1, 'storage/comprobantes/comprobante_pago_PAG-20260923-E2B723_20260923144932.pdf', NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-11\n- Pago confirmado por usuario admin3 el 23/09/2026 14:49', '2026-09-23 11:31:22', '2026-09-23 14:49:32'),
(15, 7, 'PAG-20260923-CC9D3C', '2026-12', '2026-12-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-12', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(16, 7, 'PAG-20260923-BE6457', '2027-01', '2027-01-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-01', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(17, 7, 'PAG-20260923-1545A8', '2027-02', '2027-02-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-02', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(18, 7, 'PAG-20260923-E71C7C', '2027-03', '2027-03-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-03', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(19, 7, 'PAG-20260923-E08084', '2027-04', '2027-04-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-04', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(20, 7, 'PAG-20260923-82B9BE', '2027-05', '2027-05-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-05', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(21, 7, 'PAG-20260923-52A8CB', '2027-06', '2027-06-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-06', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(22, 7, 'PAG-20260923-3FB91E', '2027-07', '2027-07-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-07', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(23, 7, 'PAG-20260923-8E2D56', '2027-08', '2027-08-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-08', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(24, 7, 'PAG-20260923-134587', '2027-09', '2027-09-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-09', '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(25, 8, 'PAG-20260923-FC9F6B', '2026-10', '2026-10-01', '2026-09-23 00:00:00', 80.00, 'PLIN', '789655', 'REALIZADO', 1, NULL, NULL, NULL, NULL, 'uuuuuuuuuuuuuuuuuuuuuuuuuuu', '2026-09-23 12:41:53', '2026-09-23 13:31:57'),
(26, 8, 'PAG-20260923-DA98EE', '2026-11', '2026-11-01', '2026-09-23 00:00:00', 80.00, 'TRANSFERENCIA', '544444444444444', 'REALIZADO', 1, 'storage/comprobantes/comprobante_pago_PAG-20260923-DA98EE_20260923144914.pdf', NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-11\n- Pago confirmado por usuario admin3 el 23/09/2026 14:49', '2026-09-23 12:41:53', '2026-09-23 14:49:14'),
(27, 8, 'PAG-20260923-D188CE', '2026-12', '2026-12-01', NULL, 80.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-12', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(28, 9, 'PAG-20260923-B95062', '2026-09', '2026-09-22', NULL, 50.00, NULL, NULL, 'VENCIDO', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-09', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(29, 10, 'PAG-20260923-265771', '2026-10', '2026-10-01', '2026-09-23 00:00:00', 120.00, 'TRANSFERENCIA', '799244521', 'REALIZADO', 1, 'storage/comprobantes/comprobante_pago_PAG-20260923-265771_20260923140400.pdf', NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-10', '2026-09-23 12:41:53', '2026-09-23 14:04:06'),
(30, 10, 'PAG-20260923-536CC4', '2026-11', '2026-11-01', '2026-09-23 00:00:00', 120.00, 'TRANSFERENCIA', '555555555555555555555555555', 'REALIZADO', 1, 'storage/comprobantes/comprobante_pago_PAG-20260923-536CC4_20260923144849.pdf', NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-11\n- Pago confirmado por usuario admin3 el 23/09/2026 14:48', '2026-09-23 12:41:53', '2026-09-23 14:48:49'),
(31, 10, 'PAG-20260923-F2C35D', '2026-12', '2026-12-01', NULL, 120.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-12', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(32, 10, 'PAG-20260923-5454E6', '2027-01', '2027-01-01', NULL, 120.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-01', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(33, 10, 'PAG-20260923-B9A972', '2027-02', '2027-02-01', NULL, 120.00, NULL, NULL, 'ANULADO', 1, NULL, NULL, NULL, NULL, 'yo', '2026-09-23 12:41:53', '2026-09-23 14:46:24'),
(34, 10, 'PAG-20260923-CFDF96', '2027-03', '2027-03-01', NULL, 120.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-03', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(35, 11, 'PAG-20260923-A07149', '2026-10', '2026-10-01', '2026-09-23 00:00:00', 150.00, 'PLIN', NULL, 'REALIZADO', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-10', '2026-09-23 12:41:53', '2026-09-23 13:29:25'),
(36, 11, 'PAG-20260923-67BC15', '2026-11', '2026-11-01', '2026-09-23 00:00:00', 150.00, 'PLIN', '7777777777777', 'REALIZADO', 1, 'storage/comprobantes/comprobante_pago_PAG-20260923-67BC15_20260923141234.pdf', NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-11\n- Pago confirmado por usuario admin3 el 23/09/2026 14:12', '2026-09-23 12:41:53', '2026-09-23 14:12:34'),
(37, 11, 'PAG-20260923-EA86CF', '2026-12', '2026-12-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2026-12', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(38, 11, 'PAG-20260923-F43D57', '2027-01', '2027-01-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-01', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(39, 11, 'PAG-20260923-1C396D', '2027-02', '2027-02-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-02', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(40, 11, 'PAG-20260923-82AEC0', '2027-03', '2027-03-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-03', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(41, 11, 'PAG-20260923-90EA37', '2027-04', '2027-04-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-04', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(42, 11, 'PAG-20260923-95D578', '2027-05', '2027-05-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-05', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(43, 11, 'PAG-20260923-DE4B44', '2027-06', '2027-06-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-06', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(44, 11, 'PAG-20260923-F50A35', '2027-07', '2027-07-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-07', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(45, 11, 'PAG-20260923-28E48D', '2027-08', '2027-08-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-08', '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(46, 11, 'PAG-20260923-1F2B8B', '2027-09', '2027-09-01', NULL, 150.00, NULL, NULL, 'PENDIENTE', 1, NULL, NULL, NULL, NULL, 'Pago generado automáticamente para período 2027-09', '2026-09-23 12:41:53', '2026-09-23 12:41:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos_instalacion`
--

CREATE TABLE `pedidos_instalacion` (
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `contrato_id` bigint(20) UNSIGNED NOT NULL,
  `cliente_id` bigint(20) UNSIGNED NOT NULL,
  `tecnico_id` bigint(20) UNSIGNED DEFAULT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_programada` datetime DEFAULT NULL,
  `fecha_original` datetime DEFAULT NULL,
  `estado` enum('PENDIENTE','PROGRAMADO','REPROGRAMADO','EN_PROCESO','COMPLETADO','CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
  `motivo_reprogramacion` varchar(500) DEFAULT NULL,
  `observaciones` varchar(500) DEFAULT NULL,
  `creado_por` bigint(20) UNSIGNED NOT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_equipos`
--

CREATE TABLE `pedido_equipos` (
  `pedido_equipo_id` bigint(20) UNSIGNED NOT NULL,
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `equipo_id` bigint(20) UNSIGNED NOT NULL,
  `asignado_por` bigint(20) UNSIGNED NOT NULL,
  `fecha_asignacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_devolucion` datetime DEFAULT NULL,
  `condicion_devolucion` enum('NUEVO','BUENO','REGULAR','MALO','MALOGRADO') DEFAULT NULL,
  `estado_asignacion` enum('ASIGNADO','UTILIZADO','DEVUELTO','CANCELADO') NOT NULL DEFAULT 'ASIGNADO',
  `equipo_asignacion_activa` bigint(20) UNSIGNED GENERATED ALWAYS AS (case when `estado_asignacion` in ('ASIGNADO','UTILIZADO') and `fecha_devolucion` is null then `equipo_id` else NULL end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_materiales`
--

CREATE TABLE `pedido_materiales` (
  `pedido_material_id` bigint(20) UNSIGNED NOT NULL,
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `material_id` bigint(20) UNSIGNED NOT NULL,
  `cantidad_asignada` decimal(12,3) NOT NULL,
  `cantidad_utilizada` decimal(12,3) NOT NULL DEFAULT 0.000,
  `cantidad_devuelta` decimal(12,3) NOT NULL DEFAULT 0.000,
  `asignado_por` bigint(20) UNSIGNED NOT NULL,
  `asignado_en` datetime NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `periodos_contractuales`
--

CREATE TABLE `periodos_contractuales` (
  `periodo_id` bigint(20) UNSIGNED NOT NULL,
  `contrato_id` bigint(20) UNSIGNED NOT NULL,
  `numero_periodo` smallint(5) UNSIGNED NOT NULL COMMENT '1, 2, 3... secuencial por contrato',
  `fecha_inicio_periodo` date NOT NULL,
  `fecha_fin_periodo` date NOT NULL,
  `duracion_meses_periodo` smallint(5) UNSIGNED NOT NULL,
  `estado_periodo` enum('ACTIVO','CANCELADO','FINALIZADO') NOT NULL DEFAULT 'ACTIVO',
  `motivo_cancelacion` varchar(500) DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT NULL,
  `cancelado_por` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'FK a usuarios',
  `origen_cancelacion` enum('MANUAL','AUTOMATICO_FALTA_PAGO') DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `periodos_contractuales`
--

INSERT INTO `periodos_contractuales` (`periodo_id`, `contrato_id`, `numero_periodo`, `fecha_inicio_periodo`, `fecha_fin_periodo`, `duracion_meses_periodo`, `estado_periodo`, `motivo_cancelacion`, `fecha_cancelacion`, `cancelado_por`, `origen_cancelacion`, `creado_en`, `actualizado_en`) VALUES
(2, 3, 1, '2026-10-01', '2027-01-01', 3, 'CANCELADO', 'Cancelado automáticamente por falta de pago. Pago IMPAGO: PAG-20260923-B5F7F8', '2026-09-23 13:15:22', NULL, 'AUTOMATICO_FALTA_PAGO', '2026-09-23 11:30:23', '2026-09-23 13:15:22'),
(3, 5, 1, '2026-10-01', '2027-01-01', 3, 'CANCELADO', 'Cancelado automáticamente por falta de pago. Pago IMPAGO: PAG-20260923-3F6AA5', '2026-09-23 13:15:22', NULL, 'AUTOMATICO_FALTA_PAGO', '2026-09-23 11:31:22', '2026-09-23 13:15:22'),
(4, 6, 1, '2026-10-01', '2027-04-01', 6, 'ACTIVO', NULL, NULL, NULL, NULL, '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(5, 7, 1, '2026-10-01', '2027-10-01', 12, 'ACTIVO', NULL, NULL, NULL, NULL, '2026-09-23 11:31:22', '2026-09-23 11:31:22'),
(6, 8, 1, '2026-10-01', '2027-01-01', 3, 'ACTIVO', NULL, NULL, NULL, NULL, '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(7, 9, 1, '2026-09-01', '2026-10-01', 1, 'ACTIVO', NULL, NULL, NULL, NULL, '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(8, 10, 1, '2026-10-01', '2027-04-01', 6, 'ACTIVO', NULL, NULL, NULL, NULL, '2026-09-23 12:41:53', '2026-09-23 12:41:53'),
(9, 11, 1, '2026-10-01', '2027-10-01', 12, 'ACTIVO', NULL, NULL, NULL, NULL, '2026-09-23 12:41:53', '2026-09-23 12:41:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `permiso_id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(100) NOT NULL,
  `modulo` varchar(60) NOT NULL,
  `accion` varchar(60) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planes_servicio`
--

CREATE TABLE `planes_servicio` (
  `plan_id` bigint(20) UNSIGNED NOT NULL,
  `numero_plan` varchar(30) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `tipo_servicio` varchar(80) NOT NULL,
  `velocidad_mbps` int(10) UNSIGNED DEFAULT NULL,
  `precio_mensual` decimal(12,2) NOT NULL,
  `condiciones` text DEFAULT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_por` bigint(20) UNSIGNED NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `planes_servicio`
--

INSERT INTO `planes_servicio` (`plan_id`, `numero_plan`, `nombre`, `tipo_servicio`, `velocidad_mbps`, `precio_mensual`, `condiciones`, `descripcion`, `estado`, `creado_por`, `creado_en`, `actualizado_en`) VALUES
(1, 'PLAN-000001', 'Urb. Primavera', 'internet', 1111111, 2222222.00, '1111111111111', '111111111111', 'ACTIVO', 6, '2026-09-17 22:23:52', '2026-09-17 22:23:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plan_zona_cobertura`
--

CREATE TABLE `plan_zona_cobertura` (
  `plan_id` bigint(20) UNSIGNED NOT NULL,
  `zona_id` bigint(20) UNSIGNED NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `asignado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recordatorios_pago`
--

CREATE TABLE `recordatorios_pago` (
  `recordatorio_id` bigint(20) UNSIGNED NOT NULL,
  `pago_id` bigint(20) UNSIGNED NOT NULL,
  `creado_por` bigint(20) UNSIGNED NOT NULL,
  `fecha_recordatorio` datetime NOT NULL DEFAULT current_timestamp(),
  `mensaje` varchar(500) NOT NULL,
  `estado` enum('PENDIENTE','ATENDIDO','DESCARTADO') NOT NULL DEFAULT 'PENDIENTE',
  `atendido_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes_generados`
--

CREATE TABLE `reportes_generados` (
  `reporte_id` bigint(20) UNSIGNED NOT NULL,
  `tipo_reporte` varchar(80) NOT NULL,
  `formato` enum('PDF','EXCEL') NOT NULL,
  `filtros_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filtros_json`)),
  `ruta_archivo` varchar(500) NOT NULL,
  `generado_por` bigint(20) UNSIGNED NOT NULL,
  `generado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `rol_id` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`rol_id`, `nombre`, `descripcion`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 'Gerencia', NULL, 'INACTIVO', '2026-09-12 14:52:04', '2026-09-17 21:37:46'),
(2, 'Personal autorizado', NULL, 'INACTIVO', '2026-09-12 14:52:04', '2026-09-17 21:37:46'),
(3, 'Administrador', NULL, 'ACTIVO', '2026-09-17 14:35:24', '2026-09-17 14:35:24'),
(4, 'Gerente', NULL, 'ACTIVO', '2026-09-17 14:35:24', '2026-09-17 14:35:24'),
(5, 'Usuario de oficina', NULL, 'ACTIVO', '2026-09-17 14:35:24', '2026-09-17 14:35:24'),
(6, 'Técnico', NULL, 'ACTIVO', '2026-09-17 14:35:24', '2026-09-17 14:35:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permiso`
--

CREATE TABLE `rol_permiso` (
  `rol_id` bigint(20) UNSIGNED NOT NULL,
  `permiso_id` bigint(20) UNSIGNED NOT NULL,
  `permitido` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `secuencias_sistema`
--

CREATE TABLE `secuencias_sistema` (
  `nombre` varchar(40) NOT NULL,
  `valor` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `secuencias_sistema`
--

INSERT INTO `secuencias_sistema` (`nombre`, `valor`) VALUES
('planes', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones_usuario`
--

CREATE TABLE `sesiones_usuario` (
  `sesion_id` char(36) NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `iniciada_en` datetime NOT NULL DEFAULT current_timestamp(),
  `ultima_actividad_en` datetime NOT NULL DEFAULT current_timestamp(),
  `expira_en` datetime NOT NULL,
  `cerrada_en` datetime DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `estado` enum('ACTIVA','CERRADA','EXPIRADA') NOT NULL DEFAULT 'ACTIVA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sesiones_usuario`
--

INSERT INTO `sesiones_usuario` (`sesion_id`, `usuario_id`, `iniciada_en`, `ultima_actividad_en`, `expira_en`, `cerrada_en`, `ip`, `user_agent`, `estado`) VALUES
('06f75366-7bbf-42cf-a60a-270d890c65ad', 6, '2026-09-21 22:09:30', '2026-09-21 22:11:03', '2026-09-22 00:11:03', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'ACTIVA'),
('1e6a6e49-efc2-46ec-9343-47bb7678f4e9', 3, '2026-09-12 18:11:40', '2026-09-12 18:15:41', '2026-09-12 20:15:41', '2026-09-17 21:37:46', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'CERRADA'),
('2a074130-1332-4576-bc79-0efa3b0e624a', 5, '2026-09-17 14:48:26', '2026-09-17 14:52:17', '2026-09-17 16:52:17', '2026-09-17 21:37:46', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'CERRADA'),
('2ddc9fe5-e184-416c-938d-56ed76ae9ed3', 6, '2026-09-23 09:49:15', '2026-09-23 14:51:46', '2026-09-23 16:51:46', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'ACTIVA'),
('3260e276-e79a-432c-9cdb-3578e776c6ad', 6, '2026-09-17 23:31:55', '2026-09-18 00:21:17', '2026-09-18 02:21:17', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'ACTIVA'),
('355ae39c-12d9-4ac8-9fd3-b133d328fb3f', 2, '2026-09-12 18:08:40', '2026-09-12 18:10:28', '2026-09-12 20:10:28', '2026-09-12 18:10:28', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'CERRADA'),
('3cdc1634-9a80-4daf-a412-9bce73e06e12', 3, '2026-09-14 15:18:28', '2026-09-14 15:28:11', '2026-09-14 17:28:11', '2026-09-17 21:37:46', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'CERRADA'),
('3e61a9de-8dd0-431b-99aa-a67e8baa8fc6', 3, '2026-09-14 04:41:19', '2026-09-14 06:59:47', '2026-09-14 08:59:47', '2026-09-17 21:37:46', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'CERRADA'),
('3f741bc3-ee49-4a0e-88d5-39b3b4420a4b', 6, '2026-09-18 19:26:55', '2026-09-18 20:39:54', '2026-09-18 22:39:54', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'ACTIVA'),
('54bd2da9-1c6f-4824-8172-bd4e089a9cc5', 3, '2026-09-12 18:11:30', '2026-09-12 18:11:36', '2026-09-12 20:11:36', '2026-09-12 18:11:36', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'CERRADA'),
('72c82747-ae4d-4c23-b5db-d131a549c64c', 6, '2026-09-22 08:11:51', '2026-09-22 08:22:22', '2026-09-22 10:22:22', '2026-09-22 10:30:20', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'EXPIRADA'),
('8831afe9-5ea8-478d-8cfe-fae6321f8416', 6, '2026-09-18 08:42:15', '2026-09-18 11:01:32', '2026-09-18 13:01:32', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'ACTIVA'),
('be86a95e-f0e3-4d1a-9cb8-78c425415d01', 6, '2026-09-20 00:12:25', '2026-09-20 00:12:32', '2026-09-20 02:12:32', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'ACTIVA'),
('d4c69b4b-24ed-4a90-a2f7-2562222bd129', 3, '2026-09-14 15:18:17', '2026-09-14 15:18:17', '2026-09-14 17:18:17', '2026-09-17 21:37:46', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'CERRADA'),
('e0da16ba-f648-460c-a0f9-a76f148508e0', 1, '2026-09-12 17:49:37', '2026-09-12 18:06:32', '2026-09-12 20:06:32', '2026-09-12 18:06:32', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'CERRADA'),
('e83a76fd-713c-492e-94d6-bb44b9b1a7eb', 6, '2026-09-17 21:48:37', '2026-09-17 22:55:46', '2026-09-18 00:55:46', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', 'ACTIVA'),
('f826ae02-655f-4022-96f7-d24616ba1b91', 1, '2026-09-12 14:58:20', '2026-09-12 15:08:52', '2026-09-12 17:08:52', '2026-09-17 21:37:46', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'CERRADA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `empleado_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rol_id` bigint(20) UNSIGNED NOT NULL,
  `nombre_usuario` varchar(60) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO','BLOQUEADO') NOT NULL DEFAULT 'ACTIVO',
  `ultimo_acceso_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`usuario_id`, `empleado_id`, `rol_id`, `nombre_usuario`, `correo`, `password_hash`, `estado`, `ultimo_acceso_en`, `creado_en`, `actualizado_en`) VALUES
(1, NULL, 5, 'personal01', 'antuamenu@gmail', '$2y$12$qDPl5l0kVw6RW8iNHxr54uT8l9rOjBrSPrxWIDDNTluDezAmQrv76', 'ACTIVO', '2026-09-12 17:49:37', '2026-09-12 14:57:16', '2026-09-17 21:37:46'),
(2, NULL, 4, 'admin', 'antuamenu@gmail.com', '$2y$12$Vn7xmcuaN31MuURys7qD4eKH45LjzIWJbYLWp2S4jypgrfMkGhWjG', 'ACTIVO', '2026-09-12 18:08:40', '2026-09-12 18:01:57', '2026-09-17 21:37:46'),
(3, NULL, 4, 'admin2', 'fabricios@gmail.com', '$2y$12$D1FleF5OlhSqHOx11tzO3u3.ndUOdSiuZsiKo0PkCmECxU3tUZJcK', 'ACTIVO', '2026-09-14 15:18:28', '2026-09-12 18:10:17', '2026-09-17 21:37:46'),
(4, NULL, 5, 'articuno', 'fabricio@gmil.com', '$2y$12$3LuSsOqqAwMfwhZ5eZ3/wuuYlYRDrAa8ODTEfZYtGj6.P8Y.whcay', 'ACTIVO', NULL, '2026-09-17 14:44:30', '2026-09-17 14:44:30'),
(5, NULL, 5, 'articuno2', 'kingelprogamer@gmail.com', '$2y$12$D.LM1SNrYpliDs/ghlY2yOJp4RdtKLXy4KLxkZU00gXaHJRazYgy2', 'ACTIVO', '2026-09-17 14:48:26', '2026-09-17 14:47:01', '2026-09-17 14:48:26'),
(6, NULL, 3, 'admin3', 'drivevirtual0176@gmail.com', '$2y$12$hLOBr2yHb8qjqyEezkAkle/A.rvk/20NJKxpIH9Dh5NlSCXQWErtO', 'ACTIVO', '2026-09-23 09:49:15', '2026-09-17 21:47:39', '2026-09-23 09:49:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `venta_id` bigint(20) UNSIGNED NOT NULL,
  `contrato_id` bigint(20) UNSIGNED NOT NULL,
  `cliente_id` bigint(20) UNSIGNED NOT NULL,
  `responsable_id` bigint(20) UNSIGNED NOT NULL,
  `fecha_venta` datetime NOT NULL DEFAULT current_timestamp(),
  `monto` decimal(12,2) NOT NULL,
  `estado` enum('CONFIRMADA','ANULADA') NOT NULL DEFAULT 'CONFIRMADA'
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `zonas_cobertura`
--

CREATE TABLE `zonas_cobertura` (
  `zona_id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `distrito` varchar(100) NOT NULL,
  `provincia` varchar(100) DEFAULT NULL,
  `departamento` varchar(100) NOT NULL DEFAULT 'La Libertad',
  `descripcion` varchar(500) DEFAULT NULL,
  `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  `creado_por` bigint(20) UNSIGNED NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `zonas_cobertura`
--

INSERT INTO `zonas_cobertura` (`zona_id`, `codigo`, `nombre`, `distrito`, `provincia`, `departamento`, `descripcion`, `estado`, `creado_por`, `creado_en`, `actualizado_en`) VALUES
(10, 'ZONA-001', 'Zona Centro Lima', 'Lima Centro', NULL, 'La Libertad', NULL, 'ACTIVA', 1, '2026-09-23 11:24:05', '2026-09-23 11:24:05'),
(11, 'ZONA-002', 'Zona Norte Lima', 'Los Olivos', NULL, 'La Libertad', NULL, 'ACTIVA', 1, '2026-09-23 11:24:05', '2026-09-23 11:24:05'),
(12, 'ZONA-003', 'Zona Sur Lima', 'Villa El Salvador', NULL, 'La Libertad', NULL, 'ACTIVA', 1, '2026-09-23 11:24:05', '2026-09-23 11:24:05'),
(13, 'ZONA-TEST-001', 'Zona Centro Lima', 'Lima Centro', NULL, 'La Libertad', NULL, 'ACTIVA', 1, '2026-09-23 11:24:19', '2026-09-23 11:24:19'),
(14, 'ZONA-TEST-002', 'Zona Norte Lima', 'Los Olivos', NULL, 'La Libertad', NULL, 'ACTIVA', 1, '2026-09-23 11:24:19', '2026-09-23 11:24:19'),
(15, 'ZONA-TEST-003', 'Zona Sur Lima', 'Villa El Salvador', NULL, 'La Libertad', NULL, 'ACTIVA', 1, '2026-09-23 11:24:19', '2026-09-23 11:24:19');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertas_inventario`
--
ALTER TABLE `alertas_inventario`
  ADD PRIMARY KEY (`alerta_id`),
  ADD KEY `fk_alerta_usuario` (`atendida_por`),
  ADD KEY `ix_alerta_material_estado` (`material_id`,`estado`);

--
-- Indices de la tabla `auditoria_cambios`
--
ALTER TABLE `auditoria_cambios`
  ADD PRIMARY KEY (`auditoria_id`),
  ADD KEY `fk_auditoria_usuario` (`usuario_id`),
  ADD KEY `ix_auditoria_tabla_registro` (`tabla_afectada`,`registro_id`),
  ADD KEY `ix_auditoria_fecha` (`fecha_hora`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`cliente_id`),
  ADD UNIQUE KEY `uq_clientes_documento` (`tipo_documento`,`numero_documento`),
  ADD KEY `fk_clientes_creado_por` (`creado_por`),
  ADD KEY `ix_clientes_nombre` (`nombres_razon_social`),
  ADD KEY `ix_clientes_estado` (`estado`);

--
-- Indices de la tabla `codigos_acceso_2fa`
--
ALTER TABLE `codigos_acceso_2fa`
  ADD PRIMARY KEY (`codigo_2fa_id`),
  ADD KEY `ix_2fa_usuario_estado` (`usuario_id`,`estado`),
  ADD KEY `ix_2fa_vencimiento` (`vence_en`);

--
-- Indices de la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD PRIMARY KEY (`contrato_id`),
  ADD UNIQUE KEY `uq_contrato_codigo` (`codigo`),
  ADD KEY `fk_contrato_zona` (`zona_id`),
  ADD KEY `fk_contrato_responsable` (`responsable_id`),
  ADD KEY `ix_contrato_cliente` (`cliente_id`,`estado`),
  ADD KEY `ix_contrato_plan` (`plan_id`),
  ADD KEY `ix_contrato_periodo` (`fecha_inicio`,`fecha_fin`),
  ADD KEY `ix_contrato_estado` (`estado`),
  ADD KEY `ix_contrato_periodo_actual` (`periodo_actual`);

--
-- Indices de la tabla `conversaciones_ia`
--
ALTER TABLE `conversaciones_ia`
  ADD PRIMARY KEY (`conversacion_id`),
  ADD KEY `ix_conversacion_usuario` (`usuario_id`,`creada_en`);

--
-- Indices de la tabla `datos_facturacion`
--
ALTER TABLE `datos_facturacion`
  ADD PRIMARY KEY (`dato_facturacion_id`),
  ADD KEY `fk_datos_fact_usuario` (`creado_por`),
  ADD KEY `ix_datos_fact_cliente_estado` (`cliente_id`,`estado`),
  ADD KEY `ix_datos_fact_documento` (`tipo_documento`,`numero_documento`);

--
-- Indices de la tabla `documentos_facturacion`
--
ALTER TABLE `documentos_facturacion`
  ADD PRIMARY KEY (`documento_facturacion_id`),
  ADD UNIQUE KEY `uq_documento_fact_codigo` (`codigo`),
  ADD KEY `fk_documento_fact_dato` (`dato_facturacion_id`),
  ADD KEY `fk_documento_fact_generado_por` (`generado_por`),
  ADD KEY `fk_documento_fact_anulado_por` (`anulado_por`),
  ADD KEY `ix_documento_fact_cliente` (`cliente_id`,`fecha_emision`),
  ADD KEY `ix_documento_fact_contrato` (`contrato_id`,`fecha_emision`),
  ADD KEY `ix_documento_fact_pago` (`pago_id`),
  ADD KEY `ix_documento_fact_estado` (`estado`,`fecha_emision`),
  ADD KEY `ix_documento_fact_formato` (`formato_facturacion_id`);

--
-- Indices de la tabla `ejecuciones_instalacion`
--
ALTER TABLE `ejecuciones_instalacion`
  ADD PRIMARY KEY (`ejecucion_id`),
  ADD UNIQUE KEY `uq_ejecucion_pedido` (`pedido_id`),
  ADD KEY `ix_ejecucion_tecnico` (`tecnico_id`,`estado`);

--
-- Indices de la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`empleado_id`),
  ADD UNIQUE KEY `uq_empleados_documento` (`tipo_documento`,`numero_documento`),
  ADD UNIQUE KEY `uq_empleados_correo` (`correo`);

--
-- Indices de la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD PRIMARY KEY (`equipo_id`),
  ADD UNIQUE KEY `uq_equipo_codigo` (`codigo`),
  ADD UNIQUE KEY `uq_equipo_serie` (`numero_serie`),
  ADD KEY `fk_equipo_creado_por` (`creado_por`),
  ADD KEY `ix_equipo_nombre` (`nombre`),
  ADD KEY `ix_equipo_estado` (`estado`);

--
-- Indices de la tabla `evidencias_contrato`
--
ALTER TABLE `evidencias_contrato`
  ADD PRIMARY KEY (`evidencia_contrato_id`),
  ADD KEY `fk_evid_contrato_usuario` (`subido_por`),
  ADD KEY `ix_evidencia_contrato` (`contrato_id`,`tipo`);

--
-- Indices de la tabla `evidencias_instalacion`
--
ALTER TABLE `evidencias_instalacion`
  ADD PRIMARY KEY (`evidencia_id`),
  ADD KEY `fk_evidencia_ejecucion` (`ejecucion_id`),
  ADD KEY `fk_evidencia_usuario` (`subido_por`),
  ADD KEY `ix_evidencia_pedido` (`pedido_id`);

--
-- Indices de la tabla `formatos_facturacion`
--
ALTER TABLE `formatos_facturacion`
  ADD PRIMARY KEY (`formato_facturacion_id`),
  ADD UNIQUE KEY `uq_formato_fact_codigo_version` (`codigo`,`version`),
  ADD KEY `fk_formato_fact_usuario` (`aprobado_por`),
  ADD KEY `ix_formato_fact_estado` (`estado`);

--
-- Indices de la tabla `fuentes_respuesta_ia`
--
ALTER TABLE `fuentes_respuesta_ia`
  ADD PRIMARY KEY (`fuente_id`),
  ADD KEY `ix_fuente_mensaje` (`mensaje_id`);

--
-- Indices de la tabla `historial_accesos`
--
ALTER TABLE `historial_accesos`
  ADD PRIMARY KEY (`acceso_id`),
  ADD KEY `ix_acceso_usuario_fecha` (`usuario_id`,`fecha_hora`),
  ADD KEY `ix_acceso_resultado_fecha` (`resultado`,`fecha_hora`);

--
-- Indices de la tabla `historial_facturacion`
--
ALTER TABLE `historial_facturacion`
  ADD PRIMARY KEY (`historial_facturacion_id`),
  ADD KEY `fk_hist_fact_usuario` (`realizado_por`),
  ADD KEY `ix_hist_fact_documento_fecha` (`documento_facturacion_id`,`realizado_en`),
  ADD KEY `ix_hist_fact_accion_fecha` (`accion`,`realizado_en`);

--
-- Indices de la tabla `materiales`
--
ALTER TABLE `materiales`
  ADD PRIMARY KEY (`material_id`),
  ADD UNIQUE KEY `uq_material_codigo` (`codigo`),
  ADD KEY `fk_material_creado_por` (`creado_por`),
  ADD KEY `ix_material_nombre` (`nombre`),
  ADD KEY `ix_material_estado` (`estado`);

--
-- Indices de la tabla `mensajes_ia`
--
ALTER TABLE `mensajes_ia`
  ADD PRIMARY KEY (`mensaje_id`),
  ADD KEY `ix_mensaje_conversacion_fecha` (`conversacion_id`,`creado_en`);

--
-- Indices de la tabla `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  ADD PRIMARY KEY (`movimiento_id`),
  ADD KEY `fk_mov_responsable` (`responsable_id`),
  ADD KEY `ix_mov_material_fecha` (`material_id`,`fecha_movimiento`),
  ADD KEY `ix_mov_equipo_fecha` (`equipo_id`,`fecha_movimiento`),
  ADD KEY `ix_mov_pedido` (`pedido_id`),
  ADD KEY `ix_mov_tipo_fecha` (`tipo_movimiento`,`fecha_movimiento`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`pago_id`),
  ADD UNIQUE KEY `uq_pago_codigo` (`codigo`),
  ADD KEY `fk_pago_usuario` (`registrado_por`),
  ADD KEY `ix_pago_contrato_estado` (`contrato_id`,`estado`),
  ADD KEY `ix_pago_vencimiento_estado` (`fecha_vencimiento`,`estado`),
  ADD KEY `ix_pago_fecha` (`fecha_pago`),
  ADD KEY `ix_pago_recordatorio_previo` (`recordatorio_previo_enviado_en`),
  ADD KEY `ix_pago_recordatorio_vencido` (`recordatorio_vencido_enviado_en`);

--
-- Indices de la tabla `pedidos_instalacion`
--
ALTER TABLE `pedidos_instalacion`
  ADD PRIMARY KEY (`pedido_id`),
  ADD UNIQUE KEY `uq_pedido_codigo` (`codigo`),
  ADD KEY `fk_pedido_cliente` (`cliente_id`),
  ADD KEY `fk_pedido_creado_por` (`creado_por`),
  ADD KEY `ix_pedido_tecnico_estado` (`tecnico_id`,`estado`),
  ADD KEY `ix_pedido_fecha` (`fecha_programada`),
  ADD KEY `ix_pedido_contrato` (`contrato_id`);

--
-- Indices de la tabla `pedido_equipos`
--
ALTER TABLE `pedido_equipos`
  ADD PRIMARY KEY (`pedido_equipo_id`),
  ADD UNIQUE KEY `uq_equipo_asignacion_activa` (`equipo_asignacion_activa`),
  ADD KEY `fk_pedido_equipo_usuario` (`asignado_por`),
  ADD KEY `ix_pedido_equipo_pedido` (`pedido_id`),
  ADD KEY `ix_pedido_equipo_equipo` (`equipo_id`);

--
-- Indices de la tabla `pedido_materiales`
--
ALTER TABLE `pedido_materiales`
  ADD PRIMARY KEY (`pedido_material_id`),
  ADD UNIQUE KEY `uq_pedido_material` (`pedido_id`,`material_id`),
  ADD KEY `fk_pm_material` (`material_id`),
  ADD KEY `fk_pm_usuario` (`asignado_por`);

--
-- Indices de la tabla `periodos_contractuales`
--
ALTER TABLE `periodos_contractuales`
  ADD PRIMARY KEY (`periodo_id`),
  ADD UNIQUE KEY `uq_contrato_periodo_numero` (`contrato_id`,`numero_periodo`),
  ADD KEY `periodos_contractuales_cancelado_por_foreign` (`cancelado_por`),
  ADD KEY `ix_periodo_contrato_numero` (`contrato_id`,`numero_periodo`),
  ADD KEY `ix_periodo_contrato_estado` (`contrato_id`,`estado_periodo`),
  ADD KEY `ix_periodo_fecha_inicio` (`fecha_inicio_periodo`),
  ADD KEY `ix_periodo_fecha_fin` (`fecha_fin_periodo`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`permiso_id`),
  ADD UNIQUE KEY `uq_permisos_codigo` (`codigo`);

--
-- Indices de la tabla `planes_servicio`
--
ALTER TABLE `planes_servicio`
  ADD PRIMARY KEY (`plan_id`),
  ADD UNIQUE KEY `uq_plan_numero` (`numero_plan`),
  ADD KEY `fk_plan_creado_por` (`creado_por`),
  ADD KEY `ix_plan_nombre_estado` (`nombre`,`estado`);

--
-- Indices de la tabla `plan_zona_cobertura`
--
ALTER TABLE `plan_zona_cobertura`
  ADD PRIMARY KEY (`plan_id`,`zona_id`),
  ADD KEY `fk_plan_zona_zona` (`zona_id`);

--
-- Indices de la tabla `recordatorios_pago`
--
ALTER TABLE `recordatorios_pago`
  ADD PRIMARY KEY (`recordatorio_id`),
  ADD KEY `fk_recordatorio_usuario` (`creado_por`),
  ADD KEY `ix_recordatorio_pago_estado` (`pago_id`,`estado`);

--
-- Indices de la tabla `reportes_generados`
--
ALTER TABLE `reportes_generados`
  ADD PRIMARY KEY (`reporte_id`),
  ADD KEY `fk_reporte_usuario` (`generado_por`),
  ADD KEY `ix_reporte_tipo_fecha` (`tipo_reporte`,`generado_en`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`rol_id`),
  ADD UNIQUE KEY `uq_roles_nombre` (`nombre`);

--
-- Indices de la tabla `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD PRIMARY KEY (`rol_id`,`permiso_id`),
  ADD KEY `fk_rol_permiso_permiso` (`permiso_id`);

--
-- Indices de la tabla `secuencias_sistema`
--
ALTER TABLE `secuencias_sistema`
  ADD PRIMARY KEY (`nombre`);

--
-- Indices de la tabla `sesiones_usuario`
--
ALTER TABLE `sesiones_usuario`
  ADD PRIMARY KEY (`sesion_id`),
  ADD KEY `ix_sesion_usuario_estado` (`usuario_id`,`estado`),
  ADD KEY `ix_sesion_expira` (`expira_en`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`usuario_id`),
  ADD UNIQUE KEY `uq_usuarios_nombre` (`nombre_usuario`),
  ADD UNIQUE KEY `uq_usuarios_correo` (`correo`),
  ADD UNIQUE KEY `uq_usuarios_empleado` (`empleado_id`),
  ADD KEY `fk_usuarios_rol` (`rol_id`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`venta_id`),
  ADD UNIQUE KEY `uq_venta_contrato` (`contrato_id`),
  ADD KEY `ix_venta_cliente_fecha` (`cliente_id`,`fecha_venta`),
  ADD KEY `ix_venta_responsable_fecha` (`responsable_id`,`fecha_venta`);

--
-- Indices de la tabla `zonas_cobertura`
--
ALTER TABLE `zonas_cobertura`
  ADD PRIMARY KEY (`zona_id`),
  ADD UNIQUE KEY `uq_zona_codigo` (`codigo`),
  ADD KEY `fk_zona_creado_por` (`creado_por`),
  ADD KEY `ix_zona_nombre_estado` (`nombre`,`estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alertas_inventario`
--
ALTER TABLE `alertas_inventario`
  MODIFY `alerta_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `auditoria_cambios`
--
ALTER TABLE `auditoria_cambios`
  MODIFY `auditoria_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `cliente_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `codigos_acceso_2fa`
--
ALTER TABLE `codigos_acceso_2fa`
  MODIFY `codigo_2fa_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `contratos`
--
ALTER TABLE `contratos`
  MODIFY `contrato_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `conversaciones_ia`
--
ALTER TABLE `conversaciones_ia`
  MODIFY `conversacion_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `datos_facturacion`
--
ALTER TABLE `datos_facturacion`
  MODIFY `dato_facturacion_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documentos_facturacion`
--
ALTER TABLE `documentos_facturacion`
  MODIFY `documento_facturacion_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ejecuciones_instalacion`
--
ALTER TABLE `ejecuciones_instalacion`
  MODIFY `ejecucion_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `empleados`
--
ALTER TABLE `empleados`
  MODIFY `empleado_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `equipos`
--
ALTER TABLE `equipos`
  MODIFY `equipo_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `evidencias_contrato`
--
ALTER TABLE `evidencias_contrato`
  MODIFY `evidencia_contrato_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `evidencias_instalacion`
--
ALTER TABLE `evidencias_instalacion`
  MODIFY `evidencia_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `formatos_facturacion`
--
ALTER TABLE `formatos_facturacion`
  MODIFY `formato_facturacion_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fuentes_respuesta_ia`
--
ALTER TABLE `fuentes_respuesta_ia`
  MODIFY `fuente_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_accesos`
--
ALTER TABLE `historial_accesos`
  MODIFY `acceso_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de la tabla `historial_facturacion`
--
ALTER TABLE `historial_facturacion`
  MODIFY `historial_facturacion_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materiales`
--
ALTER TABLE `materiales`
  MODIFY `material_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mensajes_ia`
--
ALTER TABLE `mensajes_ia`
  MODIFY `mensaje_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  MODIFY `movimiento_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `pago_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos_instalacion`
--
ALTER TABLE `pedidos_instalacion`
  MODIFY `pedido_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedido_equipos`
--
ALTER TABLE `pedido_equipos`
  MODIFY `pedido_equipo_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedido_materiales`
--
ALTER TABLE `pedido_materiales`
  MODIFY `pedido_material_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `periodos_contractuales`
--
ALTER TABLE `periodos_contractuales`
  MODIFY `periodo_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `permiso_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `planes_servicio`
--
ALTER TABLE `planes_servicio`
  MODIFY `plan_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recordatorios_pago`
--
ALTER TABLE `recordatorios_pago`
  MODIFY `recordatorio_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reportes_generados`
--
ALTER TABLE `reportes_generados`
  MODIFY `reporte_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `rol_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `usuario_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `venta_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `zonas_cobertura`
--
ALTER TABLE `zonas_cobertura`
  MODIFY `zona_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alertas_inventario`
--
ALTER TABLE `alertas_inventario`
  ADD CONSTRAINT `fk_alerta_material` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`material_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alerta_usuario` FOREIGN KEY (`atendida_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `auditoria_cambios`
--
ALTER TABLE `auditoria_cambios`
  ADD CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `fk_clientes_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `codigos_acceso_2fa`
--
ALTER TABLE `codigos_acceso_2fa`
  ADD CONSTRAINT `fk_2fa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD CONSTRAINT `contratos_periodo_actual_foreign` FOREIGN KEY (`periodo_actual`) REFERENCES `periodos_contractuales` (`periodo_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contrato_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`cliente_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contrato_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes_servicio` (`plan_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contrato_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `empleados` (`empleado_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contrato_zona` FOREIGN KEY (`zona_id`) REFERENCES `zonas_cobertura` (`zona_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `conversaciones_ia`
--
ALTER TABLE `conversaciones_ia`
  ADD CONSTRAINT `fk_conversacion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `datos_facturacion`
--
ALTER TABLE `datos_facturacion`
  ADD CONSTRAINT `fk_datos_fact_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`cliente_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_datos_fact_usuario` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `documentos_facturacion`
--
ALTER TABLE `documentos_facturacion`
  ADD CONSTRAINT `fk_documento_fact_anulado_por` FOREIGN KEY (`anulado_por`) REFERENCES `usuarios` (`usuario_id`),
  ADD CONSTRAINT `fk_documento_fact_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`cliente_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documento_fact_contrato` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documento_fact_dato` FOREIGN KEY (`dato_facturacion_id`) REFERENCES `datos_facturacion` (`dato_facturacion_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documento_fact_formato` FOREIGN KEY (`formato_facturacion_id`) REFERENCES `formatos_facturacion` (`formato_facturacion_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documento_fact_generado_por` FOREIGN KEY (`generado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documento_fact_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos` (`pago_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `ejecuciones_instalacion`
--
ALTER TABLE `ejecuciones_instalacion`
  ADD CONSTRAINT `fk_ejecucion_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos_instalacion` (`pedido_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ejecucion_tecnico` FOREIGN KEY (`tecnico_id`) REFERENCES `empleados` (`empleado_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD CONSTRAINT `fk_equipo_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `evidencias_contrato`
--
ALTER TABLE `evidencias_contrato`
  ADD CONSTRAINT `fk_evid_contrato` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evid_contrato_usuario` FOREIGN KEY (`subido_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `evidencias_instalacion`
--
ALTER TABLE `evidencias_instalacion`
  ADD CONSTRAINT `fk_evidencia_ejecucion` FOREIGN KEY (`ejecucion_id`) REFERENCES `ejecuciones_instalacion` (`ejecucion_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evidencia_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos_instalacion` (`pedido_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evidencia_usuario` FOREIGN KEY (`subido_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `formatos_facturacion`
--
ALTER TABLE `formatos_facturacion`
  ADD CONSTRAINT `fk_formato_fact_usuario` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `fuentes_respuesta_ia`
--
ALTER TABLE `fuentes_respuesta_ia`
  ADD CONSTRAINT `fk_fuente_mensaje` FOREIGN KEY (`mensaje_id`) REFERENCES `mensajes_ia` (`mensaje_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_accesos`
--
ALTER TABLE `historial_accesos`
  ADD CONSTRAINT `fk_historial_acceso_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_facturacion`
--
ALTER TABLE `historial_facturacion`
  ADD CONSTRAINT `fk_hist_fact_documento` FOREIGN KEY (`documento_facturacion_id`) REFERENCES `documentos_facturacion` (`documento_facturacion_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_fact_usuario` FOREIGN KEY (`realizado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `materiales`
--
ALTER TABLE `materiales`
  ADD CONSTRAINT `fk_material_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `mensajes_ia`
--
ALTER TABLE `mensajes_ia`
  ADD CONSTRAINT `fk_mensaje_conversacion` FOREIGN KEY (`conversacion_id`) REFERENCES `conversaciones_ia` (`conversacion_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  ADD CONSTRAINT `fk_mov_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`equipo_id`),
  ADD CONSTRAINT `fk_mov_material` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`material_id`),
  ADD CONSTRAINT `fk_mov_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos_instalacion` (`pedido_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mov_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `fk_pago_contrato` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pago_usuario` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedidos_instalacion`
--
ALTER TABLE `pedidos_instalacion`
  ADD CONSTRAINT `fk_pedido_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`cliente_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_contrato` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_tecnico` FOREIGN KEY (`tecnico_id`) REFERENCES `empleados` (`empleado_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedido_equipos`
--
ALTER TABLE `pedido_equipos`
  ADD CONSTRAINT `fk_pedido_equipo_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`equipo_id`),
  ADD CONSTRAINT `fk_pedido_equipo_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos_instalacion` (`pedido_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_equipo_usuario` FOREIGN KEY (`asignado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedido_materiales`
--
ALTER TABLE `pedido_materiales`
  ADD CONSTRAINT `fk_pm_material` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`material_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pm_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos_instalacion` (`pedido_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pm_usuario` FOREIGN KEY (`asignado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `periodos_contractuales`
--
ALTER TABLE `periodos_contractuales`
  ADD CONSTRAINT `periodos_contractuales_cancelado_por_foreign` FOREIGN KEY (`cancelado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `periodos_contractuales_contrato_id_foreign` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `planes_servicio`
--
ALTER TABLE `planes_servicio`
  ADD CONSTRAINT `fk_plan_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `plan_zona_cobertura`
--
ALTER TABLE `plan_zona_cobertura`
  ADD CONSTRAINT `fk_plan_zona_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes_servicio` (`plan_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_plan_zona_zona` FOREIGN KEY (`zona_id`) REFERENCES `zonas_cobertura` (`zona_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `recordatorios_pago`
--
ALTER TABLE `recordatorios_pago`
  ADD CONSTRAINT `fk_recordatorio_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos` (`pago_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recordatorio_usuario` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `reportes_generados`
--
ALTER TABLE `reportes_generados`
  ADD CONSTRAINT `fk_reporte_usuario` FOREIGN KEY (`generado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD CONSTRAINT `fk_rol_permiso_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`permiso_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rol_permiso_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`rol_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `sesiones_usuario`
--
ALTER TABLE `sesiones_usuario`
  ADD CONSTRAINT `fk_sesion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`rol_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`cliente_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_venta_contrato` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`contrato_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_venta_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `empleados` (`empleado_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `zonas_cobertura`
--
ALTER TABLE `zonas_cobertura`
  ADD CONSTRAINT `fk_zona_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
