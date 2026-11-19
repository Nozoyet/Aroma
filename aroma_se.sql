-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Nov 19, 2026 at 09:01 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aroma_se`
--
CREATE DATABASE IF NOT EXISTS `aroma_se` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `aroma_se`;

-- --------------------------------------------------------

--
-- Table structure for table `calendario_dia`
--

CREATE TABLE `calendario_dia` (
  `fecha` date NOT NULL,
  `tipo_dia` varchar(15) NOT NULL,
  `nota` varchar(100) DEFAULT NULL
) ;

--
-- Dumping data for table `calendario_dia`
--

INSERT INTO `calendario_dia` (`fecha`, `tipo_dia`, `nota`) VALUES
('2026-10-01', 'laboral', NULL),
('2026-10-02', 'laboral', NULL),
('2026-10-03', 'fin_de_semana', NULL),
('2026-10-04', 'fin_de_semana', NULL),
('2026-10-05', 'laboral', NULL),
('2026-10-06', 'laboral', NULL),
('2026-10-07', 'laboral', NULL),
('2026-10-08', 'laboral', NULL),
('2026-10-09', 'laboral', NULL),
('2026-10-10', 'fin_de_semana', NULL),
('2026-10-11', 'fin_de_semana', NULL),
('2026-10-12', 'laboral', NULL),
('2026-10-13', 'laboral', NULL),
('2026-10-14', 'laboral', NULL),
('2026-10-15', 'laboral', NULL),
('2026-10-16', 'laboral', NULL),
('2026-10-17', 'fin_de_semana', NULL),
('2026-10-18', 'fin_de_semana', NULL),
('2026-10-19', 'laboral', NULL),
('2026-10-20', 'laboral', NULL),
('2026-10-21', 'laboral', NULL),
('2026-10-22', 'laboral', NULL),
('2026-10-23', 'laboral', NULL),
('2026-10-24', 'fin_de_semana', NULL),
('2026-10-25', 'fin_de_semana', NULL),
('2026-10-26', 'laboral', NULL),
('2026-10-27', 'laboral', NULL),
('2026-10-28', 'laboral', NULL),
('2026-10-29', 'laboral', NULL),
('2026-10-30', 'laboral', NULL),
('2026-10-31', 'fin_de_semana', NULL),
('2026-11-01', 'fin_de_semana', NULL),
('2026-11-02', 'feriado', 'Todos los Santos'),
('2026-11-03', 'laboral', NULL),
('2026-11-04', 'laboral', NULL),
('2026-11-05', 'laboral', NULL),
('2026-11-06', 'laboral', NULL),
('2026-11-07', 'fin_de_semana', NULL),
('2026-11-08', 'fin_de_semana', NULL),
('2026-11-09', 'laboral', NULL),
('2026-11-10', 'laboral', NULL),
('2026-11-11', 'laboral', NULL),
('2026-11-12', 'laboral', NULL),
('2026-11-13', 'laboral', NULL),
('2026-11-14', 'fin_de_semana', NULL),
('2026-11-15', 'fin_de_semana', NULL),
('2026-11-16', 'laboral', NULL),
('2026-11-17', 'laboral', NULL),
('2026-11-18', 'laboral', NULL),
('2026-11-19', 'laboral', NULL),
('2026-11-20', 'laboral', NULL),
('2026-11-21', 'fin_de_semana', NULL),
('2026-11-22', 'fin_de_semana', NULL),
('2026-11-23', 'laboral', NULL),
('2026-11-24', 'laboral', NULL),
('2026-11-25', 'laboral', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categoria_riesgo`
--

