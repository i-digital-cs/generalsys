-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 18, 2026 at 12:24 PM
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u449614343_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `advances`
--

CREATE TABLE `advances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `date_given` date NOT NULL,
  `deduction_amount_monthly` decimal(12,2) NOT NULL,
  `remaining_amount` decimal(12,2) NOT NULL,
  `status` enum('ACTIVE','FULLY_DEDUCTED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `shift_type` enum('FULL_TIME','ONLINE_HALF','ONLINE_FULL','EXTRA_SHIFT','OVERTIME') NOT NULL,
  `is_friday_work` tinyint(1) NOT NULL DEFAULT 0,
  `is_day_off_work` tinyint(1) NOT NULL DEFAULT 0,
  `is_official_holiday_work` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_by_tl_id` bigint(20) UNSIGNED DEFAULT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_in_lat` decimal(10,7) DEFAULT NULL,
  `clock_in_lng` decimal(10,7) DEFAULT NULL,
  `clock_in_accuracy_m` int(10) UNSIGNED DEFAULT NULL,
  `clock_in_distance_m` int(10) UNSIGNED DEFAULT NULL,
  `clock_in_outside_geofence` tinyint(1) NOT NULL DEFAULT 0,
  `clock_out` datetime DEFAULT NULL,
  `clock_out_lat` decimal(10,7) DEFAULT NULL,
  `clock_out_lng` decimal(10,7) DEFAULT NULL,
  `clock_out_accuracy_m` int(10) UNSIGNED DEFAULT NULL,
  `clock_out_distance_m` int(10) UNSIGNED DEFAULT NULL,
  `clock_out_outside_geofence` tinyint(1) NOT NULL DEFAULT 0,
  `planned_hours` decimal(4,2) NOT NULL DEFAULT 0.00,
  `actual_hours` decimal(4,2) NOT NULL DEFAULT 0.00,
  `status` enum('PRESENT','LATE','EARLY_LEAVE','REST_DAY','OFFICIAL_HOLIDAY','EXCUSED_ABSENCE','UNEXCUSED_ABSENCE','VACATION') NOT NULL DEFAULT 'PRESENT',
  `late_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `early_departure_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_auto_clock_out` tinyint(1) NOT NULL DEFAULT 0,
  `daily_report` text DEFAULT NULL,
  `team_leader_response` text DEFAULT NULL,
  `performance_score` decimal(5,2) DEFAULT NULL,
  `behavior_score` decimal(5,2) DEFAULT NULL,
  `productivity_score` decimal(5,2) DEFAULT NULL,
  `interest_return_score` decimal(5,2) DEFAULT NULL,
  `attendance_score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `date`, `shift_type`, `is_friday_work`, `is_day_off_work`, `is_official_holiday_work`, `assigned_by_tl_id`, `clock_in`, `clock_in_lat`, `clock_in_lng`, `clock_in_accuracy_m`, `clock_in_distance_m`, `clock_in_outside_geofence`, `clock_out`, `clock_out_lat`, `clock_out_lng`, `clock_out_accuracy_m`, `clock_out_distance_m`, `clock_out_outside_geofence`, `planned_hours`, `actual_hours`, `status`, `late_minutes`, `early_departure_minutes`, `is_auto_clock_out`, `daily_report`, `team_leader_response`, `performance_score`, `behavior_score`, `productivity_score`, `interest_return_score`, `attendance_score`, `created_at`, `updated_at`) VALUES
(1, 4, '2026-08-13', 'FULL_TIME', 0, 0, 0, 1, '2026-08-13 16:01:46', NULL, NULL, NULL, NULL, 0, '2026-08-13 18:22:28', 30.1193940, 31.3405205, 108, NULL, 0, 8.00, 2.35, 'LATE', 361, 0, 0, '8-13-2026\r\n1 -تم تحديث جميع الصفحات .\r\n2 - تم رفع بوست لمهرة ستور . \r\n3 - تم عمل كونتنت للبوست . \r\n4 - تم جدولة 3 بوست . \r\n5 - تم تحديث شيت الجدولة . \r\n6 - تم متابعة الصفحات و صفحة مستر هارون .\r\n7 - تم تنزيل بوست علي قناه واتساب مهرة .\r\n8 - تم عمل 5 افكار .', NULL, NULL, NULL, NULL, NULL, 29.38, '2026-08-13 16:01:46', '2026-08-15 10:57:03'),
(2, 2, '2026-08-13', 'FULL_TIME', 0, 0, 0, 1, '2026-08-13 16:09:06', NULL, NULL, NULL, NULL, 0, '2026-08-13 18:22:05', 30.1192413, 31.3405171, 100, NULL, 0, 8.00, 2.22, 'LATE', 369, 0, 0, 'تحديث الاستوري\r\nمتابعة جروبات العمل\r\nمتابعة الاستلامات و التسليمات مع السواقين و الصالونات\r\nتحديث الشيت \r\nمتابعة مشاكل المغسلة\r\nتعديل رسالة الحركة لكريم و صدام\r\nعمل رسالة حركة كريم و صدام غدا\r\nعمل جرد كريم و صدام\r\nترويج للعملاء المستمرين و لعملاء جدد\r\nترويج لسبا من جوجل مابس\r\nمتابعة دفع الفواتير مع العملاء\r\nارسال اوردرات الصالونات في المنصة\r\nنشر بوستات و استوري على فيسبوك و جروبات فيسبوك', NULL, NULL, NULL, NULL, NULL, 27.75, '2026-08-13 16:09:06', '2026-08-15 10:09:09'),
(3, 3, '2026-08-13', 'FULL_TIME', 0, 0, 0, 1, '2026-08-13 16:30:23', NULL, NULL, NULL, NULL, 0, '2026-08-13 18:14:33', 30.1188005, 31.3404340, 141, NULL, 0, 8.00, 1.74, 'LATE', 390, 0, 0, 'مع ماجد', NULL, NULL, NULL, NULL, NULL, 21.75, '2026-08-13 16:30:23', '2026-08-13 18:15:13'),
(4, 3, '2026-08-15', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-15 10:06:18', 30.1192086, 31.3406204, 25, NULL, 0, '2026-08-15 18:03:28', 30.1192250, 31.3406138, 18, NULL, 0, 8.00, 7.95, 'LATE', 6, 0, 0, 'تم تجهيز الاوردرات \r\nتم تنسيق الميتينج \r\nتم تقفيل الحساب مع امير \r\nتم تقفيل الحساب مع اوركيد \r\nتم جرد المخزن \r\nتم تسجيل الجرد علي الشيت', NULL, NULL, NULL, NULL, NULL, 99.38, '2026-08-15 10:06:18', '2026-08-15 18:04:31'),
(5, 2, '2026-08-15', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-15 10:09:44', 30.1192608, 31.3406055, 18, NULL, 0, '2026-08-15 18:00:44', 30.1192423, 31.3405873, 19, NULL, 0, 8.00, 7.85, 'LATE', 9, 0, 0, 'تحديث الاستوري\r\nتحديث الشيت\r\nارسال الفواتير للعملاء\r\nتعديل رسالة الحركة لكريم و صدام\r\nمتابعة جروبات العمل\r\nمتابعة الاستلامات و التسليمات مع السواقين و الصالونات\r\nعمل جرد كريم\r\nعمل رسالة حركة كريم غدا\r\nنشر بوستات و استوري على فيسبوك\r\nترويج للسبا من جوجل مابس\r\nعمل تاسك المغاسل\r\nترويج للعملاء المستمرين\r\nترويج لعملاء جدد و من كشكول العملاء\r\nمتابعة مشاكل المغسلة', NULL, NULL, NULL, NULL, NULL, 98.13, '2026-08-15 10:09:44', '2026-08-15 18:01:56'),
(6, 4, '2026-08-15', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-15 10:57:47', 30.1194075, 31.3405254, 115, NULL, 0, '2026-08-15 18:11:19', 30.1193379, 31.3404423, 97, NULL, 0, 8.00, 7.23, 'LATE', 57, 0, 0, '8-15-2025\r\n\r\n1 - تم تحديث جميع الصفحات . \r\n2 - تم جدولة 6 بوست . \r\n3 - تم تحديث شيت الجدولة . \r\n4 - تم متابعة صفحة مستر هارون .\r\n5 - تم عمل 1 فكرة . \r\n6 - تم تحديث البلان . \r\n7 - تم رفع 2 بوست لمهرة . \r\n8 - تم عمل 2 كونتنت .\r\n9 - تم تنفيذ تاسك PDF للشوزات . \r\n10 - تم عمل 3 صور لل PDF . \r\n11 - تم تصميم 4 صورة جرافيك بدون لوجو . \r\n12 - تم رفع 2 بوست علي 2 قناة .', NULL, NULL, NULL, NULL, NULL, 90.38, '2026-08-15 10:57:47', '2026-08-15 18:11:58'),
(7, 6, '2026-08-15', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-15 15:46:14', 30.1190579, 31.3405375, 20, NULL, 0, '2026-08-15 18:01:06', 30.1196021, 31.3402346, 33, NULL, 0, 8.00, 2.25, 'LATE', 346, 0, 0, 'لا يوجد', NULL, NULL, NULL, NULL, NULL, 28.13, '2026-08-15 15:46:14', '2026-08-15 18:02:10'),
(8, 6, '2026-08-16', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-16 09:58:36', 30.1193142, 31.3404666, 25, NULL, 0, '2026-08-16 17:59:00', 30.1192012, 31.3405993, 12, NULL, 0, 8.00, 8.01, 'PRESENT', 0, 0, 0, 'لا يوجد', NULL, NULL, NULL, NULL, NULL, 100.00, '2026-08-16 09:58:36', '2026-08-16 17:59:07'),
(9, 3, '2026-08-16', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-16 10:00:26', 30.1192501, 31.3406170, 13, NULL, 0, '2026-08-16 18:16:29', 30.1192176, 31.3405631, 21, NULL, 0, 8.00, 8.27, 'PRESENT', 0, 0, 0, 'تم ارسال رساله الحركه \r\nتجهيز الاوردرات \r\nمتابعه السوشيال ميديا \r\nالتقييم فردي \r\nكتابه الاوردرات علي شيت الاكسيل \r\nشير علي الفيس بوك \r\nرساله النوتس', NULL, NULL, NULL, NULL, NULL, 100.00, '2026-08-16 10:00:26', '2026-08-16 18:23:44'),
(10, 4, '2026-08-16', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-16 12:31:53', 30.1339504, 31.3310933, 105, NULL, 0, '2026-08-17 10:24:55', 30.1194208, 31.3405189, 98, NULL, 0, 8.00, 21.88, 'LATE', 151, 0, 0, '8-16-2026\r\n1 - تم تحديث جميع الصفحات .\r\n2 - تم جدولة  5  بوستات . \r\n3 - تم تحديث شيت الجدولة . \r\n4 -تم تصميم 2 صورة بدون لوجو جرافيك .\r\n5 - تم عمل 3 افكار في البلان لجينيرال لاندري .\r\n6 - تم تحديث البلان .\r\n7 - تم متابعة الصفحات .', NULL, NULL, NULL, NULL, NULL, 100.00, '2026-08-16 12:31:53', '2026-08-17 10:25:21'),
(11, 6, '2026-08-17', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-17 10:00:50', 30.1192380, 31.3405738, 16, NULL, 0, '2026-08-17 18:00:28', 30.1191779, 31.3405464, 28, NULL, 0, 8.00, 7.99, 'PRESENT', 0, 0, 0, 'لا يوجد', 'اوك', 95.00, 100.00, 95.00, 90.00, 99.88, '2026-08-17 10:00:50', '2026-08-18 12:09:09'),
(12, 2, '2026-08-17', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-17 10:00:57', 30.1192537, 31.3406073, 18, NULL, 0, '2026-08-17 18:04:40', 30.1193026, 31.3406017, 26, NULL, 0, 8.00, 8.06, 'PRESENT', 0, 0, 0, 'تحديث الشيت\r\nتحديث الاستوري\r\nمتابعة جروبات العمل\r\nالاتقاق مع شركة نظافة و تحديد موعد غدا لزيارته من مستر محمد\r\nترويج للصالونات من جوجل مابس و سبا و ارسال البرايس ليست لسبا\r\nترويج للعملاء المستمرين و لعملاء جدد و من كشكول العملاء\r\nارسال اوردرات الصالونات في المنصة\r\nارسال اوردرات عملاء في المنصة\r\nتعديل رسالة الحركة لكريم و صدام\r\nعمل رسالة حركة كريم غدا\r\nعمل جرد كريم\r\nنشر بوستات على جروبات فيسبوك و استوري', NULL, NULL, NULL, NULL, NULL, 100.00, '2026-08-17 10:00:57', '2026-08-17 18:06:18'),
(13, 3, '2026-08-17', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-17 10:00:58', 30.1191966, 31.3405679, 18, NULL, 0, '2026-08-17 18:04:59', 30.1192087, 31.3405608, 19, NULL, 0, 8.00, 8.07, 'PRESENT', 0, 0, 0, 'ارسال النوتس\r\nرسالة الحركة\r\nمتابعة السوشيال \r\nتسجيل الاوردرات في الشيت\r\nتسجيل الاوردرات على اوركيد\r\nالاداء الفردي السيلز\r\nتجهيز الاوردرات \r\nشير فيسبوك \r\nارسال الجرد \r\nتظبيط مواعيد الشيفت  \r\nتقفيل اوركيد', NULL, NULL, NULL, NULL, NULL, 100.00, '2026-08-17 10:00:58', '2026-08-17 18:10:03'),
(14, 4, '2026-08-17', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-17 10:25:34', 30.1194208, 31.3405189, 98, NULL, 0, '2026-08-17 18:06:21', 30.1193532, 31.3404925, 94, NULL, 0, 8.00, 7.68, 'LATE', 25, 0, 0, '8-17-2026\r\n1 - تم تحديث جميع الصفحات . \r\n2 - تم متابعة صفحة مستر هارون .\r\n3 - تم جدولة 2 بوست .\r\n4 - تم تحديث شيت الجدولة . \r\n5 - تم عمل 15 صورة مدرسية . \r\n6 - تم رفع 2 بوست لصفحة  جمله .', NULL, NULL, NULL, NULL, NULL, 96.00, '2026-08-17 10:25:34', '2026-08-17 18:06:39'),
(15, 6, '2026-08-18', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-18 10:00:46', 30.1191920, 31.3405485, 22, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 8.00, 0.00, 'PRESENT', 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 10:00:46', '2026-08-18 10:00:46'),
(16, 3, '2026-08-18', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-18 10:01:28', 30.1195790, 31.3400790, 36, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 8.00, 0.00, 'LATE', 1, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 10:01:28', '2026-08-18 10:01:28'),
(17, 2, '2026-08-18', 'ONLINE_HALF', 0, 0, 0, 1, '2026-08-18 10:08:37', 30.1192233, 31.3405709, 18, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 4.00, 0.00, 'PRESENT', 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 10:08:37', '2026-08-18 10:08:37'),
(18, 4, '2026-08-18', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-18 10:59:56', 30.1193667, 31.3405038, 97, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 8.00, 0.00, 'LATE', 59, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 10:59:56', '2026-08-18 10:59:56'),
(19, 7, '2026-08-18', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-18 11:03:14', 29.9948395, 31.1835660, 100, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 8.00, 0.00, 'LATE', 183, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 11:03:14', '2026-08-18 11:03:14'),
(20, 11, '2026-08-18', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-18 14:33:35', 25.2693241, 55.3301931, 124, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 8.00, 0.00, 'LATE', 273, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 14:33:35', '2026-08-18 14:33:35'),
(21, 8, '2026-08-18', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-18 14:53:21', 29.8562931, 31.3879311, 100, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 8.00, 0.00, 'LATE', 353, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 14:53:21', '2026-08-18 14:53:21'),
(22, 9, '2026-08-18', 'FULL_TIME', 0, 0, 0, NULL, '2026-08-18 15:03:13', 30.1286549, 31.3343265, 100, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 8.00, 0.00, 'LATE', 303, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 15:03:13', '2026-08-18 15:03:13');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_photos`
--

