-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: tespgpos_test_snapshot
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounts_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts`
--

LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
INSERT INTO `accounts` VALUES (1,'1101','Kas','asset',1,'2026-02-20 17:14:59','2026-02-20 17:14:59'),(2,'1102','Piutang Usaha','asset',1,'2026-02-20 17:14:59','2026-02-20 17:14:59'),(3,'1201','Persediaan','asset',1,'2026-02-20 17:14:59','2026-02-20 17:14:59'),(4,'2101','Hutang Supplier','liability',1,'2026-02-20 17:14:59','2026-02-20 17:14:59'),(5,'4101','Penjualan','revenue',1,'2026-02-20 17:14:59','2026-02-20 17:14:59'),(6,'5101','Retur Penjualan','expense',1,'2026-02-20 17:14:59','2026-02-20 17:14:59'),(7,'5102','Biaya Operasional Pengiriman','expense',1,'2026-02-21 20:10:14','2026-02-21 20:10:14');
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=158 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_settings`
--

LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
INSERT INTO `app_settings` VALUES (1,'product_unit_options','exp|Exemplar','2026-02-21 20:04:32','2026-03-12 10:48:29'),(2,'product_default_unit','exp','2026-02-21 20:04:32','2026-03-12 10:48:29'),(3,'outgoing_unit_options','exp|Exemplar,roll|roll,rim|rim,kaleng20kg|kaleng 20kg','2026-02-21 20:04:32','2026-03-12 10:48:29'),(4,'semester_period_options','S2-2425,S1-2526,S2-2526','2026-02-21 20:04:33','2026-03-12 10:48:29'),(5,'semester_active_periods','S1-2526,S2-2526','2026-02-21 20:04:33','2026-03-12 10:48:29'),(6,'company_name','CV. MITRA SEJATI BERKAH','2026-02-21 20:04:33','2026-03-12 10:48:29'),(7,'company_address','jl puter utara no 23','2026-02-21 20:04:33','2026-03-12 10:48:29'),(8,'company_phone','081321444712','2026-02-21 20:04:33','2026-03-12 10:48:29'),(9,'company_email','mochagsr@gmail.com','2026-02-21 20:04:33','2026-03-12 10:48:29'),(10,'company_notes','','2026-02-21 20:04:33','2026-03-12 10:48:29'),(11,'company_invoice_notes','rek bca : 6546546546524\r\nrek mandiri : 654658532187','2026-02-21 20:04:33','2026-03-12 10:48:29'),(12,'company_billing_note','','2026-02-21 20:04:33','2026-03-12 10:48:29'),(13,'company_transfer_accounts','','2026-02-21 20:04:33','2026-03-12 10:48:29'),(14,'report_header_text','','2026-02-21 20:04:33','2026-03-12 10:48:29'),(15,'report_footer_text','','2026-02-21 20:04:33','2026-03-07 19:13:38'),(34,'company_logo_path','company/tZa7q0v1KOqw9uqBfovUj8NO2189mcxDzLFOESg6.png','2026-02-28 19:05:47','2026-02-28 19:05:47'),(62,'print_workflow_mode','browser','2026-03-07 19:13:22','2026-03-12 10:48:29'),(63,'print_paper_preset','auto','2026-03-07 19:13:22','2026-03-12 10:48:29'),(64,'print_small_rows_threshold','35','2026-03-07 19:13:22','2026-03-12 10:48:29'),(151,'closed_semester_periods','S2-2425','2026-03-11 16:03:37','2026-03-11 16:03:37'),(152,'closed_semester_period_metadata','{\"S2-2425\":{\"closed_at\":\"2026-03-11 23:03:37\"}}','2026-03-11 16:03:37','2026-03-11 16:03:37'),(157,'semester_period_metadata','{\"S2-2425\":{\"created_at\":\"2026-03-12 17:48:29\"},\"S1-2526\":{\"created_at\":\"2026-03-12 17:48:29\"},\"S2-2526\":{\"created_at\":\"2026-03-12 17:48:29\"}}','2026-03-12 10:48:29','2026-03-12 10:48:29');
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_requests`
--

DROP TABLE IF EXISTS `approval_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `subject_id` bigint unsigned DEFAULT NULL,
  `subject_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `requested_by_user_id` bigint unsigned NOT NULL,
  `approved_by_user_id` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `approval_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approval_status_created_idx` (`status`,`created_at`),
  KEY `approval_module_action_idx` (`module`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_requests`
--