CREATE TABLE `categoria_riesgo` (
  `categoria_id` int(11) NOT NULL,
  `nombre` varchar(10) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categoria_riesgo`
--

INSERT INTO `categoria_riesgo` (`categoria_id`, `nombre`, `descripcion`) VALUES
(1, 'alto', 'Carnes, pescados y mariscos: Un lote vencido nunca debe usarse'),
(2, 'medio', 'Verduras frescas, lacteos y salsas preparadas: Riesgo moderado'),
(3, 'bajo', 'Granos, frutos secos, papa y especias: riesgo sanitario bajo');

-- --------------------------------------------------------

--
-- Table structure for table `consulta`
--

CREATE TABLE `consulta` (
  `consulta_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo` varchar(12) NOT NULL,
  `fecha_base` date NOT NULL,
  `observaciones` varchar(300) DEFAULT NULL
) ;

--
-- Dumping data for table `consulta`
--

INSERT INTO `consulta` (`consulta_id`, `usuario_id`, `fecha_hora`, `tipo`, `fecha_base`, `observaciones`) VALUES
(1, 1, '2026-11-19 15:54:00', 'plan_3_dias', '2026-11-19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `decision`
--

CREATE TABLE `decision` (
  `decision_id` int(11) NOT NULL,
  `consulta_id` int(11) NOT NULL,
  `lote_id` int(11) DEFAULT NULL,
  `plato_id` int(11) DEFAULT NULL,
  `dia_plan` tinyint(3) UNSIGNED DEFAULT NULL,
  `accion_recomendada` varchar(15) NOT NULL,
  `porciones` int(11) DEFAULT NULL,
  `fc_conclusion` decimal(4,3) NOT NULL,
  `costo_estimado` decimal(10,2) DEFAULT NULL,
  `ahorro_estimado` decimal(10,2) DEFAULT NULL,
  `explicacion` text NOT NULL,
  `accion_tomada` varchar(15) DEFAULT NULL,
  `merma_real_bs` decimal(10,2) DEFAULT NULL
) ;

--
-- Dumping data for table `decision`
--

INSERT INTO `decision` (`decision_id`, `consulta_id`, `lote_id`, `plato_id`, `dia_plan`, `accion_recomendada`, `porciones`, `fc_conclusion`, `costo_estimado`, `ahorro_estimado`, `explicacion`, `accion_tomada`, `merma_real_bs`) VALUES
(1, 1, 1, 3, NULL, 'menu_emergencia', 4, 0.450, NULL, 45.00, 'Lote 1 de Pollo: 1000 g, vence hoy, riesgo alto, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R3: SI dias_vida = 0 ENTONCES estado_lote = vence_hoy  [FC 1.00]\n  R11: SI estado_lote = vence_hoy AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.5  [FC 0.70]\n  R12: SI estado_lote = vence_hoy AND apto_consumo = si ENTONCES riesgo_merma = alto  [FC 0.95]\n  R18: SI estado_lote = vence_hoy AND apto_consumo = si AND fc_calidad >= umbral_fc_emergencia ENTONCES accion_recomendada = menu_emergencia  [FC 0.45]\nAcciones candidatas: menu_emergencia (FC 0.45). Se elige \"menu_emergencia\" por prioridad (la inocuidad va primero).\nALERTA: Lote en riesgo de merma sin acción posible (estado visual bueno): validar con el chef.\nBúsqueda DFS en el grafo: 3 nodos explorados, 1 hojas evaluadas.\nMeta: rescatar al menos 70 % del lote (31.50 de 45.00 Bs).\n  - Pollo Picante: 4 porciones\nSe rescatan 45.00 Bs (100.0 %) usando 1000 g; sobran 0 g (0.00 Bs de merma).', NULL, NULL),
(2, 1, 2, NULL, NULL, 'mantener', NULL, 0.448, NULL, NULL, 'Lote 2 de Lengua de res: 6000 g, vence en 1 día(s), riesgo alto, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R2: SI dias_vida BETWEEN 1 AND 2 ENTONCES estado_lote = por_vencer  [FC 1.00]\n  R10: SI estado_lote = por_vencer AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.7  [FC 0.80]\n  R13: SI estado_lote = por_vencer AND cobertura_dias > dias_vida AND apto_consumo = si ENTONCES riesgo_merma = alto  [FC 0.80]\n  R16: SI riesgo_merma = alto AND apto_consumo = si AND fc_calidad >= umbral_fc_calidad AND existe plato_usa_insumo ENTONCES candidato_promocion = plato  [FC 0.56]\n  R17: SI candidato_promocion AND demanda_pronosticada < porciones_posibles ENTONCES accion_recomendada = promocionar  [FC 0.45]\nAcciones candidatas: promocionar (FC 0.45). Se elige \"promocionar\" por prioridad (la inocuidad va primero).\nALERTA: Lote en riesgo de merma sin acción posible (estado visual bueno): validar con el chef.\nBúsqueda A*: 2 nodos expandidos, 5 generados.\nSin intervenir se perderían 0.00 Bs. Con el plan: 0.00 Bs (descuentos 0.00 + merma 0 g). Ahorro: 0.00 Bs.\n  - No hace falta promocionar: la demanda esperada ya consume el lote.', NULL, NULL),
(3, 1, 8, NULL, NULL, 'mantener', NULL, 0.720, NULL, NULL, 'Lote 8 de Lechuga: 2000 g, vence en 2 día(s), riesgo medio, estado visual regular, cadena de frío cumple.\nReglas disparadas (en orden):\n  R2: SI dias_vida BETWEEN 1 AND 2 ENTONCES estado_lote = por_vencer  [FC 1.00]\n  R14: SI estado_lote = por_vencer AND cobertura_dias <= dias_vida ENTONCES riesgo_merma = bajo  [FC 0.80]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.72]\nAcciones candidatas: mantener (FC 0.72). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(4, 1, 6, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 6 de Aji chino: 900 g, vence en 4 día(s), riesgo medio, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(5, 1, 7, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 7 de Tomate: 3500 g, vence en 5 día(s), riesgo medio, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(6, 1, 15, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 15 de Salsa anticuchera: 2500 g, vence en 6 día(s), riesgo medio, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(7, 1, 32, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 32 de Maní: 500 g, vence en 11 día(s), riesgo bajo, estado visual bueno, cadena de frío no cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(8, 1, 5, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 5 de Papa: 25000 g, vence en 12 día(s), riesgo bajo, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(9, 1, 16, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 16 de Mantequilla: 800 g, vence en 15 día(s), riesgo medio, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(10, 1, 11, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 11 de Pasta de curry: 1500 g, vence en 80 día(s), riesgo bajo, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(11, 1, 10, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 10 de Salsa thai: 3000 g, vence en 80 día(s), riesgo bajo, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(12, 1, 12, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 12 de Tahini: 1500 g, vence en 100 día(s), riesgo bajo, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(13, 1, 13, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 13 de Maní: 4000 g, vence en 100 día(s), riesgo bajo, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(14, 1, 9, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 9 de Especias chinas: 700 g, vence en 150 día(s), riesgo bajo, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL),
(15, 1, 14, NULL, NULL, 'mantener', NULL, 0.810, NULL, NULL, 'Lote 14 de Quinua: 5000 g, vence en 170 día(s), riesgo bajo, estado visual bueno, cadena de frío cumple.\nReglas disparadas (en orden):\n  R1: SI dias_vida > 2 ENTONCES estado_lote = fresco  [FC 1.00]\n  R9: SI estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple ENTONCES fc_calidad = 0.9  [FC 0.90]\n  R15: SI estado_lote = fresco ENTONCES riesgo_merma = bajo  [FC 0.90]\n  R22: SI riesgo_merma = bajo AND cobertura_dias >= 1 ENTONCES accion_recomendada = mantener  [FC 0.81]\nAcciones candidatas: mantener (FC 0.81). Se elige \"mantener\" por prioridad (la inocuidad va primero).', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `decision_regla`
--

CREATE TABLE `decision_regla` (
  `decision_id` int(11) NOT NULL,
  `regla_id` varchar(5) NOT NULL,
  `fc_aplicado` decimal(4,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `decision_regla`
--

INSERT INTO `decision_regla` (`decision_id`, `regla_id`, `fc_aplicado`) VALUES
(1, 'R11', 0.700),
(1, 'R12', 0.950),
(1, 'R18', 0.450),
(1, 'R3', 1.000),
(2, 'R10', 0.800),
(2, 'R13', 0.800),
(2, 'R16', 0.560),
(2, 'R17', 0.448),
(2, 'R2', 1.000),
(3, 'R14', 0.800),
(3, 'R2', 1.000),
(3, 'R22', 0.720),
(4, 'R1', 1.000),
(4, 'R15', 0.900),
(4, 'R22', 0.810),
(4, 'R9', 0.900),
(5, 'R1', 1.000),
(5, 'R15', 0.900),
(5, 'R22', 0.810),
(5, 'R9', 0.900),
(6, 'R1', 1.000),
(6, 'R15', 0.900),
(6, 'R22', 0.810),
(6, 'R9', 0.900),
(7, 'R1', 1.000),
(7, 'R15', 0.900),
(7, 'R22', 0.810),
(8, 'R1', 1.000),
(8, 'R15', 0.900),
(8, 'R22', 0.810),
(8, 'R9', 0.900),
(9, 'R1', 1.000),
(9, 'R15', 0.900),
(9, 'R22', 0.810),
(9, 'R9', 0.900),
(10, 'R1', 1.000),
(10, 'R15', 0.900),
(10, 'R22', 0.810),
(10, 'R9', 0.900),
(11, 'R1', 1.000),
(11, 'R15', 0.900),
(11, 'R22', 0.810),
(11, 'R9', 0.900),
(12, 'R1', 1.000),
(12, 'R15', 0.900),
(12, 'R22', 0.810),
(12, 'R9', 0.900),
(13, 'R1', 1.000),
(13, 'R15', 0.900),
(13, 'R22', 0.810),
(13, 'R9', 0.900),
(14, 'R1', 1.000),
(14, 'R15', 0.900),
(14, 'R22', 0.810),
(14, 'R9', 0.900),
(15, 'R1', 1.000),
(15, 'R15', 0.900),
(15, 'R22', 0.810),
(15, 'R9', 0.900);

-- --------------------------------------------------------

--
-- Table structure for table `insumo`
--

CREATE TABLE `insumo` (
  `insumo_id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `unidad` varchar(5) NOT NULL DEFAULT 'g',
  `costo_unitario` decimal(10,4) NOT NULL,
  `vida_util_dias` smallint(5) UNSIGNED NOT NULL
) ;

