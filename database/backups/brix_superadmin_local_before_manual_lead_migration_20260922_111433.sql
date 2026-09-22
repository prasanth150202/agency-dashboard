-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: brix_superadmin
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `agency_id` int(10) unsigned DEFAULT NULL,
  `store_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(160) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `store_id` (`store_id`),
  KEY `idx_activity_created` (`created_at`),
  KEY `idx_activity_agency` (`agency_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_ibfk_3` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,NULL,1,NULL,'referral_link.created','{\"tracking_link_id\":1,\"code\":\"BRIX-BHSM\",\"channel\":\"WhatsApp\"}','127.0.0.1','2026-09-21 17:03:09'),(2,NULL,1,NULL,'referral_link.created','{\"tracking_link_id\":2,\"code\":\"BRIX-AGG9\",\"channel\":\"WhatsApp\"}','127.0.0.1','2026-09-21 17:03:13'),(3,NULL,1,NULL,'referral_link.deactivated','{\"tracking_link_id\":2}','127.0.0.1','2026-09-21 17:03:30');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('SUPER_ADMIN','FINANCE_ADMIN','SUPPORT_ADMIN','ANALYST') NOT NULL DEFAULT 'ANALYST',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `avatar_color` varchar(7) NOT NULL DEFAULT '#3b82f6',
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'Abilashmi Rao','admin@brix.app','$2y$10$c0fVHQaRr1uWGfR6qdk/g.75Ns.J4CroXamz94yVEmwLGR.3UM5VS','SUPER_ADMIN','active','#3b82f6','D7Xq8OgUbhIL7hbXhbEQfYqvQT3XZ4MkozKfVPQA5iZ8SbBcydcqPSiTYXl1','2026-09-22 10:47:35','2025-07-31 11:53:56','2026-09-22 10:47:35'),(2,'Rahul Mehta','finance@brix.app','$2y$10$tbRmx4/DzgjOijuYLuzoe.V.v/VcNKa03doyDgn41TmO/XSmi4Lgy','FINANCE_ADMIN','active','#22c55e',NULL,'2026-08-25 12:39:58','2025-06-01 02:26:49','2026-08-25 17:14:16'),(3,'Sneha Iyer','support@brix.app','$2y$10$dyYKfpQmo0n9hMfMCDXEZ.SnucQ.aaAKy.kus3Iyd6btDRtgWKqXK','SUPPORT_ADMIN','active','#f59e0b',NULL,'2026-08-22 08:58:44','2024-09-13 02:57:10','2026-08-25 17:14:17'),(4,'Karthik Nair','analyst@brix.app','$2y$10$hu/XlJ/zocv/FBTEYxSXfOzS3rYO3ggUR3Fv7z6UPhJQf7.oRAYei','ANALYST','active','#a855f7',NULL,'2026-08-24 17:04:02','2025-02-21 19:17:32','2026-08-25 17:14:17');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agencies`
--

DROP TABLE IF EXISTS `agencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `owner_name` varchar(120) NOT NULL,
  `owner_email` varchar(180) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `status` enum('active','trial','suspended') NOT NULL DEFAULT 'trial',
  `commission_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `commission_type` varchar(20) NOT NULL DEFAULT 'percentage',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 30.00,
  `country` varchar(60) NOT NULL DEFAULT 'India',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_agencies_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agencies`
--

LOCK TABLES `agencies` WRITE;
/*!40000 ALTER TABLE `agencies` DISABLE KEYS */;
INSERT INTO `agencies` VALUES (1,'Test Org','test-org','Test User','test@example.com',NULL,'active',1,'percentage',30.00,'India','2026-09-18 18:39:39','2026-09-18 18:39:39');
/*!40000 ALTER TABLE `agencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agency_ledger`
--

DROP TABLE IF EXISTS `agency_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agency_ledger` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agency_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned DEFAULT NULL,
  `transaction_id` int(10) unsigned DEFAULT NULL,
  `payout_id` int(10) unsigned DEFAULT NULL,
  `type` enum('COMMISSION','PAYOUT','REFUND','ADJUSTMENT','REVERSAL') NOT NULL DEFAULT 'ADJUSTMENT',
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(180) NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `payout_id` (`payout_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_ledger_agency` (`agency_id`),
  KEY `idx_ledger_created` (`created_at`),
  KEY `idx_ledger_type` (`type`),
  KEY `agency_ledger_store_id_foreign` (`store_id`),
  CONSTRAINT `agency_ledger_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agency_ledger_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `agency_ledger_ibfk_3` FOREIGN KEY (`payout_id`) REFERENCES `payouts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `agency_ledger_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `agency_ledger_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agency_ledger`
--