CREATE TABLE `attendance_photos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attendance_id` bigint(20) UNSIGNED NOT NULL,
  `kind` enum('CLOCK_IN','CLOCK_OUT') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `size_bytes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `hire_date` date NOT NULL,
  `contract_start` date NOT NULL,
  `contract_end` date NOT NULL,
  `contract_type` varchar(100) NOT NULL,
  `document_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `user_id`, `hire_date`, `contract_start`, `contract_end`, `contract_type`, `document_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-06-02', '2026-06-02', '2027-06-02', 'سنة', NULL, 1, '2026-08-13 12:40:09', '2026-08-13 12:40:09'),
(2, 2, '2025-01-10', '2026-04-01', '2027-03-31', 'سنوى', NULL, 1, '2026-08-13 13:51:13', '2026-08-18 02:06:26'),
(3, 3, '2026-08-09', '2026-06-01', '2027-06-01', 'سنوى', NULL, 1, '2026-08-13 14:03:04', '2026-08-17 23:41:17'),
(4, 4, '2025-06-01', '2026-06-05', '2027-06-05', 'سنوى', NULL, 1, '2026-08-13 14:18:51', '2026-08-13 14:18:51'),
(5, 5, '2023-05-25', '2024-01-01', '2027-01-01', 'سنوى', NULL, 1, '2026-08-13 16:18:12', '2026-08-18 12:07:38'),
(6, 6, '2025-10-04', '2025-10-04', '2026-10-03', 'سنوى', NULL, 1, '2026-08-13 16:19:28', '2026-08-17 23:14:30'),
(7, 7, '2026-07-28', '2026-07-28', '2027-07-28', 'سنوى', NULL, 1, '2026-08-13 16:38:13', '2026-08-18 02:41:11'),
(8, 8, '2026-08-01', '2026-08-01', '2027-08-01', 'سنوى', NULL, 1, '2026-08-13 16:55:00', '2026-08-18 04:04:21'),
(9, 9, '2026-01-01', '2026-01-01', '2027-01-01', 'سنوى', NULL, 1, '2026-08-13 17:01:25', '2026-08-13 17:01:25'),
(10, 10, '2023-11-01', '2023-11-01', '2026-08-30', 'سنوى', NULL, 1, '2026-08-18 00:06:34', '2026-08-18 00:06:34'),
(11, 11, '2026-08-12', '2026-08-12', '2026-10-08', 'شهر', NULL, 1, '2026-08-18 00:20:30', '2026-08-18 00:20:30'),
(12, 12, '2026-08-03', '2026-08-03', '2028-08-18', 'اقامة سنتين', NULL, 1, '2026-08-18 00:26:07', '2026-08-18 00:26:07'),
(13, 13, '2026-08-03', '2026-08-03', '2028-08-01', 'اقامة سنتين', NULL, 1, '2026-08-18 00:30:39', '2026-08-18 00:30:39'),
(14, 14, '2026-08-15', '2026-08-15', '2026-11-18', 'سنوى', NULL, 1, '2026-08-18 00:35:27', '2026-08-18 00:35:27'),
(15, 15, '2024-11-15', '2026-04-01', '2027-03-31', 'سنوى', NULL, 1, '2026-08-18 02:20:47', '2026-08-18 02:20:47');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_contacts`
--