--
-- Dumping data for table `insumo`
--

INSERT INTO `insumo` (`insumo_id`, `nombre`, `categoria_id`, `unidad`, `costo_unitario`, `vida_util_dias`) VALUES
(1, 'Calamar', 1, 'g', 0.0900, 2),
(2, 'Trucha', 1, 'g', 0.0800, 2),
(3, 'Pollo', 1, 'g', 0.0450, 3),
(4, 'Lengua de res', 1, 'g', 0.0600, 4),
(5, 'Especias chinas', 3, 'g', 0.1500, 180),
(6, 'Salsa thai', 3, 'g', 0.0500, 90),
(7, 'Pasta de curry', 3, 'g', 0.1200, 90),
(8, 'Tahini', 3, 'g', 0.0700, 120),
(9, 'Maní', 3, 'g', 0.0250, 120),
(10, 'Quinua', 3, 'g', 0.0300, 180),
(11, 'Papa', 3, 'g', 0.0060, 15),
(12, 'Salsa anticuchera', 2, 'g', 0.0400, 10),
(13, 'Aji chino', 2, 'g', 0.0400, 7),
(14, 'Mantequilla', 2, 'g', 0.0600, 20),
(15, 'Tomate', 2, 'g', 0.0080, 6),
(16, 'Lechuga', 2, 'g', 0.0100, 4);

-- --------------------------------------------------------

--
-- Table structure for table `lote`
--