LOCK TABLES `agency_ledger` WRITE;
/*!40000 ALTER TABLE `agency_ledger` DISABLE KEYS */;
/*!40000 ALTER TABLE `agency_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agency_store_onboarding`
--

DROP TABLE IF EXISTS `agency_store_onboarding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agency_store_onboarding` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agency_id` int(10) unsigned NOT NULL,
  `state_token` char(64) NOT NULL,
  `shop_domain` varchar(180) NOT NULL,
  `status` enum('STARTED','AUTHORIZING','INSTALL_REQUIRED','INSTALLING','AUTHORIZED','COMPLETED','FAILED','EXPIRED') NOT NULL DEFAULT 'STARTED',
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_store_id` int(10) unsigned DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_onboarding_state` (`state_token`),
  KEY `created_store_id` (`created_store_id`),
  KEY `idx_onboarding_agency` (`agency_id`),
  KEY `idx_onboarding_shop` (`shop_domain`),
  KEY `idx_onboarding_status` (`status`),
  CONSTRAINT `agency_store_onboarding_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agency_store_onboarding_ibfk_2` FOREIGN KEY (`created_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agency_store_onboarding`
--

LOCK TABLES `agency_store_onboarding` WRITE;
/*!40000 ALTER TABLE `agency_store_onboarding` DISABLE KEYS */;
/*!40000 ALTER TABLE `agency_store_onboarding` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agency_stores`
--

DROP TABLE IF EXISTS `agency_stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agency_stores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agency_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `relationship_status` enum('PENDING','AUTHORIZED','ACTIVE','DISCONNECTED') NOT NULL DEFAULT 'PENDING',
  `authorized_at` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `disconnected_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_agency_store` (`agency_id`,`store_id`),
  KEY `idx_agency_id` (`agency_id`),
  KEY `idx_store_id` (`store_id`),
  KEY `idx_relationship_status` (`relationship_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agency_stores`
--

LOCK TABLES `agency_stores` WRITE;
/*!40000 ALTER TABLE `agency_stores` DISABLE KEYS */;
/*!40000 ALTER TABLE `agency_stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agency_users`
--

DROP TABLE IF EXISTS `agency_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agency_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agency_id` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` varchar(60) NOT NULL DEFAULT 'Member',
  `status` enum('active','invited','suspended') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_agency_users_agency` (`agency_id`),
  CONSTRAINT `agency_users_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=292 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agency_users`
--

LOCK TABLES `agency_users` WRITE;
/*!40000 ALTER TABLE `agency_users` DISABLE KEYS */;
INSERT INTO `agency_users` VALUES (1,1,'Vikram Bhatt','vikram.bhatt0@copperhead-growth.com','$2y$10$w54Z0RzqqX561yUYjn3GWOQgANPDLVVWL4G21.gwhONS9pvE92Ul6','Owner','active','2025-07-26 13:24:28'),(2,1,'Vikram Sharma','vikram.sharma1@copperhead-growth.com',NULL,'Member','active','2025-07-26 13:24:28'),(5,3,'Rohan Nair','rohan.nair0@maple-commerce.com',NULL,'Owner','active','2025-07-29 04:15:05'),(6,3,'Meera Pillai','meera.pillai1@maple-commerce.com',NULL,'Member','active','2025-07-29 04:15:05'),(7,3,'Abilashmi Menon','abilashmi.menon2@maple-commerce.com',NULL,'Member','active','2025-07-29 04:15:05'),(8,4,'Neha Joshi','neha.joshi0@bright-digital.com',NULL,'Owner','active','2025-06-12 17:48:52'),(9,4,'Riya Agarwal','riya.agarwal1@bright-digital.com',NULL,'Member','active','2025-06-12 17:48:52'),(10,5,'Divya Sharma','divya.sharma0@crescent-partners.com',NULL,'Owner','active','2025-05-07 10:08:26'),(11,5,'Kabir Agarwal','kabir.agarwal1@crescent-partners.com',NULL,'Member','active','2025-05-07 10:08:26'),(12,6,'Sneha Rao','sneha.rao0@northwind-growth.com',NULL,'Owner','active','2024-12-17 07:01:45'),(13,7,'Shreya Desai','shreya.desai0@amberly-commerce.com',NULL,'Owner','active','2025-04-24 10:16:11'),(14,8,'Ananya Chatterjee','ananya.chatterjee0@sundial-solutions.com',NULL,'Owner','active','2024-11-17 22:14:42'),(15,8,'Manish Verma','manish.verma1@sundial-solutions.com',NULL,'Member','active','2024-11-17 22:14:42'),(16,9,'Aditi Desai','aditi.desai0@maple-co.com',NULL,'Owner','active','2025-02-23 09:44:20'),(17,10,'Priya Joshi','priya.joshi0@vertex-commerce.com',NULL,'Owner','active','2025-06-21 01:03:22'),(18,10,'Divya Iyer','divya.iyer1@vertex-commerce.com',NULL,'Member','active','2025-06-21 01:03:22'),(19,10,'Priya Gupta','priya.gupta2@vertex-commerce.com',NULL,'Member','active','2025-06-21 01:03:22'),(20,11,'Aarav Desai','aarav.desai0@velocity-labs.com',NULL,'Owner','active','2024-08-27 01:58:03'),(21,12,'Rohan Chawla','rohan.chawla0@vertex-growth.com',NULL,'Owner','active','2025-07-26 14:30:13'),(22,13,'Sneha Agarwal','sneha.agarwal0@sundial-commerce.com',NULL,'Owner','active','2025-07-06 15:34:44'),(23,13,'Nikhil Kulkarni','nikhil.kulkarni1@sundial-commerce.com',NULL,'Member','active','2025-07-06 15:34:44'),(24,13,'Aditi Sharma','aditi.sharma2@sundial-commerce.com',NULL,'Member','active','2025-07-06 15:34:44'),(25,14,'Nikhil Verma','nikhil.verma0@crescent-solutions.com',NULL,'Owner','active','2024-09-28 14:07:53'),(26,15,'Divya Menon','divya.menon0@crescent-agency.com',NULL,'Owner','active','2024-12-31 01:56:45'),(27,15,'Tanvi Agarwal','tanvi.agarwal1@crescent-agency.com',NULL,'Member','active','2024-12-31 01:56:45'),(28,16,'Riya Kapoor','riya.kapoor0@copperhead-collective.com',NULL,'Owner','active','2025-06-24 01:43:57'),(29,16,'Kavya Verma','kavya.verma1@copperhead-collective.com',NULL,'Member','active','2025-06-24 01:43:57'),(30,16,'Vivaan Joshi','vivaan.joshi2@copperhead-collective.com',NULL,'Member','active','2025-06-24 01:43:57'),(31,17,'Aditya Agarwal','aditya.agarwal0@crescent-growth.com',NULL,'Owner','active','2024-12-27 03:24:02'),(32,17,'Nikhil Nair','nikhil.nair1@crescent-growth.com',NULL,'Member','active','2024-12-27 03:24:02'),(33,17,'Kabir Reddy','kabir.reddy2@crescent-growth.com',NULL,'Member','active','2024-12-27 03:24:02'),(34,18,'Ananya Joshi','ananya.joshi0@velocity-digital.com',NULL,'Owner','active','2025-03-16 23:47:38'),(35,18,'Ananya Kulkarni','ananya.kulkarni1@velocity-digital.com',NULL,'Member','active','2025-03-16 23:47:38'),(36,18,'Kavya Gupta','kavya.gupta2@velocity-digital.com',NULL,'Member','active','2025-03-16 23:47:38'),(37,19,'Divya Bose','divya.bose0@pixel-collective.com',NULL,'Owner','active','2024-10-26 02:24:14'),(38,19,'Vikram Chatterjee','vikram.chatterjee1@pixel-collective.com',NULL,'Member','active','2024-10-26 02:24:14'),(39,20,'Nikhil Nair','nikhil.nair0@crescent-agency.com',NULL,'Owner','active','2025-05-20 11:18:06'),(40,21,'Divya Sharma','divya.sharma0@velocity-works.com',NULL,'Owner','active','2024-11-10 20:20:12'),(41,22,'Shreya Reddy','shreya.reddy0@silverline-ventures.com',NULL,'Owner','active','2024-11-06 15:58:30'),(42,22,'Ishaan Joshi','ishaan.joshi1@silverline-ventures.com',NULL,'Member','active','2024-11-06 15:58:30'),(43,22,'Aarav Nair','aarav.nair2@silverline-ventures.com',NULL,'Member','active','2024-11-06 15:58:30'),(44,23,'Abilashmi Kapoor','abilashmi.kapoor0@crescent-growth.com',NULL,'Owner','active','2024-12-29 02:06:01'),(45,23,'Diya Verma','diya.verma1@crescent-growth.com',NULL,'Member','active','2024-12-29 02:06:01'),(46,23,'Saanvi Mehta','saanvi.mehta2@crescent-growth.com',NULL,'Member','active','2024-12-29 02:06:01'),(47,24,'Nikhil Agarwal','nikhil.agarwal0@maple-collective.com',NULL,'Owner','active','2025-08-05 22:33:51'),(48,24,'Arjun Desai','arjun.desai1@maple-collective.com',NULL,'Member','active','2025-08-05 22:33:51'),(49,25,'Karthik Desai','karthik.desai0@granite-group.com',NULL,'Owner','active','2025-08-09 05:43:55'),(50,26,'Rohan Reddy','rohan.reddy0@ironclad-collective.com',NULL,'Owner','active','2025-05-12 20:59:19'),(51,26,'Sanjana Joshi','sanjana.joshi1@ironclad-collective.com',NULL,'Member','active','2025-05-12 20:59:19'),(52,27,'Sneha Kapoor','sneha.kapoor0@silverline-collective.com',NULL,'Owner','active','2024-10-23 07:46:11'),(53,28,'Shreya Desai','shreya.desai0@sundial-labs.com',NULL,'Owner','active','2025-02-11 10:51:37'),(54,28,'Vivaan Desai','vivaan.desai1@sundial-labs.com',NULL,'Member','active','2025-02-11 10:51:37'),(55,29,'Rahul Verma','rahul.verma0@pixel-growth.com',NULL,'Owner','active','2025-06-17 12:37:17'),(56,29,'Vivaan Reddy','vivaan.reddy1@pixel-growth.com',NULL,'Member','active','2025-06-17 12:37:17'),(57,29,'Aditya Desai','aditya.desai2@pixel-growth.com',NULL,'Member','active','2025-06-17 12:37:17'),(58,30,'Divya Chatterjee','divya.chatterjee0@silverline-media.com',NULL,'Owner','active','2024-12-13 17:09:33'),(59,30,'Priya Reddy','priya.reddy1@silverline-media.com',NULL,'Member','active','2024-12-13 17:09:33'),(60,31,'Nikhil Menon','nikhil.menon0@skyline-works.com',NULL,'Owner','active','2025-04-16 06:20:29'),(61,31,'Tanvi Gupta','tanvi.gupta1@skyline-works.com',NULL,'Member','active','2025-04-16 06:20:29'),(62,31,'Ananya Desai','ananya.desai2@skyline-works.com',NULL,'Member','active','2025-04-16 06:20:29'),(63,32,'Divya Rao','divya.rao0@firefly-group.com',NULL,'Owner','active','2025-08-06 06:39:20'),(64,32,'Varun Kulkarni','varun.kulkarni1@firefly-group.com',NULL,'Member','active','2025-08-06 06:39:20'),(65,33,'Saanvi Mehta','saanvi.mehta0@firefly-solutions.com',NULL,'Owner','active','2025-02-15 21:16:47'),(66,33,'Manish Verma','manish.verma1@firefly-solutions.com',NULL,'Member','active','2025-02-15 21:16:47'),(67,33,'Arjun Kulkarni','arjun.kulkarni2@firefly-solutions.com',NULL,'Member','active','2025-02-15 21:16:47'),(68,34,'Diya Desai','diya.desai0@summit-studio.com',NULL,'Owner','active','2025-08-18 08:17:03'),(69,35,'Arjun Menon','arjun.menon0@granite-collective.com',NULL,'Owner','active','2024-09-15 16:12:54'),(70,35,'Vikram Desai','vikram.desai1@granite-collective.com',NULL,'Member','active','2024-09-15 16:12:54'),(71,35,'Yash Bhatt','yash.bhatt2@granite-collective.com',NULL,'Member','active','2024-09-15 16:12:54'),(72,36,'Arjun Malhotra','arjun.malhotra0@falcon-media.com',NULL,'Owner','active','2024-09-14 20:50:50'),(73,36,'Yash Mehta','yash.mehta1@falcon-media.com',NULL,'Member','active','2024-09-14 20:50:50'),(74,37,'Rahul Gupta','rahul.gupta0@cedarwood-labs.com',NULL,'Owner','active','2024-10-28 04:43:30'),(75,38,'Ishaan Bhatt','ishaan.bhatt0@velocity-partners.com',NULL,'Owner','active','2024-11-12 13:49:31'),(76,38,'Yash Bhatt','yash.bhatt1@velocity-partners.com',NULL,'Member','active','2024-11-12 13:49:31'),(77,38,'Pooja Chatterjee','pooja.chatterjee2@velocity-partners.com',NULL,'Member','active','2024-11-12 13:49:31'),(78,39,'Shreya Sharma','shreya.sharma0@harbor-collective.com',NULL,'Owner','active','2025-01-01 07:05:11'),(79,40,'Tanvi Rao','tanvi.rao0@willowbrook-labs.com',NULL,'Owner','active','2024-09-10 06:05:34'),(80,40,'Varun Nair','varun.nair1@willowbrook-labs.com',NULL,'Member','active','2024-09-10 06:05:34'),(81,40,'Ishaan Kapoor','ishaan.kapoor2@willowbrook-labs.com',NULL,'Member','active','2024-09-10 06:05:34'),(82,41,'Aditi Verma','aditi.verma0@granite-media.com',NULL,'Owner','active','2025-08-23 04:49:43'),(83,41,'Manish Bose','manish.bose1@granite-media.com',NULL,'Member','active','2025-08-23 04:49:43'),(84,42,'Riya Mehta','riya.mehta0@everline-ventures.com',NULL,'Owner','active','2025-03-26 05:32:37'),(85,42,'Sanjana Desai','sanjana.desai1@everline-ventures.com',NULL,'Member','active','2025-03-26 05:32:37'),(86,43,'Rahul Joshi','rahul.joshi0@northwind-growth.com',NULL,'Owner','active','2025-03-23 16:36:29'),(87,43,'Ishaan Chawla','ishaan.chawla1@northwind-growth.com',NULL,'Member','active','2025-03-23 16:36:29'),(88,43,'Meera Pillai','meera.pillai2@northwind-growth.com',NULL,'Member','active','2025-03-23 16:36:29'),(89,44,'Sneha Kulkarni','sneha.kulkarni0@bright-collective.com',NULL,'Owner','active','2025-03-28 10:22:03'),(90,44,'Manish Joshi','manish.joshi1@bright-collective.com',NULL,'Member','active','2025-03-28 10:22:03'),(91,44,'Aditya Mehta','aditya.mehta2@bright-collective.com',NULL,'Member','active','2025-03-28 10:22:03'),(92,45,'Karthik Mehta','karthik.mehta0@vertex-collective.com',NULL,'Owner','active','2024-12-05 01:58:12'),(93,46,'Karthik Rao','karthik.rao0@falcon-growth.com',NULL,'Owner','active','2025-04-25 17:23:53'),(94,47,'Shreya Kulkarni','shreya.kulkarni0@pixel-partners.com',NULL,'Owner','active','2024-11-02 23:20:51'),(95,47,'Aditya Sharma','aditya.sharma1@pixel-partners.com',NULL,'Member','active','2024-11-02 23:20:51'),(96,48,'Saanvi Chawla','saanvi.chawla0@pixel-house.com',NULL,'Owner','active','2024-10-17 02:28:00'),(97,48,'Priya Malhotra','priya.malhotra1@pixel-house.com',NULL,'Member','active','2024-10-17 02:28:00'),(98,49,'Meera Kapoor','meera.kapoor0@crescent-media.com',NULL,'Owner','active','2025-03-19 12:50:04'),(99,50,'Priya Reddy','priya.reddy0@growth-labs.com',NULL,'Owner','active','2025-07-24 20:25:12'),(100,50,'Yash Bose','yash.bose1@growth-labs.com',NULL,'Member','active','2025-07-24 20:25:12'),(101,51,'Shreya Mehta','shreya.mehta0@everline-collective.com',NULL,'Owner','active','2025-01-03 23:52:02'),(102,51,'Saanvi Pillai','saanvi.pillai1@everline-collective.com',NULL,'Member','active','2025-01-03 23:52:02'),(103,51,'Kavya Agarwal','kavya.agarwal2@everline-collective.com',NULL,'Member','active','2025-01-03 23:52:02'),(104,52,'Karthik Menon','karthik.menon0@pixel-group.com',NULL,'Owner','active','2025-07-23 17:25:17'),(105,52,'Vivaan Menon','vivaan.menon1@pixel-group.com',NULL,'Member','active','2025-07-23 17:25:17'),(106,52,'Nikhil Gupta','nikhil.gupta2@pixel-group.com',NULL,'Member','active','2025-07-23 17:25:17'),(107,53,'Riya Verma','riya.verma0@everline-ventures.com',NULL,'Owner','active','2024-11-07 14:19:40'),(108,53,'Diya Gupta','diya.gupta1@everline-ventures.com',NULL,'Member','active','2024-11-07 14:19:40'),(109,54,'Ananya Mehta','ananya.mehta0@bluewave-house.com',NULL,'Owner','active','2024-12-25 05:53:10'),(110,54,'Diya Joshi','diya.joshi1@bluewave-house.com',NULL,'Member','active','2024-12-25 05:53:10'),(111,55,'Rohan Bhatt','rohan.bhatt0@harbor-collective.com',NULL,'Owner','active','2025-08-15 12:25:30'),(112,55,'Shreya Kulkarni','shreya.kulkarni1@harbor-collective.com',NULL,'Member','active','2025-08-15 12:25:30'),(113,56,'Diya Malhotra','diya.malhotra0@vertex-co.com',NULL,'Owner','active','2025-01-06 11:49:52'),(114,56,'Sneha Rao','sneha.rao1@vertex-co.com',NULL,'Member','active','2025-01-06 11:49:52'),(115,57,'Sneha Agarwal','sneha.agarwal0@orchid-growth.com',NULL,'Owner','active','2024-10-23 19:19:04'),(116,57,'Aditi Kulkarni','aditi.kulkarni1@orchid-growth.com',NULL,'Member','active','2024-10-23 19:19:04'),(117,57,'Nikhil Gupta','nikhil.gupta2@orchid-growth.com',NULL,'Member','active','2024-10-23 19:19:04'),(118,58,'Shreya Gupta','shreya.gupta0@anchor-labs.com',NULL,'Owner','active','2025-05-19 20:06:47'),(119,58,'Aarav Bose','aarav.bose1@anchor-labs.com',NULL,'Member','active','2025-05-19 20:06:47'),(120,59,'Vikram Bhatt','vikram.bhatt0@amberly-partners.com',NULL,'Owner','active','2025-02-24 14:02:21'),(121,60,'Rohan Verma','rohan.verma0@pixel-co.com',NULL,'Owner','active','2025-03-25 09:12:52'),(122,61,'Diya Rao','diya.rao0@willowbrook-collective.com',NULL,'Owner','active','2024-11-16 17:35:35'),(123,62,'Aditya Reddy','aditya.reddy0@firefly-digital.com',NULL,'Owner','active','2024-09-15 04:27:41'),(124,62,'Kabir Iyer','kabir.iyer1@firefly-digital.com',NULL,'Member','active','2024-09-15 04:27:41'),(125,63,'Yash Iyer','yash.iyer0@velocity-agency.com',NULL,'Owner','active','2025-02-14 06:36:32'),(126,64,'Nikhil Menon','nikhil.menon0@silverline-growth.com',NULL,'Owner','active','2024-11-12 21:43:52'),(127,65,'Rahul Iyer','rahul.iyer0@bright-commerce.com',NULL,'Owner','active','2025-07-16 20:34:13'),(128,65,'Shreya Agarwal','shreya.agarwal1@bright-commerce.com',NULL,'Member','active','2025-07-16 20:34:13'),(129,66,'Aditi Mehta','aditi.mehta0@nova-ventures.com',NULL,'Owner','active','2024-10-30 09:58:04'),(130,66,'Rohan Menon','rohan.menon1@nova-ventures.com',NULL,'Member','active','2024-10-30 09:58:04'),(131,66,'Aditya Kapoor','aditya.kapoor2@nova-ventures.com',NULL,'Member','active','2024-10-30 09:58:04'),(132,67,'Vikram Sharma','vikram.sharma0@amberly-co.com',NULL,'Owner','active','2024-12-15 10:53:45'),(133,67,'Ananya Menon','ananya.menon1@amberly-co.com',NULL,'Member','active','2024-12-15 10:53:45'),(134,68,'Abilashmi Bose','abilashmi.bose0@orchid-media.com',NULL,'Owner','active','2025-01-10 14:44:53'),(135,68,'Yash Rao','yash.rao1@orchid-media.com',NULL,'Member','active','2025-01-10 14:44:53'),(136,69,'Arjun Mehta','arjun.mehta0@growth-collective.com',NULL,'Owner','active','2025-07-26 03:01:53'),(137,69,'Aditya Verma','aditya.verma1@growth-collective.com',NULL,'Member','active','2025-07-26 03:01:53'),(138,70,'Karthik Reddy','karthik.reddy0@meridian-solutions.com',NULL,'Owner','active','2025-02-26 15:05:45'),(139,71,'Divya Joshi','divya.joshi0@anchor-solutions.com',NULL,'Owner','active','2025-05-17 01:31:39'),(140,71,'Shreya Agarwal','shreya.agarwal1@anchor-solutions.com',NULL,'Member','active','2025-05-17 01:31:39'),(141,72,'Rahul Bose','rahul.bose0@sundial-media.com',NULL,'Owner','active','2025-05-05 22:54:25'),(142,72,'Vivaan Reddy','vivaan.reddy1@sundial-media.com',NULL,'Member','active','2025-05-05 22:54:25'),(143,73,'Arjun Joshi','arjun.joshi0@silverline-partners.com',NULL,'Owner','active','2025-03-20 12:40:46'),(144,73,'Vivaan Pillai','vivaan.pillai1@silverline-partners.com',NULL,'Member','active','2025-03-20 12:40:46'),(145,74,'Yash Chatterjee','yash.chatterjee0@falcon-ventures.com',NULL,'Owner','active','2024-10-16 04:22:16'),(146,74,'Vivaan Iyer','vivaan.iyer1@falcon-ventures.com',NULL,'Member','active','2024-10-16 04:22:16'),(147,75,'Aditi Chatterjee','aditi.chatterjee0@ironclad-partners.com',NULL,'Owner','active','2025-01-21 01:28:03'),(148,76,'Neha Rao','neha.rao0@bright-digital.com',NULL,'Owner','active','2024-10-15 03:58:12'),(149,76,'Vikram Kulkarni','vikram.kulkarni1@bright-digital.com',NULL,'Member','active','2024-10-15 03:58:12'),(150,77,'Arjun Bhatt','arjun.bhatt0@lumen-works.com',NULL,'Owner','active','2024-10-23 15:12:24'),(151,77,'Pooja Chatterjee','pooja.chatterjee1@lumen-works.com',NULL,'Member','active','2024-10-23 15:12:24'),(152,78,'Ananya Sharma','ananya.sharma0@meridian-collective.com',NULL,'Owner','active','2025-02-07 16:31:50'),(153,79,'Sneha Joshi','sneha.joshi0@cobalt-media.com',NULL,'Owner','active','2024-11-01 07:57:13'),(154,80,'Diya Bose','diya.bose0@lumen-partners.com',NULL,'Owner','active','2024-11-06 23:42:20'),(155,80,'Rohan Desai','rohan.desai1@lumen-partners.com',NULL,'Member','active','2024-11-06 23:42:20'),(156,80,'Rahul Pillai','rahul.pillai2@lumen-partners.com',NULL,'Member','active','2024-11-06 23:42:20'),(157,81,'Nikhil Kulkarni','nikhil.kulkarni0@anchor-media.com',NULL,'Owner','active','2025-06-30 17:28:35'),(158,82,'Ananya Agarwal','ananya.agarwal0@crescent-agency.com',NULL,'Owner','active','2024-11-16 00:42:16'),(159,83,'Meera Pillai','meera.pillai0@summit-media.com',NULL,'Owner','active','2024-12-28 06:42:26'),(160,83,'Abilashmi Bose','abilashmi.bose1@summit-media.com',NULL,'Member','active','2024-12-28 06:42:26'),(161,83,'Priya Gupta','priya.gupta2@summit-media.com',NULL,'Member','active','2024-12-28 06:42:26'),(162,84,'Riya Reddy','riya.reddy0@pixel-group.com',NULL,'Owner','active','2025-06-20 09:35:07'),(163,85,'Rahul Bhatt','rahul.bhatt0@everline-ventures.com',NULL,'Owner','active','2024-09-19 08:52:00'),(164,85,'Rahul Chatterjee','rahul.chatterjee1@everline-ventures.com',NULL,'Member','active','2024-09-19 08:52:00'),(165,86,'Divya Gupta','divya.gupta0@cobalt-works.com',NULL,'Owner','active','2025-05-13 11:09:36'),(166,86,'Aditi Menon','aditi.menon1@cobalt-works.com',NULL,'Member','active','2025-05-13 11:09:36'),(167,87,'Abilashmi Bose','abilashmi.bose0@copperhead-partners.com',NULL,'Owner','active','2025-06-28 05:20:44'),(168,88,'Vivaan Menon','vivaan.menon0@lumen-partners.com',NULL,'Owner','active','2024-11-25 02:04:23'),(169,88,'Vikram Desai','vikram.desai1@lumen-partners.com',NULL,'Member','active','2024-11-25 02:04:23'),(170,89,'Priya Bose','priya.bose0@silverline-labs.com',NULL,'Owner','active','2025-07-10 13:00:35'),(171,89,'Riya Desai','riya.desai1@silverline-labs.com',NULL,'Member','active','2025-07-10 13:00:35'),(172,90,'Saanvi Kapoor','saanvi.kapoor0@meridian-digital.com',NULL,'Owner','active','2025-05-30 05:04:11'),(173,90,'Karthik Chatterjee','karthik.chatterjee1@meridian-digital.com',NULL,'Member','active','2025-05-30 05:04:11'),(174,90,'Yash Agarwal','yash.agarwal2@meridian-digital.com',NULL,'Member','active','2025-05-30 05:04:11'),(175,91,'Nikhil Iyer','nikhil.iyer0@crescent-house.com',NULL,'Owner','active','2024-09-25 03:29:01'),(176,91,'Riya Reddy','riya.reddy1@crescent-house.com',NULL,'Member','active','2024-09-25 03:29:01'),(177,91,'Divya Kulkarni','divya.kulkarni2@crescent-house.com',NULL,'Member','active','2024-09-25 03:29:01'),(178,92,'Nikhil Kapoor','nikhil.kapoor0@everline-media.com',NULL,'Owner','active','2024-09-24 16:46:19'),(179,92,'Nikhil Reddy','nikhil.reddy1@everline-media.com',NULL,'Member','active','2024-09-24 16:46:19'),(180,93,'Manish Iyer','manish.iyer0@amberly-agency.com',NULL,'Owner','active','2025-08-20 06:21:58'),(181,93,'Riya Rao','riya.rao1@amberly-agency.com',NULL,'Member','active','2025-08-20 06:21:58'),(182,93,'Rohan Desai','rohan.desai2@amberly-agency.com',NULL,'Member','active','2025-08-20 06:21:58'),(183,94,'Nikhil Verma','nikhil.verma0@velocity-studio.com',NULL,'Owner','active','2025-07-06 21:30:01'),(184,95,'Divya Kulkarni','divya.kulkarni0@lumen-works.com',NULL,'Owner','active','2024-10-13 03:13:51'),(185,95,'Meera Reddy','meera.reddy1@lumen-works.com',NULL,'Member','active','2024-10-13 03:13:51'),(186,95,'Yash Sharma','yash.sharma2@lumen-works.com',NULL,'Member','active','2024-10-13 03:13:51'),(187,96,'Rohan Malhotra','rohan.malhotra0@pixel-group.com',NULL,'Owner','active','2024-10-14 11:05:09'),(188,96,'Ishaan Kulkarni','ishaan.kulkarni1@pixel-group.com',NULL,'Member','active','2024-10-14 11:05:09'),(189,97,'Sneha Mehta','sneha.mehta0@summit-digital.com',NULL,'Owner','active','2025-08-03 21:26:39'),(190,97,'Manish Chawla','manish.chawla1@summit-digital.com',NULL,'Member','active','2025-08-03 21:26:39'),(191,97,'Karthik Desai','karthik.desai2@summit-digital.com',NULL,'Member','active','2025-08-03 21:26:39'),(192,98,'Tanvi Bhatt','tanvi.bhatt0@ironclad-agency.com',NULL,'Owner','active','2025-03-31 08:52:50'),(193,99,'Shreya Desai','shreya.desai0@harbor-studio.com',NULL,'Owner','active','2024-11-14 20:15:47'),(194,100,'Saanvi Menon','saanvi.menon0@summit-collective.com',NULL,'Owner','active','2024-10-11 20:07:57'),(195,100,'Ishaan Iyer','ishaan.iyer1@summit-collective.com',NULL,'Member','active','2024-10-11 20:07:57'),(196,101,'Manish Desai','manish.desai0@bright-digital.com',NULL,'Owner','active','2025-02-20 22:42:28'),(197,102,'Nikhil Menon','nikhil.menon0@cobalt-group.com',NULL,'Owner','active','2025-02-04 18:10:12'),(198,103,'Ishaan Joshi','ishaan.joshi0@pixel-collective.com',NULL,'Owner','active','2025-01-23 19:32:49'),(199,103,'Pooja Sharma','pooja.sharma1@pixel-collective.com',NULL,'Member','active','2025-01-23 19:32:49'),(200,104,'Karthik Nair','karthik.nair0@bright-group.com',NULL,'Owner','active','2025-03-03 13:47:46'),(201,104,'Rohan Chawla','rohan.chawla1@bright-group.com',NULL,'Member','active','2025-03-03 13:47:46'),(202,104,'Kavya Mehta','kavya.mehta2@bright-group.com',NULL,'Member','active','2025-03-03 13:47:46'),(203,105,'Vivaan Reddy','vivaan.reddy0@maple-media.com',NULL,'Owner','active','2024-10-27 06:50:39'),(204,106,'Tanvi Pillai','tanvi.pillai0@ironclad-studio.com',NULL,'Owner','active','2025-07-02 22:28:01'),(205,107,'Divya Kapoor','divya.kapoor0@ironclad-co.com',NULL,'Owner','active','2024-09-30 03:24:24'),(206,108,'Yash Menon','yash.menon0@redstone-media.com',NULL,'Owner','active','2024-09-29 09:49:40'),(207,109,'Diya Verma','diya.verma0@crescent-partners.com',NULL,'Owner','active','2024-12-10 02:12:21'),(208,109,'Rohan Joshi','rohan.joshi1@crescent-partners.com',NULL,'Member','active','2024-12-10 02:12:21'),(209,109,'Kavya Bhatt','kavya.bhatt2@crescent-partners.com',NULL,'Member','active','2024-12-10 02:12:21'),(210,110,'Aditi Verma','aditi.verma0@amberly-group.com',NULL,'Owner','active','2025-03-25 09:55:21'),(211,110,'Kabir Kapoor','kabir.kapoor1@amberly-group.com',NULL,'Member','active','2025-03-25 09:55:21'),(212,111,'Priya Mehta','priya.mehta0@pixel-ventures.com',NULL,'Owner','active','2025-06-28 20:42:56'),(213,111,'Aditi Rao','aditi.rao1@pixel-ventures.com',NULL,'Member','active','2025-06-28 20:42:56'),(214,111,'Sneha Kapoor','sneha.kapoor2@pixel-ventures.com',NULL,'Member','active','2025-06-28 20:42:56'),(215,112,'Rahul Rao','rahul.rao0@falcon-works.com',NULL,'Owner','active','2025-03-09 12:19:25'),(216,112,'Abilashmi Kulkarni','abilashmi.kulkarni1@falcon-works.com',NULL,'Member','active','2025-03-09 12:19:25'),(217,113,'Shreya Iyer','shreya.iyer0@falcon-media.com',NULL,'Owner','active','2025-06-30 08:25:12'),(218,113,'Saanvi Bose','saanvi.bose1@falcon-media.com',NULL,'Member','active','2025-06-30 08:25:12'),(219,114,'Nikhil Menon','nikhil.menon0@skyline-agency.com',NULL,'Owner','active','2024-09-14 06:09:05'),(220,115,'Vivaan Reddy','vivaan.reddy0@silverline-ventures.com',NULL,'Owner','active','2025-05-16 21:10:44'),(221,115,'Aditi Menon','aditi.menon1@silverline-ventures.com',NULL,'Member','active','2025-05-16 21:10:44'),(222,115,'Sanjana Kapoor','sanjana.kapoor2@silverline-ventures.com',NULL,'Member','active','2025-05-16 21:10:44'),(223,116,'Priya Chatterjee','priya.chatterjee0@copperhead-agency.com',NULL,'Owner','active','2024-11-16 22:45:16'),(224,116,'Rohan Joshi','rohan.joshi1@copperhead-agency.com',NULL,'Member','active','2024-11-16 22:45:16'),(225,116,'Shreya Menon','shreya.menon2@copperhead-agency.com',NULL,'Member','active','2024-11-16 22:45:16'),(226,117,'Nikhil Chatterjee','nikhil.chatterjee0@firefly-co.com',NULL,'Owner','active','2025-03-24 20:37:03'),(227,117,'Riya Mehta','riya.mehta1@firefly-co.com',NULL,'Member','active','2025-03-24 20:37:03'),(228,118,'Priya Malhotra','priya.malhotra0@redstone-house.com',NULL,'Owner','active','2025-08-04 19:01:23'),(229,118,'Yash Chatterjee','yash.chatterjee1@redstone-house.com',NULL,'Member','active','2025-08-04 19:01:23'),(230,118,'Abilashmi Reddy','abilashmi.reddy2@redstone-house.com',NULL,'Member','active','2025-08-04 19:01:23'),(231,119,'Diya Desai','diya.desai0@granite-media.com',NULL,'Owner','active','2024-09-20 14:02:26'),(232,120,'Ishaan Menon','ishaan.menon0@cobalt-ventures.com',NULL,'Owner','active','2024-12-15 18:03:03'),(233,120,'Neha Gupta','neha.gupta1@cobalt-ventures.com',NULL,'Member','active','2024-12-15 18:03:03'),(234,121,'Aditya Kulkarni','aditya.kulkarni0@copperhead-labs.com',NULL,'Owner','active','2025-01-02 10:50:28'),(235,122,'Vikram Chawla','vikram.chawla0@meridian-group.com',NULL,'Owner','active','2024-09-11 17:24:24'),(236,122,'Ishaan Rao','ishaan.rao1@meridian-group.com',NULL,'Member','active','2024-09-11 17:24:24'),(237,122,'Karthik Chatterjee','karthik.chatterjee2@meridian-group.com',NULL,'Member','active','2024-09-11 17:24:24'),(238,123,'Shreya Pillai','shreya.pillai0@ironclad-media.com',NULL,'Owner','active','2024-09-05 07:34:25'),(239,123,'Aditi Chatterjee','aditi.chatterjee1@ironclad-media.com',NULL,'Member','active','2024-09-05 07:34:25'),(240,124,'Shreya Nair','shreya.nair0@granite-solutions.com',NULL,'Owner','active','2025-07-21 18:59:33'),(241,125,'Karthik Agarwal','karthik.agarwal0@vertex-agency.com',NULL,'Owner','active','2025-06-23 16:27:46'),(242,125,'Sneha Chawla','sneha.chawla1@vertex-agency.com',NULL,'Member','active','2025-06-23 16:27:46'),(243,126,'Arjun Malhotra','arjun.malhotra0@everline-partners.com',NULL,'Owner','active','2024-10-28 15:36:54'),(244,126,'Divya Iyer','divya.iyer1@everline-partners.com',NULL,'Member','active','2024-10-28 15:36:54'),(245,127,'Priya Desai','priya.desai0@granite-media.com',NULL,'Owner','active','2025-08-13 05:53:08'),(246,127,'Diya Agarwal','diya.agarwal1@granite-media.com',NULL,'Member','active','2025-08-13 05:53:08'),(247,127,'Abilashmi Desai','abilashmi.desai2@granite-media.com',NULL,'Member','active','2025-08-13 05:53:08'),(248,128,'Sanjana Reddy','sanjana.reddy0@northwind-co.com',NULL,'Owner','active','2024-12-01 13:35:32'),(249,129,'Yash Reddy','yash.reddy0@meridian-works.com',NULL,'Owner','active','2025-02-15 04:06:26'),(250,129,'Yash Kapoor','yash.kapoor1@meridian-works.com',NULL,'Member','active','2025-02-15 04:06:26'),(251,129,'Ishaan Verma','ishaan.verma2@meridian-works.com',NULL,'Member','active','2025-02-15 04:06:26'),(252,130,'Vivaan Agarwal','vivaan.agarwal0@sundial-solutions.com',NULL,'Owner','active','2025-01-10 00:48:40'),(253,130,'Ananya Gupta','ananya.gupta1@sundial-solutions.com',NULL,'Member','active','2025-01-10 00:48:40'),(254,131,'Sanjana Reddy','sanjana.reddy0@summit-house.com',NULL,'Owner','active','2024-08-30 08:39:37'),(255,132,'Karthik Chatterjee','karthik.chatterjee0@sundial-agency.com',NULL,'Owner','active','2024-09-10 14:45:07'),(256,133,'Karthik Kulkarni','karthik.kulkarni0@ironclad-labs.com',NULL,'Owner','active','2025-02-05 08:57:15'),(257,133,'Rahul Kulkarni','rahul.kulkarni1@ironclad-labs.com',NULL,'Member','active','2025-02-05 08:57:15'),(258,134,'Riya Chatterjee','riya.chatterjee0@orchid-works.com',NULL,'Owner','active','2025-06-07 15:08:12'),(259,134,'Pooja Joshi','pooja.joshi1@orchid-works.com',NULL,'Member','active','2025-06-07 15:08:12'),(260,134,'Aarav Chatterjee','aarav.chatterjee2@orchid-works.com',NULL,'Member','active','2025-06-07 15:08:12'),(261,135,'Priya Agarwal','priya.agarwal0@orchid-studio.com',NULL,'Owner','active','2025-08-19 07:17:37'),(262,135,'Riya Chatterjee','riya.chatterjee1@orchid-studio.com',NULL,'Member','active','2025-08-19 07:17:37'),(263,135,'Neha Menon','neha.menon2@orchid-studio.com',NULL,'Member','active','2025-08-19 07:17:37'),(264,136,'Sanjana Sharma','sanjana.sharma0@skyline-growth.com',NULL,'Owner','active','2024-12-15 18:53:06'),(265,137,'Aditya Chatterjee','aditya.chatterjee0@silverline-collective.com',NULL,'Owner','active','2024-09-02 19:26:15'),(266,137,'Rohan Malhotra','rohan.malhotra1@silverline-collective.com',NULL,'Member','active','2024-09-02 19:26:15'),(267,137,'Sanjana Reddy','sanjana.reddy2@silverline-collective.com',NULL,'Member','active','2024-09-02 19:26:15'),(268,138,'Ishaan Bhatt','ishaan.bhatt0@bright-co.com',NULL,'Owner','active','2025-02-16 01:23:45'),(269,139,'Ananya Sharma','ananya.sharma0@summit-group.com',NULL,'Owner','active','2024-09-25 18:26:49'),(270,139,'Ananya Gupta','ananya.gupta1@summit-group.com',NULL,'Member','active','2024-09-25 18:26:49'),(271,139,'Divya Nair','divya.nair2@summit-group.com',NULL,'Member','active','2024-09-25 18:26:49'),(272,140,'Divya Gupta','divya.gupta0@maple-solutions.com',NULL,'Owner','active','2025-03-16 15:34:17'),(273,141,'Karthik Kapoor','karthik.kapoor0@bright-commerce.com',NULL,'Owner','active','2025-07-10 09:48:00'),(274,141,'Abilashmi Joshi','abilashmi.joshi1@bright-commerce.com',NULL,'Member','active','2025-07-10 09:48:00'),(275,141,'Rahul Malhotra','rahul.malhotra2@bright-commerce.com',NULL,'Member','active','2025-07-10 09:48:00'),(276,142,'Nikhil Reddy','nikhil.reddy0@harbor-commerce.com',NULL,'Owner','active','2025-02-16 19:34:44'),(277,143,'Rahul Chatterjee','rahul.chatterjee0@willowbrook-commerce.com',NULL,'Owner','active','2025-02-24 06:32:03'),(278,144,'Meera Kapoor','meera.kapoor0@maple-digital.com',NULL,'Owner','active','2025-02-07 13:25:23'),(279,145,'Nikhil Chatterjee','nikhil.chatterjee0@orchid-solutions.com',NULL,'Owner','active','2024-12-04 06:59:54'),(280,146,'Divya Reddy','divya.reddy0@bluewave-commerce.com',NULL,'Owner','active','2024-10-16 05:00:06'),(281,147,'Divya Bose','divya.bose0@pixel-group.com',NULL,'Owner','active','2024-11-09 16:40:19'),(282,147,'Tanvi Pillai','tanvi.pillai1@pixel-group.com',NULL,'Member','active','2024-11-09 16:40:19'),(283,147,'Karthik Mehta','karthik.mehta2@pixel-group.com',NULL,'Member','active','2024-11-09 16:40:19'),(284,148,'Karthik Agarwal','karthik.agarwal0@skyline-agency.com',NULL,'Owner','active','2025-06-19 06:43:21'),(285,148,'Karthik Pillai','karthik.pillai1@skyline-agency.com',NULL,'Member','active','2025-06-19 06:43:21'),(286,149,'Nikhil Chatterjee','nikhil.chatterjee0@summit-group.com',NULL,'Owner','active','2025-02-02 06:55:02'),(287,149,'Riya Bhatt','riya.bhatt1@summit-group.com',NULL,'Member','active','2025-02-02 06:55:02'),(288,149,'Karthik Desai','karthik.desai2@summit-group.com',NULL,'Member','active','2025-02-02 06:55:02'),(289,150,'Abilashmi Desai','abilashmi.desai0@velocity-group.com',NULL,'Owner','active','2024-11-25 17:22:58'),(290,150,'Manish Sharma','manish.sharma1@velocity-group.com',NULL,'Member','active','2024-11-25 17:22:58'),(291,150,'Riya Kapoor','riya.kapoor2@velocity-group.com',NULL,'Member','active','2024-11-25 17:22:58');
/*!40000 ALTER TABLE `agency_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_settings` (
  `setting_key` varchar(80) NOT NULL,
  `value` text NOT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `app_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_settings`
--

LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
INSERT INTO `app_settings` VALUES ('commission_holding_period_days','7',NULL,'2026-09-18 17:48:30'),('minimum_payout_amount','1000',NULL,'2026-09-18 17:48:30');
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('brix-cache-admin@agency.com|127.0.0.1','i:1;',1789990299),('brix-cache-admin@agency.com|127.0.0.1:timer','i:1789990299;',1789990299),('brix-cache-admin@brix.app|127.0.0.1','i:1;',1789990274),('brix-cache-admin@brix.app|127.0.0.1:timer','i:1789990274;',1789990274);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
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
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
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
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `finished_at` int(11) DEFAULT NULL,
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
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
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
-- Table structure for table `lead_events`
--

DROP TABLE IF EXISTS `lead_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(30) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lead_events_lead_id_event_type_index` (`lead_id`,`event_type`),
  CONSTRAINT `lead_events_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_events`
--

LOCK TABLES `lead_events` WRITE;
/*!40000 ALTER TABLE `lead_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `lead_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `agency_id` int(10) unsigned NOT NULL,
  `tracking_link_id` bigint(20) unsigned DEFAULT NULL,
  `store_id` int(10) unsigned DEFAULT NULL,
  `shop_domain` varchar(255) DEFAULT NULL,
  `lead_stage` varchar(20) NOT NULL DEFAULT 'NEW',
  `brix_status` varchar(20) DEFAULT NULL,
  `first_clicked_at` timestamp NULL DEFAULT NULL,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `installed_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leads_shop_domain_unique` (`shop_domain`),
  KEY `leads_tracking_link_id_foreign` (`tracking_link_id`),
  KEY `leads_agency_id_lead_stage_index` (`agency_id`,`lead_stage`),
  CONSTRAINT `leads_tracking_link_id_foreign` FOREIGN KEY (`tracking_link_id`) REFERENCES `tracking_links` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_08_19_074534_create_organisations_table',1),(5,'2026_08_19_074535_create_organisation_user_table',1),(6,'2026_08_19_074537_create_organisation_settings_table',1),(7,'2026_09_01_080746_create_store_connection_attempts_table',1),(8,'2026_09_02_115221_add_brix_agency_id_to_organisations_table',1),(9,'2026_09_18_000001_add_holding_period_fields_to_transactions_table',1),(10,'2026_09_18_000002_add_partner_fields_to_payouts_table',1),(11,'2026_09_18_000003_add_partner_fields_to_payout_accounts_table',1),(12,'2026_09_18_000004_add_store_id_to_agency_ledger_table',1),(13,'2026_09_18_000005_add_partner_fields_to_store_modules_table',1),(14,'2026_09_18_000006_create_transaction_payout_table',1),(15,'2026_09_18_000007_create_partner_notifications_table',1),(16,'2026_09_18_000008_add_commission_source_to_transactions_and_seed_app_settings',2),(17,'2026_09_18_000009_reconcile_historical_paid_commissions',3),(18,'2026_09_18_000010_exclude_failed_transactions_from_commission',4),(19,'2026_09_18_000011_add_remember_token_to_admin_users_table',5),(20,'2026_09_21_135019_create_tracking_links_table',6),(21,'2026_09_21_135020_create_referral_clicks_table',6),(22,'2026_09_21_135021_create_leads_table',6),(23,'2026_09_21_135022_create_lead_events_table',6),(24,'2026_09_22_100000_create_referral_revenue_events_table',6),(25,'2026_09_22_100100_create_referral_commissions_table',6),(26,'2026_09_22_100200_add_commission_revenue_source_to_organisation_settings_table',6),(27,'2026_09_23_100000_add_source_to_referral_clicks_table',7),(28,'2026_09_23_110000_create_referral_commission_payout_table',8);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` int(10) unsigned DEFAULT NULL,
  `type` varchar(40) NOT NULL DEFAULT 'info',
  `title` varchar(160) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`admin_user_id`),
  KEY `idx_notifications_read` (`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,NULL,'store','A store went offline','One of your monitored stores stopped syncing.','/admin/stores.php?status=offline',0,'2026-08-24 16:58:04'),(2,NULL,'agency','New agency registered','A new agency just signed up on the platform.','/admin/agencies.php',0,'2026-08-24 21:05:55'),(3,4,'payout','3 payout requests require approval','Review pending payouts in the Finance section.','/admin/payouts.php',0,'2026-08-07 10:56:19'),(4,NULL,'payout','3 payout requests require approval','Review pending payouts in the Finance section.','/admin/payouts.php',1,'2026-08-24 13:52:34'),(5,NULL,'analytics','Stores reached 90% AI usage','Several stores are heavily using AI BRIX this week.','/admin/analytics.php',1,'2026-08-11 02:53:02'),(6,NULL,'subscription','An agency upgraded a subscription','A store subscription was upgraded to a higher plan.','/admin/subscriptions.php',1,'2026-08-22 16:09:22'),(7,NULL,'subscription','An agency upgraded a subscription','A store subscription was upgraded to a higher plan.','/admin/subscriptions.php',0,'2026-08-08 10:48:42'),(8,NULL,'store','A store went offline','One of your monitored stores stopped syncing.','/admin/stores.php?status=offline',0,'2026-08-09 13:09:08'),(9,NULL,'agency','New agency registered','A new agency just signed up on the platform.','/admin/agencies.php',0,'2026-08-23 03:46:56'),(10,NULL,'analytics','Stores reached 90% AI usage','Several stores are heavily using AI BRIX this week.','/admin/analytics.php',0,'2026-08-20 17:31:31'),(11,4,'analytics','Stores reached 90% AI usage','Several stores are heavily using AI BRIX this week.','/admin/analytics.php',1,'2026-08-18 14:16:05'),(12,NULL,'agency','New agency registered','A new agency just signed up on the platform.','/admin/agencies.php',0,'2026-08-10 22:44:03'),(13,NULL,'payout','3 payout requests require approval','Review pending payouts in the Finance section.','/admin/payouts.php',0,'2026-08-20 01:58:16'),(14,NULL,'payout','3 payout requests require approval','Review pending payouts in the Finance section.','/admin/payouts.php',0,'2026-08-23 19:21:03'),(15,NULL,'store','A store went offline','One of your monitored stores stopped syncing.','/admin/stores.php?status=offline',1,'2026-08-22 22:39:57'),(16,NULL,'payout','3 payout requests require approval','Review pending payouts in the Finance section.','/admin/payouts.php',1,'2026-08-22 22:31:19'),(17,NULL,'analytics','Stores reached 90% AI usage','Several stores are heavily using AI BRIX this week.','/admin/analytics.php',1,'2026-08-09 18:01:30'),(18,NULL,'analytics','Stores reached 90% AI usage','Several stores are heavily using AI BRIX this week.','/admin/analytics.php',0,'2026-08-13 09:00:57'),(19,NULL,'store','A store went offline','One of your monitored stores stopped syncing.','/admin/stores.php?status=offline',0,'2026-08-14 07:26:32'),(20,3,'store','A store went offline','One of your monitored stores stopped syncing.','/admin/stores.php?status=offline',1,'2026-08-24 14:07:54'),(21,3,'payout','3 payout requests require approval','Review pending payouts in the Finance section.','/admin/payouts.php',1,'2026-08-12 08:43:26'),(22,NULL,'subscription','An agency upgraded a subscription','A store subscription was upgraded to a higher plan.','/admin/subscriptions.php',1,'2026-08-07 19:11:44'),(23,NULL,'agency','New agency registered','A new agency just signed up on the platform.','/admin/agencies.php',0,'2026-08-24 13:17:36'),(24,NULL,'agency','New agency registered','A new agency just signed up on the platform.','/admin/agencies.php',1,'2026-08-14 02:26:30'),(25,1,'subscription','An agency upgraded a subscription','A store subscription was upgraded to a higher plan.','/admin/subscriptions.php',1,'2026-08-13 00:52:00'),(26,1,'store','A store went offline','One of your monitored stores stopped syncing.','/admin/stores.php?status=offline',1,'2026-08-10 10:23:30'),(27,NULL,'analytics','Stores reached 90% AI usage','Several stores are heavily using AI BRIX this week.','/admin/analytics.php',1,'2026-08-06 04:14:27'),(28,NULL,'subscription','An agency upgraded a subscription','A store subscription was upgraded to a higher plan.','/admin/subscriptions.php',0,'2026-08-18 01:52:14'),(29,1,'store','A store went offline','One of your monitored stores stopped syncing.','/admin/stores.php?status=offline',0,'2026-08-22 12:37:36'),(30,NULL,'analytics','Stores reached 90% AI usage','Several stores are heavily using AI BRIX this week.','/admin/analytics.php',1,'2026-08-17 07:48:37'),(31,NULL,'payout','3 payout requests require approval','Review pending payouts in the Finance section.','/admin/payouts.php',1,'2026-08-20 12:00:16'),(32,4,'agency','New agency registered','A new agency just signed up on the platform.','/admin/agencies.php',0,'2026-08-16 08:15:11'),(33,NULL,'store','A store went offline','One of your monitored stores stopped syncing.','/admin/stores.php?status=offline',0,'2026-08-21 18:53:18'),(34,2,'subscription','An agency upgraded a subscription','A store subscription was upgraded to a higher plan.','/admin/subscriptions.php',1,'2026-08-10 19:38:01'),(35,NULL,'agency','New agency registered','A new agency just signed up on the platform.','/admin/agencies.php',1,'2026-08-18 15:17:16'),(36,NULL,'analytics','Stores reached 90% AI usage','Several stores are heavily using AI BRIX this week.','/admin/analytics.php',1,'2026-08-17 08:46:06'),(37,NULL,'agency','New agency registered','A new agency just signed up on the platform.','/admin/agencies.php',1,'2026-08-16 11:34:16'),(38,NULL,'subscription','An agency upgraded a subscription','A store subscription was upgraded to a higher plan.','/admin/subscriptions.php',0,'2026-08-24 21:05:06'),(39,NULL,'agency','New agency registered','A new agency just signed up on the platform.','/admin/agencies.php',1,'2026-08-12 23:34:30'),(40,NULL,'agency','New agency registered','A new agency just signed up on the platform.','/admin/agencies.php',0,'2026-08-18 18:41:59'),(41,NULL,'payout','New payout request','Cobalt Group requested a payout of ₹8K.','/admin/payout-details.php?id=475',0,'2026-08-25 17:42:23'),(42,NULL,'payout','Payout sent to RazorpayX','Cobalt Group\'s payout of ₹8K was approved and sent to RazorpayX.','/admin/payout-details.php?id=475',0,'2026-08-25 17:47:07');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organisation_settings`
--

DROP TABLE IF EXISTS `organisation_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organisation_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organisation_id` bigint(20) unsigned NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `commission_revenue_source` varchar(20) NOT NULL DEFAULT 'both',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organisation_settings_organisation_id_unique` (`organisation_id`),
  CONSTRAINT `organisation_settings_organisation_id_foreign` FOREIGN KEY (`organisation_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organisation_settings`
--

LOCK TABLES `organisation_settings` WRITE;
/*!40000 ALTER TABLE `organisation_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `organisation_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organisation_user`
--

DROP TABLE IF EXISTS `organisation_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organisation_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organisation_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'member',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organisation_user_organisation_id_user_id_unique` (`organisation_id`,`user_id`),
  KEY `organisation_user_user_id_foreign` (`user_id`),
  CONSTRAINT `organisation_user_organisation_id_foreign` FOREIGN KEY (`organisation_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `organisation_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organisation_user`
--

LOCK TABLES `organisation_user` WRITE;
/*!40000 ALTER TABLE `organisation_user` DISABLE KEYS */;
INSERT INTO `organisation_user` VALUES (1,1,1,'owner','2026-09-18 12:28:40','2026-09-18 12:28:40');
/*!40000 ALTER TABLE `organisation_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organisations`
--

DROP TABLE IF EXISTS `organisations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organisations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `brix_agency_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organisations_slug_unique` (`slug`),
  KEY `organisations_brix_agency_id_foreign` (`brix_agency_id`),
  CONSTRAINT `organisations_brix_agency_id_foreign` FOREIGN KEY (`brix_agency_id`) REFERENCES `agencies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organisations`
--

LOCK TABLES `organisations` WRITE;
/*!40000 ALTER TABLE `organisations` DISABLE KEYS */;
INSERT INTO `organisations` VALUES (1,'Test Org','test-org',NULL,1,'2026-09-18 12:28:40','2026-09-18 13:09:39');
/*!40000 ALTER TABLE `organisations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `partner_notifications`
--

DROP TABLE IF EXISTS `partner_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organisation_id` bigint(20) unsigned NOT NULL,
  `store_id` int(10) unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` varchar(255) NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partner_notifications_store_id_foreign` (`store_id`),
  KEY `partner_notifications_organisation_id_read_at_index` (`organisation_id`,`read_at`),
  CONSTRAINT `partner_notifications_organisation_id_foreign` FOREIGN KEY (`organisation_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `partner_notifications_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `partner_notifications`
--

LOCK TABLES `partner_notifications` WRITE;
/*!40000 ALTER TABLE `partner_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `partner_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
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
-- Table structure for table `payout_accounts`
--

DROP TABLE IF EXISTS `payout_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payout_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agency_id` int(10) unsigned NOT NULL,
  `type` enum('bank','upi') NOT NULL,
  `account_holder_name` varchar(120) DEFAULT NULL,
  `bank_account_number` varchar(40) DEFAULT NULL,
  `bank_account_number_encrypted` text DEFAULT NULL,
  `account_last4` varchar(4) DEFAULT NULL,
  `bank_ifsc` varchar(11) DEFAULT NULL,
  `account_type` varchar(20) DEFAULT NULL COMMENT 'savings, current',
  `upi_vpa` varchar(120) DEFAULT NULL,
  `razorpayx_contact_id` varchar(60) DEFAULT NULL,
  `razorpayx_fund_account_id` varchar(60) DEFAULT NULL,
  `verification_status` enum('pending','verified','failed') NOT NULL DEFAULT 'pending',
  `is_default` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payout_accounts_agency` (`agency_id`),
  CONSTRAINT `payout_accounts_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payout_accounts`
--

LOCK TABLES `payout_accounts` WRITE;
/*!40000 ALTER TABLE `payout_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `payout_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payout_events`
--

DROP TABLE IF EXISTS `payout_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payout_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payout_id` int(10) unsigned NOT NULL,
  `event_type` varchar(60) NOT NULL,
  `provider_event_id` varchar(120) DEFAULT NULL,
  `provider_status` varchar(40) DEFAULT NULL,
  `payload_hash` char(64) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payout_events_hash` (`payload_hash`),
  KEY `idx_payout_events_payout` (`payout_id`),
  CONSTRAINT `payout_events_ibfk_1` FOREIGN KEY (`payout_id`) REFERENCES `payouts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payout_events`
--

LOCK TABLES `payout_events` WRITE;
/*!40000 ALTER TABLE `payout_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `payout_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payout_items`
--

DROP TABLE IF EXISTS `payout_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payout_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payout_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(180) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `store_id` (`store_id`),
  KEY `idx_payout_items_payout` (`payout_id`),
  CONSTRAINT `payout_items_ibfk_1` FOREIGN KEY (`payout_id`) REFERENCES `payouts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payout_items_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payout_items`
--

LOCK TABLES `payout_items` WRITE;
/*!40000 ALTER TABLE `payout_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `payout_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payouts`
--

DROP TABLE IF EXISTS `payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payouts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payout_code` varchar(20) DEFAULT NULL,
  `agency_id` int(10) unsigned NOT NULL,
  `payout_account_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `payment_method` varchar(60) NOT NULL DEFAULT 'Bank Transfer',
  `status` enum('pending','approved','processing','paid','failed','rejected','reversed','cancelled') NOT NULL DEFAULT 'pending',
  `provider` enum('RAZORPAYX','MANUAL') NOT NULL DEFAULT 'MANUAL',
  `provider_payout_id` varchar(60) DEFAULT NULL,
  `provider_status` varchar(40) DEFAULT NULL,
  `environment` enum('test','live') DEFAULT NULL,
  `idempotency_key` varchar(80) NOT NULL,
  `requested_at` datetime NOT NULL,
  `approved_at` datetime DEFAULT NULL,
  `processing_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `processed_by` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payout_idempotency` (`idempotency_key`),
  UNIQUE KEY `payouts_payout_code_unique` (`payout_code`),
  KEY `payout_account_id` (`payout_account_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  KEY `processed_by` (`processed_by`),
  KEY `idx_payouts_agency` (`agency_id`),
  KEY `idx_payouts_status` (`status`),
  KEY `idx_payouts_provider_payout` (`provider_payout_id`),
  CONSTRAINT `payouts_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payouts_ibfk_2` FOREIGN KEY (`payout_account_id`) REFERENCES `payout_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payouts_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payouts_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payouts_ibfk_5` FOREIGN KEY (`processed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payouts`
--

LOCK TABLES `payouts` WRITE;
/*!40000 ALTER TABLE `payouts` DISABLE KEYS */;
/*!40000 ALTER TABLE `payouts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limit_hits`
--

DROP TABLE IF EXISTS `rate_limit_hits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limit_hits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rate_key` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rate_limit_key_time` (`rate_key`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limit_hits`
--

LOCK TABLES `rate_limit_hits` WRITE;
/*!40000 ALTER TABLE `rate_limit_hits` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_limit_hits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_clicks`
--

DROP TABLE IF EXISTS `referral_clicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referral_clicks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `agency_id` int(10) unsigned NOT NULL,
  `tracking_link_id` bigint(20) unsigned NOT NULL,
  `referral_code` varchar(40) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `shop_domain` varchar(255) DEFAULT NULL,
  `referrer` varchar(255) DEFAULT NULL,
  `source` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `referral_clicks_agency_id_created_at_index` (`agency_id`,`created_at`),
  KEY `referral_clicks_tracking_link_id_created_at_index` (`tracking_link_id`,`created_at`),
  KEY `referral_clicks_shop_domain_index` (`shop_domain`),
  CONSTRAINT `referral_clicks_tracking_link_id_foreign` FOREIGN KEY (`tracking_link_id`) REFERENCES `tracking_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_clicks`
--

LOCK TABLES `referral_clicks` WRITE;
/*!40000 ALTER TABLE `referral_clicks` DISABLE KEYS */;
/*!40000 ALTER TABLE `referral_clicks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_commission_payout`
--

DROP TABLE IF EXISTS `referral_commission_payout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referral_commission_payout` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `referral_commission_id` bigint(20) unsigned NOT NULL,
  `payout_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_referral_commission_payout` (`referral_commission_id`,`payout_id`),
  KEY `referral_commission_payout_payout_id_index` (`payout_id`),
  CONSTRAINT `referral_commission_payout_referral_commission_id_foreign` FOREIGN KEY (`referral_commission_id`) REFERENCES `referral_commissions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_commission_payout`
--

LOCK TABLES `referral_commission_payout` WRITE;
/*!40000 ALTER TABLE `referral_commission_payout` DISABLE KEYS */;
/*!40000 ALTER TABLE `referral_commission_payout` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_commissions`
--

DROP TABLE IF EXISTS `referral_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referral_commissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `revenue_event_id` bigint(20) unsigned NOT NULL,
  `agency_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned DEFAULT NULL,
  `tracking_link_id` bigint(20) unsigned DEFAULT NULL,
  `revenue_type` varchar(20) NOT NULL,
  `revenue_amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `rate_source` varchar(20) NOT NULL,
  `commission_amount` decimal(12,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `available_at` timestamp NULL DEFAULT NULL,
  `rule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rule`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referral_commissions_revenue_event_id_unique` (`revenue_event_id`),
  KEY `referral_commissions_agency_id_status_index` (`agency_id`,`status`),
  KEY `referral_commissions_lead_id_index` (`lead_id`),
  KEY `referral_commissions_tracking_link_id_index` (`tracking_link_id`),
  CONSTRAINT `referral_commissions_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`),
  CONSTRAINT `referral_commissions_revenue_event_id_foreign` FOREIGN KEY (`revenue_event_id`) REFERENCES `referral_revenue_events` (`id`),
  CONSTRAINT `referral_commissions_tracking_link_id_foreign` FOREIGN KEY (`tracking_link_id`) REFERENCES `tracking_links` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_commissions`
--

LOCK TABLES `referral_commissions` WRITE;
/*!40000 ALTER TABLE `referral_commissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `referral_commissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_revenue_events`
--

DROP TABLE IF EXISTS `referral_revenue_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referral_revenue_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `agency_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `lead_id` bigint(20) unsigned DEFAULT NULL,
  `shop_domain` varchar(255) NOT NULL,
  `revenue_type` varchar(20) NOT NULL,
  `source` varchar(40) NOT NULL,
  `external_event_id` varchar(255) NOT NULL,
  `revenue_amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'verified',
  `reversal_of_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_referral_revenue_event` (`source`,`external_event_id`,`revenue_type`),
  KEY `referral_revenue_events_agency_id_revenue_type_index` (`agency_id`,`revenue_type`),
  KEY `referral_revenue_events_lead_id_index` (`lead_id`),
  KEY `referral_revenue_events_store_id_index` (`store_id`),
  KEY `referral_revenue_events_reversal_of_id_index` (`reversal_of_id`),
  CONSTRAINT `referral_revenue_events_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_revenue_events`
--

LOCK TABLES `referral_revenue_events` WRITE;
/*!40000 ALTER TABLE `referral_revenue_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `referral_revenue_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
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
INSERT INTO `sessions` VALUES ('cSAQNNfzv7aWU3DUZqm1sOMTFxZuMA634TpWNNKC',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','YTo2OntzOjY6Il90b2tlbiI7czo0MDoidW1QMFlhdk9qT1N0NG5TbVJyWkNaejBUdGxvV3BMNzZReFpueWg4WCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjE1OiJvcmdhbmlzYXRpb25faWQiO2k6MTtzOjUyOiJsb2dpbl9hZG1pbl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7fQ==',1790055756),('hFM2gbyqvp4O2y4OC7E68onRR0nXix7f7kRkZFQ9',NULL,'127.0.0.1','curl/8.9.0','YToyOntzOjY6Il90b2tlbiI7czo0MDoiVENtUlhXUUQ3S1ZzTzJBZVI1WllnVlpQc2Y0RmlNRUp2NUJQWm5jTyI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1790051136),('sOxs2bBNrfG31pKW9Fzk5BujhVUSxGdpnHpEWa7k',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.138.0 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoidU1BQTFBM1g1NFg1RXBFbGw1Q3RYZ0s3MmpPNnlFbHZudXptRHV2TyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1790055449),('SvdchL2RAJhUt1wY7Q026LBGw6boFmlEF0c5wQUL',NULL,'127.0.0.1','curl/8.9.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiRnZkeUJJOGR5MVNWUUY5MDU2N2NwcVU4RVMxNWpiR1MwckpnNW5XZCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1790051125);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `store_connection_attempts`
--

DROP TABLE IF EXISTS `store_connection_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `store_connection_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organisation_id` bigint(20) unsigned NOT NULL,
  `state_token` varchar(64) NOT NULL,
  `shop_domain` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'STARTED',
  `failure_reason` varchar(255) DEFAULT NULL,
  `store_id` int(10) unsigned DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_connection_attempts_state_token_unique` (`state_token`),
  KEY `store_connection_attempts_store_id_foreign` (`store_id`),
  KEY `store_connection_attempts_shop_domain_status_index` (`shop_domain`,`status`),
  KEY `store_connection_attempts_organisation_id_index` (`organisation_id`),
  CONSTRAINT `store_connection_attempts_organisation_id_foreign` FOREIGN KEY (`organisation_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_connection_attempts_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `store_connection_attempts`
--

LOCK TABLES `store_connection_attempts` WRITE;
/*!40000 ALTER TABLE `store_connection_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `store_connection_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `store_modules`
--

DROP TABLE IF EXISTS `store_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `store_modules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `module_name` varchar(60) NOT NULL,
  `module_key` varchar(40) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_store_module` (`store_id`,`module_name`),
  CONSTRAINT `store_modules_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `store_modules`
--

LOCK TABLES `store_modules` WRITE;
/*!40000 ALTER TABLE `store_modules` DISABLE KEYS */;
/*!40000 ALTER TABLE `store_modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stores`
--

DROP TABLE IF EXISTS `stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agency_id` int(10) unsigned NOT NULL,
  `shop_domain` varchar(180) NOT NULL,
  `shopify_shop_id` varchar(60) DEFAULT NULL,
  `store_name` varchar(160) NOT NULL,
  `status` enum('active','attention','trial','offline') NOT NULL DEFAULT 'trial',
  `installation_status` enum('NOT_INSTALLED','INSTALLING','INSTALLED','UNINSTALLED','ERROR') NOT NULL DEFAULT 'NOT_INSTALLED',
  `authorization_status` enum('NOT_AUTHORIZED','AUTHORIZING','AUTHORIZED','EXPIRED','REVOKED') NOT NULL DEFAULT 'NOT_AUTHORIZED',
  `plan` varchar(60) NOT NULL DEFAULT 'Starter',
  `commission_override_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `commission_override_rate` decimal(5,2) DEFAULT NULL,
  `installed_at` datetime NOT NULL,
  `uninstalled_at` datetime DEFAULT NULL,
  `last_active_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `shop_domain` (`shop_domain`),
  KEY `idx_stores_agency` (`agency_id`),
  KEY `idx_stores_status` (`status`),
  KEY `idx_stores_plan` (`plan`),
  CONSTRAINT `stores_ibfk_1` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stores`
--

LOCK TABLES `stores` WRITE;
/*!40000 ALTER TABLE `stores` DISABLE KEYS */;
/*!40000 ALTER TABLE `stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `plan_name` varchar(60) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `status` enum('active','trial','cancelled') NOT NULL DEFAULT 'trial',
  `started_at` datetime NOT NULL,
  `next_billing_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_subscriptions_store` (`store_id`),
  KEY `idx_subscriptions_status` (`status`),
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracking_links`
--

DROP TABLE IF EXISTS `tracking_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tracking_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `agency_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(40) NOT NULL,
  `channel` varchar(20) NOT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `destination_url` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracking_links_code_unique` (`code`),
  KEY `tracking_links_agency_id_status_index` (`agency_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracking_links`
--

LOCK TABLES `tracking_links` WRITE;
/*!40000 ALTER TABLE `tracking_links` DISABLE KEYS */;
INSERT INTO `tracking_links` VALUES (1,1,'whatsapp campaign','BRIX-BHSM','WhatsApp','sept','https://apps.shopify.com/thebrix-io',NULL,'ACTIVE','2026-09-21 11:33:09','2026-09-21 11:33:09'),(2,1,'whatsapp campaign','BRIX-AGG9','WhatsApp','sept','https://apps.shopify.com/thebrix-io',NULL,'INACTIVE','2026-09-21 11:33:13','2026-09-21 11:33:30');
/*!40000 ALTER TABLE `tracking_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction_payout`
--

DROP TABLE IF EXISTS `transaction_payout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction_payout` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` int(10) unsigned NOT NULL,
  `payout_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_payout_transaction_id_payout_id_unique` (`transaction_id`,`payout_id`),
  KEY `transaction_payout_payout_id_index` (`payout_id`),
  CONSTRAINT `transaction_payout_payout_id_foreign` FOREIGN KEY (`payout_id`) REFERENCES `payouts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaction_payout_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_payout`
--

LOCK TABLES `transaction_payout` WRITE;
/*!40000 ALTER TABLE `transaction_payout` DISABLE KEYS */;
/*!40000 ALTER TABLE `transaction_payout` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` int(10) unsigned DEFAULT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `agency_id` int(10) unsigned NOT NULL,
  `gross_amount` decimal(10,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `commission_source` varchar(20) NOT NULL DEFAULT 'agency_default' COMMENT 'agency_default, custom — which rate was used at the time',
  `agency_commission` decimal(10,2) NOT NULL,
  `brix_revenue` decimal(10,2) NOT NULL,
  `type` enum('subscription','renewal','upgrade','refund') NOT NULL DEFAULT 'renewal',
  `commission_status` varchar(20) NOT NULL DEFAULT 'available' COMMENT 'pending, available, in_payout, paid, refunded, adjusted, cancelled',
  `available_at` timestamp NULL DEFAULT NULL,
  `status` enum('success','pending','failed','refunded') NOT NULL DEFAULT 'success',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `subscription_id` (`subscription_id`),
  KEY `idx_transactions_agency` (`agency_id`),
  KEY `idx_transactions_store` (`store_id`),
  KEY `idx_transactions_created` (`created_at`),
  KEY `idx_transactions_status` (`status`),
  KEY `transactions_agency_id_commission_status_index` (`agency_id`,`commission_status`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`agency_id`) REFERENCES `agencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Test User','test@example.com',NULL,'$2y$12$t6SJx6DatqmZ82ETEA9C0uRNpiLFAcfx3cApaPKllVWNVPcAOzpym',NULL,'2026-09-18 12:28:40','2026-09-18 12:28:40');
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

-- Dump completed on 2026-09-22 11:14:34