LOCK TABLES `approval_requests` WRITE;
/*!40000 ALTER TABLE `approval_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `approval_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `before_data` json DEFAULT NULL,
  `after_data` json DEFAULT NULL,
  `meta_data` json DEFAULT NULL,
  `request_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_created_at_idx` (`created_at`),
  KEY `audit_logs_action_created_at_idx` (`action`,`created_at`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `audit_logs_request_id_idx` (`request_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'3b52f445-be6a-4558-b3dd-6374a7f69f32','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 18:31:42','2026-02-21 18:31:42'),(2,1,'created','App\\Models\\Product',1,'Product \'matematika 1 ed 6 smt 2 2526\' created with code \'mt1e6s2\'',NULL,NULL,NULL,'23a59f2d-3117-4e83-b02f-511e15153b36',NULL,NULL,'2026-02-21 19:53:16','2026-02-21 19:53:16'),(3,1,'master.product.create','App\\Models\\Product',1,'Product created: mt1e6s2',NULL,NULL,NULL,'23a59f2d-3117-4e83-b02f-511e15153b36','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 19:53:16','2026-02-21 19:53:16'),(4,1,'created','App\\Models\\Product',2,'Product \'bhs indonesia 8 ed7 smt1 2526\' created with code \'bh8e7s1\'',NULL,NULL,NULL,'7a3fa8d9-079e-4007-9eff-6902bb6de878',NULL,NULL,'2026-02-21 19:57:02','2026-02-21 19:57:02'),(5,1,'master.product.create','App\\Models\\Product',2,'Product created: bh8e7s1',NULL,NULL,NULL,'7a3fa8d9-079e-4007-9eff-6902bb6de878','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 19:57:02','2026-02-21 19:57:02'),(6,1,'created','App\\Models\\Customer',1,'Customer \'difa pustaka\' created with code \'CUS-20260222-6607\'',NULL,NULL,NULL,'91b1c272-ae6e-4d0c-870a-7149ca1d60af',NULL,NULL,'2026-02-21 19:58:21','2026-02-21 19:58:21'),(7,1,'created','App\\Models\\Customer',2,'Customer \'eko\' created with code \'CUS-20260222-0354\'',NULL,NULL,NULL,'e375c1b4-e0c0-4dec-a58f-e009901b6ac9',NULL,NULL,'2026-02-21 19:58:55','2026-02-21 19:58:55'),(8,1,'master.supplier.create','App\\Models\\Supplier',1,'Supplier created: cv cemani cato tinta',NULL,NULL,NULL,'7d2821a7-f56e-4efb-8912-ee3cb55de688','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 19:59:44','2026-02-21 19:59:44'),(9,1,'financial.created','App\\Models\\OutgoingTransaction',1,'OutgoingTransaction created',NULL,'{\"id\": 1, \"notes\": null, \"total\": 540000, \"created_at\": \"2026-02-22 03:05:39\", \"updated_at\": \"2026-02-22 03:05:39\", \"note_number\": \"n35411111\", \"supplier_id\": 1, \"semester_period\": \"S2-2526\", \"transaction_date\": \"2026-02-22 00:00:00\", \"created_by_user_id\": 1, \"transaction_number\": \"TRXK-22022026-0001\", \"supplier_invoice_photo_path\": null}',NULL,'d3fbe9bd-33a0-40c8-a8b4-2441acfb8523',NULL,NULL,'2026-02-21 20:05:39','2026-02-21 20:05:39'),(10,1,'financial.created','App\\Models\\SupplierLedger',1,'SupplierLedger created',NULL,'{\"id\": 1, \"debit\": 540000, \"credit\": 0, \"created_at\": \"2026-02-22 03:05:39\", \"entry_date\": \"2026-02-22 00:00:00\", \"updated_at\": \"2026-02-22 03:05:39\", \"description\": \"Transaksi keluar TRXK-22022026-0001\", \"period_code\": \"S2-2526\", \"supplier_id\": 1, \"balance_after\": 540000, \"supplier_payment_id\": null, \"outgoing_transaction_id\": 1}',NULL,'d3fbe9bd-33a0-40c8-a8b4-2441acfb8523',NULL,NULL,'2026-02-21 20:05:39','2026-02-21 20:05:39'),(11,1,'supplier.payable.debit.create','App\\Models\\OutgoingTransaction',1,'Transaksi keluar dibuat: TRXK-22022026-0001','{\"outstanding_payable\": 0}','{\"outstanding_payable\": 540000}','{\"supplier_id\": 1}','d3fbe9bd-33a0-40c8-a8b4-2441acfb8523','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 20:05:39','2026-02-21 20:05:39'),(12,1,'financial.created','App\\Models\\OutgoingTransaction',2,'OutgoingTransaction created',NULL,'{\"id\": 2, \"notes\": null, \"total\": 270000, \"created_at\": \"2026-02-22 04:20:30\", \"updated_at\": \"2026-02-22 04:20:30\", \"note_number\": null, \"supplier_id\": 1, \"semester_period\": \"S2-2526\", \"transaction_date\": \"2026-02-22 00:00:00\", \"created_by_user_id\": 1, \"transaction_number\": \"TRXK-22022026-0002\", \"supplier_invoice_photo_path\": null}',NULL,'1f01769b-cb8d-42d8-89eb-e994557c67ef',NULL,NULL,'2026-02-21 21:20:30','2026-02-21 21:20:30'),(13,1,'financial.created','App\\Models\\SupplierLedger',2,'SupplierLedger created',NULL,'{\"id\": 2, \"debit\": 270000, \"credit\": 0, \"created_at\": \"2026-02-22 04:20:30\", \"entry_date\": \"2026-02-22 00:00:00\", \"updated_at\": \"2026-02-22 04:20:30\", \"description\": \"Transaksi keluar TRXK-22022026-0002\", \"period_code\": \"S2-2526\", \"supplier_id\": 1, \"balance_after\": 810000, \"supplier_payment_id\": null, \"outgoing_transaction_id\": 2}',NULL,'1f01769b-cb8d-42d8-89eb-e994557c67ef',NULL,NULL,'2026-02-21 21:20:30','2026-02-21 21:20:30'),(14,1,'supplier.payable.debit.create','App\\Models\\OutgoingTransaction',2,'Transaksi keluar dibuat: TRXK-22022026-0002','{\"outstanding_payable\": 540000}','{\"outstanding_payable\": 810000}','{\"supplier_id\": 1}','1f01769b-cb8d-42d8-89eb-e994557c67ef','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 21:20:30','2026-02-21 21:20:30'),(15,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'656db89b-abc2-4d93-86db-b28ef2a63576','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 08:23:52','2026-02-22 08:23:52'),(16,1,'created','App\\Models\\Product',3,'Product \'tinta bw merk kuda\' created with code \'tn\'',NULL,NULL,NULL,'a64b7338-3482-4e56-bf71-8ebe6d664dbf',NULL,NULL,'2026-02-22 09:10:32','2026-02-22 09:10:32'),(17,1,'updated','App\\Models\\Product',3,'Updated: stock',NULL,NULL,NULL,'a64b7338-3482-4e56-bf71-8ebe6d664dbf',NULL,NULL,'2026-02-22 09:10:32','2026-02-22 09:10:32'),(18,1,'supplier.stock.manual_adjust','App\\Models\\Product',3,'Manual stock adjusted via supplier stock card: tinta bw merk kuda (0 -> 35)','{\"stock\": 0}','{\"stock\": 35}',NULL,'a64b7338-3482-4e56-bf71-8ebe6d664dbf','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 09:10:32','2026-02-22 09:10:32'),(19,1,'created','App\\Models\\Product',4,'Product \'tinta bw web\' created with code \'tn01\'',NULL,NULL,NULL,'00b4a1fa-9919-4cac-b30c-bc912d4904a2',NULL,NULL,'2026-02-22 09:10:38','2026-02-22 09:10:38'),(20,1,'financial.created','App\\Models\\SupplierPayment',1,'SupplierPayment created',NULL,'{\"id\": 1, \"notes\": null, \"amount\": 350000, \"created_at\": \"2026-02-22 16:11:05\", \"updated_at\": \"2026-02-22 16:11:05\", \"supplier_id\": 1, \"payment_date\": \"2026-02-22 00:00:00\", \"proof_number\": null, \"payment_number\": \"KWTS-22022026-0001\", \"user_signature\": \"Admin PgPOS\", \"amount_in_words\": \"Tiga ratus lima puluh  ribu rupiah\", \"created_by_user_id\": 1, \"supplier_signature\": null, \"payment_proof_photo_path\": null}',NULL,'ed74c74f-08c5-4f2f-8a17-5087f104486e',NULL,NULL,'2026-02-22 09:11:05','2026-02-22 09:11:05'),(21,1,'financial.created','App\\Models\\SupplierLedger',3,'SupplierLedger created',NULL,'{\"id\": 3, \"debit\": 0, \"credit\": 350000, \"created_at\": \"2026-02-22 16:11:05\", \"entry_date\": \"2026-02-22 00:00:00\", \"updated_at\": \"2026-02-22 16:11:05\", \"description\": \"Pembayaran hutang supplier KWTS-22022026-0001\", \"period_code\": \"S2-2526\", \"supplier_id\": 1, \"balance_after\": 460000, \"supplier_payment_id\": 1, \"outgoing_transaction_id\": null}',NULL,'ed74c74f-08c5-4f2f-8a17-5087f104486e',NULL,NULL,'2026-02-22 09:11:05','2026-02-22 09:11:05'),(22,1,'supplier.payment.create','App\\Models\\SupplierPayment',1,'Pembayaran hutang supplier berhasil disimpan.','{\"outstanding_payable\": 810000}','{\"outstanding_payable\": 460000}','{\"supplier_id\": 1, \"payment_number\": \"KWTS-22022026-0001\"}','ed74c74f-08c5-4f2f-8a17-5087f104486e','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 09:11:05','2026-02-22 09:11:05'),(23,1,'updated','App\\Models\\Product',3,'Updated: stock, updated_at',NULL,NULL,NULL,'81d1b4b1-ce57-4ab0-b114-63ccd5ba1d7d',NULL,NULL,'2026-02-22 09:20:22','2026-02-22 09:20:22'),(24,1,'supplier.stock.manual_adjust','App\\Models\\Product',3,'Manual stock adjusted via supplier stock card: tinta bw merk kuda (35 -> 50)','{\"stock\": 35}','{\"stock\": 50}',NULL,'81d1b4b1-ce57-4ab0-b114-63ccd5ba1d7d','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 09:20:22','2026-02-22 09:20:22'),(25,1,'supplier.stock.manual_adjust','App\\Models\\Product',4,'Manual stock adjusted via supplier stock card: tinta bw web (10 -> 0)','{\"stock\": 0, \"supplier_stock\": 10}','{\"stock\": 0, \"supplier_stock\": 0}',NULL,'22103a9b-ef3b-49bf-8d08-c480f24e85b6','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 09:22:52','2026-02-22 09:22:52'),(26,1,'updated','App\\Models\\Product',2,'Updated: stock, updated_at',NULL,NULL,NULL,'e0b9f654-88cc-4cb0-abb5-1dec3815c836',NULL,NULL,'2026-02-22 09:28:11','2026-02-22 09:28:11'),(27,1,'master.product.quick_stock_update','App\\Models\\Product',2,'Quick stock update: bh8e7s1 (1000 -> 950)','{\"stock\": 1000}','{\"stock\": 950}',NULL,'e0b9f654-88cc-4cb0-abb5-1dec3815c836','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 09:28:11','2026-02-22 09:28:11'),(28,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'f67135f9-afb8-4b2e-98b9-0f00b1bc300a','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 11:52:35','2026-02-22 11:52:35'),(29,1,'master.supplier.create','App\\Models\\Supplier',2,'Supplier created: pt rumah cetak kita',NULL,NULL,NULL,'32d4cfe6-e52e-46a9-9f0b-9241082d6b36','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 12:06:28','2026-02-22 12:06:28'),(30,1,'updated','App\\Models\\Product',4,'Updated: stock, updated_at',NULL,NULL,NULL,'123cbd1f-2451-495c-bb82-793f7e75c2f7',NULL,NULL,'2026-02-22 12:15:15','2026-02-22 12:15:15'),(31,1,'supplier.stock.manual_adjust','App\\Models\\Product',4,'Manual stock adjusted via supplier stock card: tinta bw web (0 -> 2)','{\"stock\": 0, \"supplier_stock\": 0}','{\"stock\": 2, \"supplier_stock\": 2}',NULL,'123cbd1f-2451-495c-bb82-793f7e75c2f7','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 12:15:15','2026-02-22 12:15:15'),(32,1,'school.bulk.create','App\\Models\\SchoolBulkTransaction',1,'Transaksi sebar sekolah BLK-22022026-0001 dibuat.',NULL,NULL,NULL,'1f1e53f6-9f7f-4558-b1e7-cfa041d33e98','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 12:29:56','2026-02-22 12:29:56'),(33,1,'created','App\\Models\\SalesInvoice',1,'Invoice \'INV-22022026-0001\' created with total 12000',NULL,NULL,NULL,'97fb9e38-3d0d-4749-8b9f-d7ffce60f5e7',NULL,NULL,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(34,1,'updated','App\\Models\\Product',2,'Updated: stock',NULL,NULL,NULL,'97fb9e38-3d0d-4749-8b9f-d7ffce60f5e7',NULL,NULL,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(35,1,'updated','App\\Models\\Customer',1,'Updated: outstanding_receivable, updated_at',NULL,NULL,NULL,'97fb9e38-3d0d-4749-8b9f-d7ffce60f5e7',NULL,NULL,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(36,1,'financial.created','App\\Models\\ReceivableLedger',1,'ReceivableLedger created',NULL,'{\"id\": 1, \"debit\": 12000, \"credit\": 0, \"created_at\": \"2026-02-22 19:29:59\", \"entry_date\": \"2026-02-22 00:00:00\", \"updated_at\": \"2026-02-22 19:29:59\", \"customer_id\": 1, \"description\": \"Invoice INV-22022026-0001\", \"period_code\": \"S2-2526\", \"balance_after\": 12000, \"sales_invoice_id\": 1}',NULL,'97fb9e38-3d0d-4749-8b9f-d7ffce60f5e7',NULL,NULL,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(37,1,'created','App\\Models\\SalesInvoice',2,'Invoice \'INV-22022026-0002\' created with total 12000',NULL,NULL,NULL,'97fb9e38-3d0d-4749-8b9f-d7ffce60f5e7',NULL,NULL,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(38,1,'updated','App\\Models\\Product',2,'Updated: stock',NULL,NULL,NULL,'97fb9e38-3d0d-4749-8b9f-d7ffce60f5e7',NULL,NULL,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(39,1,'updated','App\\Models\\Customer',1,'Updated: outstanding_receivable',NULL,NULL,NULL,'97fb9e38-3d0d-4749-8b9f-d7ffce60f5e7',NULL,NULL,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(40,1,'financial.created','App\\Models\\ReceivableLedger',2,'ReceivableLedger created',NULL,'{\"id\": 2, \"debit\": 12000, \"credit\": 0, \"created_at\": \"2026-02-22 19:29:59\", \"entry_date\": \"2026-02-22 00:00:00\", \"updated_at\": \"2026-02-22 19:29:59\", \"customer_id\": 1, \"description\": \"Invoice INV-22022026-0002\", \"period_code\": \"S2-2526\", \"balance_after\": 24000, \"sales_invoice_id\": 2}',NULL,'97fb9e38-3d0d-4749-8b9f-d7ffce60f5e7',NULL,NULL,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(41,1,'school.bulk.generate_invoices','App\\Models\\SchoolBulkTransaction',1,'Generate faktur dari transaksi sebar BLK-22022026-0001. Dibuat 2, terlewati 0.',NULL,NULL,NULL,'97fb9e38-3d0d-4749-8b9f-d7ffce60f5e7','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 12:29:59','2026-02-22 12:29:59'),(42,1,'school.bulk.generate_invoices','App\\Models\\SchoolBulkTransaction',1,'Generate faktur dari transaksi sebar BLK-22022026-0001. Dibuat 0, terlewati 2.',NULL,NULL,NULL,'bf42985a-72fd-4480-bb49-beffd5167221','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-22 12:41:54','2026-02-22 12:41:54'),(43,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'03b20378-04c0-47cc-9e7a-dd09629c2472','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 17:34:48','2026-02-23 17:34:48'),(44,1,'updated','App\\Models\\Product',3,'Updated: stock, updated_at',NULL,NULL,NULL,'236cc966-15d8-4b1f-bccb-94214fb83243',NULL,NULL,'2026-02-23 18:51:20','2026-02-23 18:51:20'),(45,1,'supplier.stock.manual_adjust','App\\Models\\Product',3,'Manual stock adjusted via supplier stock card: tinta bw merk kuda (35 -> 38)','{\"stock\": 50, \"supplier_stock\": 35}','{\"stock\": 53, \"supplier_stock\": 38}',NULL,'236cc966-15d8-4b1f-bccb-94214fb83243','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 18:51:20','2026-02-23 18:51:20'),(46,1,'updated','App\\Models\\Product',3,'Updated: stock, updated_at',NULL,NULL,NULL,'7cd5c2c4-f030-4221-8936-35dc545d8064',NULL,NULL,'2026-02-23 18:51:24','2026-02-23 18:51:24'),(47,1,'supplier.stock.manual_adjust','App\\Models\\Product',3,'Manual stock adjusted via supplier stock card: tinta bw merk kuda (38 -> 3)','{\"stock\": 53, \"supplier_stock\": 38}','{\"stock\": 18, \"supplier_stock\": 3}',NULL,'7cd5c2c4-f030-4221-8936-35dc545d8064','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 18:51:24','2026-02-23 18:51:24'),(48,1,'updated','App\\Models\\Product',3,'Updated: stock, updated_at',NULL,NULL,NULL,'679d4b99-de2a-4f50-a7d3-995be195f530',NULL,NULL,'2026-02-23 18:51:25','2026-02-23 18:51:25'),(49,1,'supplier.stock.manual_adjust','App\\Models\\Product',3,'Manual stock adjusted via supplier stock card: tinta bw merk kuda (3 -> 31)','{\"stock\": 18, \"supplier_stock\": 3}','{\"stock\": 46, \"supplier_stock\": 31}',NULL,'679d4b99-de2a-4f50-a7d3-995be195f530','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 18:51:25','2026-02-23 18:51:25'),(50,1,'updated','App\\Models\\Product',2,'Updated: stock, updated_at',NULL,NULL,NULL,'87d1c380-e24b-42a8-8e41-7f304ff7ffea',NULL,NULL,'2026-02-23 18:51:35','2026-02-23 18:51:35'),(51,1,'master.product.quick_stock_update','App\\Models\\Product',2,'Quick stock update: bh8e7s1 (948 -> 946)','{\"stock\": 948}','{\"stock\": 946}',NULL,'87d1c380-e24b-42a8-8e41-7f304ff7ffea','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 18:51:35','2026-02-23 18:51:35'),(52,1,'updated','App\\Models\\Product',2,'Updated: stock, updated_at',NULL,NULL,NULL,'bdd56be9-84a4-4bc6-ad08-d8d78dd568de',NULL,NULL,'2026-02-23 18:54:23','2026-02-23 18:54:23'),(53,1,'master.product.quick_stock_update','App\\Models\\Product',2,'Quick stock update: bh8e7s1 (946 -> 94)','{\"stock\": 946}','{\"stock\": 94}',NULL,'bdd56be9-84a4-4bc6-ad08-d8d78dd568de','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 18:54:23','2026-02-23 18:54:23'),(54,1,'updated','App\\Models\\Product',2,'Updated: stock, updated_at',NULL,NULL,NULL,'04b4a197-a063-4f0c-ad23-aefbec417f0a',NULL,NULL,'2026-02-23 18:54:28','2026-02-23 18:54:28'),(55,1,'master.product.quick_stock_update','App\\Models\\Product',2,'Quick stock update: bh8e7s1 (94 -> 948)','{\"stock\": 94}','{\"stock\": 948}',NULL,'04b4a197-a063-4f0c-ad23-aefbec417f0a','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 18:54:28','2026-02-23 18:54:28'),(56,1,'updated','App\\Models\\Product',4,'Updated: stock, updated_at',NULL,NULL,NULL,'6d89d295-00b6-446c-9d74-c1b5df060cc2',NULL,NULL,'2026-02-23 18:54:34','2026-02-23 18:54:34'),(57,1,'master.product.quick_stock_update','App\\Models\\Product',4,'Quick stock update: tn01 (2 -> 21)','{\"stock\": 2}','{\"stock\": 21}',NULL,'6d89d295-00b6-446c-9d74-c1b5df060cc2','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 18:54:34','2026-02-23 18:54:34'),(58,1,'updated','App\\Models\\Product',4,'Updated: stock, updated_at',NULL,NULL,NULL,'37498828-9976-4c80-aff0-b8bbe95df11c',NULL,NULL,'2026-02-23 18:54:45','2026-02-23 18:54:45'),(59,1,'master.product.quick_stock_update','App\\Models\\Product',4,'Quick stock update: tn01 (21 -> 2)','{\"stock\": 21}','{\"stock\": 2}',NULL,'37498828-9976-4c80-aff0-b8bbe95df11c','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 18:54:45','2026-02-23 18:54:45'),(60,1,'updated','App\\Models\\Product',4,'Updated: stock, updated_at',NULL,NULL,NULL,'d2a4f784-478a-488d-bb9c-7705a732a55d',NULL,NULL,'2026-02-23 18:54:50','2026-02-23 18:54:50'),(61,1,'master.product.quick_stock_update','App\\Models\\Product',4,'Quick stock update: tn01 (2 -> 25)','{\"stock\": 2}','{\"stock\": 25}',NULL,'d2a4f784-478a-488d-bb9c-7705a732a55d','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 18:54:50','2026-02-23 18:54:50'),(62,1,'updated','App\\Models\\Product',2,'Updated: stock, updated_at',NULL,NULL,NULL,'474fd9dc-688e-4790-9a5f-17b0155a69c4',NULL,NULL,'2026-02-23 18:59:54','2026-02-23 18:59:54'),(63,1,'master.product.quick_stock_update','App\\Models\\Product',2,'Quick stock update: bh8e7s1 (948 -> 949)','{\"stock\": 948}','{\"stock\": 949}',NULL,'474fd9dc-688e-4790-9a5f-17b0155a69c4','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 18:59:54','2026-02-23 18:59:54'),(64,1,'updated','App\\Models\\Product',1,'Updated: stock, updated_at',NULL,NULL,NULL,'6bd99bf8-2c7b-4c87-95fb-c3a6cf70fa8a',NULL,NULL,'2026-02-23 19:26:02','2026-02-23 19:26:02'),(65,1,'master.product.quick_stock_update','App\\Models\\Product',1,'Quick stock update: mt1e6s2 (1000 -> 985)','{\"stock\": 1000}','{\"stock\": 985}',NULL,'6bd99bf8-2c7b-4c87-95fb-c3a6cf70fa8a','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 19:26:02','2026-02-23 19:26:02'),(66,1,'updated','App\\Models\\Product',1,'Updated: stock, updated_at',NULL,NULL,NULL,'e2e4cf8e-f992-46ab-b9a0-e6ad5659cdac',NULL,NULL,'2026-02-23 19:32:25','2026-02-23 19:32:25'),(67,1,'master.product.quick_stock_update','App\\Models\\Product',1,'Quick stock update: mt1e6s2 (985 -> 988)','{\"stock\": 985}','{\"stock\": 988}',NULL,'e2e4cf8e-f992-46ab-b9a0-e6ad5659cdac','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 19:32:25','2026-02-23 19:32:25'),(68,1,'updated','App\\Models\\Product',1,'Updated: price_general, updated_at',NULL,NULL,NULL,'eb147f66-e86e-4874-b6cd-eaf2359fe50b',NULL,NULL,'2026-02-23 19:32:43','2026-02-23 19:32:43'),(69,1,'master.product.update','App\\Models\\Product',1,'Product updated: mt1e6s2',NULL,NULL,NULL,'eb147f66-e86e-4874-b6cd-eaf2359fe50b','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 19:32:43','2026-02-23 19:32:43'),(70,1,'updated','App\\Models\\Product',1,'Updated: price_general, updated_at',NULL,NULL,NULL,'ff06eace-89e3-4045-9f77-f2749aac4fd4',NULL,NULL,'2026-02-23 19:34:51','2026-02-23 19:34:51'),(71,1,'master.product.update','App\\Models\\Product',1,'Product updated: mt1e6s2',NULL,NULL,NULL,'ff06eace-89e3-4045-9f77-f2749aac4fd4','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-23 19:34:52','2026-02-23 19:34:52'),(72,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'1295b924-fb01-423d-ad6c-4129c366363e','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-24 19:24:18','2026-02-24 19:24:18'),(73,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'fa559b82-54b2-4a50-9b36-5357bb56a1c5','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 04:00:58','2026-02-27 04:00:58'),(74,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'3d0e36f6-7d40-4043-b4b1-7a46164d03d3','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 14:49:19','2026-02-27 14:49:19'),(75,1,'financial.created','App\\Models\\InvoicePayment',1,'InvoicePayment created',NULL,'{\"id\": 1, \"notes\": \"Diskon piutang dari mutasi piutang\", \"amount\": 12000, \"method\": \"discount\", \"created_at\": \"2026-02-27 21:49:34\", \"updated_at\": \"2026-02-27 21:49:34\", \"payment_date\": \"2026-02-27 00:00:00\", \"sales_invoice_id\": 1}',NULL,'586ea842-19ed-4f53-8f1b-97fb7a350735',NULL,NULL,'2026-02-27 14:49:34','2026-02-27 14:49:34'),(76,1,'updated','App\\Models\\SalesInvoice',1,'Updated: total_paid, balance, payment_status, updated_at',NULL,NULL,NULL,'586ea842-19ed-4f53-8f1b-97fb7a350735',NULL,NULL,'2026-02-27 14:49:34','2026-02-27 14:49:34'),(77,1,'updated','App\\Models\\Customer',1,'Updated: outstanding_receivable, updated_at',NULL,NULL,NULL,'586ea842-19ed-4f53-8f1b-97fb7a350735',NULL,NULL,'2026-02-27 14:49:34','2026-02-27 14:49:34'),(78,1,'financial.created','App\\Models\\ReceivableLedger',3,'ReceivableLedger created',NULL,'{\"id\": 3, \"debit\": 0, \"credit\": 12000, \"created_at\": \"2026-02-27 21:49:34\", \"entry_date\": \"2026-02-27 00:00:00\", \"updated_at\": \"2026-02-27 21:49:34\", \"customer_id\": 1, \"description\": \"Diskon piutang untuk INV-22022026-0001\", \"period_code\": \"S2-2526\", \"balance_after\": 12000, \"sales_invoice_id\": 1}',NULL,'586ea842-19ed-4f53-8f1b-97fb7a350735',NULL,NULL,'2026-02-27 14:49:34','2026-02-27 14:49:34'),(79,1,'financial.created','App\\Models\\InvoicePayment',2,'InvoicePayment created',NULL,'{\"id\": 2, \"notes\": \"Diskon piutang dari mutasi piutang\", \"amount\": 12000, \"method\": \"discount\", \"created_at\": \"2026-02-27 21:49:34\", \"updated_at\": \"2026-02-27 21:49:34\", \"payment_date\": \"2026-02-27 00:00:00\", \"sales_invoice_id\": 2}',NULL,'586ea842-19ed-4f53-8f1b-97fb7a350735',NULL,NULL,'2026-02-27 14:49:34','2026-02-27 14:49:34'),(80,1,'updated','App\\Models\\SalesInvoice',2,'Updated: total_paid, balance, payment_status, updated_at',NULL,NULL,NULL,'586ea842-19ed-4f53-8f1b-97fb7a350735',NULL,NULL,'2026-02-27 14:49:34','2026-02-27 14:49:34'),(81,1,'updated','App\\Models\\Customer',1,'Updated: outstanding_receivable',NULL,NULL,NULL,'586ea842-19ed-4f53-8f1b-97fb7a350735',NULL,NULL,'2026-02-27 14:49:34','2026-02-27 14:49:34'),(82,1,'financial.created','App\\Models\\ReceivableLedger',4,'ReceivableLedger created',NULL,'{\"id\": 4, \"debit\": 0, \"credit\": 12000, \"created_at\": \"2026-02-27 21:49:34\", \"entry_date\": \"2026-02-27 00:00:00\", \"updated_at\": \"2026-02-27 21:49:34\", \"customer_id\": 1, \"description\": \"Diskon piutang untuk INV-22022026-0002\", \"period_code\": \"S2-2526\", \"balance_after\": 0, \"sales_invoice_id\": 2}',NULL,'586ea842-19ed-4f53-8f1b-97fb7a350735',NULL,NULL,'2026-02-27 14:49:34','2026-02-27 14:49:34'),(83,1,'created','App\\Models\\Product',5,'Product \'bhs jawa 3 ed 8 2526\' created with code \'bh3e8\'',NULL,NULL,NULL,'354cb1d7-a547-4997-b85b-ee6e155a0d69',NULL,NULL,'2026-02-27 15:24:46','2026-02-27 15:24:46'),(84,1,'master.product.create','App\\Models\\Product',5,'Product created: bh3e8',NULL,NULL,NULL,'354cb1d7-a547-4997-b85b-ee6e155a0d69','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 15:24:46','2026-02-27 15:24:46'),(85,1,'created','App\\Models\\Product',6,'Product \'pendidikan pancasila 4 ed 6 tahun 2526\' created with code \'cpn4e656\'',NULL,NULL,NULL,'2c5439a3-9797-4fce-af69-21077eb03c3e',NULL,NULL,'2026-02-27 16:05:23','2026-02-27 16:05:23'),(86,1,'master.product.create','App\\Models\\Product',6,'Product created: cpn4e656',NULL,NULL,NULL,'2c5439a3-9797-4fce-af69-21077eb03c3e','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 16:05:23','2026-02-27 16:05:23'),(87,1,'created','App\\Models\\Customer',3,'Customer \'angga\' created with code \'CUS-20260227-3150\'',NULL,NULL,NULL,'a38b6a69-5543-4be3-8bbb-b1bceaa541d4',NULL,NULL,'2026-02-27 16:07:29','2026-02-27 16:07:29'),(88,1,'master.supplier.create','App\\Models\\Supplier',3,'Supplier created: cv sinar grafindo',NULL,NULL,NULL,'acc4c55b-cc36-4d99-a1c1-2f7f4c61652a','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 16:08:34','2026-02-27 16:08:34'),(89,1,'financial.created','App\\Models\\OutgoingTransaction',3,'OutgoingTransaction created',NULL,'{\"id\": 3, \"notes\": null, \"total\": 16500, \"created_at\": \"2026-02-27 23:09:40\", \"updated_at\": \"2026-02-27 23:09:40\", \"note_number\": \"n7768990\", \"supplier_id\": 3, \"semester_period\": \"S2-2526\", \"transaction_date\": \"2026-02-27 00:00:00\", \"created_by_user_id\": 1, \"transaction_number\": \"TRXK-27022026-0001\", \"supplier_invoice_photo_path\": null}',NULL,'34ba4f4d-b213-4998-9bfc-46758ab7739b',NULL,NULL,'2026-02-27 16:09:40','2026-02-27 16:09:40'),(90,1,'financial.created','App\\Models\\SupplierLedger',4,'SupplierLedger created',NULL,'{\"id\": 4, \"debit\": 16500, \"credit\": 0, \"created_at\": \"2026-02-27 23:09:40\", \"entry_date\": \"2026-02-27 00:00:00\", \"updated_at\": \"2026-02-27 23:09:40\", \"description\": \"Transaksi keluar TRXK-27022026-0001\", \"period_code\": \"S2-2526\", \"supplier_id\": 3, \"balance_after\": 16500, \"supplier_payment_id\": null, \"outgoing_transaction_id\": 3}',NULL,'34ba4f4d-b213-4998-9bfc-46758ab7739b',NULL,NULL,'2026-02-27 16:09:40','2026-02-27 16:09:40'),(91,1,'supplier.payable.debit.create','App\\Models\\OutgoingTransaction',3,'Surat tanda terima barang dibuat: TRXK-27022026-0001','{\"outstanding_payable\": 0}','{\"outstanding_payable\": 16500}','{\"supplier_id\": 3}','34ba4f4d-b213-4998-9bfc-46758ab7739b','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 16:09:40','2026-02-27 16:09:40'),(92,NULL,'created','App\\Models\\Product',7,'Product \'kertas web 68gr\' created with code \'kr\'',NULL,NULL,NULL,'73a300c9-8b2e-45ae-af61-a554b995b348',NULL,NULL,'2026-02-27 17:00:28','2026-02-27 17:00:28'),(93,NULL,'updated','App\\Models\\Product',7,'Updated: stock',NULL,NULL,NULL,'73a300c9-8b2e-45ae-af61-a554b995b348',NULL,NULL,'2026-02-27 17:00:28','2026-02-27 17:00:28'),(94,1,'order.note.create','App\\Models\\OrderNote',1,'Surat pesanan dibuat: PO-28022026-0001',NULL,NULL,NULL,'8782ce2b-233e-4393-b2ff-a855eaf43dbb','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 18:32:11','2026-02-27 18:32:11'),(95,1,'created','App\\Models\\SalesInvoice',3,'Invoice \'INV-28022026-0001\' created with total 52500',NULL,NULL,NULL,'9c736677-91e4-464d-a31d-ab47d906e514',NULL,NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(96,1,'updated','App\\Models\\Product',2,'Updated: stock',NULL,NULL,NULL,'9c736677-91e4-464d-a31d-ab47d906e514',NULL,NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(97,1,'updated','App\\Models\\Product',5,'Updated: stock',NULL,NULL,NULL,'9c736677-91e4-464d-a31d-ab47d906e514',NULL,NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(98,1,'updated','App\\Models\\Customer',3,'Updated: outstanding_receivable, updated_at',NULL,NULL,NULL,'9c736677-91e4-464d-a31d-ab47d906e514',NULL,NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(99,1,'financial.created','App\\Models\\ReceivableLedger',5,'ReceivableLedger created',NULL,'{\"id\": 5, \"debit\": 52500, \"credit\": 0, \"created_at\": \"2026-02-28 02:26:43\", \"entry_date\": \"2026-02-28 00:00:00\", \"updated_at\": \"2026-02-28 02:26:43\", \"customer_id\": 3, \"description\": \"Invoice INV-28022026-0001\", \"period_code\": \"S2-2526\", \"balance_after\": 52500, \"sales_invoice_id\": 3}',NULL,'9c736677-91e4-464d-a31d-ab47d906e514',NULL,NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(100,1,'financial.created','App\\Models\\InvoicePayment',3,'InvoicePayment created',NULL,'{\"id\": 3, \"notes\": \"Pelunasan penuh saat membuat faktur\", \"amount\": 52500, \"method\": \"cash\", \"created_at\": \"2026-02-28 02:26:43\", \"updated_at\": \"2026-02-28 02:26:43\", \"payment_date\": \"2026-02-28 00:00:00\", \"sales_invoice_id\": 3}',NULL,'9c736677-91e4-464d-a31d-ab47d906e514',NULL,NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(101,1,'updated','App\\Models\\SalesInvoice',3,'Updated: total_paid, balance, payment_status',NULL,NULL,NULL,'9c736677-91e4-464d-a31d-ab47d906e514',NULL,NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(102,1,'updated','App\\Models\\Customer',3,'Updated: outstanding_receivable',NULL,NULL,NULL,'9c736677-91e4-464d-a31d-ab47d906e514',NULL,NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(103,1,'financial.created','App\\Models\\ReceivableLedger',6,'ReceivableLedger created',NULL,'{\"id\": 6, \"debit\": 0, \"credit\": 52500, \"created_at\": \"2026-02-28 02:26:43\", \"entry_date\": \"2026-02-28 00:00:00\", \"updated_at\": \"2026-02-28 02:26:43\", \"customer_id\": 3, \"description\": \"Pembayaran untuk INV-28022026-0001\", \"period_code\": \"S2-2526\", \"balance_after\": 0, \"sales_invoice_id\": 3}',NULL,'9c736677-91e4-464d-a31d-ab47d906e514',NULL,NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(104,1,'sales.invoice.create','App\\Models\\SalesInvoice',3,'Faktur dibuat: INV-28022026-0001',NULL,NULL,NULL,'9c736677-91e4-464d-a31d-ab47d906e514','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 19:26:43','2026-02-27 19:26:43'),(105,1,'updated','App\\Models\\SalesInvoice',3,'Updated: total_paid, balance, payment_status, updated_at',NULL,NULL,NULL,'8a18899e-71db-44a6-93d9-42efff6d75e0',NULL,NULL,'2026-02-27 19:40:03','2026-02-27 19:40:03'),(106,1,'updated','App\\Models\\Customer',3,'Updated: outstanding_receivable, updated_at',NULL,NULL,NULL,'8a18899e-71db-44a6-93d9-42efff6d75e0',NULL,NULL,'2026-02-27 19:40:03','2026-02-27 19:40:03'),(107,1,'financial.created','App\\Models\\ReceivableLedger',7,'ReceivableLedger created',NULL,'{\"id\": 7, \"debit\": 52500, \"credit\": 0, \"created_at\": \"2026-02-28 02:40:03\", \"entry_date\": \"2026-02-28 00:00:00\", \"updated_at\": \"2026-02-28 02:40:03\", \"customer_id\": 3, \"description\": \"[ADMIN EDIT FAKTUR +] Penyesuaian nilai faktur INV-28022026-0001\", \"period_code\": \"S2-2526\", \"balance_after\": 52500, \"sales_invoice_id\": 3}',NULL,'8a18899e-71db-44a6-93d9-42efff6d75e0',NULL,NULL,'2026-02-27 19:40:03','2026-02-27 19:40:03'),(108,1,'sales.invoice.admin_update','App\\Models\\SalesInvoice',3,'Admin mengubah faktur INV-28022026-0001. Sebelum: bhs indonesia 8 ed7 smt1 2526:qty10:price3500 | bhs jawa 3 ed 8 2526:qty5:price3500. Sesudah: bhs indonesia 8 ed7 smt1 2526:qty10:price3500 | bhs jawa 3 ed 8 2526:qty5:price3500',NULL,NULL,NULL,'8a18899e-71db-44a6-93d9-42efff6d75e0','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 19:40:03','2026-02-27 19:40:03'),(109,1,'financial.created','App\\Models\\SalesReturn',1,'SalesReturn created',NULL,'{\"id\": 1, \"total\": 3500, \"reason\": null, \"created_at\": \"2026-02-28 02:41:22\", \"updated_at\": \"2026-02-28 02:41:22\", \"customer_id\": \"3\", \"return_date\": \"2026-02-28 00:00:00\", \"return_number\": \"RTR-28022026-0001\", \"semester_period\": \"S2-2526\"}',NULL,'2f462d0b-1afe-4f5a-a180-61bc67b1c459',NULL,NULL,'2026-02-27 19:41:22','2026-02-27 19:41:22'),(110,1,'updated','App\\Models\\Product',2,'Updated: stock',NULL,NULL,NULL,'2f462d0b-1afe-4f5a-a180-61bc67b1c459',NULL,NULL,'2026-02-27 19:41:22','2026-02-27 19:41:22'),(111,1,'updated','App\\Models\\Customer',3,'Updated: outstanding_receivable, updated_at',NULL,NULL,NULL,'2f462d0b-1afe-4f5a-a180-61bc67b1c459',NULL,NULL,'2026-02-27 19:41:22','2026-02-27 19:41:22'),(112,1,'financial.created','App\\Models\\ReceivableLedger',8,'ReceivableLedger created',NULL,'{\"id\": 8, \"debit\": 0, \"credit\": 3500, \"created_at\": \"2026-02-28 02:41:22\", \"entry_date\": \"2026-02-28 00:00:00\", \"updated_at\": \"2026-02-28 02:41:22\", \"customer_id\": 3, \"description\": \"Retur RTR-28022026-0001\", \"period_code\": \"S2-2526\", \"balance_after\": 49000, \"sales_invoice_id\": null}',NULL,'2f462d0b-1afe-4f5a-a180-61bc67b1c459',NULL,NULL,'2026-02-27 19:41:22','2026-02-27 19:41:22'),(113,1,'updated','App\\Models\\Customer',3,'Updated: outstanding_receivable',NULL,NULL,NULL,'2f462d0b-1afe-4f5a-a180-61bc67b1c459',NULL,NULL,'2026-02-27 19:41:22','2026-02-27 19:41:22'),(114,1,'sales.return.create','App\\Models\\SalesReturn',1,'Retur penjualan dibuat: RTR-28022026-0001',NULL,NULL,NULL,'2f462d0b-1afe-4f5a-a180-61bc67b1c459','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-27 19:41:22','2026-02-27 19:41:22'),(115,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'9460534a-766b-4a79-a5a1-7c10b0c857d8','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 00:07:32','2026-02-28 00:07:32'),(116,1,'financial.created','App\\Models\\SupplierPayment',2,'SupplierPayment created',NULL,'{\"id\": 2, \"notes\": null, \"amount\": 100000, \"created_at\": \"2026-02-28 07:11:49\", \"updated_at\": \"2026-02-28 07:11:49\", \"supplier_id\": 1, \"payment_date\": \"2026-02-28 00:00:00\", \"proof_number\": null, \"payment_number\": \"KWTS-28022026-0001\", \"user_signature\": \"Admin PgPOS\", \"amount_in_words\": \"Seratus  ribu rupiah\", \"created_by_user_id\": 1, \"supplier_signature\": null, \"payment_proof_photo_path\": null}',NULL,'45e0abd3-0a71-4c59-9d77-e0239af9e851',NULL,NULL,'2026-02-28 00:11:49','2026-02-28 00:11:49'),(117,1,'financial.created','App\\Models\\SupplierLedger',5,'SupplierLedger created',NULL,'{\"id\": 5, \"debit\": 0, \"credit\": 100000, \"created_at\": \"2026-02-28 07:11:49\", \"entry_date\": \"2026-02-28 00:00:00\", \"updated_at\": \"2026-02-28 07:11:49\", \"description\": \"Pembayaran hutang supplier KWTS-28022026-0001\", \"period_code\": \"S2-2526\", \"supplier_id\": 1, \"balance_after\": 360000, \"supplier_payment_id\": 2, \"outgoing_transaction_id\": null}',NULL,'45e0abd3-0a71-4c59-9d77-e0239af9e851',NULL,NULL,'2026-02-28 00:11:49','2026-02-28 00:11:49'),(118,1,'supplier.payment.create','App\\Models\\SupplierPayment',2,'Pembayaran hutang supplier berhasil disimpan.','{\"outstanding_payable\": 460000}','{\"outstanding_payable\": 360000}','{\"supplier_id\": 1, \"payment_number\": \"KWTS-28022026-0001\"}','45e0abd3-0a71-4c59-9d77-e0239af9e851','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 00:11:49','2026-02-28 00:11:49'),(119,1,'delivery.trip.create','App\\Models\\DeliveryTrip',1,'Catatan perjalanan dibuat: TRP-28022026-0001',NULL,'{\"total_cost\": 670000, \"trip_number\": \"TRP-28022026-0001\", \"assistant_name\": \"ego\"}',NULL,'acd64590-cf99-4b92-9675-c99d1485d668','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 00:17:56','2026-02-28 00:17:56'),(120,1,'delivery.trip.update','App\\Models\\DeliveryTrip',1,'Catatan perjalanan diperbarui: TRP-28022026-0001','{\"fuel_cost\": 500000, \"meal_cost\": 100000, \"toll_cost\": 35000, \"trip_date\": \"2026-02-28\", \"other_cost\": 35000, \"total_cost\": 670000, \"driver_name\": \"peendik\", \"member_count\": 0, \"vehicle_plate\": \"n 98996 kmlj\", \"assistant_name\": \"ego\"}','{\"fuel_cost\": 500000, \"meal_cost\": 100000, \"toll_cost\": 35000, \"trip_date\": \"2026-02-28\", \"other_cost\": 35000, \"total_cost\": 670000, \"driver_name\": \"peendik\", \"member_count\": 0, \"vehicle_plate\": \"n 98996 kmlj\", \"assistant_name\": \"ego\"}',NULL,'40030804-3fdd-4996-aab2-851f2d9a136e','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 00:18:11','2026-02-28 00:18:11'),(121,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'0c59122d-9e94-4d2e-8bc9-54075bab6466','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 05:34:22','2026-02-28 05:34:22'),(122,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'fa015d2d-d6bf-4ef8-b2c5-daaacf92a068','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 19:02:54','2026-02-28 19:02:54'),(123,1,'delivery.note.create','App\\Models\\DeliveryNote',1,'Surat jalan dibuat: SJ-01032026-0001',NULL,NULL,NULL,'59b0eafc-d204-4bcd-adf8-3ff9f3c9af28','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 19:03:36','2026-02-28 19:03:36'),(124,1,'updated','App\\Models\\Product',2,'Data diperbarui: stock',NULL,NULL,NULL,'23ee902e-5eeb-49df-908b-bb041843bbb0',NULL,NULL,'2026-02-28 20:50:33','2026-02-28 20:50:33'),(125,1,'delivery.note.admin_update','App\\Models\\DeliveryNote',1,'Admin mengubah surat jalan: SJ-01032026-0001',NULL,NULL,NULL,'23ee902e-5eeb-49df-908b-bb041843bbb0','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-28 20:50:33','2026-02-28 20:50:33'),(126,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'345099f8-fdc3-4085-a733-d150c38661af','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-01 15:11:50','2026-03-01 15:11:50'),(127,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'93a220f8-8a40-4b74-ba39-fd4cc731d7fb','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-01 17:14:06','2026-03-01 17:14:06'),(128,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'a3252e7c-d6f8-4302-8d36-15e89e8aef30','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-07 16:55:51','2026-03-07 16:55:51'),(129,1,'school.bulk.generate_invoices','App\\Models\\SchoolBulkTransaction',1,'Generate faktur dari transaksi sebar BLK-22022026-0001. Dibuat 0, terlewati 2.',NULL,NULL,NULL,'0cfbed7d-b925-4bbb-b8b9-830052a86b37','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-07 18:50:42','2026-03-07 18:50:42'),(130,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'274f68c9-21be-416d-b3bc-4d36e6303b56','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-03-10 16:59:21','2026-03-10 16:59:21'),(131,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'8f429647-963a-4668-9ae0-d341b6de3418','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-03-11 15:21:56','2026-03-11 15:21:56'),(132,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'4f52952b-e2c4-4fec-b1e0-8fd1cd55df87','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-03-11 15:22:13','2026-03-11 15:22:13'),(133,1,'created','App\\Models\\SalesInvoice',4,'Faktur INV-11032026-0001 dibuat dengan total Rp 70.000',NULL,NULL,NULL,'8195b07d-585e-400a-bdee-339c8cb3da3d',NULL,NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(134,1,'updated','App\\Models\\Product',2,'Data diperbarui: stock',NULL,NULL,NULL,'8195b07d-585e-400a-bdee-339c8cb3da3d',NULL,NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(135,1,'updated','App\\Models\\Product',5,'Data diperbarui: stock',NULL,NULL,NULL,'8195b07d-585e-400a-bdee-339c8cb3da3d',NULL,NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(136,1,'updated','App\\Models\\Customer',3,'Data diperbarui: outstanding_receivable, updated_at',NULL,NULL,NULL,'8195b07d-585e-400a-bdee-339c8cb3da3d',NULL,NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(137,1,'financial.created','App\\Models\\ReceivableLedger',9,'ReceivableLedger dibuat',NULL,'{\"id\": 9, \"debit\": 70000, \"credit\": 0, \"created_at\": \"2026-03-11 23:00:01\", \"entry_date\": \"2026-03-11 00:00:00\", \"updated_at\": \"2026-03-11 23:00:01\", \"customer_id\": 3, \"description\": \"Invoice INV-11032026-0001\", \"period_code\": \"S2-2425\", \"balance_after\": 119000, \"sales_invoice_id\": 4}',NULL,'8195b07d-585e-400a-bdee-339c8cb3da3d',NULL,NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(138,1,'financial.created','App\\Models\\InvoicePayment',4,'InvoicePayment dibuat',NULL,'{\"id\": 4, \"notes\": \"Pelunasan penuh saat membuat faktur\", \"amount\": 70000, \"method\": \"cash\", \"created_at\": \"2026-03-11 23:00:01\", \"updated_at\": \"2026-03-11 23:00:01\", \"payment_date\": \"2026-03-11 00:00:00\", \"sales_invoice_id\": 4}',NULL,'8195b07d-585e-400a-bdee-339c8cb3da3d',NULL,NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(139,1,'updated','App\\Models\\SalesInvoice',4,'Data diperbarui: total_paid, balance, payment_status',NULL,NULL,NULL,'8195b07d-585e-400a-bdee-339c8cb3da3d',NULL,NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(140,1,'updated','App\\Models\\Customer',3,'Data diperbarui: outstanding_receivable',NULL,NULL,NULL,'8195b07d-585e-400a-bdee-339c8cb3da3d',NULL,NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(141,1,'financial.created','App\\Models\\ReceivableLedger',10,'ReceivableLedger dibuat',NULL,'{\"id\": 10, \"debit\": 0, \"credit\": 70000, \"created_at\": \"2026-03-11 23:00:01\", \"entry_date\": \"2026-03-11 00:00:00\", \"updated_at\": \"2026-03-11 23:00:01\", \"customer_id\": 3, \"description\": \"Pembayaran untuk INV-11032026-0001\", \"period_code\": \"S2-2425\", \"balance_after\": 49000, \"sales_invoice_id\": 4}',NULL,'8195b07d-585e-400a-bdee-339c8cb3da3d',NULL,NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(142,1,'sales.invoice.create','App\\Models\\SalesInvoice',4,'Faktur dibuat: INV-11032026-0001',NULL,NULL,NULL,'8195b07d-585e-400a-bdee-339c8cb3da3d','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-03-11 16:00:01','2026-03-11 16:00:01'),(143,1,'auth.login',NULL,NULL,'User logged in: admin@pgpos.local',NULL,NULL,NULL,'80a7d661-9786-4bfa-9d89-46abf9c312a4','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-03-12 10:47:53','2026-03-12 10:47:53');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel-cache-app_settings.key_value_map','a:22:{s:20:\"product_unit_options\";s:12:\"exp|Exemplar\";s:20:\"product_default_unit\";s:3:\"exp\";s:21:\"outgoing_unit_options\";s:53:\"exp|Exemplar,roll|roll,rim|rim,kaleng20kg|kaleng 20kg\";s:23:\"semester_period_options\";s:23:\"S2-2425,S1-2526,S2-2526\";s:23:\"semester_active_periods\";s:15:\"S1-2526,S2-2526\";s:12:\"company_name\";s:23:\"CV. MITRA SEJATI BERKAH\";s:15:\"company_address\";s:20:\"jl puter utara no 23\";s:13:\"company_phone\";s:12:\"081321444712\";s:13:\"company_email\";s:18:\"mochagsr@gmail.com\";s:13:\"company_notes\";s:0:\"\";s:21:\"company_invoice_notes\";s:51:\"rek bca : 6546546546524\r\nrek mandiri : 654658532187\";s:20:\"company_billing_note\";s:0:\"\";s:25:\"company_transfer_accounts\";s:0:\"\";s:18:\"report_header_text\";s:0:\"\";s:18:\"report_footer_text\";s:0:\"\";s:17:\"company_logo_path\";s:52:\"company/tZa7q0v1KOqw9uqBfovUj8NO2189mcxDzLFOESg6.png\";s:19:\"print_workflow_mode\";s:7:\"browser\";s:18:\"print_paper_preset\";s:4:\"auto\";s:26:\"print_small_rows_threshold\";s:2:\"35\";s:23:\"closed_semester_periods\";s:7:\"S2-2425\";s:31:\"closed_semester_period_metadata\";s:47:\"{\"S2-2425\":{\"closed_at\":\"2026-03-11 23:03:37\"}}\";s:24:\"semester_period_metadata\";s:142:\"{\"S2-2425\":{\"created_at\":\"2026-03-12 17:48:29\"},\"S1-2526\":{\"created_at\":\"2026-03-12 17:48:29\"},\"S2-2526\":{\"created_at\":\"2026-03-12 17:48:29\"}}\";}',2088673157),('laravel-cache-outgoing_transactions.index.semester_options.base.v1.d751713988987e9331980363e24189ce','TzoyOToiSWxsdW1pbmF0ZVxTdXBwb3J0XENvbGxlY3Rpb24iOjI6e3M6ODoiACoAaXRlbXMiO2E6Mzp7aTowO3M6NzoiUzItMjQyNSI7aToxO3M6NzoiUzEtMjUyNiI7aToyO3M6NzoiUzItMjUyNiI7fXM6Mjg6IgAqAGVzY2FwZVdoZW5DYXN0aW5nVG9TdHJpbmciO2I6MDt9',1773313276),('laravel-cache-outgoing_transactions.index.supplier_options.v1.d751713988987e9331980363e24189ce','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',1773313276),('laravel-cache-receivables.bill_statement.v1.22d2bf95c500e0a5256e1d852d2d5020','YToyOntzOjQ6InJvd3MiO086Mjk6IklsbHVtaW5hdGVcU3VwcG9ydFxDb2xsZWN0aW9uIjoyOntzOjg6IgAqAGl0ZW1zIjthOjU6e2k6MDthOjk6e3M6MTA6ImRhdGVfbGFiZWwiO3M6MTM6IlNhbGRvIFBpdXRhbmciO3M6MTA6Imludm9pY2VfaWQiO047czoxMjoicHJvb2ZfbnVtYmVyIjtzOjA6IiI7czoxMDoiZW50cnlfdHlwZSI7czo3OiJvcGVuaW5nIjtzOjE3OiJhZGp1c3RtZW50X2Ftb3VudCI7aTowO3M6MTI6ImNyZWRpdF9zYWxlcyI7aTowO3M6MTk6Imluc3RhbGxtZW50X3BheW1lbnQiO2k6MDtzOjEyOiJzYWxlc19yZXR1cm4iO2k6MDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6MDt9aToxO2E6OTp7czoxMDoiZGF0ZV9sYWJlbCI7czoxMDoiMjgtMDItMjAyNiI7czoxMDoiaW52b2ljZV9pZCI7aTozO3M6MTI6InByb29mX251bWJlciI7czoxNzoiSU5WLTI4MDIyMDI2LTAwMDEiO3M6MTA6ImVudHJ5X3R5cGUiO3M6NToiZGViaXQiO3M6MTc6ImFkanVzdG1lbnRfYW1vdW50IjtpOjA7czoxMjoiY3JlZGl0X3NhbGVzIjtpOjUyNTAwO3M6MTk6Imluc3RhbGxtZW50X3BheW1lbnQiO2k6MDtzOjEyOiJzYWxlc19yZXR1cm4iO2k6MDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6NTI1MDA7fWk6MjthOjk6e3M6MTA6ImRhdGVfbGFiZWwiO3M6MTA6IjI4LTAyLTIwMjYiO3M6MTA6Imludm9pY2VfaWQiO2k6MztzOjEyOiJwcm9vZl9udW1iZXIiO3M6MTc6IklOVi0yODAyMjAyNi0wMDAxIjtzOjEwOiJlbnRyeV90eXBlIjtzOjc6InBheW1lbnQiO3M6MTc6ImFkanVzdG1lbnRfYW1vdW50IjtpOjA7czoxMjoiY3JlZGl0X3NhbGVzIjtpOjA7czoxOToiaW5zdGFsbG1lbnRfcGF5bWVudCI7aTo1MjUwMDtzOjEyOiJzYWxlc19yZXR1cm4iO2k6MDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6MDt9aTozO2E6OTp7czoxMDoiZGF0ZV9sYWJlbCI7czoxMDoiMjgtMDItMjAyNiI7czoxMDoiaW52b2ljZV9pZCI7TjtzOjEyOiJwcm9vZl9udW1iZXIiO3M6MjM6IlJldHVyIFJUUi0yODAyMjAyNi0wMDAxIjtzOjEwOiJlbnRyeV90eXBlIjtzOjY6InJldHVybiI7czoxNzoiYWRqdXN0bWVudF9hbW91bnQiO2k6MDtzOjEyOiJjcmVkaXRfc2FsZXMiO2k6MDtzOjE5OiJpbnN0YWxsbWVudF9wYXltZW50IjtpOjA7czoxMjoic2FsZXNfcmV0dXJuIjtpOjM1MDA7czoxNToicnVubmluZ19iYWxhbmNlIjtpOi0zNTAwO31pOjQ7YTo5OntzOjEwOiJkYXRlX2xhYmVsIjtzOjEwOiIyOC0wMi0yMDI2IjtzOjEwOiJpbnZvaWNlX2lkIjtpOjM7czoxMjoicHJvb2ZfbnVtYmVyIjtzOjY0OiJbQURNSU4gRURJVCBGQUtUVVIgK10gUGVueWVzdWFpYW4gbmlsYWkgZmFrdHVyIElOVi0yODAyMjAyNi0wMDAxIjtzOjEwOiJlbnRyeV90eXBlIjtzOjEwOiJhZGp1c3RtZW50IjtzOjE3OiJhZGp1c3RtZW50X2Ftb3VudCI7aTo1MjUwMDtzOjEyOiJjcmVkaXRfc2FsZXMiO2k6MDtzOjE5OiJpbnN0YWxsbWVudF9wYXltZW50IjtpOjA7czoxMjoic2FsZXNfcmV0dXJuIjtpOjA7czoxNToicnVubmluZ19iYWxhbmNlIjtpOjQ5MDAwO319czoyODoiACoAZXNjYXBlV2hlbkNhc3RpbmdUb1N0cmluZyI7YjowO31zOjY6InRvdGFscyI7YTo1OntzOjEyOiJjcmVkaXRfc2FsZXMiO2k6NTI1MDA7czoxOToiaW5zdGFsbG1lbnRfcGF5bWVudCI7aTo1MjUwMDtzOjEyOiJzYWxlc19yZXR1cm4iO2k6MzUwMDtzOjE3OiJhZGp1c3RtZW50X2Ftb3VudCI7aTo1MjUwMDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6NDkwMDA7fX0=',1773313991),('laravel-cache-receivables.bill_statement.v1.6a132bd3652c070d9527cd47f0ac3ef8','YToyOntzOjQ6InJvd3MiO086Mjk6IklsbHVtaW5hdGVcU3VwcG9ydFxDb2xsZWN0aW9uIjoyOntzOjg6IgAqAGl0ZW1zIjthOjc6e2k6MDthOjk6e3M6MTA6ImRhdGVfbGFiZWwiO3M6MTM6IlNhbGRvIFBpdXRhbmciO3M6MTA6Imludm9pY2VfaWQiO047czoxMjoicHJvb2ZfbnVtYmVyIjtzOjA6IiI7czoxMDoiZW50cnlfdHlwZSI7czo3OiJvcGVuaW5nIjtzOjE3OiJhZGp1c3RtZW50X2Ftb3VudCI7aTowO3M6MTI6ImNyZWRpdF9zYWxlcyI7aTowO3M6MTk6Imluc3RhbGxtZW50X3BheW1lbnQiO2k6MDtzOjEyOiJzYWxlc19yZXR1cm4iO2k6MDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6MDt9aToxO2E6OTp7czoxMDoiZGF0ZV9sYWJlbCI7czoxMDoiMjgtMDItMjAyNiI7czoxMDoiaW52b2ljZV9pZCI7aTozO3M6MTI6InByb29mX251bWJlciI7czoxNzoiSU5WLTI4MDIyMDI2LTAwMDEiO3M6MTA6ImVudHJ5X3R5cGUiO3M6NToiZGViaXQiO3M6MTc6ImFkanVzdG1lbnRfYW1vdW50IjtpOjA7czoxMjoiY3JlZGl0X3NhbGVzIjtpOjUyNTAwO3M6MTk6Imluc3RhbGxtZW50X3BheW1lbnQiO2k6MDtzOjEyOiJzYWxlc19yZXR1cm4iO2k6MDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6NTI1MDA7fWk6MjthOjk6e3M6MTA6ImRhdGVfbGFiZWwiO3M6MTA6IjI4LTAyLTIwMjYiO3M6MTA6Imludm9pY2VfaWQiO2k6MztzOjEyOiJwcm9vZl9udW1iZXIiO3M6MTc6IklOVi0yODAyMjAyNi0wMDAxIjtzOjEwOiJlbnRyeV90eXBlIjtzOjc6InBheW1lbnQiO3M6MTc6ImFkanVzdG1lbnRfYW1vdW50IjtpOjA7czoxMjoiY3JlZGl0X3NhbGVzIjtpOjA7czoxOToiaW5zdGFsbG1lbnRfcGF5bWVudCI7aTo1MjUwMDtzOjEyOiJzYWxlc19yZXR1cm4iO2k6MDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6MDt9aTozO2E6OTp7czoxMDoiZGF0ZV9sYWJlbCI7czoxMDoiMjgtMDItMjAyNiI7czoxMDoiaW52b2ljZV9pZCI7TjtzOjEyOiJwcm9vZl9udW1iZXIiO3M6MjM6IlJldHVyIFJUUi0yODAyMjAyNi0wMDAxIjtzOjEwOiJlbnRyeV90eXBlIjtzOjY6InJldHVybiI7czoxNzoiYWRqdXN0bWVudF9hbW91bnQiO2k6MDtzOjEyOiJjcmVkaXRfc2FsZXMiO2k6MDtzOjE5OiJpbnN0YWxsbWVudF9wYXltZW50IjtpOjA7czoxMjoic2FsZXNfcmV0dXJuIjtpOjM1MDA7czoxNToicnVubmluZ19iYWxhbmNlIjtpOi0zNTAwO31pOjQ7YTo5OntzOjEwOiJkYXRlX2xhYmVsIjtzOjEwOiIyOC0wMi0yMDI2IjtzOjEwOiJpbnZvaWNlX2lkIjtpOjM7czoxMjoicHJvb2ZfbnVtYmVyIjtzOjY0OiJbQURNSU4gRURJVCBGQUtUVVIgK10gUGVueWVzdWFpYW4gbmlsYWkgZmFrdHVyIElOVi0yODAyMjAyNi0wMDAxIjtzOjEwOiJlbnRyeV90eXBlIjtzOjEwOiJhZGp1c3RtZW50IjtzOjE3OiJhZGp1c3RtZW50X2Ftb3VudCI7aTo1MjUwMDtzOjEyOiJjcmVkaXRfc2FsZXMiO2k6MDtzOjE5OiJpbnN0YWxsbWVudF9wYXltZW50IjtpOjA7czoxMjoic2FsZXNfcmV0dXJuIjtpOjA7czoxNToicnVubmluZ19iYWxhbmNlIjtpOjQ5MDAwO31pOjU7YTo5OntzOjEwOiJkYXRlX2xhYmVsIjtzOjEwOiIxMS0wMy0yMDI2IjtzOjEwOiJpbnZvaWNlX2lkIjtpOjQ7czoxMjoicHJvb2ZfbnVtYmVyIjtzOjE3OiJJTlYtMTEwMzIwMjYtMDAwMSI7czoxMDoiZW50cnlfdHlwZSI7czo1OiJkZWJpdCI7czoxNzoiYWRqdXN0bWVudF9hbW91bnQiO2k6MDtzOjEyOiJjcmVkaXRfc2FsZXMiO2k6NzAwMDA7czoxOToiaW5zdGFsbG1lbnRfcGF5bWVudCI7aTowO3M6MTI6InNhbGVzX3JldHVybiI7aTowO3M6MTU6InJ1bm5pbmdfYmFsYW5jZSI7aToxMTkwMDA7fWk6NjthOjk6e3M6MTA6ImRhdGVfbGFiZWwiO3M6MTA6IjExLTAzLTIwMjYiO3M6MTA6Imludm9pY2VfaWQiO2k6NDtzOjEyOiJwcm9vZl9udW1iZXIiO3M6MTc6IklOVi0xMTAzMjAyNi0wMDAxIjtzOjEwOiJlbnRyeV90eXBlIjtzOjc6InBheW1lbnQiO3M6MTc6ImFkanVzdG1lbnRfYW1vdW50IjtpOjA7czoxMjoiY3JlZGl0X3NhbGVzIjtpOjA7czoxOToiaW5zdGFsbG1lbnRfcGF5bWVudCI7aTo3MDAwMDtzOjEyOiJzYWxlc19yZXR1cm4iO2k6MDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6NDkwMDA7fX1zOjI4OiIAKgBlc2NhcGVXaGVuQ2FzdGluZ1RvU3RyaW5nIjtiOjA7fXM6NjoidG90YWxzIjthOjU6e3M6MTI6ImNyZWRpdF9zYWxlcyI7aToxMjI1MDA7czoxOToiaW5zdGFsbG1lbnRfcGF5bWVudCI7aToxMjI1MDA7czoxMjoic2FsZXNfcmV0dXJuIjtpOjM1MDA7czoxNzoiYWRqdXN0bWVudF9hbW91bnQiO2k6NTI1MDA7czoxNToicnVubmluZ19iYWxhbmNlIjtpOjQ5MDAwO319',1773313424),('laravel-cache-receivables.bill_statement.v1.c80242bce5b32bbc37e7c052181e5306','YToyOntzOjQ6InJvd3MiO086Mjk6IklsbHVtaW5hdGVcU3VwcG9ydFxDb2xsZWN0aW9uIjoyOntzOjg6IgAqAGl0ZW1zIjthOjM6e2k6MDthOjk6e3M6MTA6ImRhdGVfbGFiZWwiO3M6MTM6IlNhbGRvIFBpdXRhbmciO3M6MTA6Imludm9pY2VfaWQiO047czoxMjoicHJvb2ZfbnVtYmVyIjtzOjA6IiI7czoxMDoiZW50cnlfdHlwZSI7czo3OiJvcGVuaW5nIjtzOjE3OiJhZGp1c3RtZW50X2Ftb3VudCI7aTowO3M6MTI6ImNyZWRpdF9zYWxlcyI7aTowO3M6MTk6Imluc3RhbGxtZW50X3BheW1lbnQiO2k6MDtzOjEyOiJzYWxlc19yZXR1cm4iO2k6MDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6NDkwMDA7fWk6MTthOjk6e3M6MTA6ImRhdGVfbGFiZWwiO3M6MTA6IjExLTAzLTIwMjYiO3M6MTA6Imludm9pY2VfaWQiO2k6NDtzOjEyOiJwcm9vZl9udW1iZXIiO3M6MTc6IklOVi0xMTAzMjAyNi0wMDAxIjtzOjEwOiJlbnRyeV90eXBlIjtzOjU6ImRlYml0IjtzOjE3OiJhZGp1c3RtZW50X2Ftb3VudCI7aTowO3M6MTI6ImNyZWRpdF9zYWxlcyI7aTo3MDAwMDtzOjE5OiJpbnN0YWxsbWVudF9wYXltZW50IjtpOjA7czoxMjoic2FsZXNfcmV0dXJuIjtpOjA7czoxNToicnVubmluZ19iYWxhbmNlIjtpOjExOTAwMDt9aToyO2E6OTp7czoxMDoiZGF0ZV9sYWJlbCI7czoxMDoiMTEtMDMtMjAyNiI7czoxMDoiaW52b2ljZV9pZCI7aTo0O3M6MTI6InByb29mX251bWJlciI7czoxNzoiSU5WLTExMDMyMDI2LTAwMDEiO3M6MTA6ImVudHJ5X3R5cGUiO3M6NzoicGF5bWVudCI7czoxNzoiYWRqdXN0bWVudF9hbW91bnQiO2k6MDtzOjEyOiJjcmVkaXRfc2FsZXMiO2k6MDtzOjE5OiJpbnN0YWxsbWVudF9wYXltZW50IjtpOjcwMDAwO3M6MTI6InNhbGVzX3JldHVybiI7aTowO3M6MTU6InJ1bm5pbmdfYmFsYW5jZSI7aTo0OTAwMDt9fXM6Mjg6IgAqAGVzY2FwZVdoZW5DYXN0aW5nVG9TdHJpbmciO2I6MDt9czo2OiJ0b3RhbHMiO2E6NTp7czoxMjoiY3JlZGl0X3NhbGVzIjtpOjcwMDAwO3M6MTk6Imluc3RhbGxtZW50X3BheW1lbnQiO2k6NzAwMDA7czoxMjoic2FsZXNfcmV0dXJuIjtpOjA7czoxNzoiYWRqdXN0bWVudF9hbW91bnQiO2k6MDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6NDkwMDA7fX0=',1773313460),('laravel-cache-receivables.bill_statement.v1.fbcadea127f0e50d09ec3f76ba9fa8da','YToyOntzOjQ6InJvd3MiO086Mjk6IklsbHVtaW5hdGVcU3VwcG9ydFxDb2xsZWN0aW9uIjoyOntzOjg6IgAqAGl0ZW1zIjthOjE6e2k6MDthOjk6e3M6MTA6ImRhdGVfbGFiZWwiO3M6MTM6IlNhbGRvIFBpdXRhbmciO3M6MTA6Imludm9pY2VfaWQiO047czoxMjoicHJvb2ZfbnVtYmVyIjtzOjA6IiI7czoxMDoiZW50cnlfdHlwZSI7czo3OiJvcGVuaW5nIjtzOjE3OiJhZGp1c3RtZW50X2Ftb3VudCI7aTowO3M6MTI6ImNyZWRpdF9zYWxlcyI7aTowO3M6MTk6Imluc3RhbGxtZW50X3BheW1lbnQiO2k6MDtzOjEyOiJzYWxlc19yZXR1cm4iO2k6MDtzOjE1OiJydW5uaW5nX2JhbGFuY2UiO2k6MDt9fXM6Mjg6IgAqAGVzY2FwZVdoZW5DYXN0aW5nVG9TdHJpbmciO2I6MDt9czo2OiJ0b3RhbHMiO2E6NTp7czoxMjoiY3JlZGl0X3NhbGVzIjtpOjA7czoxOToiaW5zdGFsbG1lbnRfcGF5bWVudCI7aTowO3M6MTI6InNhbGVzX3JldHVybiI7aTowO3M6MTc6ImFkanVzdG1lbnRfYW1vdW50IjtpOjA7czoxNToicnVubmluZ19iYWxhbmNlIjtpOjA7fX0=',1773314027),('laravel-cache-receivables.global_page.customer_options.v1.d751713988987e9331980363e24189ce','a:3:{i:0;a:2:{s:2:\"id\";i:3;s:5:\"label\";s:16:\"angga (sidoarjo)\";}i:1;a:2:{s:2:\"id\";i:1;s:5:\"label\";s:21:\"difa pustaka (subang)\";}i:2;a:2:{s:2:\"id\";i:2;s:5:\"label\";s:12:\"eko (malang)\";}}',1773314320),('laravel-cache-receivables.global_page.options.v1.d751713988987e9331980363e24189ce','TzoyOToiSWxsdW1pbmF0ZVxTdXBwb3J0XENvbGxlY3Rpb24iOjI6e3M6ODoiACoAaXRlbXMiO2E6Mzp7aTowO3M6NzoiUzItMjQyNSI7aToxO3M6NzoiUzEtMjUyNiI7aToyO3M6NzoiUzItMjUyNiI7fXM6Mjg6IgAqAGVzY2FwZVdoZW5DYXN0aW5nVG9TdHJpbmciO2I6MDt9',1773314320),('laravel-cache-receivables.index.semester_options.base.v1.d751713988987e9331980363e24189ce','TzoyOToiSWxsdW1pbmF0ZVxTdXBwb3J0XENvbGxlY3Rpb24iOjI6e3M6ODoiACoAaXRlbXMiO2E6Mzp7aTowO3M6NzoiUzItMjQyNSI7aToxO3M6NzoiUzEtMjUyNiI7aToyO3M6NzoiUzItMjUyNiI7fXM6Mjg6IgAqAGVzY2FwZVdoZW5DYXN0aW5nVG9TdHJpbmciO2I6MDt9',1773314318),('laravel-cache-receivables.semester_page.options.v1.d751713988987e9331980363e24189ce','TzoyOToiSWxsdW1pbmF0ZVxTdXBwb3J0XENvbGxlY3Rpb24iOjI6e3M6ODoiACoAaXRlbXMiO2E6Mzp7aTowO3M6NzoiUzItMjQyNSI7aToxO3M6NzoiUzEtMjUyNiI7aToyO3M6NzoiUzItMjUyNiI7fXM6Mjg6IgAqAGVzY2FwZVdoZW5DYXN0aW5nVG9TdHJpbmciO2I6MDt9',1773314228),('laravel-cache-reports.outgoing_suppliers.options.v1.d751713988987e9331980363e24189ce','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',1773313240),('laravel-cache-reports.receivable_customers.options.v1.d751713988987e9331980363e24189ce','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',1773313240),('laravel-cache-reports.semester_options.v1.d751713988987e9331980363e24189ce','a:2:{i:0;s:7:\"S1-2526\";i:1;s:7:\"S2-2526\";}',1773313240);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_levels`
--

DROP TABLE IF EXISTS `customer_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_levels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_levels_code_unique` (`code`),
  UNIQUE KEY `customer_levels_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_levels`
--

LOCK TABLES `customer_levels` WRITE;
/*!40000 ALTER TABLE `customer_levels` DISABLE KEYS */;
INSERT INTO `customer_levels` VALUES (1,'agen','agen','agen','2026-02-21 19:57:16','2026-02-21 19:57:22'),(2,'sales','sales','sales','2026-02-21 19:57:30','2026-02-21 19:57:30'),(3,'pelanggan umum','pelanggan umum','pelanggan umum','2026-02-21 19:57:42','2026-02-21 19:57:42');
/*!40000 ALTER TABLE `customer_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_ship_locations`
--

DROP TABLE IF EXISTS `customer_ship_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_ship_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `school_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ship_locations_customer_school_idx` (`customer_id`,`school_name`),
  KEY `ship_locations_customer_active_idx` (`customer_id`,`is_active`),
  CONSTRAINT `csl_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_ship_locations`
--

LOCK TABLES `customer_ship_locations` WRITE;
/*!40000 ALTER TABLE `customer_ship_locations` DISABLE KEYS */;
INSERT INTO `customer_ship_locations` VALUES (1,1,'sd al 1',NULL,NULL,NULL,NULL,NULL,1,'2026-02-22 12:29:02','2026-02-22 12:29:21'),(2,1,'sd al 2',NULL,NULL,NULL,NULL,NULL,1,'2026-02-22 12:29:17','2026-02-22 12:29:17');
/*!40000 ALTER TABLE `customer_ship_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_level_id` bigint unsigned DEFAULT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `id_card_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outstanding_receivable` decimal(14,2) NOT NULL DEFAULT '0.00',
  `credit_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_code_unique` (`code`),
  KEY `customers_city_index` (`city`),
  KEY `customers_name_idx` (`name`),
  KEY `customers_outstanding_idx` (`outstanding_receivable`),
  KEY `idx_customers_level` (`customer_level_id`),
  KEY `idx_customers_city` (`city`),
  KEY `customers_phone_idx` (`phone`),
  KEY `customers_city_idx` (`city`),
  CONSTRAINT `customers_customer_level_id_foreign` FOREIGN KEY (`customer_level_id`) REFERENCES `customer_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,1,'CUS-20260222-6607','difa pustaka','083233231656654','subang','jl subang jlklkjsa kota subang jawa barat','ktp/7sdJEUqhIkghJ64tXdqrfTVbhzUHBXXybqBz9NAj.jpg',0.00,0.00,NULL,'2026-02-21 19:58:20','2026-02-27 21:26:09'),(2,2,'CUS-20260222-0354','eko','098412321232','malang','jl kotalama kotalama  kolamta','ktp/X0EPqkjqk7ddqA8MFEwvqgMFeAwCKHO3TevNdhJy.jpg',0.00,0.00,NULL,'2026-02-21 19:58:55','2026-02-27 21:26:09'),(3,2,'CUS-20260227-3150','angga','087322156546','sidoarjo','jl sidoarjo dekte tol porong itu lhgo','ktp/1wRw3wa2bKDtxNDy4ZqREYiPYuoFWhrK2bwyAugw.jpg',49000.00,0.00,NULL,'2026-02-27 16:07:29','2026-03-11 16:00:01');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_note_items`
--

DROP TABLE IF EXISTS `delivery_note_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_note_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_note_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(14,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_note_items_delivery_note_id_foreign` (`delivery_note_id`),
  KEY `delivery_note_items_product_id_foreign` (`product_id`),
  CONSTRAINT `delivery_note_items_delivery_note_id_foreign` FOREIGN KEY (`delivery_note_id`) REFERENCES `delivery_notes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `delivery_note_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_note_items`
--

LOCK TABLES `delivery_note_items` WRITE;
/*!40000 ALTER TABLE `delivery_note_items` DISABLE KEYS */;
INSERT INTO `delivery_note_items` VALUES (2,1,2,'bh8e7s1','bhs indonesia 8 ed7 smt1 2526','exp',11,3500.00,NULL,'2026-02-28 20:50:33','2026-02-28 20:50:33');
/*!40000 ALTER TABLE `delivery_note_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_notes`
--

DROP TABLE IF EXISTS `delivery_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `note_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note_date` date NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_ship_location_id` bigint unsigned DEFAULT NULL,
  `recipient_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `canceled_at` timestamp NULL DEFAULT NULL,
  `canceled_by_user_id` bigint unsigned DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_notes_note_number_unique` (`note_number`),
  KEY `dn_customer_canceled_idx` (`customer_id`,`is_canceled`),
  KEY `dn_note_date_idx` (`note_date`),
  KEY `dn_canceled_note_date_idx` (`is_canceled`,`note_date`),
  KEY `dn_note_date_id_idx` (`note_date`,`id`),
  KEY `dn_ship_location_fk` (`customer_ship_location_id`),
  CONSTRAINT `delivery_notes_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `dn_ship_location_fk` FOREIGN KEY (`customer_ship_location_id`) REFERENCES `customer_ship_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_notes`
--

LOCK TABLES `delivery_notes` WRITE;
/*!40000 ALTER TABLE `delivery_notes` DISABLE KEYS */;
INSERT INTO `delivery_notes` VALUES (1,'SJ-01032026-0001','2026-03-01',3,NULL,'angga','087322156546','sidoarjo','jl sidoarjo dekte tol porong itu lhgo',NULL,'Admin PgPOS','2026-02-28 19:03:36','2026-02-28 19:03:36',0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `delivery_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_trip_members`
--

DROP TABLE IF EXISTS `delivery_trip_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_trip_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_trip_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `member_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_trip_members_user_id_foreign` (`user_id`),
  KEY `delivery_trip_members_trip_user_idx` (`delivery_trip_id`,`user_id`),
  CONSTRAINT `delivery_trip_members_delivery_trip_id_foreign` FOREIGN KEY (`delivery_trip_id`) REFERENCES `delivery_trips` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delivery_trip_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_trip_members`
--

LOCK TABLES `delivery_trip_members` WRITE;
/*!40000 ALTER TABLE `delivery_trip_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_trip_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_trips`
--

DROP TABLE IF EXISTS `delivery_trips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_trips` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trip_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trip_date` date NOT NULL,
  `driver_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assistant_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_plate` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `member_count` smallint unsigned NOT NULL DEFAULT '0',
  `fuel_cost` int NOT NULL DEFAULT '0',
  `toll_cost` int NOT NULL DEFAULT '0',
  `meal_cost` int NOT NULL DEFAULT '0',
  `other_cost` int NOT NULL DEFAULT '0',
  `total_cost` int NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `updated_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_trips_trip_number_unique` (`trip_number`),
  KEY `delivery_trips_date_idx` (`trip_date`,`id`),
  KEY `delivery_trips_plate_idx` (`vehicle_plate`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_trips`
--

LOCK TABLES `delivery_trips` WRITE;
/*!40000 ALTER TABLE `delivery_trips` DISABLE KEYS */;
INSERT INTO `delivery_trips` VALUES (1,'TRP-28022026-0001','2026-02-28','peendik','ego','n 98996 kmlj',0,500000,35000,100000,35000,670000,'kirim ke a\r\nb\r\nc\r\nd\r\ne',1,1,'2026-02-28 00:17:56','2026-02-28 00:18:11',NULL);
/*!40000 ALTER TABLE `delivery_trips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `integrity_check_logs`
--

DROP TABLE IF EXISTS `integrity_check_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `integrity_check_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_mismatch_count` int unsigned NOT NULL DEFAULT '0',
  `supplier_mismatch_count` int unsigned NOT NULL DEFAULT '0',
  `invalid_receivable_links` int unsigned NOT NULL DEFAULT '0',
  `invalid_supplier_links` int unsigned NOT NULL DEFAULT '0',
  `details` json DEFAULT NULL,
  `is_ok` tinyint(1) NOT NULL DEFAULT '1',
  `checked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `integrity_logs_status_checked_idx` (`is_ok`,`checked_at`),
  KEY `integrity_logs_checked_idx` (`checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `integrity_check_logs`
--

LOCK TABLES `integrity_check_logs` WRITE;
/*!40000 ALTER TABLE `integrity_check_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `integrity_check_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_payments`
--

DROP TABLE IF EXISTS `invoice_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_invoice_id` bigint unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_payments_sales_invoice_id_foreign` (`sales_invoice_id`),
  CONSTRAINT `invoice_payments_sales_invoice_id_foreign` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_payments`
--

LOCK TABLES `invoice_payments` WRITE;
/*!40000 ALTER TABLE `invoice_payments` DISABLE KEYS */;
INSERT INTO `invoice_payments` VALUES (1,1,'2026-02-27',12000.00,'discount','Diskon piutang dari mutasi piutang','2026-02-27 14:49:34','2026-02-27 14:49:34'),(2,2,'2026-02-27',12000.00,'discount','Diskon piutang dari mutasi piutang','2026-02-27 14:49:34','2026-02-27 14:49:34'),(4,4,'2026-03-11',70000.00,'cash','Pelunasan penuh saat membuat faktur','2026-03-11 16:00:01','2026-03-11 16:00:01');
/*!40000 ALTER TABLE `invoice_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_categories`
--

DROP TABLE IF EXISTS `item_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_categories_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_categories`
--

LOCK TABLES `item_categories` WRITE;
/*!40000 ALTER TABLE `item_categories` DISABLE KEYS */;
INSERT INTO `item_categories` VALUES (1,'cerdas','cerdas','cerdas sd lks','2026-02-21 18:32:18','2026-02-21 18:32:37'),(2,'pintar','pintar','pintar lks smp','2026-02-21 18:32:29','2026-02-21 18:32:29'),(3,'gladhen','gladhen','gladhen cerdas sd','2026-02-27 15:20:09','2026-02-27 15:20:09'),(4,'kertas web','kertas web','kertas web','2026-02-27 16:09:16','2026-02-27 16:09:16');
/*!40000 ALTER TABLE `item_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_entries`
--

DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entry_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entry_date` date NOT NULL,
  `entry_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `journal_entries_entry_number_unique` (`entry_number`),
  KEY `journal_entries_reference_idx` (`reference_type`,`reference_id`),
  KEY `journal_entries_type_date_idx` (`entry_type`,`entry_date`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_entries`
--

LOCK TABLES `journal_entries` WRITE;
/*!40000 ALTER TABLE `journal_entries` DISABLE KEYS */;
INSERT INTO `journal_entries` VALUES (1,'JR-22022026-0001','2026-02-22','outgoing_transaction_create','App\\Models\\OutgoingTransaction',1,'Posting transaksi keluar #1',1,'2026-02-21 20:05:39','2026-02-21 20:05:39'),(2,'JR-22022026-0002','2026-02-22','outgoing_transaction_create','App\\Models\\OutgoingTransaction',2,'Posting transaksi keluar #2',1,'2026-02-21 21:20:30','2026-02-21 21:20:30'),(3,'JR-22022026-0003','2026-02-22','supplier_payment_create','App\\Models\\SupplierPayment',1,'Posting pembayaran supplier #1',1,'2026-02-22 09:11:05','2026-02-22 09:11:05'),(4,'JR-22022026-0004','2026-02-22','sales_invoice_create','App\\Models\\SalesInvoice',1,'Posting invoice #1',1,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(5,'JR-22022026-0005','2026-02-22','sales_invoice_create','App\\Models\\SalesInvoice',2,'Posting invoice #2',1,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(6,'JR-27022026-0001','2026-02-27','outgoing_transaction_create','App\\Models\\OutgoingTransaction',3,'Posting transaksi keluar #3',1,'2026-02-27 16:09:40','2026-02-27 16:09:40'),(7,'JR-28022026-0001','2026-02-28','sales_invoice_create','App\\Models\\SalesInvoice',3,'Posting invoice #3',1,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(8,'JR-28022026-0002','2026-02-28','sales_return_create','App\\Models\\SalesReturn',1,'Posting retur #1',1,'2026-02-27 19:41:22','2026-02-27 19:41:22'),(9,'JR-28022026-0003','2026-02-28','supplier_payment_create','App\\Models\\SupplierPayment',2,'Posting pembayaran supplier #2',1,'2026-02-28 00:11:49','2026-02-28 00:11:49'),(10,'JR-28022026-0004','2026-02-28','delivery_trip_create','App\\Models\\DeliveryTrip',1,'Posting biaya perjalanan #1',1,'2026-02-28 00:17:56','2026-02-28 00:17:56'),(11,'JR-11032026-0001','2026-03-11','sales_invoice_create','App\\Models\\SalesInvoice',4,'Posting invoice #4',1,'2026-03-11 16:00:01','2026-03-11 16:00:01');
/*!40000 ALTER TABLE `journal_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_entry_lines`
--

DROP TABLE IF EXISTS `journal_entry_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entry_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `debit` int NOT NULL DEFAULT '0',
  `credit` int NOT NULL DEFAULT '0',
  `memo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entry_lines_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `journal_lines_account_entry_idx` (`account_id`,`journal_entry_id`),
  CONSTRAINT `journal_entry_lines_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `journal_entry_lines_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_entry_lines`
--

LOCK TABLES `journal_entry_lines` WRITE;
/*!40000 ALTER TABLE `journal_entry_lines` DISABLE KEYS */;
INSERT INTO `journal_entry_lines` VALUES (1,1,3,540000,0,'Persediaan masuk','2026-02-21 20:05:39','2026-02-21 20:05:39'),(2,1,4,0,540000,'Hutang supplier','2026-02-21 20:05:39','2026-02-21 20:05:39'),(3,2,3,270000,0,'Persediaan masuk','2026-02-21 21:20:30','2026-02-21 21:20:30'),(4,2,4,0,270000,'Hutang supplier','2026-02-21 21:20:30','2026-02-21 21:20:30'),(5,3,4,350000,0,'Pelunasan hutang supplier','2026-02-22 09:11:05','2026-02-22 09:11:05'),(6,3,1,0,350000,'Kas keluar','2026-02-22 09:11:05','2026-02-22 09:11:05'),(7,4,2,12000,0,'Penjualan','2026-02-22 12:29:59','2026-02-22 12:29:59'),(8,4,5,0,12000,'Pendapatan penjualan','2026-02-22 12:29:59','2026-02-22 12:29:59'),(9,5,2,12000,0,'Penjualan','2026-02-22 12:29:59','2026-02-22 12:29:59'),(10,5,5,0,12000,'Pendapatan penjualan','2026-02-22 12:29:59','2026-02-22 12:29:59'),(11,6,3,16500,0,'Persediaan masuk','2026-02-27 16:09:40','2026-02-27 16:09:40'),(12,6,4,0,16500,'Hutang supplier','2026-02-27 16:09:40','2026-02-27 16:09:40'),(13,7,1,52500,0,'Penjualan','2026-02-27 19:26:43','2026-02-27 19:26:43'),(14,7,5,0,52500,'Pendapatan penjualan','2026-02-27 19:26:43','2026-02-27 19:26:43'),(15,8,6,3500,0,'Retur penjualan','2026-02-27 19:41:22','2026-02-27 19:41:22'),(16,8,2,0,3500,'Pengurang piutang','2026-02-27 19:41:22','2026-02-27 19:41:22'),(17,9,4,100000,0,'Pelunasan hutang supplier','2026-02-28 00:11:49','2026-02-28 00:11:49'),(18,9,1,0,100000,'Kas keluar','2026-02-28 00:11:49','2026-02-28 00:11:49'),(19,10,7,670000,0,'Biaya operasional pengiriman','2026-02-28 00:17:56','2026-02-28 00:17:56'),(20,10,1,0,670000,'Kas keluar','2026-02-28 00:17:56','2026-02-28 00:17:56'),(21,11,1,70000,0,'Penjualan','2026-03-11 16:00:01','2026-03-11 16:00:01'),(22,11,5,0,70000,'Pendapatan penjualan','2026-03-11 16:00:01','2026-03-11 16:00:01');
/*!40000 ALTER TABLE `journal_entry_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_02_10_130000_create_item_categories_table',1),(5,'2026_02_10_130100_create_customer_levels_table',1),(6,'2026_02_10_130200_create_products_table',1),(7,'2026_02_10_130300_create_customers_table',1),(8,'2026_02_10_130400_add_erp_columns_to_users_table',1),(9,'2026_02_10_140000_create_sales_invoices_table',1),(10,'2026_02_10_140100_create_sales_invoice_items_table',1),(11,'2026_02_10_140200_create_invoice_payments_table',1),(12,'2026_02_10_140300_create_stock_mutations_table',1),(13,'2026_02_10_140400_create_receivable_ledgers_table',1),(14,'2026_02_10_150000_create_sales_returns_table',1),(15,'2026_02_10_150100_create_sales_return_items_table',1),(16,'2026_02_10_160000_create_delivery_notes_table',1),(17,'2026_02_10_160100_create_delivery_note_items_table',1),(18,'2026_02_10_170000_create_order_notes_table',1),(19,'2026_02_10_170100_create_order_note_items_table',1),(20,'2026_02_10_180000_create_audit_logs_table',1),(21,'2026_02_10_210000_create_receivable_payments_table',1),(22,'2026_02_11_090000_add_credit_balance_to_customers_table',1),(23,'2026_02_11_090000_create_app_settings_table',1),(24,'2026_02_11_120000_add_admin_control_fields_to_transactions_and_receivables',1),(25,'2026_02_11_130500_normalize_product_codes_format',1),(26,'2026_02_12_140000_create_suppliers_table',1),(27,'2026_02_12_140100_create_outgoing_transactions_table',1),(28,'2026_02_12_150200_add_performance_indexes_for_list_filters',1),(29,'2026_02_13_090500_add_audit_log_performance_indexes',1),(30,'2026_02_13_093000_add_user_filter_indexes',1),(31,'2026_02_13_113000_add_transaction_date_status_indexes',1),(32,'2026_02_13_121500_add_sales_invoice_open_balance_index',1),(33,'2026_02_14_000000_add_performance_indexes',1),(34,'2026_02_18_100000_add_supplier_payables_tables',1),(35,'2026_02_18_100100_add_change_detail_to_audit_logs',1),(36,'2026_02_18_120000_add_photo_columns_to_supplier_flows',1),(37,'2026_02_19_090000_add_permissions_to_users_table',1),(38,'2026_02_19_091000_add_large_list_search_indexes',1),(39,'2026_02_19_092000_add_soft_delete_to_financial_documents',1),(40,'2026_02_19_100000_create_report_export_tasks_table',1),(41,'2026_02_20_090000_create_accounting_journals_tables',1),(42,'2026_02_20_090100_create_approval_requests_table',1),(43,'2026_02_20_090200_add_request_id_to_audit_logs_table',1),(44,'2026_02_20_110000_create_restore_drill_logs_table',1),(45,'2026_02_20_150000_add_stock_mutation_product_indexes',1),(46,'2026_02_20_160000_add_customer_ship_locations_and_school_bulk_transactions',1),(47,'2026_02_20_170000_add_school_bulk_reference_to_sales_invoices',1),(48,'2026_02_20_180000_create_delivery_trip_tables',2),(49,'2026_02_20_181000_add_delivery_trip_expense_account',2),(50,'2026_02_27_190000_add_item_category_id_to_outgoing_transaction_items_table',3),(51,'2026_02_27_220000_add_quantity_multiplier_to_school_bulk_transaction_locations',4),(52,'2026_02_27_233000_add_location_id_to_school_bulk_transaction_items_table',5),(53,'2026_02_27_234500_add_assistant_name_to_delivery_trips_table',6),(54,'2026_02_28_000100_add_weight_to_outgoing_transaction_items_table',7),(55,'2026_02_28_010000_add_address_to_order_notes_table',7),(56,'2026_02_28_020000_add_order_note_links_to_sales_invoices_and_items',8);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_note_items`
--

DROP TABLE IF EXISTS `order_note_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_note_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_note_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_note_items_order_note_id_foreign` (`order_note_id`),
  KEY `order_note_items_product_id_foreign` (`product_id`),
  CONSTRAINT `order_note_items_order_note_id_foreign` FOREIGN KEY (`order_note_id`) REFERENCES `order_notes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_note_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_note_items`
--

LOCK TABLES `order_note_items` WRITE;
/*!40000 ALTER TABLE `order_note_items` DISABLE KEYS */;
INSERT INTO `order_note_items` VALUES (1,1,2,'bh8e7s1','bh8e7s1 - bhs indonesia 8 ed7 smt1 2526',15,NULL,'2026-02-27 18:32:11','2026-02-27 18:32:11'),(2,1,5,'bh3e8','bh3e8 - bhs jawa 3 ed 8 2526',10,NULL,'2026-02-27 18:32:11','2026-02-27 18:32:11');
/*!40000 ALTER TABLE `order_note_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_notes`
--

DROP TABLE IF EXISTS `order_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `note_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note_date` date NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `canceled_at` timestamp NULL DEFAULT NULL,
  `canceled_by_user_id` bigint unsigned DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_notes_note_number_unique` (`note_number`),
  KEY `on_customer_canceled_idx` (`customer_id`,`is_canceled`),
  KEY `on_note_date_idx` (`note_date`),
  KEY `on_canceled_note_date_idx` (`is_canceled`,`note_date`),
  KEY `on_note_date_id_idx` (`note_date`,`id`),
  CONSTRAINT `order_notes_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_notes`
--

LOCK TABLES `order_notes` WRITE;
/*!40000 ALTER TABLE `order_notes` DISABLE KEYS */;
INSERT INTO `order_notes` VALUES (1,'PO-28022026-0001','2026-02-28',3,'angga (sidoarjo)','087322156546',NULL,'sidoarjo','Admin PgPOS','dikirim senin','2026-02-27 18:32:11','2026-02-27 18:32:11',0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `order_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outgoing_transaction_items`
--

DROP TABLE IF EXISTS `outgoing_transaction_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `outgoing_transaction_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `outgoing_transaction_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `item_category_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `weight` decimal(10,3) DEFAULT NULL,
  `unit_cost` int NOT NULL,
  `line_total` int NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `outgoing_transaction_items_outgoing_transaction_id_foreign` (`outgoing_transaction_id`),
  KEY `outgoing_transaction_items_product_id_foreign` (`product_id`),
  KEY `oti_item_category_id_idx` (`item_category_id`),
  CONSTRAINT `outgoing_transaction_items_outgoing_transaction_id_foreign` FOREIGN KEY (`outgoing_transaction_id`) REFERENCES `outgoing_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `outgoing_transaction_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outgoing_transaction_items`
--

LOCK TABLES `outgoing_transaction_items` WRITE;
/*!40000 ALTER TABLE `outgoing_transaction_items` DISABLE KEYS */;
INSERT INTO `outgoing_transaction_items` VALUES (1,1,NULL,NULL,NULL,'tinta bw merk kuda','kaleng20kg',20,NULL,27000,540000,'tinta hitam bw','2026-02-21 20:05:39','2026-02-21 20:05:39'),(2,2,NULL,NULL,NULL,'tinta bw web','kaleng20kg',10,NULL,27000,270000,NULL,'2026-02-21 21:20:30','2026-02-21 21:20:30'),(3,3,7,4,'kr','kertas web 68gr','roll',1,NULL,16500,16500,NULL,'2026-02-27 16:09:40','2026-02-27 17:00:28');
/*!40000 ALTER TABLE `outgoing_transaction_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outgoing_transactions`
--

DROP TABLE IF EXISTS `outgoing_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `outgoing_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_date` date NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `semester_period` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_invoice_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` int NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outgoing_transactions_transaction_number_unique` (`transaction_number`),
  KEY `outgoing_transactions_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `ot_supplier_date_idx` (`supplier_id`,`transaction_date`),
  KEY `ot_semester_idx` (`semester_period`),
  KEY `idx_outgoing_supplier` (`supplier_id`),
  KEY `idx_outgoing_date` (`transaction_date`),
  KEY `idx_outgoing_semester` (`semester_period`),
  CONSTRAINT `outgoing_transactions_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `outgoing_transactions_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outgoing_transactions`
--

LOCK TABLES `outgoing_transactions` WRITE;
/*!40000 ALTER TABLE `outgoing_transactions` DISABLE KEYS */;
INSERT INTO `outgoing_transactions` VALUES (1,'TRXK-22022026-0001','2026-02-22',1,'S2-2526','n35411111',NULL,540000,NULL,1,'2026-02-21 20:05:39','2026-02-21 20:05:39',NULL),(2,'TRXK-22022026-0002','2026-02-22',1,'S2-2526',NULL,NULL,270000,NULL,1,'2026-02-21 21:20:30','2026-02-21 21:20:30',NULL),(3,'TRXK-27022026-0001','2026-02-27',3,'S2-2526','n7768990',NULL,16500,NULL,1,'2026-02-27 16:09:40','2026-02-27 16:09:40',NULL);
/*!40000 ALTER TABLE `outgoing_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `performance_probe_logs`
--

DROP TABLE IF EXISTS `performance_probe_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `performance_probe_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loops` int unsigned NOT NULL DEFAULT '0',
  `duration_ms` int unsigned NOT NULL DEFAULT '0',
  `avg_loop_ms` int unsigned NOT NULL DEFAULT '0',
  `search_token` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metrics` json DEFAULT NULL,
  `probed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `performance_probe_probed_idx` (`probed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `performance_probe_logs`
--

LOCK TABLES `performance_probe_logs` WRITE;
/*!40000 ALTER TABLE `performance_probe_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `performance_probe_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_category_id` bigint unsigned NOT NULL,
  `code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `price_agent` decimal(12,2) NOT NULL DEFAULT '0.00',
  `price_sales` decimal(12,2) NOT NULL DEFAULT '0.00',
  `price_general` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_code_unique` (`code`),
  KEY `products_name_idx` (`name`),
  KEY `products_category_name_idx` (`item_category_id`,`name`),
  KEY `idx_products_category` (`item_category_id`),
  KEY `idx_products_active` (`is_active`),
  KEY `idx_products_stock` (`stock`),
  KEY `products_code_idx` (`code`),
  CONSTRAINT `products_item_category_id_foreign` FOREIGN KEY (`item_category_id`) REFERENCES `item_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,1,'mt1e6s2','matematika 1 ed 6 smt 2 2526','exp',988,3000.00,3500.00,12500.00,1,'2026-02-21 19:53:16','2026-02-23 19:34:51'),(2,2,'bh8e7s1','bhs indonesia 8 ed7 smt1 2526','exp',929,3000.00,3500.00,12000.00,1,'2026-02-21 19:57:02','2026-03-11 16:00:01'),(3,1,'tn','tinta bw merk kuda','exp',46,0.00,0.00,0.00,1,'2026-02-22 09:10:32','2026-02-23 18:51:25'),(4,1,'tn01','tinta bw web','exp',25,0.00,0.00,0.00,1,'2026-02-22 09:10:38','2026-02-23 18:54:50'),(5,3,'bh3e8','bhs jawa 3 ed 8 2526','exp',985,3000.00,3500.00,12000.00,1,'2026-02-27 15:24:46','2026-03-11 16:00:01'),(6,1,'cpn4e656','pendidikan pancasila 4 ed 6 tahun 2526','exp',1000,3000.00,3500.00,12000.00,1,'2026-02-27 16:05:22','2026-02-27 16:05:22'),(7,4,'kr','kertas web 68gr','roll',1,16500.00,16500.00,16500.00,1,'2026-02-27 17:00:28','2026-02-27 17:00:28');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receivable_ledgers`
--

DROP TABLE IF EXISTS `receivable_ledgers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `receivable_ledgers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `sales_invoice_id` bigint unsigned DEFAULT NULL,
  `entry_date` date NOT NULL,
  `period_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `debit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `balance_after` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `receivable_ledgers_customer_id_entry_date_index` (`customer_id`,`entry_date`),
  KEY `receivable_ledgers_period_code_index` (`period_code`),
  KEY `rl_customer_period_entry_idx` (`customer_id`,`period_code`,`entry_date`),
  KEY `rl_sales_invoice_idx` (`sales_invoice_id`),
  KEY `rl_customer_entry_id_idx` (`customer_id`,`entry_date`,`id`),
  KEY `idx_receivable_customer` (`customer_id`),
  KEY `idx_receivable_entry_date` (`entry_date`),
  KEY `idx_receivable_combined` (`customer_id`,`entry_date`),
  KEY `receivable_ledgers_customer_date_idx` (`customer_id`,`entry_date`),
  KEY `receivable_ledgers_invoice_idx` (`sales_invoice_id`),
  CONSTRAINT `receivable_ledgers_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `receivable_ledgers_sales_invoice_id_foreign` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receivable_ledgers`
--

LOCK TABLES `receivable_ledgers` WRITE;
/*!40000 ALTER TABLE `receivable_ledgers` DISABLE KEYS */;
INSERT INTO `receivable_ledgers` VALUES (1,1,1,'2026-02-22','S2-2526','Invoice INV-22022026-0001',12000.00,0.00,12000.00,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(2,1,2,'2026-02-22','S2-2526','Invoice INV-22022026-0002',12000.00,0.00,24000.00,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(3,1,1,'2026-02-27','S2-2526','Diskon piutang untuk INV-22022026-0001',0.00,12000.00,12000.00,'2026-02-27 14:49:34','2026-02-27 14:49:34'),(4,1,2,'2026-02-27','S2-2526','Diskon piutang untuk INV-22022026-0002',0.00,12000.00,0.00,'2026-02-27 14:49:34','2026-02-27 14:49:34'),(5,3,3,'2026-02-28','S2-2526','Invoice INV-28022026-0001',52500.00,0.00,52500.00,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(6,3,3,'2026-02-28','S2-2526','Pembayaran untuk INV-28022026-0001',0.00,52500.00,0.00,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(7,3,3,'2026-02-28','S2-2526','[ADMIN EDIT FAKTUR +] Penyesuaian nilai faktur INV-28022026-0001',52500.00,0.00,52500.00,'2026-02-27 19:40:03','2026-02-27 19:40:03'),(8,3,NULL,'2026-02-28','S2-2526','Retur RTR-28022026-0001',0.00,3500.00,49000.00,'2026-02-27 19:41:22','2026-02-27 19:41:22'),(9,3,4,'2026-03-11','S2-2425','Invoice INV-11032026-0001',70000.00,0.00,119000.00,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(10,3,4,'2026-03-11','S2-2425','Pembayaran untuk INV-11032026-0001',0.00,70000.00,49000.00,'2026-03-11 16:00:01','2026-03-11 16:00:01');
/*!40000 ALTER TABLE `receivable_ledgers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receivable_payments`
--

DROP TABLE IF EXISTS `receivable_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `receivable_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `customer_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `amount_in_words` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_signature` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_signature` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `canceled_at` timestamp NULL DEFAULT NULL,
  `canceled_by_user_id` bigint unsigned DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receivable_payments_payment_number_unique` (`payment_number`),
  KEY `receivable_payments_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `rp_customer_canceled_idx` (`customer_id`,`is_canceled`),
  KEY `rp_payment_date_idx` (`payment_date`),
  KEY `rp_canceled_payment_date_idx` (`is_canceled`,`payment_date`),
  KEY `rp_payment_date_id_idx` (`payment_date`,`id`),
  CONSTRAINT `receivable_payments_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `receivable_payments_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receivable_payments`
--

LOCK TABLES `receivable_payments` WRITE;
/*!40000 ALTER TABLE `receivable_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `receivable_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report_export_tasks`
--

DROP TABLE IF EXISTS `report_export_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_export_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `dataset` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `filters` json DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_export_tasks_user_status_idx` (`user_id`,`status`,`created_at`),
  KEY `report_export_tasks_dataset_format_idx` (`dataset`,`format`,`created_at`),
  CONSTRAINT `report_export_tasks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report_export_tasks`
--

LOCK TABLES `report_export_tasks` WRITE;
/*!40000 ALTER TABLE `report_export_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `report_export_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `restore_drill_logs`
--

DROP TABLE IF EXISTS `restore_drill_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `restore_drill_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `backup_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_ms` int NOT NULL DEFAULT '0',
  `message` text COLLATE utf8mb4_unicode_ci,
  `tested_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restore_drill_status_tested_idx` (`status`,`tested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restore_drill_logs`
--

LOCK TABLES `restore_drill_logs` WRITE;
/*!40000 ALTER TABLE `restore_drill_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `restore_drill_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_invoice_items`
--

DROP TABLE IF EXISTS `sales_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_invoice_id` bigint unsigned NOT NULL,
  `order_note_item_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(14,2) NOT NULL,
  `discount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(14,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_invoice_items_sales_invoice_id_foreign` (`sales_invoice_id`),
  KEY `sales_invoice_items_product_id_foreign` (`product_id`),
  KEY `sales_invoice_items_order_note_item_product_idx` (`order_note_item_id`,`product_id`),
  CONSTRAINT `sales_invoice_items_order_note_item_id_foreign` FOREIGN KEY (`order_note_item_id`) REFERENCES `order_note_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `sales_invoice_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `sales_invoice_items_sales_invoice_id_foreign` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoice_items`
--

LOCK TABLES `sales_invoice_items` WRITE;
/*!40000 ALTER TABLE `sales_invoice_items` DISABLE KEYS */;
INSERT INTO `sales_invoice_items` VALUES (1,1,NULL,2,'bh8e7s1','bhs indonesia 8 ed7 smt1 2526',1,12000.00,0.00,12000.00,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(2,2,NULL,2,'bh8e7s1','bhs indonesia 8 ed7 smt1 2526',1,12000.00,0.00,12000.00,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(5,3,1,2,'bh8e7s1','bhs indonesia 8 ed7 smt1 2526',10,3500.00,0.00,35000.00,'2026-02-27 19:40:03','2026-02-27 19:40:03'),(6,3,2,5,'bh3e8','bhs jawa 3 ed 8 2526',5,3500.00,0.00,17500.00,'2026-02-27 19:40:03','2026-02-27 19:40:03'),(7,4,NULL,2,'bh8e7s1','bhs indonesia 8 ed7 smt1 2526',10,3500.00,0.00,35000.00,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(8,4,NULL,5,'bh3e8','bhs jawa 3 ed 8 2526',10,3500.00,0.00,35000.00,'2026-03-11 16:00:01','2026-03-11 16:00:01');
/*!40000 ALTER TABLE `sales_invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_invoices`
--

DROP TABLE IF EXISTS `sales_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `customer_ship_location_id` bigint unsigned DEFAULT NULL,
  `school_bulk_transaction_id` bigint unsigned DEFAULT NULL,
  `school_bulk_location_id` bigint unsigned DEFAULT NULL,
  `order_note_id` bigint unsigned DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `semester_period` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_paid` decimal(14,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `payment_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `ship_to_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_to_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_to_city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_to_address` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `canceled_at` timestamp NULL DEFAULT NULL,
  `canceled_by_user_id` bigint unsigned DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_invoices_invoice_number_unique` (`invoice_number`),
  KEY `si_customer_canceled_idx` (`customer_id`,`is_canceled`),
  KEY `si_semester_canceled_idx` (`semester_period`,`is_canceled`),
  KEY `si_invoice_date_idx` (`invoice_date`),
  KEY `si_payment_status_idx` (`payment_status`),
  KEY `si_canceled_invoice_date_idx` (`is_canceled`,`invoice_date`),
  KEY `si_invoice_date_id_idx` (`invoice_date`,`id`),
  KEY `si_customer_open_balance_date_idx` (`customer_id`,`is_canceled`,`balance`,`invoice_date`,`id`),
  KEY `idx_sales_invoices_customer` (`customer_id`),
  KEY `idx_sales_invoices_invoice_date` (`invoice_date`),
  KEY `idx_sales_invoices_semester` (`semester_period`),
  KEY `idx_sales_invoices_status` (`is_canceled`),
  KEY `idx_sales_invoices_combined` (`invoice_date`,`is_canceled`),
  KEY `sales_invoices_number_idx` (`invoice_number`),
  KEY `sales_invoices_customer_date_idx` (`customer_id`,`invoice_date`),
  KEY `sales_invoices_semester_date_idx` (`semester_period`,`invoice_date`),
  KEY `si_ship_location_fk` (`customer_ship_location_id`),
  KEY `sales_invoices_school_bulk_transaction_id_foreign` (`school_bulk_transaction_id`),
  KEY `sales_invoices_school_bulk_location_id_foreign` (`school_bulk_location_id`),
  KEY `sales_invoices_order_note_date_idx` (`order_note_id`,`invoice_date`),
  CONSTRAINT `sales_invoices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `sales_invoices_order_note_id_foreign` FOREIGN KEY (`order_note_id`) REFERENCES `order_notes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `sales_invoices_school_bulk_location_id_foreign` FOREIGN KEY (`school_bulk_location_id`) REFERENCES `school_bulk_transaction_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `sales_invoices_school_bulk_transaction_id_foreign` FOREIGN KEY (`school_bulk_transaction_id`) REFERENCES `school_bulk_transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `si_ship_location_fk` FOREIGN KEY (`customer_ship_location_id`) REFERENCES `customer_ship_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_invoices`
--

LOCK TABLES `sales_invoices` WRITE;
/*!40000 ALTER TABLE `sales_invoices` DISABLE KEYS */;
INSERT INTO `sales_invoices` VALUES (1,'INV-22022026-0001',1,1,1,1,NULL,'2026-02-22',NULL,'S2-2526',12000.00,12000.00,12000.00,0.00,'paid','sd al 1',NULL,NULL,NULL,'Otomatis dari transaksi sebar BLK-22022026-0001','2026-02-22 12:29:59','2026-02-27 14:49:34',0,NULL,NULL,NULL,NULL),(2,'INV-22022026-0002',1,2,1,2,NULL,'2026-02-22',NULL,'S2-2526',12000.00,12000.00,12000.00,0.00,'paid','sd al 2',NULL,NULL,NULL,'Otomatis dari transaksi sebar BLK-22022026-0001','2026-02-22 12:29:59','2026-02-27 14:49:34',0,NULL,NULL,NULL,NULL),(3,'INV-28022026-0001',3,NULL,NULL,NULL,1,'2026-02-28',NULL,'S2-2526',52500.00,52500.00,0.00,52500.00,'unpaid',NULL,NULL,NULL,NULL,NULL,'2026-02-27 19:26:43','2026-02-27 19:40:03',0,NULL,NULL,NULL,NULL),(4,'INV-11032026-0001',3,NULL,NULL,NULL,NULL,'2026-03-11',NULL,'S2-2425',70000.00,70000.00,70000.00,0.00,'paid',NULL,NULL,NULL,NULL,NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01',0,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `sales_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_return_items`
--

DROP TABLE IF EXISTS `sales_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_return_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_return_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(14,2) NOT NULL,
  `line_total` decimal(14,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_return_items_sales_return_id_foreign` (`sales_return_id`),
  KEY `sales_return_items_product_id_foreign` (`product_id`),
  CONSTRAINT `sales_return_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `sales_return_items_sales_return_id_foreign` FOREIGN KEY (`sales_return_id`) REFERENCES `sales_returns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_return_items`
--

LOCK TABLES `sales_return_items` WRITE;
/*!40000 ALTER TABLE `sales_return_items` DISABLE KEYS */;
INSERT INTO `sales_return_items` VALUES (1,1,2,'bh8e7s1','bhs indonesia 8 ed7 smt1 2526',1,3500.00,3500.00,'2026-02-27 19:41:22','2026-02-27 19:41:22');
/*!40000 ALTER TABLE `sales_return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_returns`
--

DROP TABLE IF EXISTS `sales_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_returns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `return_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `sales_invoice_id` bigint unsigned DEFAULT NULL,
  `return_date` date NOT NULL,
  `semester_period` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `canceled_at` timestamp NULL DEFAULT NULL,
  `canceled_by_user_id` bigint unsigned DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_returns_return_number_unique` (`return_number`),
  KEY `sr_customer_canceled_idx` (`customer_id`,`is_canceled`),
  KEY `sr_semester_canceled_idx` (`semester_period`,`is_canceled`),
  KEY `sr_return_date_idx` (`return_date`),
  KEY `sr_canceled_return_date_idx` (`is_canceled`,`return_date`),
  KEY `sr_return_date_id_idx` (`return_date`,`id`),
  KEY `idx_sales_return_customer` (`customer_id`),
  KEY `idx_sales_return_invoice` (`sales_invoice_id`),
  KEY `idx_sales_return_date` (`return_date`),
  CONSTRAINT `sales_returns_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `sales_returns_sales_invoice_id_foreign` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_returns`
--

LOCK TABLES `sales_returns` WRITE;
/*!40000 ALTER TABLE `sales_returns` DISABLE KEYS */;
INSERT INTO `sales_returns` VALUES (1,'RTR-28022026-0001',3,NULL,'2026-02-28','S2-2526',3500.00,NULL,'2026-02-27 19:41:22','2026-02-27 19:41:22',0,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `sales_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_bulk_transaction_items`
--

DROP TABLE IF EXISTS `school_bulk_transaction_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `school_bulk_transaction_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `school_bulk_transaction_id` bigint unsigned NOT NULL,
  `school_bulk_transaction_location_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sbti_product_fk` (`product_id`),
  KEY `school_bulk_txn_items_sort_idx` (`school_bulk_transaction_id`,`sort_order`),
  KEY `sbti_location_fk` (`school_bulk_transaction_location_id`),
  CONSTRAINT `sbti_location_fk` FOREIGN KEY (`school_bulk_transaction_location_id`) REFERENCES `school_bulk_transaction_locations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sbti_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `sbti_transaction_fk` FOREIGN KEY (`school_bulk_transaction_id`) REFERENCES `school_bulk_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_bulk_transaction_items`
--

LOCK TABLES `school_bulk_transaction_items` WRITE;
/*!40000 ALTER TABLE `school_bulk_transaction_items` DISABLE KEYS */;
INSERT INTO `school_bulk_transaction_items` VALUES (1,1,NULL,2,'bh8e7s1','bh8e7s1 - bhs indonesia 8 ed7 smt1 2526','exp',1,12000,NULL,0,'2026-02-22 12:29:55','2026-02-22 12:29:55');
/*!40000 ALTER TABLE `school_bulk_transaction_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_bulk_transaction_locations`
--

DROP TABLE IF EXISTS `school_bulk_transaction_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `school_bulk_transaction_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `school_bulk_transaction_id` bigint unsigned NOT NULL,
  `customer_ship_location_id` bigint unsigned DEFAULT NULL,
  `school_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sbtl_ship_location_fk` (`customer_ship_location_id`),
  KEY `school_bulk_txn_locations_sort_idx` (`school_bulk_transaction_id`,`sort_order`),
  CONSTRAINT `sbtl_ship_location_fk` FOREIGN KEY (`customer_ship_location_id`) REFERENCES `customer_ship_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `sbtl_transaction_fk` FOREIGN KEY (`school_bulk_transaction_id`) REFERENCES `school_bulk_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_bulk_transaction_locations`
--

LOCK TABLES `school_bulk_transaction_locations` WRITE;
/*!40000 ALTER TABLE `school_bulk_transaction_locations` DISABLE KEYS */;
INSERT INTO `school_bulk_transaction_locations` VALUES (1,1,1,'sd al 1',NULL,NULL,NULL,NULL,0,'2026-02-22 12:29:55','2026-02-22 12:29:55'),(2,1,2,'sd al 2',NULL,NULL,NULL,NULL,1,'2026-02-22 12:29:55','2026-02-22 12:29:55');
/*!40000 ALTER TABLE `school_bulk_transaction_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_bulk_transactions`
--

DROP TABLE IF EXISTS `school_bulk_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `school_bulk_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_date` date NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `semester_period` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_locations` int unsigned NOT NULL DEFAULT '0',
  `total_items` int unsigned NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_bulk_transactions_transaction_number_unique` (`transaction_number`),
  KEY `sbt_customer_fk` (`customer_id`),
  KEY `sbt_created_by_fk` (`created_by_user_id`),
  KEY `school_bulk_transactions_date_customer_idx` (`transaction_date`,`customer_id`),
  CONSTRAINT `sbt_created_by_fk` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `sbt_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_bulk_transactions`
--

LOCK TABLES `school_bulk_transactions` WRITE;
/*!40000 ALTER TABLE `school_bulk_transactions` DISABLE KEYS */;
INSERT INTO `school_bulk_transactions` VALUES (1,'BLK-22022026-0001','2026-02-22',1,'S2-2526',2,1,NULL,1,'2026-02-22 12:29:55','2026-02-22 12:29:55');
/*!40000 ALTER TABLE `school_bulk_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('BpaJkTNbyNLd5FA6DKymyaHg3ax0LFOMsGwuWyvW',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiWk1wU3NPVXRXYWdZQVNvejh5cjNvRW9aYkRmaTBPYXI0TzFpOHpoeiI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vdGVzcGdwb3MudGVzdC9yZWNlaXZhYmxlcy9nbG9iYWwiO3M6NToicm91dGUiO3M6MjQ6InJlY2VpdmFibGVzLmdsb2JhbC5pbmRleCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7fQ==',1773314260);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_mutations`
--

DROP TABLE IF EXISTS `stock_mutations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_mutations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `mutation_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_mutations_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `stock_mutations_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  KEY `idx_stock_mutations_product_created` (`product_id`,`created_at`),
  KEY `idx_stock_mutations_type` (`mutation_type`),
  CONSTRAINT `stock_mutations_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `stock_mutations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_mutations`
--

LOCK TABLES `stock_mutations` WRITE;
/*!40000 ALTER TABLE `stock_mutations` DISABLE KEYS */;
INSERT INTO `stock_mutations` VALUES (1,1,'App\\Models\\Product',1,'in',1000,'Stok awal barang',1,'2026-02-21 19:53:16','2026-02-21 19:53:16'),(2,2,'App\\Models\\Product',2,'in',1000,'Stok awal barang',1,'2026-02-21 19:57:02','2026-02-21 19:57:02'),(3,3,'App\\Models\\Product',3,'in',35,'Edit stok manual dari kartu stok supplier',1,'2026-02-22 09:10:32','2026-02-22 09:10:32'),(4,3,'App\\Models\\Supplier',1,'in',15,'Edit stok manual dari kartu stok supplier',1,'2026-02-22 09:20:22','2026-02-22 09:20:22'),(5,4,'App\\Models\\Supplier',1,'out',10,'Edit stok manual dari kartu stok supplier',1,'2026-02-22 09:22:52','2026-02-22 09:22:52'),(6,2,'App\\Models\\Product',2,'out',50,'Pengurangan stok manual dari edit barang',1,'2026-02-22 09:28:11','2026-02-22 09:28:11'),(7,4,'App\\Models\\Supplier',1,'in',2,'Edit stok manual dari kartu stok supplier',1,'2026-02-22 12:15:15','2026-02-22 12:15:15'),(8,2,'App\\Models\\SalesInvoice',1,'out',1,'Generate faktur dari BLK-22022026-0001 -> INV-22022026-0001',1,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(9,2,'App\\Models\\SalesInvoice',2,'out',1,'Generate faktur dari BLK-22022026-0001 -> INV-22022026-0002',1,'2026-02-22 12:29:59','2026-02-22 12:29:59'),(10,3,'App\\Models\\Supplier',1,'in',3,'Edit stok manual dari kartu stok supplier',1,'2026-02-23 18:51:20','2026-02-23 18:51:20'),(11,3,'App\\Models\\Supplier',1,'out',35,'Edit stok manual dari kartu stok supplier',1,'2026-02-23 18:51:24','2026-02-23 18:51:24'),(12,3,'App\\Models\\Supplier',1,'in',28,'Edit stok manual dari kartu stok supplier',1,'2026-02-23 18:51:25','2026-02-23 18:51:25'),(13,2,'App\\Models\\Product',2,'out',2,'Pengurangan stok manual dari edit barang',1,'2026-02-23 18:51:35','2026-02-23 18:51:35'),(14,2,'App\\Models\\Product',2,'out',852,'Pengurangan stok manual dari edit barang',1,'2026-02-23 18:54:23','2026-02-23 18:54:23'),(15,2,'App\\Models\\Product',2,'in',854,'Penambahan stok manual dari edit barang',1,'2026-02-23 18:54:28','2026-02-23 18:54:28'),(16,4,'App\\Models\\Product',4,'in',19,'Penambahan stok manual dari edit barang',1,'2026-02-23 18:54:34','2026-02-23 18:54:34'),(17,4,'App\\Models\\Product',4,'out',19,'Pengurangan stok manual dari edit barang',1,'2026-02-23 18:54:45','2026-02-23 18:54:45'),(18,4,'App\\Models\\Product',4,'in',23,'Penambahan stok manual dari edit barang',1,'2026-02-23 18:54:50','2026-02-23 18:54:50'),(19,2,'App\\Models\\Product',2,'in',1,'Penambahan stok manual dari edit barang',1,'2026-02-23 18:59:54','2026-02-23 18:59:54'),(20,1,'App\\Models\\Product',1,'out',15,'Pengurangan stok manual dari edit barang',1,'2026-02-23 19:26:02','2026-02-23 19:26:02'),(21,1,'App\\Models\\Product',1,'in',3,'Penambahan stok manual dari edit barang',1,'2026-02-23 19:32:25','2026-02-23 19:32:25'),(22,5,'App\\Models\\Product',5,'in',1000,'Stok awal barang',1,'2026-02-27 15:24:46','2026-02-27 15:24:46'),(23,6,'App\\Models\\Product',6,'in',1000,'Stok awal barang',1,'2026-02-27 16:05:23','2026-02-27 16:05:23'),(24,7,'App\\Models\\OutgoingTransaction',3,'in',1,'Goods receipt letter TRXK-27022026-0001',NULL,'2026-02-27 17:00:28','2026-02-27 17:00:28'),(25,2,'App\\Models\\SalesInvoice',3,'out',10,'Sales invoice INV-28022026-0001',NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(26,5,'App\\Models\\SalesInvoice',3,'out',5,'Sales invoice INV-28022026-0001',NULL,'2026-02-27 19:26:43','2026-02-27 19:26:43'),(27,2,'App\\Models\\SalesReturn',1,'in',1,'Retur RTR-28022026-0001',NULL,'2026-02-27 19:41:22','2026-02-27 19:41:22'),(28,2,'App\\Models\\DeliveryNote',1,'out',1,'Admin edit delivery note SJ-01032026-0001',1,'2026-02-28 20:50:33','2026-02-28 20:50:33'),(29,2,'App\\Models\\SalesInvoice',4,'out',10,'Sales invoice INV-11032026-0001',NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01'),(30,5,'App\\Models\\SalesInvoice',4,'out',10,'Sales invoice INV-11032026-0001',NULL,'2026-03-11 16:00:01','2026-03-11 16:00:01');
/*!40000 ALTER TABLE `stock_mutations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_ledgers`
--

DROP TABLE IF EXISTS `supplier_ledgers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_ledgers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `outgoing_transaction_id` bigint unsigned DEFAULT NULL,
  `supplier_payment_id` bigint unsigned DEFAULT NULL,
  `entry_date` date NOT NULL,
  `period_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `debit` int NOT NULL DEFAULT '0',
  `credit` int NOT NULL DEFAULT '0',
  `balance_after` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_ledgers_supplier_payment_id_foreign` (`supplier_payment_id`),
  KEY `supplier_ledgers_supplier_id_entry_date_index` (`supplier_id`,`entry_date`),
  KEY `supplier_ledgers_period_code_index` (`period_code`),
  KEY `supplier_ledgers_supplier_date_idx` (`supplier_id`,`entry_date`),
  KEY `supplier_ledgers_outgoing_idx` (`outgoing_transaction_id`),
  CONSTRAINT `supplier_ledgers_outgoing_transaction_id_foreign` FOREIGN KEY (`outgoing_transaction_id`) REFERENCES `outgoing_transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `supplier_ledgers_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `supplier_ledgers_supplier_payment_id_foreign` FOREIGN KEY (`supplier_payment_id`) REFERENCES `supplier_payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_ledgers`
--

LOCK TABLES `supplier_ledgers` WRITE;
/*!40000 ALTER TABLE `supplier_ledgers` DISABLE KEYS */;
INSERT INTO `supplier_ledgers` VALUES (1,1,1,NULL,'2026-02-22','S2-2526','Transaksi keluar TRXK-22022026-0001',540000,0,540000,'2026-02-21 20:05:39','2026-02-21 20:05:39'),(2,1,2,NULL,'2026-02-22','S2-2526','Transaksi keluar TRXK-22022026-0002',270000,0,810000,'2026-02-21 21:20:30','2026-02-21 21:20:30'),(3,1,NULL,1,'2026-02-22','S2-2526','Pembayaran hutang supplier KWTS-22022026-0001',0,350000,460000,'2026-02-22 09:11:05','2026-02-22 09:11:05'),(4,3,3,NULL,'2026-02-27','S2-2526','Transaksi keluar TRXK-27022026-0001',16500,0,16500,'2026-02-27 16:09:40','2026-02-27 16:09:40'),(5,1,NULL,2,'2026-02-28','S2-2526','Pembayaran hutang supplier KWTS-28022026-0001',0,100000,360000,'2026-02-28 00:11:49','2026-02-28 00:11:49');
/*!40000 ALTER TABLE `supplier_ledgers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_payments`
--

DROP TABLE IF EXISTS `supplier_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplier_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `proof_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_proof_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` int NOT NULL,
  `amount_in_words` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_signature` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_signature` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `is_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `canceled_at` timestamp NULL DEFAULT NULL,
  `canceled_by_user_id` bigint unsigned DEFAULT NULL,
  `cancel_reason` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_payments_payment_number_unique` (`payment_number`),
  KEY `supplier_payments_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `supplier_payments_canceled_by_user_id_foreign` (`canceled_by_user_id`),
  KEY `supplier_payments_supplier_id_payment_date_index` (`supplier_id`,`payment_date`),
  KEY `supplier_payments_is_canceled_payment_date_index` (`is_canceled`,`payment_date`),
  CONSTRAINT `supplier_payments_canceled_by_user_id_foreign` FOREIGN KEY (`canceled_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `supplier_payments_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `supplier_payments_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_payments`
--

LOCK TABLES `supplier_payments` WRITE;
/*!40000 ALTER TABLE `supplier_payments` DISABLE KEYS */;
INSERT INTO `supplier_payments` VALUES (1,'KWTS-22022026-0001',1,'2026-02-22',NULL,NULL,350000,'Tiga ratus lima puluh  ribu rupiah',NULL,'Admin PgPOS',NULL,1,0,NULL,NULL,NULL,'2026-02-22 09:11:05','2026-02-22 09:11:05',NULL),(2,'KWTS-28022026-0001',1,'2026-02-28',NULL,NULL,100000,'Seratus  ribu rupiah',NULL,'Admin PgPOS',NULL,1,0,NULL,NULL,NULL,'2026-02-28 00:11:49','2026-02-28 00:11:49',NULL);
/*!40000 ALTER TABLE `supplier_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_notes` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `outstanding_payable` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suppliers_name_idx` (`name`),
  KEY `suppliers_company_name_idx` (`company_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'cv cemani cato tinta','cemani cato','0342 6654456','jl karanglo karangkol malang',NULL,'tinta',360000,'2026-02-21 19:59:44','2026-02-28 00:11:49'),(2,'pt rumah cetak kita','rck','0855655445652','jl sidoarjo arjo arjoa arjo',NULL,'pak eko\r\nkertas',0,'2026-02-22 12:06:28','2026-02-22 12:06:28'),(3,'cv sinar grafindo','sinar grafindo','03335 6545556','jl soilo jalan jakarta kayakanya yang bener gatau',NULL,'no rek bca 685556524654\r\nno rek bni 65488654\r\nno rek bank jatim 65468656465463',16500,'2026-02-27 16:08:34','2026-02-27 16:09:40');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `permissions` json DEFAULT NULL,
  `locale` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'id',
  `theme` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'light',
  `finance_locked` tinyint(1) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_name_idx` (`name`),
  KEY `users_role_finance_name_idx` (`role`,`finance_locked`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin PgPOS','admin@pgpos.local',NULL,'$2y$12$acpEsp.ydtkLVcpv2zC0auW20XGrWthNsoqtqVvezpZ6j8TODB03S','admin',NULL,'id','dark',0,NULL,'2026-02-21 18:30:06','2026-03-10 16:59:51'),(2,'User PgPOS','user@pgpos.local',NULL,'$2y$12$Uv0gBZG8N9RIzF.xdKnEPOb6Y8KsGmBBi7ROwD/DO78FVc36O6HxK','user',NULL,'id','light',0,NULL,'2026-02-21 18:30:06','2026-02-21 18:30:06');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-12 18:44:08