CREATE TABLE `emergency_contacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `relationship` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `emergency_contacts`
--

INSERT INTO `emergency_contacts` (`id`, `user_id`, `name`, `phone`, `relationship`, `created_at`, `updated_at`) VALUES
(6, 2, 'هالة', '01064117781', 'الام', '2026-08-18 02:06:26', '2026-08-18 02:06:26'),
(10, 15, 'سماح سمير', '01014722317', 'الام', '2026-08-18 02:25:30', '2026-08-18 02:25:30'),
(11, 6, 'بلال سيد على سيد', '01550781404', 'ابن', '2026-08-18 02:28:23', '2026-08-18 02:28:23'),
(12, 3, 'هبة  صلاح', '01063153551', 'الام', '2026-08-18 11:13:04', '2026-08-18 11:13:04');

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('ID_CARD','QUALIFICATION','BIRTH_CERTIFICATE','CRIMINAL_RECORD','CONTRACT_PDF','OTHER') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_documents`
--

INSERT INTO `employee_documents` (`id`, `user_id`, `type`, `file_path`, `uploaded_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'ID_CARD', 'employee-documents/2/jVZH5vfZ0b2n7RViCsxegLB0HNq6Szm2JSld3upg.jpg', '2026-08-18 02:09:01', '2026-08-18 02:09:01', '2026-08-18 02:09:01'),
(2, 7, 'ID_CARD', 'employee-documents/7/7g1J5PeaNjKuH54jzQyQx3esIeqWglXcST1U1s7D.jpg', '2026-08-18 02:36:20', '2026-08-18 02:36:20', '2026-08-18 02:36:20'),
(3, 8, 'ID_CARD', 'employee-documents/8/aZr7jRVCBr7gEPoRdSBrW47wB4RNtdgPKF79m5xU.jpg', '2026-08-18 04:04:35', '2026-08-18 04:04:35', '2026-08-18 04:04:35'),
(4, 7, 'ID_CARD', 'employee-documents/7/1Bb9rO0BSjbhuUeuXAlZD6PS1ygzI1Q8VNLFCDro.jpg', '2026-08-18 04:14:25', '2026-08-18 04:14:25', '2026-08-18 04:14:25');

-- --------------------------------------------------------

--
-- Table structure for table `employee_profiles`
--

CREATE TABLE `employee_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `gender` enum('MALE','FEMALE') DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `marital_status` enum('SINGLE','MARRIED','DIVORCED','WIDOWED') DEFAULT NULL,
  `personal_photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_profiles`
--

INSERT INTO `employee_profiles` (`id`, `user_id`, `employee_code`, `national_id`, `birth_date`, `address`, `phone`, `gender`, `religion`, `marital_status`, `personal_photo_path`, `created_at`, `updated_at`) VALUES
(1, 1, 'EMP-717090', '51772969997178', '1974-11-25', '574 Jaycee Burg Suite 367\nNew Bernardo, NV 01885', '1-325-398-0329', 'MALE', NULL, 'WIDOWED', NULL, '2026-08-13 12:40:09', '2026-08-13 12:40:09'),
(2, 2, 'EMP-717091', '30206010100129', '2002-05-29', '10 ش حسن بدر - الزهراء - عين شمس القاهرة', '01117869459', 'FEMALE', 'مسلمة', 'SINGLE', 'employee-photos/2/UemhmWdFBuUb9VtYSMjapr4UlMPGNhzF3vUI0CiB.jpg', '2026-08-13 13:51:13', '2026-08-18 02:02:38'),
(3, 3, 'EMP-717092', '30403310100228', '2004-03-31', '٨٠ ش الزهراء احمد عصمت عين شمس الشرقيه', '01149519889', 'FEMALE', 'مسلمة', 'SINGLE', 'employee-photos/3/4vsyCGpDODiLCeTz2T4W4zoRoWntTTooknhYKIxY.jpg', '2026-08-13 14:03:04', '2026-08-17 23:41:17'),
(4, 4, 'EMP-717093', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-13 14:18:51', '2026-08-13 14:18:51'),
(5, 5, 'EMP-717094', '29508230200389', NULL, '41 ش حسن داود - ش الصفا والمروة الهرم الجيزه', '01221514221', 'FEMALE', 'مسلمة', 'MARRIED', NULL, '2026-08-13 16:18:12', '2026-08-18 12:07:38'),
(6, 6, 'EMP-717095', '28205063100109', '1982-05-06', '37ش اسامة بن زيد عرب الطوايله المطرية', '01146290871', 'FEMALE', 'مسلمة', 'WIDOWED', 'employee-photos/6/75QmYRoFo0kS25Fxz7sYxgL5anst8DiEqoYkfKv5.jpg', '2026-08-13 16:19:28', '2026-08-17 23:14:30'),
(7, 7, 'EMP-717096', '29208142101245', '1992-08-12', '9 ش يحيى شلتوت عثمان محرم الطلبية هرم', '01141905792', 'FEMALE', 'مسلمة', 'MARRIED', 'employee-photos/7/nZE2A63aHlep4ukICTxkjJimCTrxC4wLBjCB8D52.jpg', '2026-08-13 16:38:13', '2026-08-18 02:41:11'),
(8, 8, 'EMP-717097', '30305220101401', '2003-05-22', '10 ش النهضة كفر العلو التبين', '01098623052', 'FEMALE', 'مسلمة', 'SINGLE', 'employee-photos/8/gRft0novzlu6IYdmaqUUVQ6HFMbg95K6npJUIu8w.jpg', '2026-08-13 16:55:00', '2026-08-18 04:05:54'),
(9, 9, 'EMP-717098', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-13 17:01:25', '2026-08-13 17:01:25'),
(10, 10, 'EMP-717099', NULL, NULL, NULL, NULL, NULL, 'مسلم', 'MARRIED', NULL, '2026-08-18 00:06:34', '2026-08-18 00:06:34'),
(11, 11, 'EMP-717100', NULL, '1994-07-17', 'احمد عرابى عين شمس', '9710569569926', 'MALE', 'مسلم', 'SINGLE', NULL, '2026-08-18 00:20:30', '2026-08-18 14:47:13'),
(12, 12, 'EMP-717101', NULL, NULL, 'بناية افانى ديرة دبى', NULL, 'MALE', NULL, NULL, NULL, '2026-08-18 00:26:07', '2026-08-18 14:50:26'),
(13, 13, 'EMP-717102', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 00:30:39', '2026-08-18 00:30:39'),
(14, 14, 'EMP-717103', NULL, '2026-08-15', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 00:35:27', '2026-08-18 00:35:27'),
(15, 15, 'EMP-717104', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'employee-photos/15/2lLswXsXDNKcvgQTPs9PISBt1vj1mr37UudZW8Va.png', '2026-08-18 02:20:47', '2026-08-18 02:23:08');

-- --------------------------------------------------------

--
-- Table structure for table `employee_qualifications`
--

CREATE TABLE `employee_qualifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `degree` varchar(150) NOT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `graduation_year` smallint(5) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_qualifications`
--

INSERT INTO `employee_qualifications` (`id`, `user_id`, `degree`, `specialization`, `graduation_year`, `created_at`, `updated_at`) VALUES
(6, 2, 'حقوق عين شمس', 'حقوق', 2025, '2026-08-18 02:06:26', '2026-08-18 02:06:26'),
(10, 15, 'بكاليورس خدمة اجتماعية', 'خدمة اجنماعية', 2023, '2026-08-18 02:25:30', '2026-08-18 02:25:30'),
(11, 6, 'دبلوم تجارة', NULL, NULL, '2026-08-18 02:28:23', '2026-08-18 02:28:23'),
(13, 7, 'دبلوم', 'دبلوم تجارة', 2007, '2026-08-18 10:56:17', '2026-08-18 10:56:17'),
(14, 3, 'بكاليورس خدمة اجتماعية', 'خدمة اجتماعية', 2025, '2026-08-18 11:13:04', '2026-08-18 11:13:04'),
(15, 5, 'ليسانس اداب', 'لغات شرقية', 2017, '2026-08-18 12:07:38', '2026-08-18 12:07:38'),
(16, 11, 'تجارة', 'إدارة اعمال', 2015, '2026-08-18 14:47:13', '2026-08-18 14:47:13');

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `team_leader_id` bigint(20) UNSIGNED NOT NULL,
  `month` tinyint(3) UNSIGNED NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `performance_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `behavior_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `productivity_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `interest_return_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `attendance_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `evaluation_text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_08_02_000001_create_employee_details_tables', 1),
(5, '2026_08_02_000002_create_contracts_holidays_settings_tables', 1),
(6, '2026_08_02_000003_create_rest_days_table', 1),
(7, '2026_08_02_000004_create_attendance_and_requests_tables', 1),
(8, '2026_08_02_000005_expand_shift_assignment_types_for_rotation', 1),
(9, '2026_08_02_154328_create_personal_access_tokens_table', 1),
(10, '2026_08_03_151734_create_notifications_table', 1),
(11, '2026_08_03_210000_users_rest_weekdays_drop_rest_days', 1),
(12, '2026_08_03_220000_restore_rest_days_drop_weekdays_vacation_swap', 1),
(13, '2026_08_04_000001_vacations_add_forced_status', 1),
(14, '2026_08_04_000002_create_payroll_and_penalty_tables', 1),
(15, '2026_08_04_000003_create_evaluations_table', 1),
(16, '2026_08_05_190000_payroll_records_add_extra_pay', 1),
(17, '2026_08_05_193000_payroll_records_add_overtime_multiplier', 1),
(18, '2026_08_05_200000_payroll_records_excel_deduction_counts', 1),
(19, '2026_08_05_213000_attendance_is_auto_clock_out', 1),
(20, '2026_08_12_210000_create_attendance_photos_table', 2),
(21, '2026_08_12_223000_users_add_accountant_role', 2),
(22, '2026_08_13_124700_sqlite_users_role_allow_accountant', 2),
(23, '2026_08_13_131900_attendance_punch_location', 2),
(24, '2026_08_13_153500_users_shift_types_default', 2);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
('01c79c58-ce0a-4764-9dc8-dd92f1d03b53', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:23', '2026-08-15 18:02:23'),
('09a28d2d-7978-4b3e-9b32-a8c3eb6e964d', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":8,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-16\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/8\"}', NULL, '2026-08-16 17:59:09', '2026-08-16 17:59:09'),
('0adaa728-331c-4384-9aef-12db30476f1c', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":12,\"employee_id\":2,\"employee_name_ar\":\"\\u0631\\u0648\\u0627\\u0646 \\u0639\\u0627\\u062f\\u0644 \\u062d\\u0633\\u0646\",\"employee_name_en\":\"Rawan Adel Hassan\",\"date\":\"2026-08-17\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/12\"}', NULL, '2026-08-17 18:06:18', '2026-08-17 18:06:18'),
('0b5b1a97-d37d-4d98-8b9c-d9f6e02e4761', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":2,\"employee_id\":2,\"employee_name_ar\":\"\\u0631\\u0648\\u0627\\u0646 \\u0639\\u0627\\u062f\\u0644 \\u062d\\u0633\\u0646\",\"employee_name_en\":\"Rawan Adel Hassan\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/2\"}', NULL, '2026-08-15 10:09:20', '2026-08-15 10:09:20'),
('0d391cd8-37da-4462-9fe3-b080bccc9d22', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:22', '2026-08-15 18:02:22'),
('0edafeaf-cd13-4eac-8bbf-eed35c07a9e0', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":14,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-17\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/14\"}', NULL, '2026-08-17 18:06:39', '2026-08-17 18:06:39'),
('1023a09a-bd47-4e63-8f55-467e20627216', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":11,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-17\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/11\"}', NULL, '2026-08-17 18:00:54', '2026-08-17 18:00:54'),
('2480c320-2670-4f60-8fe2-61e147901384', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":3,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/3\"}', NULL, '2026-08-13 18:15:52', '2026-08-13 18:15:52'),
('28a53c2a-cf4e-4910-8102-f05836dc312e', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":2,\"employee_id\":2,\"employee_name_ar\":\"\\u0631\\u0648\\u0627\\u0646 \\u0639\\u0627\\u062f\\u0644 \\u062d\\u0633\\u0646\",\"employee_name_en\":\"Rawan Adel Hassan\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/2\"}', NULL, '2026-08-15 10:09:09', '2026-08-15 10:09:09'),
('2c4d5076-215e-4e83-ab91-ff6d960f70c9', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:23', '2026-08-15 18:02:23'),
('32617da4-b530-4caf-b940-e8033af3d160', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":4,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/4\"}', NULL, '2026-08-15 18:04:39', '2026-08-15 18:04:39'),
('32ba9c6c-385f-42f2-9cfd-f1cf3f6e36df', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:38', '2026-08-15 18:02:38'),
('347c6db6-38ea-4291-b758-84515b02e535', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:03:02', '2026-08-15 18:03:02'),
('347f9933-49bb-42a8-81c9-a81a2f10ea3f', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:39', '2026-08-15 18:02:39'),
('35c4a0f6-225a-466c-ad8c-5a3d770811bf', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:20', '2026-08-15 18:02:20'),
('3d5ec4f5-c254-495a-a4e6-269aedc14b27', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":1,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/1\"}', NULL, '2026-08-15 10:57:18', '2026-08-15 10:57:18'),
('42040de1-bd38-4fc9-bf1c-80096d9e05e8', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":5,\"employee_id\":2,\"employee_name_ar\":\"\\u0631\\u0648\\u0627\\u0646 \\u0639\\u0627\\u062f\\u0644 \\u062d\\u0633\\u0646\",\"employee_name_en\":\"Rawan Adel Hassan\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/5\"}', NULL, '2026-08-15 18:02:06', '2026-08-15 18:02:06'),
('458cc226-9f59-407f-a879-d74ba17683f0', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":9,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-16\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/9\"}', NULL, '2026-08-16 18:23:46', '2026-08-16 18:23:46'),
('4986dc72-5985-45dc-b3cb-1629ca2582f7', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":4,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/4\"}', NULL, '2026-08-15 18:04:40', '2026-08-15 18:04:40'),
('4ac1b4ee-559d-4f60-8e9f-51f439b05587', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:03:02', '2026-08-15 18:03:02'),
('50797bb3-8686-450c-a9e2-2cf0ece2fdbb', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:03:02', '2026-08-15 18:03:02'),
('5da08af1-f2a3-46c1-98cc-a7169f2038e1', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:38', '2026-08-15 18:02:38'),
('68a3e22b-30e0-4f45-a1f1-61bcf8851e20', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":4,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/4\"}', NULL, '2026-08-15 18:04:44', '2026-08-15 18:04:44'),
('69fad804-7ded-4b2c-99f4-7c017c8c5f46', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":5,\"employee_id\":2,\"employee_name_ar\":\"\\u0631\\u0648\\u0627\\u0646 \\u0639\\u0627\\u062f\\u0644 \\u062d\\u0633\\u0646\",\"employee_name_en\":\"Rawan Adel Hassan\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/5\"}', NULL, '2026-08-15 18:01:56', '2026-08-15 18:01:56'),
('6ed9044f-1e20-40a3-b083-a2a19be0753e', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":3,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/3\"}', NULL, '2026-08-13 18:15:13', '2026-08-13 18:15:13'),
('706b15c3-c62b-4eec-90f9-9b772a03753c', 'App\\Notifications\\DailyReportRepliedNotification', 'App\\Models\\User', 6, '{\"kind\":\"report_replied\",\"attendance_id\":11,\"date\":\"2026-08-17\",\"url\":\"https:\\/\\/system.genral.net\\/report?date=2026-08-17\"}', NULL, '2026-08-18 12:08:35', '2026-08-18 12:08:35'),
('7072f985-2ca4-4c2c-84cc-08188f7fe6d6', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:40', '2026-08-15 18:02:40'),
('759dc1dd-2a06-41a9-9f45-830607323d5f', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:40', '2026-08-15 18:02:40'),
('7852f54c-e2cd-44ab-a6ce-8d7634a71218', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":1,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/1\"}', NULL, '2026-08-15 10:57:18', '2026-08-15 10:57:18'),
('7f803beb-e692-456f-9f63-e79c1abf38d5', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":4,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/4\"}', NULL, '2026-08-15 18:04:33', '2026-08-15 18:04:33'),
('81edbae2-d3e2-4c98-8ae1-1eea20758ad8', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":6,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/6\"}', NULL, '2026-08-15 18:12:17', '2026-08-15 18:12:17'),
('8234813c-2524-4255-befc-c7d843ec2fd1', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":8,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-16\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/8\"}', NULL, '2026-08-16 17:59:08', '2026-08-16 17:59:08'),
('833dddd2-857c-4ad5-bfb3-d3f193e0293b', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":9,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-16\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/9\"}', NULL, '2026-08-16 18:23:44', '2026-08-16 18:23:44'),
('8429a608-dd2c-4bb3-9289-37df733945f7', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":5,\"employee_id\":2,\"employee_name_ar\":\"\\u0631\\u0648\\u0627\\u0646 \\u0639\\u0627\\u062f\\u0644 \\u062d\\u0633\\u0646\",\"employee_name_en\":\"Rawan Adel Hassan\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/5\"}', NULL, '2026-08-15 18:03:07', '2026-08-15 18:03:07'),
('84ef5dfd-5b65-4406-ac7e-dcc1bf5aa0ec', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":1,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/1\"}', NULL, '2026-08-15 10:57:15', '2026-08-15 10:57:15'),
('87d2ada6-71ea-4cce-a641-c920fc1b64c8', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":5,\"employee_id\":2,\"employee_name_ar\":\"\\u0631\\u0648\\u0627\\u0646 \\u0639\\u0627\\u062f\\u0644 \\u062d\\u0633\\u0646\",\"employee_name_en\":\"Rawan Adel Hassan\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/5\"}', NULL, '2026-08-15 18:03:30', '2026-08-15 18:03:30'),
('8c28a14f-6761-48ce-b8df-9550ef519334', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":11,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-17\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/11\"}', NULL, '2026-08-17 18:01:18', '2026-08-17 18:01:18'),
('8ea92e05-e651-4f7a-8b26-3b1a40ac9bc1', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:39', '2026-08-15 18:02:39'),
('957da560-cb71-4750-ac75-5e09aa80e2b0', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":6,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/6\"}', NULL, '2026-08-15 18:12:18', '2026-08-15 18:12:18'),
('9728b3dc-74ed-4ff3-b4a3-d3436875cc08', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":9,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-16\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/9\"}', NULL, '2026-08-16 18:23:59', '2026-08-16 18:23:59'),
('99930859-396c-4773-9ba9-a089ba6eb459', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":6,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/6\"}', NULL, '2026-08-15 18:12:18', '2026-08-15 18:12:18'),
('9ceadfa8-e05c-4fc1-b27e-da91561ec0c8', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":3,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/3\"}', NULL, '2026-08-13 18:15:46', '2026-08-13 18:15:46'),
('a975bde5-1adb-4890-9219-d48a40405e77', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:03:03', '2026-08-15 18:03:03'),
('aa67a4f3-a781-4438-9c8a-1c8f074e0b71', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":4,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/4\"}', NULL, '2026-08-15 18:04:31', '2026-08-15 18:04:31'),
('aacb3aae-41cc-48c1-96f0-527fc4d48072', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:03:03', '2026-08-15 18:03:03'),
('ac491251-852b-4501-82ba-29fcefed8cc0', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":6,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/6\"}', NULL, '2026-08-15 18:12:09', '2026-08-15 18:12:09'),
('b59fbfde-f8ac-4aab-a7cb-3cbab4d03a6d', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:20', '2026-08-15 18:02:20'),
('bf03f45a-70b9-4088-845a-a85e03e2f232', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:10', '2026-08-15 18:02:10'),
('c157a293-5119-4869-9ece-3f36b95610f0', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":8,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-16\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/8\"}', NULL, '2026-08-16 17:59:13', '2026-08-16 17:59:13'),
('c5ca0b78-2822-48cc-8662-ba45a0a07df3', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":1,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/1\"}', NULL, '2026-08-15 10:57:18', '2026-08-15 10:57:18'),
('cf87029b-1fef-4a67-9e77-1238980d9c72', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":4,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/4\"}', NULL, '2026-08-15 18:04:36', '2026-08-15 18:04:36'),
('d3228db8-3035-4f4c-803f-0f44f9e663b8', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:17', '2026-08-15 18:02:17'),
('d4df4f85-ea5c-4d89-bb49-ace1dbc7e0db', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":1,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/1\"}', NULL, '2026-08-15 10:57:15', '2026-08-15 10:57:15'),
('da5ca7db-7bd3-4f11-9680-e2c6706239b2', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:03:02', '2026-08-15 18:03:02'),
('df1a6ee7-e706-49ca-a79a-91dc68ce1a95', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":6,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/6\"}', NULL, '2026-08-15 18:12:04', '2026-08-15 18:12:04'),
('e49e5ff2-2ccf-42d4-8173-a9ce4f401eec', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":3,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/3\"}', NULL, '2026-08-13 18:15:19', '2026-08-13 18:15:19'),
('e9c31708-b495-47da-b7a2-0af12b02866e', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":10,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-16\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/10\"}', NULL, '2026-08-17 10:25:22', '2026-08-17 10:25:22'),
('ebecc666-72d2-412f-b9e5-2ac3b0a85165', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":2,\"employee_id\":2,\"employee_name_ar\":\"\\u0631\\u0648\\u0627\\u0646 \\u0639\\u0627\\u062f\\u0644 \\u062d\\u0633\\u0646\",\"employee_name_en\":\"Rawan Adel Hassan\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/2\"}', NULL, '2026-08-15 10:09:15', '2026-08-15 10:09:15'),
('eea4f334-e704-4d50-a51a-366280fe8505', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":13,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-17\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/13\"}', NULL, '2026-08-17 18:10:03', '2026-08-17 18:10:03'),
('f26461e9-60a7-46ce-b09a-6d7e1016ad67', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":3,\"employee_id\":3,\"employee_name_ar\":\"\\u0645\\u0631\\u064a\\u0645 \\u0639\\u0628\\u062f \\u0627\\u0644\\u062d\\u0644\\u064a\\u0645\",\"employee_name_en\":\"Mariam Abed-elhalem\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/3\"}', NULL, '2026-08-13 18:15:35', '2026-08-13 18:15:35'),
('f326e337-6713-4154-857c-3abbda0cc494', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":1,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-13\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/1\"}', NULL, '2026-08-15 10:57:03', '2026-08-15 10:57:03'),
('fe6bc714-a329-4f70-bdf4-5d42237aa70f', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":6,\"employee_id\":4,\"employee_name_ar\":\"\\u0645\\u0649 \\u0645\\u062d\\u0645\\u062f\",\"employee_name_en\":\"Mai Mohamed\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/6\"}', NULL, '2026-08-15 18:11:58', '2026-08-15 18:11:58'),
('ff8da163-2d7d-4a84-9382-7f3239cb4a5f', 'App\\Notifications\\DailyReportSubmittedNotification', 'App\\Models\\User', 1, '{\"kind\":\"report_submitted\",\"attendance_id\":7,\"employee_id\":6,\"employee_name_ar\":\"\\u0645\\u0646\\u0649\",\"employee_name_en\":\"MONA\",\"date\":\"2026-08-15\",\"url\":\"https:\\/\\/system.genral.net\\/team-leader\\/reports\\/7\"}', NULL, '2026-08-15 18:02:40', '2026-08-15 18:02:40');

-- --------------------------------------------------------

--
-- Table structure for table `official_holidays`
--

CREATE TABLE `official_holidays` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `name_ar` varchar(150) NOT NULL,
  `name_en` varchar(150) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_adjustments`
--

CREATE TABLE `payroll_adjustments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payroll_record_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('DEPARTMENT_COMMISSION','SALES_COMMISSION','PROFIT_COMMISSION','BONUS','ADVANCE','OTHER_DEDUCTION') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_records`
--

CREATE TABLE `payroll_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `month` tinyint(3) UNSIGNED NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `currency` enum('EGP','AED') NOT NULL,
  `base_salary` decimal(12,2) NOT NULL,
  `full_time_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `online_half_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `online_full_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `rest_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `extra_shift_hours` decimal(6,2) NOT NULL DEFAULT 0.00,
  `overtime_hours` decimal(6,2) NOT NULL DEFAULT 0.00,
  `overtime_multiplier` decimal(5,2) NOT NULL DEFAULT 0.00,
  `friday_work_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `day_off_work_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `official_holiday_work_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `extra_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `hours_deduction` decimal(6,2) NOT NULL DEFAULT 0.00,
  `hours_deduction_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `excused_absence_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `unexcused_absence_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `late_minutes_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `full_time_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `online_half_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `online_full_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `overtime_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `friday_bonus` decimal(12,2) NOT NULL DEFAULT 0.00,
  `day_off_bonus` decimal(12,2) NOT NULL DEFAULT 0.00,
  `official_holiday_bonus` decimal(12,2) NOT NULL DEFAULT 0.00,
  `extra_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_penalty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `leave_permission_penalty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_permission_penalty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `excused_absence_penalty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unexcused_absence_penalty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `forced_penalty_surcharge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `advance_deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('DRAFT','APPROVED','PAID') NOT NULL DEFAULT 'DRAFT',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penalty_rules`
--

CREATE TABLE `penalty_rules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('LATE_ARRIVAL','LEAVE_PERMISSION','LATE_PERMISSION','EXCUSED_ABSENCE','UNEXCUSED_ABSENCE') NOT NULL,
  `currency` enum('EGP','AED') NOT NULL,
  `amount_per_unit` decimal(12,2) NOT NULL,
  `unit` enum('MINUTE','HOUR','DAY') NOT NULL,
  `double_penalty_multiplier` decimal(4,2) NOT NULL DEFAULT 2.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `penalty_rules`
--

INSERT INTO `penalty_rules` (`id`, `name`, `type`, `currency`, `amount_per_unit`, `unit`, `double_penalty_multiplier`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Late arrival (EGP)', 'LATE_ARRIVAL', 'EGP', 1.00, 'MINUTE', 2.00, 1, '2026-08-13 12:40:08', '2026-08-13 12:40:08'),
(2, 'Leave permission (EGP)', 'LEAVE_PERMISSION', 'EGP', 1.00, 'MINUTE', 2.00, 1, '2026-08-13 12:40:08', '2026-08-13 12:40:08'),
(3, 'Late permission (EGP)', 'LATE_PERMISSION', 'EGP', 1.00, 'MINUTE', 2.00, 1, '2026-08-13 12:40:08', '2026-08-13 12:40:08'),
(4, 'Excused absence (EGP)', 'EXCUSED_ABSENCE', 'EGP', 1.00, 'DAY', 2.00, 1, '2026-08-13 12:40:08', '2026-08-13 12:40:08'),
(5, 'Unexcused absence (EGP)', 'UNEXCUSED_ABSENCE', 'EGP', 3.00, 'DAY', 2.00, 1, '2026-08-13 12:40:08', '2026-08-13 12:40:08'),
(6, 'Late arrival (AED)', 'LATE_ARRIVAL', 'AED', 2.00, 'MINUTE', 2.00, 1, '2026-08-13 12:40:08', '2026-08-13 12:40:08'),
(7, 'Leave permission (AED)', 'LEAVE_PERMISSION', 'AED', 2.00, 'MINUTE', 2.00, 1, '2026-08-13 12:40:08', '2026-08-13 12:40:08'),
(8, 'Late permission (AED)', 'LATE_PERMISSION', 'AED', 2.00, 'MINUTE', 2.00, 1, '2026-08-13 12:40:08', '2026-08-13 12:40:08'),
(9, 'Excused absence (AED)', 'EXCUSED_ABSENCE', 'AED', 1.00, 'DAY', 2.00, 1, '2026-08-13 12:40:08', '2026-08-13 12:40:08'),
(10, 'Unexcused absence (AED)', 'UNEXCUSED_ABSENCE', 'AED', 3.00, 'DAY', 2.00, 1, '2026-08-13 12:40:08', '2026-08-13 12:40:08');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('LEAVE_PERMISSION','LATE_PERMISSION') NOT NULL,
  `request_date` date NOT NULL,
  `from_time` time NOT NULL,
  `to_time` time NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('PENDING','ACCEPTED','REJECTED','FORCED') NOT NULL DEFAULT 'PENDING',
  `team_leader_id` bigint(20) UNSIGNED DEFAULT NULL,
  `penalty_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_double_penalty` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rest_days`
--

CREATE TABLE `rest_days` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rest_days`
--

INSERT INTO `rest_days` (`id`, `user_id`, `date`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 3, '2026-08-07', 1, '2026-08-13 14:43:49', '2026-08-13 14:43:49'),
(6, 3, '2026-08-14', 1, '2026-08-13 14:43:53', '2026-08-13 14:43:53');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(1, 'default_language', 'ar', '2026-08-13 12:40:09', '2026-08-13 12:40:09'),
(2, 'default_currency', 'EGP', '2026-08-13 12:40:09', '2026-08-13 12:40:09');

-- --------------------------------------------------------

--
-- Table structure for table `shift_assignments`
--

CREATE TABLE `shift_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_by_tl_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `shift_type` enum('FULL_TIME','ONLINE_HALF','ONLINE_FULL','EXTRA_SHIFT','OVERTIME') NOT NULL,
  `expected_start_time` time NOT NULL,
  `expected_end_time` time NOT NULL,
  `expected_hours` decimal(4,2) NOT NULL,
  `multiplier` decimal(4,2) NOT NULL DEFAULT 1.00,
  `notes` text DEFAULT NULL,
  `status` enum('SCHEDULED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shift_assignments`
--

INSERT INTO `shift_assignments` (`id`, `user_id`, `assigned_by_tl_id`, `date`, `shift_type`, `expected_start_time`, `expected_end_time`, `expected_hours`, `multiplier`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2026-08-13', 'FULL_TIME', '10:00:00', '18:00:00', 8.00, 1.00, NULL, 'CANCELLED', '2026-08-13 13:54:15', '2026-08-13 14:30:06'),
(2, 3, 1, '2026-08-13', 'FULL_TIME', '10:00:00', '18:00:00', 8.00, 1.00, NULL, 'COMPLETED', '2026-08-13 14:06:58', '2026-08-13 18:14:33'),
(3, 4, 1, '2026-08-13', 'FULL_TIME', '10:00:00', '18:00:00', 8.00, 1.00, NULL, 'COMPLETED', '2026-08-13 14:19:13', '2026-08-13 18:22:28'),
(4, 2, 1, '2026-08-18', 'ONLINE_HALF', '18:00:00', '22:00:00', 4.00, 1.00, NULL, 'SCHEDULED', '2026-08-13 14:25:23', '2026-08-13 14:25:23'),
(5, 2, 1, '2026-08-13', 'ONLINE_HALF', '18:00:00', '22:00:00', 4.00, 1.00, NULL, 'SCHEDULED', '2026-08-13 14:30:43', '2026-08-13 14:30:43'),
(6, 2, 1, '2026-08-13', 'FULL_TIME', '10:00:00', '18:00:00', 8.00, 1.00, NULL, 'COMPLETED', '2026-08-13 14:32:33', '2026-08-13 18:22:05'),
(7, 3, 1, '2026-08-13', 'ONLINE_HALF', '18:00:00', '22:00:00', 4.00, 1.00, NULL, 'SCHEDULED', '2026-08-13 16:11:15', '2026-08-13 16:11:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name_ar` varchar(150) NOT NULL,
  `name_en` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('MANAGER','TEAM_LEADER','EMPLOYEE','ACCOUNTANT') NOT NULL DEFAULT 'EMPLOYEE',
  `currency` enum('EGP','AED') NOT NULL,
  `language_preference` enum('ar','en') NOT NULL DEFAULT 'ar',
  `team_leader_id` bigint(20) UNSIGNED DEFAULT NULL,
  `base_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `online_half_day_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `online_full_day_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `overtime_hourly_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shift_type_default` enum('FULL_TIME','ONLINE_HALF','ONLINE_FULL') NOT NULL DEFAULT 'FULL_TIME',
  `shift_types_default` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shift_types_default`)),
  `work_start_time` time DEFAULT NULL,
  `work_end_time` time DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name_ar`, `name_en`, `email`, `password`, `role`, `currency`, `language_preference`, `team_leader_id`, `base_salary`, `online_half_day_rate`, `online_full_day_rate`, `overtime_hourly_rate`, `shift_type_default`, `shift_types_default`, `work_start_time`, `work_end_time`, `status`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'مدير النظام', 'System Admin', 'admin@hrpro.test', '$2y$12$SVKoxQeI2mUBZUpplUltVeYlyaFQg6sqcBM52X3j6R7Wj2Fxia/bW', 'MANAGER', 'EGP', 'ar', NULL, 0.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[\"FULL_TIME\"]', '10:00:00', '18:00:00', 'ACTIVE', 't6gLer9213Z4NAYm6XjteUhDtYO492ux85NF89ZlYa6RfbzuKrnr5M81LloH', '2026-08-13 12:40:09', '2026-08-13 12:40:09'),
(2, 'روان عادل حسن', 'Rawan Adel Hassan', 'RAWAN@genral.net', '$2y$12$.uf0TBFEemKMcIoPrHnhheKRZSaIvbm.XVnvk9Ec1vp15GMrZOlo2', 'EMPLOYEE', 'EGP', 'ar', 5, 7000.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"10:00\",\"end\":\"18:00\"},{\"type\":\"ONLINE_HALF\",\"start\":\"18:00\",\"end\":\"22:00\"}]', '10:00:00', '22:00:00', 'ACTIVE', 'yzsh7RKi4f1mIW3txTsCQ31loFoY9O1QKgKnU9JqFwRB8Fn43jpCFBHYMeaQ', '2026-08-13 13:51:13', '2026-08-18 02:06:26'),
(3, 'مريم عبد الحليم', 'Mariam Abed-elhalem', 'MARIAM@genral.net', '$2y$12$d12ctbPQnviTADDynYDChukYQfwsNAOEHfnLMJ06Jy3PY3pTYvO/.', 'TEAM_LEADER', 'EGP', 'ar', 14, 5000.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"10:00\",\"end\":\"18:00\"},{\"type\":\"ONLINE_HALF\",\"start\":\"18:00\",\"end\":\"22:00\"}]', '10:00:00', '22:00:00', 'ACTIVE', 'Mrg6UgLp0G5NKId1cczlc2pUWgAUmXmZ35Z6GB3yPBABWTc8KUNoqVmabtDZ', '2026-08-13 14:03:04', '2026-08-18 11:13:04'),
(4, 'مى محمد', 'Mai Mohamed', 'MAI@genral.net', '$2y$12$NTxVWJzRiDf64eiYdzhGXOW7hXOSvNVH83e51ByHxhPyKg.738cYO', 'EMPLOYEE', 'EGP', 'ar', 10, 5500.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"10:00\",\"end\":\"18:00\"}]', '10:00:00', '18:00:00', 'ACTIVE', 'AtEduXqOSJrEJZ2iOf2FX2tuBKJBumM4v7TVdG6MKQnYIKEnlbsL4CoeI30Y', '2026-08-13 14:18:51', '2026-08-18 03:53:14'),
(5, 'رضوى', 'RADWA', 'RADWA@genral.net', '$2y$12$hu9LxyS9clw1.APDIEDOOuWkPIOVud00GYxEYIj8hENnKWoijuL7C', 'TEAM_LEADER', 'EGP', 'ar', 15, 8000.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"10:00\",\"end\":\"18:00\"},{\"type\":\"ONLINE_HALF\",\"start\":\"06:00\",\"end\":\"10:00\"}]', '06:00:00', '18:00:00', 'ACTIVE', 'QEcqIs711Z4GV5c48pD9U7hYDmkMXP5XcHRIAO8tI1L1cNgsdNkO2QMnxXYd', '2026-08-13 16:18:12', '2026-08-18 12:07:38'),
(6, 'منى', 'MONA', 'MONA@genral.net', '$2y$12$5R93SRhrWGEpHm0Elc39Qum02eKorauGSVGt891yYo4t93dosy4rG', 'EMPLOYEE', 'EGP', 'ar', 15, 4000.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"10:00\",\"end\":\"18:00\"}]', '10:00:00', '18:00:00', 'ACTIVE', 'PT8fwzrG5mEquS60ucbEF9zNwNDHLUb8eaqbre65zgSMJqGrM4aIVA2yauDx', '2026-08-13 16:19:28', '2026-08-18 02:28:23'),
(7, 'دعاء', 'DOAA', 'DOAA@genral.net', '$2y$12$re2rIZHFzlC6vGQ56qQsleF9kG.A.g92rH5gFbl24GiPJwMaHpA52', 'EMPLOYEE', 'EGP', 'ar', 3, 3000.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"08:00\",\"end\":\"01:00\"}]', '08:00:00', '01:00:00', 'ACTIVE', 'WOgJCmq7x3inMhIdENoyEh0rmnaXmYdaRPUCjSIIVPOenhbPXE2UDHXMfVT8', '2026-08-13 16:38:13', '2026-08-18 10:56:17'),
(8, 'سامية', 'SAMIA', 'SAMIA@genral.net', '$2y$12$1QShnKB00MLHdi66/XxJ3ufD1962pjQ31jGZX/mO2mRAvS4G/YT8.', 'EMPLOYEE', 'EGP', 'ar', 3, 3000.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"09:00\",\"end\":\"02:00\"},{\"type\":\"ONLINE_HALF\",\"start\":\"06:00\",\"end\":\"10:00\"}]', '06:00:00', '10:00:00', 'ACTIVE', 'bgiuPFiKCw47z2QdMQmXyi8gjeHjt5ZjD25EChG3tKG9tily17unXS1M7smF', '2026-08-13 16:55:00', '2026-08-18 04:06:21'),
(9, 'كريم', 'KAREEM', 'KAREEM@genral.net', '$2y$12$H/s2B8R7qrX4PGr6iVcORuj8Ijzsb5/iwQm7kdGOz.Cax42dDlBWO', 'EMPLOYEE', 'EGP', 'ar', 5, 3300.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"10:00\",\"end\":\"22:00\"}]', '10:00:00', '22:00:00', 'ACTIVE', NULL, '2026-08-13 17:01:25', '2026-08-18 14:57:28'),
(10, 'ماجد زكريا', 'MAGED ZAKRIA', 'generalmaged1@gmail.com', '$2y$12$k0zZP/Sy0bG19qiFZDDVQupgsZzp4VpIAH0J2NyzONFRNX8Wq0lVO', 'TEAM_LEADER', 'EGP', 'ar', NULL, 0.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"10:00\",\"end\":\"18:00\"}]', '10:00:00', '18:00:00', 'ACTIVE', NULL, '2026-08-18 00:06:34', '2026-08-18 00:06:34'),
(11, 'محمد عيد', 'MOHAMED EID', 'MOHAMEDEID@genral.net', '$2y$12$7MSmdVMRkLl9eln.8varXu4VnY4gKNFwRvKVLgSIMyRsxfWfymuMS', 'TEAM_LEADER', 'AED', 'ar', 10, 25000.00, 100.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"10:00\",\"end\":\"23:00\"}]', '10:00:00', '23:00:00', 'ACTIVE', 'eUC6yNIE6qJTNQ6axhTth6B6aB7P5UTgdPDNguJafePbHHYhHiFJD4VP9MOA', '2026-08-18 00:20:30', '2026-08-18 14:47:13'),
(12, 'محمد رضا', 'MOHAMED REDA', 'MOHAMEDREDA@gmail.com', '$2y$12$RskGJuO1LfDOtA8qnr3nu.9RzB4.6/aZaq6slmlZFh7IlvoF2g0RK', 'EMPLOYEE', 'AED', 'ar', 11, 3100.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"22:00\",\"end\":\"10:00\"}]', '22:00:00', '10:00:00', 'ACTIVE', NULL, '2026-08-18 00:26:07', '2026-08-18 14:50:26'),
(13, 'محمد امير', 'MOHAMEDAMIR', 'MOHAMEDAMIR@genral.net', '$2y$12$PIUcGxHT8FZiRZcB0D/Pte/lZ7NSzProArBgoTejv.s5u3a2SVWuS', 'EMPLOYEE', 'AED', 'ar', NULL, 3100.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"22:00\",\"end\":\"10:00\"}]', '22:00:00', '10:00:00', 'ACTIVE', NULL, '2026-08-18 00:30:39', '2026-08-18 00:30:39'),
(14, 'مصطفى هارون', 'MOSTAFA HAROON', 'MOSTAFAHAROON@genral.net', '$2y$12$lK5ZVL.qtNtVSzOZugrsUuNXZwjKWjLBnq0YX5ja3pNA5ScTEYsXm', 'TEAM_LEADER', 'EGP', 'ar', NULL, 8000.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"12:00\",\"end\":\"18:00\"}]', '12:00:00', '18:00:00', 'ACTIVE', NULL, '2026-08-18 00:35:27', '2026-08-18 00:35:27'),
(15, 'سمر رشيدى', 'SAMAR RASHEDY', 'genral.net2@gmail.com', '$2y$12$kIem8gHBr4VrXQbJsIu8qeV4chMgItqgcYM/DPROmzH420jhTrPsu', 'TEAM_LEADER', 'EGP', 'ar', NULL, 5000.00, 0.00, 0.00, 0.00, 'FULL_TIME', '[{\"type\":\"FULL_TIME\",\"start\":\"10:00\",\"end\":\"18:00\"}]', '10:00:00', '18:00:00', 'ACTIVE', NULL, '2026-08-18 02:20:47', '2026-08-18 02:25:30');

-- --------------------------------------------------------

--
-- Table structure for table `vacations`
--

CREATE TABLE `vacations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('ANNUAL','SICK','EMERGENCY') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_requested` decimal(4,1) NOT NULL,
  `replace_rest_date` date DEFAULT NULL,
  `replace_rest_day_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('PENDING','APPROVED','REJECTED','FORCED') NOT NULL DEFAULT 'PENDING',
  `team_leader_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vacation_balances`
--

CREATE TABLE `vacation_balances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `accrued_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `used_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `pending_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `remaining_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `last_updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vacation_credit_logs`
--

CREATE TABLE `vacation_credit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vacation_balance_id` bigint(20) UNSIGNED NOT NULL,
  `added_by` bigint(20) UNSIGNED NOT NULL,
  `amount_days` decimal(5,1) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `advances`
--
ALTER TABLE `advances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_adv_user_status` (`user_id`,`status`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_att_user_date_shift` (`user_id`,`date`,`shift_type`),
  ADD KEY `attendance_assigned_by_tl_id_foreign` (`assigned_by_tl_id`),
  ADD KEY `idx_att_date` (`date`),
  ADD KEY `idx_att_user_month` (`user_id`,`date`),
  ADD KEY `idx_att_status` (`status`);

--
-- Indexes for table `attendance_photos`
--
ALTER TABLE `attendance_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_att_photos_att_kind` (`attendance_id`,`kind`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contracts_document_id_foreign` (`document_id`),
  ADD KEY `contracts_user_id_is_active_index` (`user_id`,`is_active`),
  ADD KEY `contracts_contract_end_index` (`contract_end`);

--
-- Indexes for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `emergency_contacts_user_id_index` (`user_id`);

--
-- Indexes for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_documents_user_id_type_index` (`user_id`,`type`);

--
-- Indexes for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_profiles_user_id_unique` (`user_id`),
  ADD UNIQUE KEY `employee_profiles_employee_code_unique` (`employee_code`);

--
-- Indexes for table `employee_qualifications`
--
ALTER TABLE `employee_qualifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_qualifications_user_id_index` (`user_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_eval_user_month` (`user_id`,`month`,`year`),
  ADD KEY `evaluations_team_leader_id_foreign` (`team_leader_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Indexes for table `official_holidays`
--
ALTER TABLE `official_holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `official_holidays_date_unique` (`date`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payroll_adjustments_created_by_foreign` (`created_by`),
  ADD KEY `idx_adj_payroll` (`payroll_record_id`);

--
-- Indexes for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pay_user_month` (`user_id`,`month`,`year`),
  ADD KEY `payroll_records_created_by_foreign` (`created_by`),
  ADD KEY `idx_pay_status` (`status`);

--
-- Indexes for table `penalty_rules`
--
ALTER TABLE `penalty_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rule_type_currency` (`type`,`currency`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_perm_user_date` (`user_id`,`request_date`),
  ADD KEY `idx_perm_tl_status` (`team_leader_id`,`status`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `rest_days`
--
ALTER TABLE `rest_days`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rest_days_user_id_date_unique` (`user_id`,`date`),
  ADD KEY `rest_days_created_by_foreign` (`created_by`),
  ADD KEY `rest_days_user_id_date_index` (`user_id`,`date`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_key_unique` (`key`);

--
-- Indexes for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sa_user_date` (`user_id`,`date`),
  ADD KEY `idx_sa_tl` (`assigned_by_tl_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_role_index` (`role`),
  ADD KEY `users_team_leader_id_index` (`team_leader_id`),
  ADD KEY `users_status_index` (`status`);

--
-- Indexes for table `vacations`
--
ALTER TABLE `vacations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vacations_team_leader_id_foreign` (`team_leader_id`),
  ADD KEY `idx_vac_user_status` (`user_id`,`status`),
  ADD KEY `idx_vac_dates` (`start_date`,`end_date`),
  ADD KEY `vacations_replace_rest_day_id_foreign` (`replace_rest_day_id`);

--
-- Indexes for table `vacation_balances`
--
ALTER TABLE `vacation_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vb_user_year` (`user_id`,`year`),
  ADD KEY `vacation_balances_last_updated_by_foreign` (`last_updated_by`);

--
-- Indexes for table `vacation_credit_logs`
--
ALTER TABLE `vacation_credit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vacation_credit_logs_added_by_foreign` (`added_by`),
  ADD KEY `idx_vcl_balance` (`vacation_balance_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `advances`
--
ALTER TABLE `advances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `attendance_photos`
--
ALTER TABLE `attendance_photos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `employee_qualifications`
--
ALTER TABLE `employee_qualifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `official_holidays`
--
ALTER TABLE `official_holidays`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_records`
--
ALTER TABLE `payroll_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penalty_rules`
--
ALTER TABLE `penalty_rules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rest_days`
--
ALTER TABLE `rest_days`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `vacations`
--
ALTER TABLE `vacations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vacation_balances`
--
ALTER TABLE `vacation_balances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vacation_credit_logs`
--
ALTER TABLE `vacation_credit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `advances`
--
ALTER TABLE `advances`
  ADD CONSTRAINT `advances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_assigned_by_tl_id_foreign` FOREIGN KEY (`assigned_by_tl_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attendance_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_photos`
--
ALTER TABLE `attendance_photos`
  ADD CONSTRAINT `attendance_photos_attendance_id_foreign` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_document_id_foreign` FOREIGN KEY (`document_id`) REFERENCES `employee_documents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contracts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  ADD CONSTRAINT `emergency_contacts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD CONSTRAINT `employee_documents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD CONSTRAINT `employee_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_qualifications`
--
ALTER TABLE `employee_qualifications`
  ADD CONSTRAINT `employee_qualifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_team_leader_id_foreign` FOREIGN KEY (`team_leader_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  ADD CONSTRAINT `payroll_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_adjustments_payroll_record_id_foreign` FOREIGN KEY (`payroll_record_id`) REFERENCES `payroll_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD CONSTRAINT `payroll_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_records_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_team_leader_id_foreign` FOREIGN KEY (`team_leader_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rest_days`
--
ALTER TABLE `rest_days`
  ADD CONSTRAINT `rest_days_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rest_days_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD CONSTRAINT `shift_assignments_assigned_by_tl_id_foreign` FOREIGN KEY (`assigned_by_tl_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_team_leader_id_foreign` FOREIGN KEY (`team_leader_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vacations`
--
ALTER TABLE `vacations`
  ADD CONSTRAINT `vacations_replace_rest_day_id_foreign` FOREIGN KEY (`replace_rest_day_id`) REFERENCES `rest_days` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vacations_team_leader_id_foreign` FOREIGN KEY (`team_leader_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vacations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vacation_balances`
--
ALTER TABLE `vacation_balances`
  ADD CONSTRAINT `vacation_balances_last_updated_by_foreign` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vacation_balances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vacation_credit_logs`
--
ALTER TABLE `vacation_credit_logs`
  ADD CONSTRAINT `vacation_credit_logs_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vacation_credit_logs_vacation_balance_id_foreign` FOREIGN KEY (`vacation_balance_id`) REFERENCES `vacation_balances` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