CREATE TABLE `lote` (
  `lote_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `cantidad_inicial` decimal(10,2) NOT NULL,
  `stock_actual` decimal(10,2) NOT NULL,
  `estado_visual` varchar(10) NOT NULL DEFAULT 'bueno',
  `cadena_frio` tinyint(1) NOT NULL DEFAULT 1
) ;

--
-- Dumping data for table `lote`
--

INSERT INTO `lote` (`lote_id`, `insumo_id`, `proveedor_id`, `fecha_ingreso`, `fecha_vencimiento`, `cantidad_inicial`, `stock_actual`, `estado_visual`, `cadena_frio`) VALUES
(1, 3, 1, '2026-11-17', '2026-11-19', 10000.00, 1000.00, 'bueno', 1),
(2, 4, 1, '2026-11-16', '2026-11-20', 8000.00, 6000.00, 'bueno', 1),
(3, 1, 2, '2026-11-18', '2026-11-21', 3000.00, 1800.00, 'bueno', 1),
(4, 2, 2, '2026-11-18', '2026-11-20', 4000.00, 2500.00, 'bueno', 1),
(5, 11, 3, '2026-11-17', '2026-12-01', 30000.00, 25000.00, 'bueno', 1),
(6, 13, 3, '2026-11-16', '2026-11-23', 1200.00, 900.00, 'bueno', 1),
(7, 15, 3, '2026-11-18', '2026-11-24', 4000.00, 3500.00, 'bueno', 1),
(8, 16, 3, '2026-11-17', '2026-11-21', 3000.00, 2000.00, 'regular', 1),
(9, 5, 4, '2026-11-09', '2027-04-18', 1000.00, 700.00, 'bueno', 1),
(10, 6, 4, '2026-11-09', '2027-02-07', 4000.00, 3000.00, 'bueno', 1),
(11, 7, 4, '2026-11-09', '2027-02-07', 2000.00, 1500.00, 'bueno', 1),
(12, 8, 4, '2026-11-09', '2027-02-27', 2000.00, 1500.00, 'bueno', 1),
(13, 9, 4, '2026-11-09', '2027-02-27', 5000.00, 4000.00, 'bueno', 1),
(14, 10, 4, '2026-11-09', '2027-05-08', 6000.00, 5000.00, 'bueno', 1),
(15, 12, 4, '2026-11-15', '2026-11-25', 3000.00, 2500.00, 'bueno', 1),
(16, 14, 4, '2026-11-14', '2026-12-04', 1000.00, 800.00, 'bueno', 1),
(32, 9, NULL, '2026-11-19', '2026-11-30', 500.00, 500.00, 'bueno', 0);

-- --------------------------------------------------------

--
-- Table structure for table `parametro_politica`
--

CREATE TABLE `parametro_politica` (
  `clave` varchar(40) NOT NULL,
  `valor` decimal(8,3) NOT NULL,
  `descripcion` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parametro_politica`
--

INSERT INTO `parametro_politica` (`clave`, `valor`, `descripcion`) VALUES
('descuento_promo', 0.100, 'Descuento de la promocion (10 por ciento)'),
('horizonte_dias', 3.000, 'Dias que planifica A*'),
('max_platos_menu', 3.000, 'Maximo de platos en el menu de emergencia'),
('max_porciones_promo', 10.000, 'Maximo de porciones extra en promocion por dia'),
('umbral_fc_calidad', 0.600, 'FC minimo de calidad para promocionar'),
('umbral_fc_emergencia', 0.500, 'FC minimo de calidad para el menu de emergencia (lote que vence hoy)'),
('umbral_rescate', 0.700, 'Proporcion minima del valor a rescatar en el menu de emergencia');

-- --------------------------------------------------------

--
-- Table structure for table `plato`
--

CREATE TABLE `plato` (
  `plato_id` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `descripcion` varchar(250) DEFAULT NULL,
  `precio_venta` decimal(8,2) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ;

--
-- Dumping data for table `plato`
--

INSERT INTO `plato` (`plato_id`, `nombre`, `descripcion`, `precio_venta`, `activo`) VALUES
(1, 'Lengua de Res Picante', 'Lengua cocinada con especias chinas y servida con salsa cremosa de maní, tahini y ajíes chinos', 140.00, 1),
(2, 'Lengua con Puré de Papa (promoción)', 'Lengua de Res Picante acompañada de puré de papa, versión de promoción', 125.00, 1),
(3, 'Pollo Picante', 'Trozos de pollo crocante salteados con maní y ajíes tradicionales chinos', 120.00, 1),
(4, 'Trucha al Curry', 'Filete de trucha sellado en salsa de curry estilo chino, con ensalada fresca y quinua mixta salteada', 145.00, 1),
(5, 'Calamar en Salsa Anticuchera', 'Calamar ahumado en salsa anticuchera muy picante, servido con papas nativas', 150.00, 1),
(6, 'Pollo Frito con Salsa Thai', 'Trozos de pollo crocante con salsa thai de picante bajo, ensalada fresca y guarnición a elección', 135.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `pronostico_demanda`
--

CREATE TABLE `pronostico_demanda` (
  `pronostico_id` int(11) NOT NULL,
  `fecha_calculo` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_objetivo` date NOT NULL,
  `plato_id` int(11) NOT NULL,
  `escenario` varchar(10) NOT NULL DEFAULT 'normal',
  `porciones_pronosticadas` decimal(8,2) NOT NULL,
  `fc_demanda` decimal(4,3) NOT NULL,
  `r2` decimal(5,4) DEFAULT NULL,
  `mae` decimal(8,3) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `proveedor`
--

CREATE TABLE `proveedor` (
  `proveedor_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `plazo_entrega_dias` tinyint(3) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `proveedor`
--

INSERT INTO `proveedor` (`proveedor_id`, `nombre`, `plazo_entrega_dias`) VALUES
(1, 'Frigorifico Central', 1),
(2, 'Pescaderia del Lago', 1),
(3, 'Mercado Mayorista', 1),
(4, 'Distribuidora de Abarrotes', 2);

-- --------------------------------------------------------

--
-- Table structure for table `receta`
--

CREATE TABLE `receta` (
  `plato_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `cantidad_por_porcion` decimal(8,2) NOT NULL
) ;

--
-- Dumping data for table `receta`
--

INSERT INTO `receta` (`plato_id`, `insumo_id`, `cantidad_por_porcion`) VALUES
(1, 4, 250.00),
(1, 5, 5.00),
(1, 8, 20.00),
(1, 9, 30.00),
(1, 13, 10.00),
(2, 4, 250.00),
(2, 5, 5.00),
(2, 8, 20.00),
(2, 9, 30.00),
(2, 11, 200.00),
(2, 13, 10.00),
(2, 14, 20.00),
(3, 3, 250.00),
(3, 9, 40.00),
(3, 13, 15.00),
(4, 2, 220.00),
(4, 7, 25.00),
(4, 10, 80.00),
(4, 15, 50.00),
(4, 16, 60.00),
(5, 1, 220.00),
(5, 11, 200.00),
(5, 12, 50.00),
(5, 13, 10.00),
(6, 3, 250.00),
(6, 6, 40.00),
(6, 11, 150.00),
(6, 15, 40.00),
(6, 16, 60.00);

-- --------------------------------------------------------

--
-- Table structure for table `regla`
--

CREATE TABLE `regla` (
  `regla_id` varchar(5) NOT NULL,
  `grupo` varchar(30) NOT NULL,
  `condicion` varchar(400) NOT NULL,
  `conclusion` varchar(300) NOT NULL,
  `fc_regla` decimal(3,2) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1
) ;

--
-- Dumping data for table `regla`
--

INSERT INTO `regla` (`regla_id`, `grupo`, `condicion`, `conclusion`, `fc_regla`, `activa`) VALUES
('R1', 'Clasificacion del lote', 'dias_vida > 2', 'estado_lote = fresco', 1.00, 1),
('R10', 'Calidad', 'estado_lote = por_vencer AND estado_visual = bueno AND cadena_frio = cumple', 'fc_calidad = 0.7', 0.80, 1),
('R11', 'Calidad', 'estado_lote = vence_hoy AND estado_visual = bueno AND cadena_frio = cumple', 'fc_calidad = 0.5', 0.70, 1),
('R12', 'Riesgo de merma', 'estado_lote = vence_hoy AND apto_consumo = si', 'riesgo_merma = alto', 0.95, 1),
('R13', 'Riesgo de merma', 'estado_lote = por_vencer AND cobertura_dias > dias_vida AND apto_consumo = si', 'riesgo_merma = alto', 0.80, 1),
('R14', 'Riesgo de merma', 'estado_lote = por_vencer AND cobertura_dias <= dias_vida', 'riesgo_merma = bajo', 0.80, 1),
('R15', 'Riesgo de merma', 'estado_lote = fresco', 'riesgo_merma = bajo', 0.90, 1),
('R16', 'Acciones', 'riesgo_merma = alto AND apto_consumo = si AND fc_calidad >= umbral_fc_calidad AND existe plato_usa_insumo', 'candidato_promocion = plato', 0.80, 1),
('R17', 'Acciones', 'candidato_promocion AND demanda_pronosticada < porciones_posibles', 'accion_recomendada = promocionar', 0.80, 1),
('R18', 'Acciones', 'estado_lote = vence_hoy AND apto_consumo = si AND fc_calidad >= umbral_fc_emergencia', 'accion_recomendada = menu_emergencia', 0.90, 1),
('R19', 'Acciones', 'apto_consumo = no', 'accion_recomendada = descartar', 1.00, 1),
('R2', 'Clasificacion del lote', 'dias_vida BETWEEN 1 AND 2', 'estado_lote = por_vencer', 1.00, 1),
('R20', 'Acciones', 'cobertura_dias < 1 AND estado_lote = fresco', 'accion_recomendada = comprar', 0.85, 1),
('R21', 'Acciones', 'tipo_dia IN (fin_de_semana, feriado) AND cobertura_dias < 2', 'accion_recomendada = comprar', 0.70, 1),
('R22', 'Acciones', 'riesgo_merma = bajo AND cobertura_dias >= 1', 'accion_recomendada = mantener', 0.90, 1),
('R3', 'Clasificacion del lote', 'dias_vida = 0', 'estado_lote = vence_hoy', 1.00, 1),
('R4', 'Clasificacion del lote', 'dias_vida < 0', 'estado_lote = vencido', 1.00, 1),
('R5', 'Inocuidad', 'estado_lote = vencido AND categoria_riesgo = alto', 'apto_consumo = no', 1.00, 1),
('R6', 'Inocuidad', 'estado_lote = vencido AND categoria_riesgo = medio', 'apto_consumo = no', 0.90, 1),
('R7', 'Inocuidad', 'cadena_frio = no_cumple AND categoria_riesgo = alto', 'apto_consumo = no', 0.95, 1),
('R8', 'Inocuidad', 'estado_visual = malo', 'apto_consumo = no', 0.90, 1),
('R9', 'Calidad', 'estado_lote = fresco AND estado_visual = bueno AND cadena_frio = cumple', 'fc_calidad = 0.9', 0.90, 1);

-- --------------------------------------------------------

--
-- Table structure for table `usuario`
--

CREATE TABLE `usuario` (
  `usuario_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `rol` varchar(15) NOT NULL
) ;

--
-- Dumping data for table `usuario`
--

INSERT INTO `usuario` (`usuario_id`, `nombre`, `rol`) VALUES
(1, 'Administrador', 'administrador'),
(2, 'Chef Aroma', 'chef');

-- --------------------------------------------------------

--
-- Table structure for table `venta_diaria`
--

CREATE TABLE `venta_diaria` (
  `venta_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `plato_id` int(11) NOT NULL,
  `porciones_vendidas` int(11) NOT NULL
) ;

--
-- Dumping data for table `venta_diaria`
--

INSERT INTO `venta_diaria` (`venta_id`, `fecha`, `plato_id`, `porciones_vendidas`) VALUES
(1, '2026-10-01', 1, 16),
(2, '2026-10-01', 2, 9),
(3, '2026-10-01', 3, 13),
(4, '2026-10-01', 4, 6),
(5, '2026-10-01', 5, 8),
(6, '2026-10-01', 6, 17),
(7, '2026-10-02', 1, 15),
(8, '2026-10-02', 2, 10),
(9, '2026-10-02', 3, 22),
(10, '2026-10-02', 4, 12),
(11, '2026-10-02', 5, 12),
(12, '2026-10-02', 6, 23),
(13, '2026-10-03', 1, 24),
(14, '2026-10-03', 2, 11),
(15, '2026-10-03', 3, 27),
(16, '2026-10-03', 4, 15),
(17, '2026-10-03', 5, 14),
(18, '2026-10-03', 6, 30),
(19, '2026-10-04', 1, 17),
(20, '2026-10-04', 2, 7),
(21, '2026-10-04', 3, 17),
(22, '2026-10-04', 4, 17),
(23, '2026-10-04', 5, 11),
(24, '2026-10-04', 6, 20),
(25, '2026-10-05', 1, 9),
(26, '2026-10-05', 2, 5),
(27, '2026-10-05', 3, 12),
(28, '2026-10-05', 4, 3),
(29, '2026-10-05', 5, 4),
(30, '2026-10-05', 6, 10),
(31, '2026-10-06', 1, 14),
(32, '2026-10-06', 2, 3),
(33, '2026-10-06', 3, 9),
(34, '2026-10-06', 4, 6),
(35, '2026-10-06', 5, 7),
(36, '2026-10-06', 6, 11),
(37, '2026-10-07', 1, 13),
(38, '2026-10-07', 2, 6),
(39, '2026-10-07', 3, 15),
(40, '2026-10-07', 4, 10),
(41, '2026-10-07', 5, 4),
(42, '2026-10-07', 6, 15),
(43, '2026-10-08', 1, 15),
(44, '2026-10-08', 2, 5),
(45, '2026-10-08', 3, 14),
(46, '2026-10-08', 4, 12),
(47, '2026-10-08', 5, 10),
(48, '2026-10-08', 6, 16),
(49, '2026-10-09', 1, 19),
(50, '2026-10-09', 2, 6),
(51, '2026-10-09', 3, 21),
(52, '2026-10-09', 4, 13),
(53, '2026-10-09', 5, 12),
(54, '2026-10-09', 6, 27),
(55, '2026-10-10', 1, 19),
(56, '2026-10-10', 2, 13),
(57, '2026-10-10', 3, 22),
(58, '2026-10-10', 4, 20),
(59, '2026-10-10', 5, 10),
(60, '2026-10-10', 6, 31),
(61, '2026-10-11', 1, 17),
(62, '2026-10-11', 2, 5),
(63, '2026-10-11', 3, 17),
(64, '2026-10-11', 4, 14),
(65, '2026-10-11', 5, 12),
(66, '2026-10-11', 6, 23),
(67, '2026-10-12', 1, 11),
(68, '2026-10-12', 2, 6),
(69, '2026-10-12', 3, 15),
(70, '2026-10-12', 4, 10),
(71, '2026-10-12', 5, 7),
(72, '2026-10-12', 6, 15),
(73, '2026-10-13', 1, 8),
(74, '2026-10-13', 2, 4),
(75, '2026-10-13', 3, 10),
(76, '2026-10-13', 4, 6),
(77, '2026-10-13', 5, 6),
(78, '2026-10-13', 6, 15),
(79, '2026-10-14', 1, 14),
(80, '2026-10-14', 2, 2),
(81, '2026-10-14', 3, 15),
(82, '2026-10-14', 4, 5),
(83, '2026-10-14', 5, 4),
(84, '2026-10-14', 6, 18),
(85, '2026-10-15', 1, 15),
(86, '2026-10-15', 2, 5),
(87, '2026-10-15', 3, 14),
(88, '2026-10-15', 4, 7),
(89, '2026-10-15', 5, 10),
(90, '2026-10-15', 6, 21),
(91, '2026-10-16', 1, 20),
(92, '2026-10-16', 2, 8),
(93, '2026-10-16', 3, 24),
(94, '2026-10-16', 4, 16),
(95, '2026-10-16', 5, 15),
(96, '2026-10-16', 6, 24),
(97, '2026-10-17', 1, 21),
(98, '2026-10-17', 2, 12),
(99, '2026-10-17', 3, 28),
(100, '2026-10-17', 4, 21),
(101, '2026-10-17', 5, 15),
(102, '2026-10-17', 6, 29),
(103, '2026-10-18', 1, 15),
(104, '2026-10-18', 2, 9),
(105, '2026-10-18', 3, 19),
(106, '2026-10-18', 4, 16),
(107, '2026-10-18', 5, 9),
(108, '2026-10-18', 6, 20),
(109, '2026-10-19', 1, 7),
(110, '2026-10-19', 2, 4),
(111, '2026-10-19', 3, 13),
(112, '2026-10-19', 4, 6),
(113, '2026-10-19', 5, 3),
(114, '2026-10-19', 6, 14),
(115, '2026-10-20', 1, 10),
(116, '2026-10-20', 2, 7),
(117, '2026-10-20', 3, 16),
(118, '2026-10-20', 4, 7),
(119, '2026-10-20', 5, 9),
(120, '2026-10-20', 6, 16),
(121, '2026-10-21', 1, 14),
(122, '2026-10-21', 2, 8),
(123, '2026-10-21', 3, 12),
(124, '2026-10-21', 4, 11),
(125, '2026-10-21', 5, 5),
(126, '2026-10-21', 6, 16),
(127, '2026-10-22', 1, 15),
(128, '2026-10-22', 2, 3),
(129, '2026-10-22', 3, 14),
(130, '2026-10-22', 4, 7),
(131, '2026-10-22', 5, 9),
(132, '2026-10-22', 6, 17),
(133, '2026-10-23', 1, 20),
(134, '2026-10-23', 2, 6),
(135, '2026-10-23', 3, 21),
(136, '2026-10-23', 4, 19),
(137, '2026-10-23', 5, 10),
(138, '2026-10-23', 6, 26),
(139, '2026-10-24', 1, 20),
(140, '2026-10-24', 2, 10),
(141, '2026-10-24', 3, 26),
(142, '2026-10-24', 4, 20),
(143, '2026-10-24', 5, 12),
(144, '2026-10-24', 6, 27),
(145, '2026-10-25', 1, 17),
(146, '2026-10-25', 2, 5),
(147, '2026-10-25', 3, 21),
(148, '2026-10-25', 4, 17),
(149, '2026-10-25', 5, 9),
(150, '2026-10-25', 6, 25),
(151, '2026-10-26', 1, 12),
(152, '2026-10-26', 2, 7),
(153, '2026-10-26', 3, 15),
(154, '2026-10-26', 4, 6),
(155, '2026-10-26', 5, 6),
(156, '2026-10-26', 6, 13),
(157, '2026-10-27', 1, 13),
(158, '2026-10-27', 2, 5),
(159, '2026-10-27', 3, 14),
(160, '2026-10-27', 4, 8),
(161, '2026-10-27', 5, 8),
(162, '2026-10-27', 6, 17),
(163, '2026-10-28', 1, 11),
(164, '2026-10-28', 2, 4),
(165, '2026-10-28', 3, 14),
(166, '2026-10-28', 4, 9),
(167, '2026-10-28', 5, 6),
(168, '2026-10-28', 6, 16),
(169, '2026-10-29', 1, 13),
(170, '2026-10-29', 2, 8),
(171, '2026-10-29', 3, 17),
(172, '2026-10-29', 4, 8),
(173, '2026-10-29', 5, 8),
(174, '2026-10-29', 6, 20),
(175, '2026-10-30', 1, 19),
(176, '2026-10-30', 2, 12),
(177, '2026-10-30', 3, 24),
(178, '2026-10-30', 4, 13),
(179, '2026-10-30', 5, 10),
(180, '2026-10-30', 6, 22),
(181, '2026-10-31', 1, 24),
(182, '2026-10-31', 2, 11),
(183, '2026-10-31', 3, 24),
(184, '2026-10-31', 4, 18),
(185, '2026-10-31', 5, 16),
(186, '2026-10-31', 6, 32),
(187, '2026-11-01', 1, 18),
(188, '2026-11-01', 2, 10),
(189, '2026-11-01', 3, 18),
(190, '2026-11-01', 4, 14),
(191, '2026-11-01', 5, 10),
(192, '2026-11-01', 6, 23),
(193, '2026-11-02', 1, 9),
(194, '2026-11-02', 2, 4),
(195, '2026-11-02', 3, 14),
(196, '2026-11-02', 4, 6),
(197, '2026-11-02', 5, 7),
(198, '2026-11-02', 6, 10),
(199, '2026-11-03', 1, 11),
(200, '2026-11-03', 2, 2),
(201, '2026-11-03', 3, 14),
(202, '2026-11-03', 4, 5),
(203, '2026-11-03', 5, 6),
(204, '2026-11-03', 6, 15),
(205, '2026-11-04', 1, 16),
(206, '2026-11-04', 2, 7),
(207, '2026-11-04', 3, 18),
(208, '2026-11-04', 4, 8),
(209, '2026-11-04', 5, 9),
(210, '2026-11-04', 6, 15),
(211, '2026-11-05', 1, 18),
(212, '2026-11-05', 2, 6),
(213, '2026-11-05', 3, 19),
(214, '2026-11-05', 4, 11),
(215, '2026-11-05', 5, 10),
(216, '2026-11-05', 6, 18),
(217, '2026-11-06', 1, 22),
(218, '2026-11-06', 2, 12),
(219, '2026-11-06', 3, 26),
(220, '2026-11-06', 4, 19),
(221, '2026-11-06', 5, 11),
(222, '2026-11-06', 6, 27),
(223, '2026-11-07', 1, 22),
(224, '2026-11-07', 2, 12),
(225, '2026-11-07', 3, 24),
(226, '2026-11-07', 4, 16),
(227, '2026-11-07', 5, 13),
(228, '2026-11-07', 6, 30),
(229, '2026-11-08', 1, 20),
(230, '2026-11-08', 2, 9),
(231, '2026-11-08', 3, 19),
(232, '2026-11-08', 4, 17),
(233, '2026-11-08', 5, 10),
(234, '2026-11-08', 6, 27),
(235, '2026-11-09', 1, 10),
(236, '2026-11-09', 2, 6),
(237, '2026-11-09', 3, 10),
(238, '2026-11-09', 4, 9),
(239, '2026-11-09', 5, 4),
(240, '2026-11-09', 6, 11),
(241, '2026-11-10', 1, 10),
(242, '2026-11-10', 2, 6),
(243, '2026-11-10', 3, 10),
(244, '2026-11-10', 4, 10),
(245, '2026-11-10', 5, 5),
(246, '2026-11-10', 6, 13),
(247, '2026-11-11', 1, 12),
(248, '2026-11-11', 2, 3),
(249, '2026-11-11', 3, 12),
(250, '2026-11-11', 4, 8),
(251, '2026-11-11', 5, 5),
(252, '2026-11-11', 6, 15),
(253, '2026-11-12', 1, 16),
(254, '2026-11-12', 2, 5),
(255, '2026-11-12', 3, 18),
(256, '2026-11-12', 4, 10),
(257, '2026-11-12', 5, 5),
(258, '2026-11-12', 6, 20),
(259, '2026-11-13', 1, 22),
(260, '2026-11-13', 2, 13),
(261, '2026-11-13', 3, 21),
(262, '2026-11-13', 4, 19),
(263, '2026-11-13', 5, 14),
(264, '2026-11-13', 6, 23),
(265, '2026-11-14', 1, 22),
(266, '2026-11-14', 2, 12),
(267, '2026-11-14', 3, 27),
(268, '2026-11-14', 4, 22),
(269, '2026-11-14', 5, 12),
(270, '2026-11-14', 6, 33),
(271, '2026-11-15', 1, 20),
(272, '2026-11-15', 2, 12),
(273, '2026-11-15', 3, 21),
(274, '2026-11-15', 4, 14),
(275, '2026-11-15', 5, 14),
(276, '2026-11-15', 6, 22),
(277, '2026-11-16', 1, 10),
(278, '2026-11-16', 2, 2),
(279, '2026-11-16', 3, 16),
(280, '2026-11-16', 4, 5),
(281, '2026-11-16', 5, 3),
(282, '2026-11-16', 6, 13),
(283, '2026-11-17', 1, 14),
(284, '2026-11-17', 2, 7),
(285, '2026-11-17', 3, 11),
(286, '2026-11-17', 4, 5),
(287, '2026-11-17', 5, 7),
(288, '2026-11-17', 6, 13),
(289, '2026-11-18', 1, 14),
(290, '2026-11-18', 2, 7),
(291, '2026-11-18', 3, 16),
(292, '2026-11-18', 4, 12),
(293, '2026-11-18', 5, 8),
(294, '2026-11-18', 6, 15);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_cobertura_insumo`
-- (See below for the actual view)
--
CREATE TABLE `vw_cobertura_insumo` (
`insumo_id` int(11)
,`insumo` varchar(50)
,`stock_total_g` decimal(32,2)
,`consumo_diario_g` decimal(45,6)
,`cobertura_dias` decimal(42,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_consumo_promedio_30d`
-- (See below for the actual view)
--
CREATE TABLE `vw_consumo_promedio_30d` (
`insumo_id` int(11)
,`consumo_diario_g` decimal(45,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_lote_estado`
-- (See below for the actual view)
--
CREATE TABLE `vw_lote_estado` (
`lote_id` int(11)
,`insumo_id` int(11)
,`insumo` varchar(50)
,`categoria_riesgo` varchar(10)
,`stock_actual` decimal(10,2)
,`estado_visual` varchar(10)
,`cadena_frio` varchar(9)
,`dias_vida` int(7)
,`estado_lote` varchar(10)
,`valor_lote` decimal(17,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_porciones_posibles`
-- (See below for the actual view)
--
CREATE TABLE `vw_porciones_posibles` (
`plato_id` int(11)
,`plato` varchar(60)
,`porciones_posibles` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_ventas_modelo`
-- (See below for the actual view)
--
CREATE TABLE `vw_ventas_modelo` (
`fecha` date
,`plato_id` int(11)
,`plato` varchar(60)
,`porciones_vendidas` int(11)
,`dia_semana` int(3)
,`tipo_dia` varchar(15)
,`t` int(8)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_cobertura_insumo`
--
DROP TABLE IF EXISTS `vw_cobertura_insumo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_cobertura_insumo`  AS SELECT `i`.`insumo_id` AS `insumo_id`, `i`.`nombre` AS `insumo`, ifnull(sum(`l`.`stock_actual`),0) AS `stock_total_g`, `c`.`consumo_diario_g` AS `consumo_diario_g`, CASE WHEN `c`.`consumo_diario_g` > 0 THEN ifnull(sum(`l`.`stock_actual`),0) / `c`.`consumo_diario_g` END AS `cobertura_dias` FROM ((`insumo` `i` left join `lote` `l` on(`l`.`insumo_id` = `i`.`insumo_id` and `l`.`stock_actual` > 0 and `l`.`fecha_vencimiento` >= curdate())) left join `vw_consumo_promedio_30d` `c` on(`c`.`insumo_id` = `i`.`insumo_id`)) GROUP BY `i`.`insumo_id`, `i`.`nombre`, `c`.`consumo_diario_g` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_consumo_promedio_30d`
--
DROP TABLE IF EXISTS `vw_consumo_promedio_30d`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_consumo_promedio_30d`  AS SELECT `r`.`insumo_id` AS `insumo_id`, sum(`v`.`porciones_vendidas` * `r`.`cantidad_por_porcion`) / 30.0 AS `consumo_diario_g` FROM (`venta_diaria` `v` join `receta` `r` on(`r`.`plato_id` = `v`.`plato_id`)) WHERE `v`.`fecha` >= curdate() - interval 30 day GROUP BY `r`.`insumo_id` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_lote_estado`
--
DROP TABLE IF EXISTS `vw_lote_estado`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_lote_estado`  AS SELECT `t`.`lote_id` AS `lote_id`, `t`.`insumo_id` AS `insumo_id`, `t`.`insumo` AS `insumo`, `t`.`categoria_riesgo` AS `categoria_riesgo`, `t`.`stock_actual` AS `stock_actual`, `t`.`estado_visual` AS `estado_visual`, if(`t`.`cadena_frio` = 1,'cumple','no_cumple') AS `cadena_frio`, `t`.`dias_vida` AS `dias_vida`, CASE WHEN `t`.`dias_vida` < 0 THEN 'vencido' WHEN `t`.`dias_vida` = 0 THEN 'vence_hoy' WHEN `t`.`dias_vida` <= 2 THEN 'por_vencer' ELSE 'fresco' END AS `estado_lote`, round(`t`.`stock_actual` * `t`.`costo_unitario`,2) AS `valor_lote` FROM (select `l`.`lote_id` AS `lote_id`,`l`.`insumo_id` AS `insumo_id`,`i`.`nombre` AS `insumo`,`cr`.`nombre` AS `categoria_riesgo`,`l`.`stock_actual` AS `stock_actual`,`l`.`estado_visual` AS `estado_visual`,`l`.`cadena_frio` AS `cadena_frio`,`i`.`costo_unitario` AS `costo_unitario`,to_days(`l`.`fecha_vencimiento`) - to_days(curdate()) AS `dias_vida` from ((`lote` `l` join `insumo` `i` on(`i`.`insumo_id` = `l`.`insumo_id`)) join `categoria_riesgo` `cr` on(`cr`.`categoria_id` = `i`.`categoria_id`)) where `l`.`stock_actual` > 0) AS `t` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_porciones_posibles`
--
DROP TABLE IF EXISTS `vw_porciones_posibles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_porciones_posibles`  AS SELECT `p`.`plato_id` AS `plato_id`, `p`.`nombre` AS `plato`, cast(min(floor(`ci`.`stock_total_g` / `r`.`cantidad_por_porcion`)) as signed) AS `porciones_posibles` FROM ((`plato` `p` join `receta` `r` on(`r`.`plato_id` = `p`.`plato_id`)) join `vw_cobertura_insumo` `ci` on(`ci`.`insumo_id` = `r`.`insumo_id`)) WHERE `p`.`activo` = 1 GROUP BY `p`.`plato_id`, `p`.`nombre` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_ventas_modelo`
--
DROP TABLE IF EXISTS `vw_ventas_modelo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_ventas_modelo`  AS SELECT `v`.`fecha` AS `fecha`, `v`.`plato_id` AS `plato_id`, `p`.`nombre` AS `plato`, `v`.`porciones_vendidas` AS `porciones_vendidas`, weekday(`v`.`fecha`) + 1 AS `dia_semana`, `c`.`tipo_dia` AS `tipo_dia`, to_days(`v`.`fecha`) - to_days((select min(`venta_diaria`.`fecha`) from `venta_diaria`)) + 1 AS `t` FROM ((`venta_diaria` `v` join `plato` `p` on(`p`.`plato_id` = `v`.`plato_id`)) join `calendario_dia` `c` on(`c`.`fecha` = `v`.`fecha`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `calendario_dia`
--
ALTER TABLE `calendario_dia`
  ADD PRIMARY KEY (`fecha`);

--
-- Indexes for table `categoria_riesgo`
--
ALTER TABLE `categoria_riesgo`
  ADD PRIMARY KEY (`categoria_id`),
  ADD UNIQUE KEY `uq_categoria_nombre` (`nombre`);

--
-- Indexes for table `consulta`
--
ALTER TABLE `consulta`
  ADD PRIMARY KEY (`consulta_id`),
  ADD KEY `fk_consulta_usuario` (`usuario_id`);

--
-- Indexes for table `decision`
--
ALTER TABLE `decision`
  ADD PRIMARY KEY (`decision_id`),
  ADD KEY `fk_decision_lote` (`lote_id`),
  ADD KEY `fk_decision_plato` (`plato_id`),
  ADD KEY `ix_decision_consulta` (`consulta_id`);

--
-- Indexes for table `decision_regla`
--
ALTER TABLE `decision_regla`
  ADD PRIMARY KEY (`decision_id`,`regla_id`),
  ADD KEY `fk_decreg_regla` (`regla_id`);

--
-- Indexes for table `insumo`
--
ALTER TABLE `insumo`
  ADD PRIMARY KEY (`insumo_id`),
  ADD UNIQUE KEY `uq_insumo_nombre` (`nombre`),
  ADD KEY `fk_insumo_categoria` (`categoria_id`);

--
-- Indexes for table `lote`
--
ALTER TABLE `lote`
  ADD PRIMARY KEY (`lote_id`),
  ADD KEY `fk_lote_proveedor` (`proveedor_id`),
  ADD KEY `ix_lote_insumo_venc` (`insumo_id`,`fecha_vencimiento`);

--
-- Indexes for table `parametro_politica`
--
ALTER TABLE `parametro_politica`
  ADD PRIMARY KEY (`clave`);

--
-- Indexes for table `plato`
--
ALTER TABLE `plato`
  ADD PRIMARY KEY (`plato_id`),
  ADD UNIQUE KEY `uq_plato_nombre` (`nombre`);

--
-- Indexes for table `pronostico_demanda`
--
ALTER TABLE `pronostico_demanda`
  ADD PRIMARY KEY (`pronostico_id`),
  ADD KEY `fk_pron_plato` (`plato_id`),
  ADD KEY `ix_pron_objetivo` (`fecha_objetivo`,`plato_id`);

--
-- Indexes for table `proveedor`
--
ALTER TABLE `proveedor`
  ADD PRIMARY KEY (`proveedor_id`);

--
-- Indexes for table `receta`
--
ALTER TABLE `receta`
  ADD PRIMARY KEY (`plato_id`,`insumo_id`),
  ADD KEY `fk_receta_insumo` (`insumo_id`);

--
-- Indexes for table `regla`
--
ALTER TABLE `regla`
  ADD PRIMARY KEY (`regla_id`);

--
-- Indexes for table `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`usuario_id`);

--
-- Indexes for table `venta_diaria`
--
ALTER TABLE `venta_diaria`
  ADD PRIMARY KEY (`venta_id`),
  ADD UNIQUE KEY `uq_venta_fecha_plato` (`fecha`,`plato_id`),
  ADD KEY `fk_venta_plato` (`plato_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categoria_riesgo`
--
ALTER TABLE `categoria_riesgo`
  MODIFY `categoria_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `consulta`
--
ALTER TABLE `consulta`
  MODIFY `consulta_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `decision`
--
ALTER TABLE `decision`
  MODIFY `decision_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `insumo`
--
ALTER TABLE `insumo`
  MODIFY `insumo_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lote`
--
ALTER TABLE `lote`
  MODIFY `lote_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plato`
--
ALTER TABLE `plato`
  MODIFY `plato_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pronostico_demanda`
--
ALTER TABLE `pronostico_demanda`
  MODIFY `pronostico_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proveedor`
--
ALTER TABLE `proveedor`
  MODIFY `proveedor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `usuario`
--
ALTER TABLE `usuario`
  MODIFY `usuario_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `venta_diaria`
--
ALTER TABLE `venta_diaria`
  MODIFY `venta_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `consulta`
--
ALTER TABLE `consulta`
  ADD CONSTRAINT `fk_consulta_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`usuario_id`);

--
-- Constraints for table `decision`
--
ALTER TABLE `decision`
  ADD CONSTRAINT `fk_decision_consulta` FOREIGN KEY (`consulta_id`) REFERENCES `consulta` (`consulta_id`),
  ADD CONSTRAINT `fk_decision_lote` FOREIGN KEY (`lote_id`) REFERENCES `lote` (`lote_id`),
  ADD CONSTRAINT `fk_decision_plato` FOREIGN KEY (`plato_id`) REFERENCES `plato` (`plato_id`);

--
-- Constraints for table `decision_regla`
--
ALTER TABLE `decision_regla`
  ADD CONSTRAINT `fk_decreg_decision` FOREIGN KEY (`decision_id`) REFERENCES `decision` (`decision_id`),
  ADD CONSTRAINT `fk_decreg_regla` FOREIGN KEY (`regla_id`) REFERENCES `regla` (`regla_id`);

--
-- Constraints for table `insumo`
--
ALTER TABLE `insumo`
  ADD CONSTRAINT `fk_insumo_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categoria_riesgo` (`categoria_id`);

--
-- Constraints for table `lote`
--
ALTER TABLE `lote`
  ADD CONSTRAINT `fk_lote_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumo` (`insumo_id`),
  ADD CONSTRAINT `fk_lote_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedor` (`proveedor_id`);

--
-- Constraints for table `pronostico_demanda`
--
ALTER TABLE `pronostico_demanda`
  ADD CONSTRAINT `fk_pron_calendario` FOREIGN KEY (`fecha_objetivo`) REFERENCES `calendario_dia` (`fecha`),
  ADD CONSTRAINT `fk_pron_plato` FOREIGN KEY (`plato_id`) REFERENCES `plato` (`plato_id`);

--
-- Constraints for table `receta`
--
ALTER TABLE `receta`
  ADD CONSTRAINT `fk_receta_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumo` (`insumo_id`),
  ADD CONSTRAINT `fk_receta_plato` FOREIGN KEY (`plato_id`) REFERENCES `plato` (`plato_id`);

--
-- Constraints for table `venta_diaria`
--
ALTER TABLE `venta_diaria`
  ADD CONSTRAINT `fk_venta_calendario` FOREIGN KEY (`fecha`) REFERENCES `calendario_dia` (`fecha`),
  ADD CONSTRAINT `fk_venta_plato` FOREIGN KEY (`plato_id`) REFERENCES `plato` (`plato_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
