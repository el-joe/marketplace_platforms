/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `ab_test_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ab_test_results` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ab_test_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant` char(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `visitors` int NOT NULL DEFAULT '0',
  `impressions` int NOT NULL DEFAULT '0',
  `clicks` int NOT NULL DEFAULT '0',
  `add_to_cart_count` int NOT NULL DEFAULT '0',
  `orders` int NOT NULL DEFAULT '0',
  `revenue_cents` bigint NOT NULL DEFAULT '0',
  `conversion_rate` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `revenue_per_visitor_cents` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ab_test_results_ab_test_id_variant_date_unique` (`ab_test_id`,`variant`,`date`),
  KEY `ab_test_results_ab_test_id_index` (`ab_test_id`),
  CONSTRAINT `ab_test_results_ab_test_id_foreign` FOREIGN KEY (`ab_test_id`) REFERENCES `ab_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ab_tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ab_tests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hypothesis` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `traffic_split_pct` int NOT NULL DEFAULT '50',
  `winner` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_metric` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'conversion_rate',
  `min_sample_size` int NOT NULL DEFAULT '1000',
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `result_notes` text COLLATE utf8mb4_unicode_ci,
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ab_tests_page_id_status_index` (`page_id`,`status`),
  KEY `ab_tests_page_id_index` (`page_id`),
  KEY `ab_tests_created_by_admin_id_index` (`created_by_admin_id`),
  CONSTRAINT `ab_tests_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `log_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `properties` json NOT NULL,
  `event` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_log_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `activity_log_causer_type_causer_id_index` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_created_at_index` (`log_name`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_campaign_category_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_campaign_category_targets` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bid_override` bigint DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ad_campaign_category_targets_ad_campaign_id_index` (`ad_campaign_id`),
  KEY `ad_campaign_category_targets_category_id_index` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_campaign_keywords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_campaign_keywords` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keyword` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keyword_normalized` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `match_type` enum('broad','phrase','exact') COLLATE utf8mb4_unicode_ci NOT NULL,
  `bid_override` bigint DEFAULT NULL,
  `is_negative` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ad_campaign_keywords_ad_campaign_id_index` (`ad_campaign_id`),
  KEY `ad_campaign_keywords_keyword_normalized_index` (`keyword_normalized`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_campaign_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_campaign_products` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ad_campaign_products_ad_campaign_id_index` (`ad_campaign_id`),
  KEY `ad_campaign_products_product_variant_id_index` (`product_variant_id`),
  KEY `ad_campaign_products_vendor_id_index` (`vendor_id`),
  KEY `ad_campaign_products_vendor_listing_id_index` (`vendor_listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_campaigns` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('cpc','cpm') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `budget_total` bigint NOT NULL,
  `budget_daily` bigint DEFAULT NULL,
  `budget_spent_total` bigint NOT NULL DEFAULT '0',
  `budget_spent_today` bigint NOT NULL DEFAULT '0',
  `bid` bigint NOT NULL,
  `targeting_type` enum('auto','keyword','category','mixed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `quality_score` decimal(3,2) NOT NULL,
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ad_campaigns_vendor_id_index` (`vendor_id`),
  KEY `ad_campaigns_country_id_index` (`country_id`),
  KEY `ad_campaigns_approved_by_admin_id_index` (`approved_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_clicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_clicks` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_impression_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `is_fraud_suspect` tinyint(1) NOT NULL DEFAULT '0',
  `fraud_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_cents` bigint NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_daily_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_daily_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ad_campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `impressions` int NOT NULL DEFAULT '0',
  `clicks` int NOT NULL DEFAULT '0',
  `valid_clicks` int NOT NULL DEFAULT '0',
  `conversions` int NOT NULL DEFAULT '0',
  `spend_cents` bigint NOT NULL DEFAULT '0',
  `revenue_attributed_cents` bigint NOT NULL DEFAULT '0',
  `ctr` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `cvr` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `acos` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `average_position` decimal(4,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_fraud_patterns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_fraud_patterns` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clicks_last_hour` int NOT NULL DEFAULT '0',
  `clicks_last_24h` int NOT NULL DEFAULT '0',
  `is_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `blocked_at` timestamp NULL DEFAULT NULL,
  `block_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_image_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_image_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `file_id` bigint unsigned DEFAULT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_open_new_tab` tinyint(1) NOT NULL DEFAULT '0',
  `alt_text_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_title_overlay` tinyint(1) NOT NULL DEFAULT '1',
  `aspect_ratio` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '4:3',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ad_image_items_page_block_id_position_is_active_index` (`page_block_id`,`position`,`is_active`),
  KEY `ad_image_items_page_block_id_index` (`page_block_id`),
  KEY `ad_image_items_file_id_foreign` (`file_id`),
  CONSTRAINT `ad_image_items_file_id_foreign` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ad_image_items_page_block_id_foreign` FOREIGN KEY (`page_block_id`) REFERENCES `page_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_impressions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_impressions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `placement_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_query` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_shown` int NOT NULL,
  `bid_at_impression_cents` bigint NOT NULL,
  `quality_score_at_impression` decimal(3,2) NOT NULL,
  `was_clicked` tinyint(1) NOT NULL DEFAULT '0',
  `was_converted` tinyint(1) NOT NULL DEFAULT '0',
  `cost_charged_cents` bigint NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shown_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_quality_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_quality_scores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ad_campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` decimal(3,2) NOT NULL,
  `ctr_score` decimal(3,2) NOT NULL,
  `relevance_score` decimal(3,2) NOT NULL,
  `landing_score` decimal(3,2) NOT NULL,
  `vendor_score` decimal(3,2) NOT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `addressable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `addressable_id` bigint unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `building` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `floor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apartment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landmark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `address_type` enum('shipping','billing','both') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_addressable_type_addressable_id_index` (`addressable_type`,`addressable_id`),
  KEY `addresses_country_id_index` (`country_id`),
  KEY `addresses_city_id_index` (`city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_login_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_login_sessions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `impersonating_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_info` json DEFAULT NULL,
  `started_at` timestamp NOT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_login_sessions_admin_id_index` (`admin_id`),
  KEY `admin_login_sessions_started_at_index` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_email_unique` (`email`),
  KEY `admins_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attribute_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attribute_values` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_hex` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attribute_values_attribute_id_index` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attributes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('text','number','boolean','select','multi_select','color') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_variant_attribute` tinyint(1) NOT NULL DEFAULT '0',
  `is_filterable` tinyint(1) NOT NULL DEFAULT '0',
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attributes_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banner_placement_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banner_placement_definitions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `width_px` int NOT NULL,
  `height_px` int NOT NULL,
  `max_file_size_kb` int NOT NULL,
  `allowed_formats` json NOT NULL,
  `device_restriction` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_simultaneous` int NOT NULL,
  `supports_vendor_ads` tinyint(1) NOT NULL,
  `base_rate_weekly_cents` bigint DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `banner_placement_definitions_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banners` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `placement_code` enum('homepage_hero','homepage_secondary_left','homepage_secondary_right','homepage_midpage','category_top_{slug}','category_sidebar','search_top','cart_banner','checkout_banner','product_page_bottom','app_splash','app_home_top','email_header') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_en` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_ar` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_en` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_reference_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_target` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `audience` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` int NOT NULL DEFAULT '0',
  `impressions_count` bigint NOT NULL DEFAULT '0',
  `clicks_count` bigint NOT NULL DEFAULT '0',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `banners_country_id_index` (`country_id`),
  KEY `banners_created_by_admin_id_index` (`created_by_admin_id`),
  KEY `banners_updated_by_admin_id_index` (`updated_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `block_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `block_analytics` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `impressions` int NOT NULL DEFAULT '0',
  `clicks` int NOT NULL DEFAULT '0',
  `unique_visitors` int NOT NULL DEFAULT '0',
  `add_to_cart_count` int NOT NULL DEFAULT '0',
  `orders_attributed` int NOT NULL DEFAULT '0',
  `revenue_attributed_cents` bigint NOT NULL DEFAULT '0',
  `ctr` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `block_analytics_page_block_id_date_unique` (`page_block_id`,`date`),
  KEY `block_analytics_page_id_date_index` (`page_id`,`date`),
  KEY `block_analytics_country_id_date_index` (`country_id`,`date`),
  KEY `block_analytics_date_index` (`date`),
  KEY `block_analytics_page_block_id_index` (`page_block_id`),
  KEY `block_analytics_page_id_index` (`page_id`),
  KEY `block_analytics_country_id_index` (`country_id`),
  CONSTRAINT `block_analytics_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `block_analytics_page_block_id_foreign` FOREIGN KEY (`page_block_id`) REFERENCES `page_blocks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `block_analytics_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `block_click_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `block_click_events` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `click_target` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `click_target_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `block_click_events_page_block_id_clicked_at_index` (`page_block_id`,`clicked_at`),
  KEY `block_click_events_clicked_at_index` (`clicked_at`),
  KEY `block_click_events_page_block_id_index` (`page_block_id`),
  KEY `block_click_events_user_id_index` (`user_id`),
  KEY `block_click_events_country_id_index` (`country_id`),
  CONSTRAINT `block_click_events_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `block_click_events_page_block_id_foreign` FOREIGN KEY (`page_block_id`) REFERENCES `page_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `block_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `block_types` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_en` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_ar` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `config_schema` json DEFAULT NULL,
  `default_config` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `requires_permission` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_per_page` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `block_types_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `brands` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo_media_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `website_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '1',
  `is_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `brands_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cart_inventory_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_inventory_locks` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cart_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_inventory_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cart_inventory_locks_cart_id_index` (`cart_id`),
  KEY `cart_inventory_locks_warehouse_inventory_id_index` (`warehouse_inventory_id`),
  KEY `cart_inventory_locks_vendor_listing_id_index` (`vendor_listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cart_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` bigint NOT NULL,
  `added_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cart_items_cart_id_index` (`cart_id`),
  KEY `cart_items_vendor_listing_id_index` (`vendor_listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` bigint NOT NULL,
  `discount` bigint NOT NULL,
  `estimated_shipping` bigint NOT NULL,
  `estimated_tax` bigint NOT NULL,
  `estimated_total` bigint NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `carts_user_id_index` (`user_id`),
  KEY `carts_coupon_id_index` (`coupon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `lft` int DEFAULT NULL,
  `rgt` int DEFAULT NULL,
  `depth` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `seo_title_ar` text COLLATE utf8mb4_unicode_ci,
  `seo_title_en` text COLLATE utf8mb4_unicode_ci,
  `seo_description_ar` text COLLATE utf8mb4_unicode_ci,
  `seo_description_en` text COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_parent_id_index` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `category_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_attributes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_attributes_category_id_index` (`category_id`),
  KEY `category_attributes_attribute_id_index` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_zone_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `cod_available` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cities_country_id_index` (`country_id`),
  KEY `cities_shipping_zone_id_index` (`shipping_zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commissions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rate_pct` decimal(5,2) NOT NULL,
  `rate_type` enum('flat','tiered') COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_commission` bigint NOT NULL DEFAULT '0',
  `max_commission` bigint DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `priority` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commissions_vendor_id_index` (`vendor_id`),
  KEY `commissions_category_id_index` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code_2` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code_3` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `site_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_domain` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_prefix` char(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_locale` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `timezone` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'UTC',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_launched` tinyint(1) NOT NULL DEFAULT '0',
  `cod_available` tinyint(1) NOT NULL DEFAULT '0',
  `launched_at` timestamp NULL DEFAULT NULL,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `countries_iso_code_2_unique` (`iso_code_2`),
  UNIQUE KEY `countries_iso_code_3_unique` (`iso_code_3`),
  UNIQUE KEY `countries_name_ar_unique` (`name_ar`),
  UNIQUE KEY `countries_name_en_unique` (`name_en`),
  UNIQUE KEY `countries_site_code_unique` (`site_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `country_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `country_categories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `unavailable_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `country_categories_country_id_index` (`country_id`),
  KEY `country_categories_category_id_index` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `country_payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `country_payment_methods` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name_en` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `fee_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `fee_fixed_cents` bigint unsigned NOT NULL DEFAULT '0',
  `min_order_cents` bigint unsigned NOT NULL DEFAULT '0',
  `max_order_cents` bigint unsigned DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `country_payment_methods_country_id_index` (`country_id`),
  CONSTRAINT `country_payment_methods_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `country_shipping_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `country_shipping_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `free_shipping_threshold_cents` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `country_shipping_settings_country_id_shipping_method_id_unique` (`country_id`,`shipping_method_id`),
  KEY `country_shipping_settings_country_id_index` (`country_id`),
  KEY `country_shipping_settings_shipping_method_id_index` (`shipping_method_id`),
  CONSTRAINT `country_shipping_settings_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `country_shipping_settings_shipping_method_id_foreign` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupon_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupon_products` (
  `coupon_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `coupon_products_coupon_id_index` (`coupon_id`),
  KEY `coupon_products_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupon_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupon_usages` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_amount` bigint NOT NULL,
  `used_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coupon_usages_coupon_id_index` (`coupon_id`),
  KEY `coupon_usages_customer_id_index` (`customer_id`),
  KEY `coupon_usages_order_id_index` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` enum('percentage','fixed_amount','free_shipping','bogo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(15,2) NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope` enum('platform','vendor','category','product') COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_order_amount` bigint DEFAULT NULL,
  `max_discount` bigint DEFAULT NULL,
  `usage_limit_total` int DEFAULT NULL,
  `usage_limit_per_customer` int NOT NULL DEFAULT '1',
  `times_used` int NOT NULL DEFAULT '0',
  `customer_eligibility` enum('all','new_customers','specific_segment','specific_users') COLLATE utf8mb4_unicode_ci NOT NULL,
  `valid_from` timestamp NOT NULL,
  `valid_until` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_stackable` tinyint(1) NOT NULL DEFAULT '0',
  `created_by_user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`),
  KEY `coupons_vendor_id_index` (`vendor_id`),
  KEY `coupons_category_id_index` (`category_id`),
  KEY `coupons_created_by_user_id_index` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `decimal_places` tinyint NOT NULL DEFAULT '2',
  `base_currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `exchange_rate_to_base` decimal(15,6) NOT NULL DEFAULT '1.000000',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_manually_overridden` tinyint(1) NOT NULL DEFAULT '0',
  `rate_updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','suspended','banned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `date_of_birth` date DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_orders` int NOT NULL DEFAULT '0',
  `total_spent` decimal(10,2) NOT NULL DEFAULT '0.00',
  `referral_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referred_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loyalty_points` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_email_unique` (`email`),
  UNIQUE KEY `customers_phone_unique` (`phone`),
  KEY `customers_country_id_index` (`country_id`),
  KEY `customers_referred_by_index` (`referred_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dispute_evidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispute_evidence` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispute_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_by_user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `dispute_evidence_dispute_id_index` (`dispute_id`),
  KEY `dispute_evidence_uploaded_by_user_id_index` (`uploaded_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dispute_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispute_messages` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispute_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_role` enum('customer','seller','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_internal_note` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `dispute_messages_dispute_id_index` (`dispute_id`),
  KEY `dispute_messages_sender_user_id_index` (`sender_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `disputes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disputes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispute_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `return_request_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` enum('item_not_received','item_damaged','item_not_as_described','counterfeit','wrong_item','quality_issue','seller_unresponsive','refund_not_received','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','seller_responded','under_review','escalated','resolved','closed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolution` enum('favor_customer','favor_seller','split','no_action') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `compensation_cents` bigint DEFAULT NULL,
  `assigned_to_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disputes_dispute_number_unique` (`dispute_number`),
  KEY `disputes_order_id_index` (`order_id`),
  KEY `disputes_sub_order_id_index` (`sub_order_id`),
  KEY `disputes_customer_id_index` (`customer_id`),
  KEY `disputes_vendor_id_index` (`vendor_id`),
  KEY `disputes_return_request_id_index` (`return_request_id`),
  KEY `disputes_assigned_to_admin_id_index` (`assigned_to_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `file_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extension` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned NOT NULL DEFAULT '0',
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `files_model_type_model_id_index` (`model_type`,`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `files_hashes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files_hashes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `md5_hash` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `perceptual_hash` char(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `files_hashes_file_id_index` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sale_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sale_analytics` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_submission_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `units_sold` int NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gross_revenue` bigint NOT NULL,
  `revenue_at_normal_price` bigint NOT NULL,
  `discount_given` bigint NOT NULL,
  `platform_commission` bigint NOT NULL,
  `vendor_payout` bigint NOT NULL,
  `views` int NOT NULL,
  `add_to_cart_count` int NOT NULL,
  `conversion_rate` decimal(6,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sale_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sale_orders` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_submission_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_item_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_price` bigint NOT NULL,
  `original_price` bigint NOT NULL,
  `discount_amount` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sale_price_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sale_price_histories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_at` timestamp NOT NULL,
  `recorded_by` enum('system','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flash_sale_price_histories_vendor_listing_id_index` (`vendor_listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sale_submission_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sale_submission_histories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_submission_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by_user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by_role` enum('admin','vendor','system') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flash_sale_submission_histories_flash_sale_submission_id_index` (`flash_sale_submission_id`),
  KEY `flash_sale_submission_histories_changed_by_user_id_index` (`changed_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sale_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sale_submissions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_price` bigint NOT NULL,
  `original_price` bigint NOT NULL,
  `calculated_discount_pct` decimal(5,2) NOT NULL,
  `reference_price_30d` bigint DEFAULT NULL,
  `max_quantity_total` int NOT NULL,
  `max_quantity_per_customer` int NOT NULL DEFAULT '1',
  `quantity_sold` int NOT NULL DEFAULT '0',
  `quantity_remaining` int GENERATED ALWAYS AS ((`max_quantity_total` - `quantity_sold`)) VIRTUAL,
  `flash_price_currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rejection_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `sold_out_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `vendor_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flash_sale_submissions_flash_sale_id_index` (`flash_sale_id`),
  KEY `flash_sale_submissions_vendor_id_index` (`vendor_id`),
  KEY `flash_sale_submissions_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `flash_sale_submissions_reviewed_by_admin_id_index` (`reviewed_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sale_vendor_invititions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sale_vendor_invititions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invitation_type` enum('auto','manual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','accepted','declined','submitted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `invited_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notified_at` timestamp NULL DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `decline_reason` text COLLATE utf8mb4_unicode_ci,
  `slots_allocated` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flash_sale_vendor_invititions_flash_sale_id_index` (`flash_sale_id`),
  KEY `flash_sale_vendor_invititions_vendor_id_index` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sales` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_en` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `submission_opens_at` timestamp NOT NULL,
  `submission_closes_at` timestamp NOT NULL,
  `review_deadline_at` timestamp NOT NULL,
  `sale_starts_at` timestamp NOT NULL,
  `sale_ends_at` timestamp NOT NULL,
  `min_discount_pct` decimal(5,2) NOT NULL,
  `max_products_per_seller` int DEFAULT NULL,
  `eligible_categories` json DEFAULT NULL,
  `eligible_seller_tiers` json DEFAULT NULL,
  `min_seller_rating` decimal(3,2) DEFAULT NULL,
  `commission_override_pct` decimal(5,2) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `is_exclusive` tinyint(1) NOT NULL DEFAULT '0',
  `price_drop_required` tinyint(1) NOT NULL DEFAULT '1',
  `max_total_slots` int DEFAULT NULL,
  `approved_slots_count` int NOT NULL DEFAULT '0',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flash_sales_country_id_index` (`country_id`),
  KEY `flash_sales_created_by_admin_id_index` (`created_by_admin_id`),
  KEY `flash_sales_updated_by_admin_id_index` (`updated_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idempotency_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idempotency_keys` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `operation_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_status` int DEFAULT NULL,
  `response_body` json DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_keys_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inbound_shipment_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inbound_shipment_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inbound_shipment_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_quantity` int NOT NULL,
  `received_quantity` int NOT NULL DEFAULT '0',
  `damaged_quantity` int NOT NULL DEFAULT '0',
  `condition_notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inbound_shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inbound_shipments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipment_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','submitted','in_transit','arrived','receiving','received','rejected') COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_arrival_date` date DEFAULT NULL,
  `arrived_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `received_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inbound_shipments_shipment_code_unique` (`shipment_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_movements` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_inventory_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `movement_type` enum('inbound','outbound','reservation','release','adjustment','damage','return','transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_delta` int NOT NULL,
  `quantity_after` int NOT NULL,
  `reference_type` enum('order','inbound_shipment','transfer','adjustment') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `inventory_movements_warehouse_inventory_id_index` (`warehouse_inventory_id`),
  KEY `inventory_movements_created_by_user_id_index` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_transfer_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inventory_transfer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_requested` int NOT NULL,
  `quantity_received` int NOT NULL DEFAULT '0',
  `damaged_quantity` int NOT NULL DEFAULT '0',
  `condition_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_transfer_items_inventory_transfer_id_index` (`inventory_transfer_id`),
  KEY `inventory_transfer_items_vendor_listing_id_index` (`vendor_listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_transfers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_warehouse_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_warehouse_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','in_transit','received','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL,
  `initiated_by_user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_arrival_date` date DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_transfers_transfer_number_unique` (`transfer_number`),
  KEY `inventory_transfers_initiated_by_user_id_index` (`initiated_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `languages` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `native_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `english_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('ltr','rtl') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ltr',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `languages_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ledger_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ledger_entries` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_group_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` enum('customer_payment','platform_revenue','platform_commission','seller_payable','gateway_fee','tax_payable','refund_liability','shipping_revenue','cod_clearing') COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_holder_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_holder_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `debit` bigint NOT NULL DEFAULT '0',
  `credit` bigint NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ledger_entries_transaction_group_id_index` (`transaction_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`permission_id`,`model_uuid`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_uuid`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`role_id`,`model_uuid`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_uuid`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json NOT NULL,
  `channel` enum('database','email','sms','push','whatsapp') COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_read_at_index` (`notifiable_type`,`notifiable_id`,`read_at`),
  KEY `notifications_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_snapshot` json NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` bigint NOT NULL,
  `unit_cost_price` bigint DEFAULT NULL,
  `line_subtotal` bigint NOT NULL,
  `line_discount` bigint NOT NULL DEFAULT '0',
  `line_tax` bigint NOT NULL,
  `line_total` bigint NOT NULL,
  `commission_rate_pct` decimal(5,2) NOT NULL,
  `commission_amount` bigint NOT NULL,
  `fulfillment_status` enum('pending','picked','packed','shipped','delivered','returned','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL,
  `return_eligible_until` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_index` (`order_id`),
  KEY `order_items_sub_order_id_index` (`sub_order_id`),
  KEY `order_items_product_variant_id_index` (`product_variant_id`),
  KEY `order_items_vendor_id_index` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_status_histories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_order_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_status_histories_order_id_index` (`order_id`),
  KEY `order_status_histories_sub_order_id_index` (`sub_order_id`),
  KEY `order_status_histories_changed_by_admin_id_index` (`changed_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('placed','confirmed','partially_shipped','shipped','partially_delivered','delivered','completed','cancelled','refunded','disputed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` bigint NOT NULL,
  `discount` bigint NOT NULL DEFAULT '0',
  `shipping` bigint NOT NULL,
  `tax` bigint NOT NULL,
  `cod_fee` bigint NOT NULL DEFAULT '0',
  `total` bigint NOT NULL,
  `coupon_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coupon_code_used` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` enum('card','wallet','cod','bnpl','bank_transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_status` enum('pending','authorized','captured','failed','refunded','partially_refunded') COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_address_snapshot` json NOT NULL,
  `billing_address_snapshot` json DEFAULT NULL,
  `customer_notes` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `device_fingerprint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risk_score` decimal(5,2) DEFAULT NULL,
  `placed_at` timestamp NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  KEY `orders_customer_id_index` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_block_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_block_categories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_block_categories_page_block_id_category_id_unique` (`page_block_id`,`category_id`),
  KEY `page_block_categories_page_block_id_index` (`page_block_id`),
  KEY `page_block_categories_category_id_index` (`category_id`),
  CONSTRAINT `page_block_categories_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_block_categories_page_block_id_foreign` FOREIGN KEY (`page_block_id`) REFERENCES `page_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_block_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_block_products` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `added_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_block_products_page_block_id_product_variant_id_unique` (`page_block_id`,`product_variant_id`),
  KEY `page_block_products_page_block_id_index` (`page_block_id`),
  KEY `page_block_products_product_variant_id_index` (`product_variant_id`),
  KEY `page_block_products_added_by_admin_id_index` (`added_by_admin_id`),
  CONSTRAINT `page_block_products_page_block_id_foreign` FOREIGN KEY (`page_block_id`) REFERENCES `page_blocks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_block_products_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_block_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_block_revisions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revision_number` int NOT NULL,
  `config_snapshot` json NOT NULL,
  `is_visible_snapshot` tinyint(1) NOT NULL,
  `position_snapshot` int NOT NULL,
  `changed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `page_block_revisions_page_block_id_revision_number_index` (`page_block_id`,`revision_number`),
  KEY `page_block_revisions_page_id_created_at_index` (`page_id`,`created_at`),
  KEY `page_block_revisions_changed_by_admin_id_created_at_index` (`changed_by_admin_id`,`created_at`),
  KEY `page_block_revisions_page_block_id_index` (`page_block_id`),
  KEY `page_block_revisions_page_id_index` (`page_id`),
  KEY `page_block_revisions_changed_by_admin_id_index` (`changed_by_admin_id`),
  CONSTRAINT `page_block_revisions_page_block_id_foreign` FOREIGN KEY (`page_block_id`) REFERENCES `page_blocks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_block_revisions_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_block_sellers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_block_sellers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seller_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_block_sellers_page_block_id_seller_id_unique` (`page_block_id`,`seller_id`),
  KEY `page_block_sellers_page_block_id_index` (`page_block_id`),
  KEY `page_block_sellers_seller_id_index` (`seller_id`),
  CONSTRAINT `page_block_sellers_page_block_id_foreign` FOREIGN KEY (`page_block_id`) REFERENCES `page_blocks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_block_sellers_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_blocks` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `block_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `config` json DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `visible_from` timestamp NULL DEFAULT NULL,
  `visible_until` timestamp NULL DEFAULT NULL,
  `device_target` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `audience` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `country_override` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ab_test_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ab_variant` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cache_ttl_seconds` int NOT NULL DEFAULT '60',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page_blocks_page_id_position_index` (`page_id`,`position`),
  KEY `page_blocks_section_id_position_index` (`section_id`,`position`),
  KEY `page_blocks_is_visible_visible_from_visible_until_index` (`is_visible`,`visible_from`,`visible_until`),
  KEY `page_blocks_deleted_at_index` (`deleted_at`),
  KEY `page_blocks_page_id_index` (`page_id`),
  KEY `page_blocks_section_id_index` (`section_id`),
  KEY `page_blocks_block_type_index` (`block_type`),
  KEY `page_blocks_country_override_index` (`country_override`),
  KEY `page_blocks_ab_test_id_index` (`ab_test_id`),
  KEY `page_blocks_created_by_admin_id_index` (`created_by_admin_id`),
  KEY `page_blocks_updated_by_admin_id_index` (`updated_by_admin_id`),
  CONSTRAINT `page_blocks_ab_test_id_foreign` FOREIGN KEY (`ab_test_id`) REFERENCES `ab_tests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `page_blocks_country_override_foreign` FOREIGN KEY (`country_override`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `page_blocks_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_blocks_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `page_sections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_revisions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` int NOT NULL,
  `blocks_snapshot` json NOT NULL,
  `published_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `publish_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `page_revisions_page_id_version_index` (`page_id`,`version`),
  KEY `page_revisions_page_id_index` (`page_id`),
  KEY `page_revisions_published_by_admin_id_index` (`published_by_admin_id`),
  CONSTRAINT `page_revisions_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_sections` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `background_color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padding_top` int NOT NULL DEFAULT '0',
  `padding_bottom` int NOT NULL DEFAULT '0',
  `max_width` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page_sections_page_id_position_index` (`page_id`,`position`),
  KEY `page_sections_page_id_index` (`page_id`),
  CONSTRAINT `page_sections_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `publish_at` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `unpublish_at` timestamp NULL DEFAULT NULL,
  `published_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_edited_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `seo_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description` text COLLATE utf8mb4_unicode_ci,
  `og_image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pages_country_id_slug_unique` (`country_id`,`slug`),
  KEY `pages_country_id_page_type_is_default_index` (`country_id`,`page_type`,`is_default`),
  KEY `pages_status_publish_at_index` (`status`,`publish_at`),
  KEY `pages_country_id_page_type_reference_id_index` (`country_id`,`page_type`,`reference_id`),
  KEY `pages_deleted_at_index` (`deleted_at`),
  KEY `pages_country_id_index` (`country_id`),
  KEY `pages_published_by_admin_id_index` (`published_by_admin_id`),
  KEY `pages_last_edited_by_admin_id_index` (`last_edited_by_admin_id`),
  CONSTRAINT `pages_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paid_ad_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paid_ad_bookings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `booking_reference` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid_ad_slot_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing_model` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `booked_from` date NOT NULL,
  `booked_until` date NOT NULL,
  `agreed_rate_cents` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `payment_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_transaction_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoiced_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `impressions_delivered` int NOT NULL DEFAULT '0',
  `clicks_delivered` int NOT NULL DEFAULT '0',
  `cpm_impressions_billed` int NOT NULL DEFAULT '0',
  `total_charged` bigint NOT NULL DEFAULT '0',
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `paid_ad_bookings_booking_reference_unique` (`booking_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paid_ad_creatives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paid_ad_creatives` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid_ad_booking_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_en` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_reference_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `rejection_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '0',
  `reviewed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paid_ad_creatives_paid_ad_booking_id_index` (`paid_ad_booking_id`),
  KEY `paid_ad_creatives_vendor_id_index` (`vendor_id`),
  KEY `paid_ad_creatives_reviewed_by_admin_id_index` (`reviewed_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paid_ad_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paid_ad_slots` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `placement_definition_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slot_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing_model` enum('fixed_weekly','fixed_monthly','cpm','cpc') COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_rate_cents` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_booking_days` int NOT NULL,
  `max_booking_days` int NOT NULL,
  `is_available` tinyint(1) NOT NULL,
  `requires_approval` tinyint(1) NOT NULL,
  `min_seller_tier` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes_for_vendors` text COLLATE utf8mb4_unicode_ci,
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `paid_ad_slots_slot_code_unique` (`slot_code`),
  KEY `paid_ad_slots_placement_definition_id_index` (`placement_definition_id`),
  KEY `paid_ad_slots_country_id_index` (`country_id`),
  KEY `paid_ad_slots_created_by_admin_id_index` (`created_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paid_banner_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paid_banner_bookings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seller_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `booking_reference` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing_model` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_cents` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_charged_cents` bigint NOT NULL DEFAULT '0',
  `booked_from` date NOT NULL,
  `booked_until` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `impressions_delivered` int NOT NULL DEFAULT '0',
  `clicks_delivered` int NOT NULL DEFAULT '0',
  `booked_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `paid_banner_bookings_booking_reference_unique` (`booking_reference`),
  KEY `paid_banner_bookings_page_block_id_status_index` (`page_block_id`,`status`),
  KEY `paid_banner_bookings_booked_from_booked_until_index` (`booked_from`,`booked_until`),
  KEY `paid_banner_bookings_page_block_id_index` (`page_block_id`),
  KEY `paid_banner_bookings_seller_id_index` (`seller_id`),
  KEY `paid_banner_bookings_booked_by_admin_id_index` (`booked_by_admin_id`),
  CONSTRAINT `paid_banner_bookings_page_block_id_foreign` FOREIGN KEY (`page_block_id`) REFERENCES `page_blocks` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `paid_banner_bookings_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_methods` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('card','wallet','bank') COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `card_brand` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_last4` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_exp_month` tinyint DEFAULT NULL,
  `card_exp_year` smallint DEFAULT NULL,
  `billing_address_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_methods_customer_id_index` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_transactions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('authorization','capture','sale','refund','void','chargeback') COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_fee` bigint NOT NULL DEFAULT '0',
  `status` enum('pending','succeeded','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL,
  `failure_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_message` text COLLATE utf8mb4_unicode_ci,
  `payment_method_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_request` json DEFAULT NULL,
  `raw_response` json DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_transactions_gateway_transaction_id_unique` (`gateway_transaction_id`),
  UNIQUE KEY `payment_transactions_idempotency_key_unique` (`idempotency_key`),
  KEY `payment_transactions_order_id_index` (`order_id`),
  KEY `payment_transactions_customer_id_index` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payout_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payout_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payout_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gross` bigint NOT NULL,
  `commission` bigint NOT NULL,
  `net` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payout_items_payout_id_index` (`payout_id`),
  KEY `payout_items_sub_order_id_index` (`sub_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payouts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payout_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `gross_sales` bigint NOT NULL,
  `commission` bigint NOT NULL,
  `refunds_deducted` bigint NOT NULL,
  `chargebacks_deducted` bigint NOT NULL,
  `storage_fees` bigint NOT NULL DEFAULT '0',
  `ad_fees` bigint NOT NULL DEFAULT '0',
  `other_adjustments` bigint NOT NULL DEFAULT '0',
  `net_amount` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','processing','completed','failed','on_hold') COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payout_method` enum('bank_transfer','wallet','paypal') COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `failed_reason` text COLLATE utf8mb4_unicode_ci,
  `receipt_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payouts_payout_number_unique` (`payout_number`),
  KEY `payouts_vendor_id_index` (`vendor_id`),
  KEY `payouts_bank_account_id_index` (`bank_account_id`),
  KEY `payouts_approved_by_admin_id_index` (`approved_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_countries` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `unavailable_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_override_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_override_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_override_en` text COLLATE utf8mb4_unicode_ci,
  `description_override_ar` text COLLATE utf8mb4_unicode_ci,
  `requires_local_cert` tinyint(1) NOT NULL DEFAULT '0',
  `is_age_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `min_age` tinyint DEFAULT NULL,
  `seo_title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description_en` text COLLATE utf8mb4_unicode_ci,
  `seo_description_ar` text COLLATE utf8mb4_unicode_ci,
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_countries_product_id_index` (`product_id`),
  KEY `product_countries_country_id_index` (`country_id`),
  KEY `product_countries_updated_by_admin_id_index` (`updated_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_country_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_country_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `unavailable_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_override_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_override_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_local_cert` tinyint(1) NOT NULL DEFAULT '0',
  `seo_title` varchar(70) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_country_settings_product_id_country_id_unique` (`product_id`,`country_id`),
  KEY `product_country_settings_country_id_foreign` (`country_id`),
  CONSTRAINT `product_country_settings_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_country_settings_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_image_hashes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_image_hashes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_image_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `md5_hash` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `perceptual_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_image_hashes_product_image_id_foreign` (`product_image_id`),
  KEY `product_image_hashes_md5_hash_index` (`md5_hash`),
  KEY `product_image_hashes_perceptual_hash_index` (`perceptual_hash`),
  CONSTRAINT `product_image_hashes_product_image_id_foreign` FOREIGN KEY (`product_image_id`) REFERENCES `product_images` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_images` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `mime_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `alt_text_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_images_product_id_foreign` (`product_id`),
  KEY `product_images_product_variant_id_index` (`product_variant_id`),
  CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_variant_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variant_attributes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_value_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_text_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_text_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_number` decimal(15,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_variant_attributes_product_variant_id_index` (`product_variant_id`),
  KEY `product_variant_attributes_attribute_id_index` (`attribute_id`),
  KEY `product_variant_attributes_attribute_value_id_index` (`attribute_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variants` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight_grams` int DEFAULT NULL,
  `length_cm` decimal(8,2) DEFAULT NULL,
  `width_cm` decimal(8,2) DEFAULT NULL,
  `height_cm` decimal(8,2) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `position` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_variants_sku_unique` (`sku`),
  KEY `product_variants_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_views` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` enum('search','category','recommendation','direct','ad','social') COLLATE utf8mb4_unicode_ci NOT NULL,
  `referrer_url` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_views_product_id_index` (`product_id`),
  KEY `product_views_customer_id_index` (`customer_id`),
  KEY `product_views_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gtin` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `short_desc_en` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_desc_ar` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','active','discontinued','restricted') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `requires_brand_auth` tinyint(1) NOT NULL DEFAULT '0',
  `is_age_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `min_age` tinyint DEFAULT NULL,
  `is_hazardous` tinyint(1) NOT NULL DEFAULT '0',
  `has_variants` tinyint(1) NOT NULL DEFAULT '0',
  `seller_count` int NOT NULL DEFAULT '0',
  `rating_avg` decimal(3,2) NOT NULL DEFAULT '0.00',
  `rating_count` int NOT NULL DEFAULT '0',
  `total_sold` int NOT NULL DEFAULT '0',
  `view_count` bigint NOT NULL DEFAULT '0',
  `ai_quality_score` tinyint unsigned DEFAULT NULL,
  `seo_title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description_en` text COLLATE utf8mb4_unicode_ci,
  `seo_description_ar` text COLLATE utf8mb4_unicode_ci,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  KEY `products_category_id_index` (`category_id`),
  KEY `products_brand_id_index` (`brand_id`),
  KEY `products_created_by_admin_id_index` (`created_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refunds` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_transaction_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refund_transaction_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` enum('customer_request','out_of_stock','damaged','wrong_item','not_as_described','late_delivery','duplicate_order','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_notes` text COLLATE utf8mb4_unicode_ci,
  `refund_type` enum('full','partial','shipping_only') COLLATE utf8mb4_unicode_ci NOT NULL,
  `initiated_by_customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_charged_back` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','approved','processing','completed','failed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `refunds_order_id_index` (`order_id`),
  KEY `refunds_sub_order_id_index` (`sub_order_id`),
  KEY `refunds_original_transaction_id_index` (`original_transaction_id`),
  KEY `refunds_refund_transaction_id_index` (`refund_transaction_id`),
  KEY `refunds_initiated_by_customer_id_index` (`initiated_by_customer_id`),
  KEY `refunds_approved_by_admin_id_index` (`approved_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `return_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_request_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `return_request_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_item_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `condition_received` enum('new','opened','used','damaged') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `restock_decision` enum('restock','dispose','return_to_seller','liquidate') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `return_request_items_return_request_id_index` (`return_request_id`),
  KEY `return_request_items_order_item_id_index` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `return_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_requests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `return_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` enum('changed_mind','wrong_item','defective','damaged','not_as_described','size_issue','quality_issue','arrived_late','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_description` text COLLATE utf8mb4_unicode_ci,
  `return_type` enum('refund','exchange','store_credit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('requested','approved','rejected','awaiting_pickup','in_transit','received','inspecting','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_address_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_scheduled_at` timestamp NULL DEFAULT NULL,
  `received_at_warehouse_at` timestamp NULL DEFAULT NULL,
  `inspection_result` enum('good','damaged','missing','counterfeit') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspection_notes` text COLLATE utf8mb4_unicode_ci,
  `liability` enum('customer','seller','platform','carrier') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refund_amount_cents` bigint DEFAULT NULL,
  `refund_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_requests_return_number_unique` (`return_number`),
  KEY `return_requests_order_id_index` (`order_id`),
  KEY `return_requests_sub_order_id_index` (`sub_order_id`),
  KEY `return_requests_customer_id_index` (`customer_id`),
  KEY `return_requests_vendor_id_index` (`vendor_id`),
  KEY `return_requests_pickup_address_id_index` (`pickup_address_id`),
  KEY `return_requests_refund_id_index` (`refund_id`),
  KEY `return_requests_reviewed_by_admin_id_index` (`reviewed_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `review_vendor_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_vendor_replies` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `review_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('published','hidden') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `review_vendor_replies_review_id_unique` (`review_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `review_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_votes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `review_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vote` enum('helpful','not_helpful') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_item_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ai_flag_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moderated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `helpful_count` int NOT NULL DEFAULT '0',
  `not_helpful_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reviews_product_id_index` (`product_id`),
  KEY `reviews_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `reviews_customer_id_index` (`customer_id`),
  KEY `reviews_order_item_id_index` (`order_item_id`),
  KEY `reviews_country_id_index` (`country_id`),
  KEY `reviews_moderated_by_admin_id_index` (`moderated_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `search_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_logs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `query` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `query_normalized` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filters_json` json NOT NULL,
  `results_count` int NOT NULL,
  `clicked_product_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clicked_position` int DEFAULT NULL,
  `converted_order_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `search_logs_query_normalized_index` (`query_normalized`),
  KEY `search_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`),
  KEY `settings_category_index` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipment_tracking_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipment_tracking_events` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipment_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipment_tracking_events_shipment_id_index` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `awb_label_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight_grams` int NOT NULL,
  `dimensions` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost_actual` bigint NOT NULL,
  `status` enum('label_created','picked_up','in_transit','out_for_delivery','delivered','failed','returned') COLLATE utf8mb4_unicode_ci NOT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `delivery_otp` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipments_sub_order_id_index` (`sub_order_id`),
  KEY `shipments_carrier_id_index` (`carrier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_carriers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_carriers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_endpoint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credentials_encrypted` text COLLATE utf8mb4_unicode_ci,
  `tracking_url_pattern` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supports_cod` tinyint(1) NOT NULL DEFAULT '0',
  `supports_returns` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipping_carriers_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_methods` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `min_delivery_days` int DEFAULT NULL,
  `max_delivery_days` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipping_methods_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_rates` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origin_zone_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_zone_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_fee` bigint NOT NULL,
  `rate_per_kg` bigint NOT NULL DEFAULT '0',
  `min_weight_grams` int NOT NULL DEFAULT '0',
  `volumetric_divisor` int NOT NULL DEFAULT '5000',
  `free_shipping_threshold` bigint DEFAULT NULL,
  `cod_extra_fee` bigint NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipping_rates_origin_zone_id_index` (`origin_zone_id`),
  KEY `shipping_rates_destination_zone_id_index` (`destination_zone_id`),
  KEY `shipping_rates_shipping_method_id_index` (`shipping_method_id`),
  KEY `shipping_rates_carrier_id_index` (`carrier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_zones` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipping_zones_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `slider_slides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `slider_slides` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `desktop_file_id` bigint unsigned DEFAULT NULL,
  `mobile_file_id` bigint unsigned DEFAULT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_en` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_ar` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_en` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_open_new_tab` tinyint(1) NOT NULL DEFAULT '0',
  `text_color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#ffffff',
  `text_position` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `overlay_opacity` decimal(3,2) NOT NULL DEFAULT '0.30',
  `link_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_reference_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `visible_from` timestamp NULL DEFAULT NULL,
  `visible_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `slider_slides_page_block_id_position_is_active_index` (`page_block_id`,`position`,`is_active`),
  KEY `slider_slides_page_block_id_index` (`page_block_id`),
  KEY `slider_slides_desktop_file_id_foreign` (`desktop_file_id`),
  KEY `slider_slides_mobile_file_id_foreign` (`mobile_file_id`),
  CONSTRAINT `slider_slides_desktop_file_id_foreign` FOREIGN KEY (`desktop_file_id`) REFERENCES `files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `slider_slides_mobile_file_id_foreign` FOREIGN KEY (`mobile_file_id`) REFERENCES `files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `slider_slides_page_block_id_foreign` FOREIGN KEY (`page_block_id`) REFERENCES `page_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sub_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_orders` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_number` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('placed','confirmed','processing','packed','shipped','out_for_delivery','delivered','completed','cancelled','returned','refunded') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fulfillment_model` enum('fbm','fbn') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` bigint NOT NULL,
  `shipping` bigint NOT NULL,
  `tax` bigint NOT NULL,
  `platform_commission` bigint NOT NULL,
  `vendor_payout` bigint NOT NULL,
  `shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_delivery_date` date DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `sla_ship_deadline` timestamp NULL DEFAULT NULL,
  `sla_breached` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sub_orders_sub_order_number_unique` (`sub_order_number`),
  KEY `sub_orders_order_id_index` (`order_id`),
  KEY `sub_orders_vendor_id_index` (`vendor_id`),
  KEY `sub_orders_warehouse_id_index` (`warehouse_id`),
  KEY `sub_orders_shipping_method_id_index` (`shipping_method_id`),
  KEY `sub_orders_carrier_id_index` (`carrier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_role` enum('customer','seller') COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('order_issue','payment_issue','account','technical','product_inquiry','policy','payout','catalog','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','in_progress','waiting_customer','resolved','closed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_to_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_order_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_product_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_response_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `satisfaction_rating` tinyint DEFAULT NULL,
  `satisfaction_comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_ticket_number_unique` (`ticket_number`),
  KEY `support_tickets_requester_user_id_index` (`requester_user_id`),
  KEY `support_tickets_assigned_to_admin_id_index` (`assigned_to_admin_id`),
  KEY `support_tickets_related_order_id_index` (`related_order_id`),
  KEY `support_tickets_related_product_id_index` (`related_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_invoices` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` bigint NOT NULL,
  `tax` bigint NOT NULL,
  `total` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pdf_media_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_to_authority` tinyint(1) NOT NULL DEFAULT '0',
  `authority_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `issued_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_invoices_invoice_number_unique` (`invoice_number`),
  KEY `tax_invoices_order_id_index` (`order_id`),
  KEY `tax_invoices_vendor_id_index` (`vendor_id`),
  KEY `tax_invoices_customer_id_index` (`customer_id`),
  KEY `tax_invoices_pdf_media_id_index` (`pdf_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_rules` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_type` enum('vat','gst','sales_tax') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_pct` decimal(5,2) NOT NULL,
  `applies_to` enum('product','shipping','both') COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_includes_tax` tinyint(1) NOT NULL DEFAULT '0',
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_rules_country_id_index` (`country_id`),
  KEY `tax_rules_category_id_index` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_attachments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_message_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_attachments_ticket_message_id_index` (`ticket_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_messages` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_internal_note` tinyint(1) NOT NULL DEFAULT '0',
  `is_ai_generated` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_messages_sender_type_sender_id_index` (`sender_type`,`sender_id`),
  KEY `ticket_messages_ticket_id_index` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_admins` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('owner','manager','staff') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'owner',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_admins_email_unique` (`email`),
  KEY `vendor_admins_vendor_id_index` (`vendor_id`),
  CONSTRAINT `vendor_admins_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_bank_accounts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_holder_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iban` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number_encrypted` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `swift_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `verification_status` enum('pending','verified','rejected') COLLATE utf8mb4_unicode_ci NOT NULL,
  `verified_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_bank_accounts_vendor_id_index` (`vendor_id`),
  KEY `vendor_bank_accounts_verified_by_admin_id_index` (`verified_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_documents` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` enum('business_license','tax_certificate','owner_id','bank_proof','vat_registration') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','verified','rejected','expired') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `verified_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_documents_vendor_id_index` (`vendor_id`),
  KEY `vendor_documents_verified_by_admin_id_index` (`verified_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_listings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` bigint NOT NULL,
  `cost_price` bigint DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `condition` enum('new','like_new','good','acceptable','refurbished') COLLATE utf8mb4_unicode_ci NOT NULL,
  `condition_notes` text COLLATE utf8mb4_unicode_ci,
  `fulfillment_model` enum('fbm','fbn','cross_dock') COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','pending_review','active','paused','rejected','out_of_stock','archived') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `max_order_quantity` int DEFAULT NULL,
  `low_stock_threshold` int NOT NULL DEFAULT '5',
  `total_sold` int NOT NULL DEFAULT '0',
  `rating_avg` decimal(3,2) DEFAULT NULL,
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_listings_vendor_id_index` (`vendor_id`),
  KEY `vendor_listings_product_variant_id_index` (`product_variant_id`),
  KEY `vendor_listings_country_id_index` (`country_id`),
  KEY `vendor_listings_approved_by_admin_id_index` (`approved_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_strikes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_strikes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` enum('late_shipment','poor_quality','customer_complaint','policy_violation','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('warning','minor','major','critical') COLLATE utf8mb4_unicode_ci DEFAULT 'minor',
  `description` text COLLATE utf8mb4_unicode_ci,
  `issued_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_strikes_vendor_id_index` (`vendor_id`),
  KEY `vendor_strikes_issued_by_admin_id_index` (`issued_by_admin_id`),
  KEY `vendor_strikes_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendors` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_description` text COLLATE utf8mb4_unicode_ci,
  `business_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_type` enum('individual','sole_prop','llc','corp') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `business_registration_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `default_warehouse_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payout_schedule` enum('weekly','biweekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `payout_hold_active` tinyint(1) NOT NULL DEFAULT '0',
  `payout_hold_reason` text COLLATE utf8mb4_unicode_ci,
  `store_rating_avg` decimal(3,2) NOT NULL DEFAULT '0.00',
  `store_rating_count` int NOT NULL DEFAULT '0',
  `total_sales` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_orders` int NOT NULL DEFAULT '0',
  `return_rate_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `cancellation_rate_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `sla_compliance_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `strikes_count` decimal(5,2) NOT NULL DEFAULT '0.00',
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','suspended','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` tinyint(1) NOT NULL DEFAULT '0',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendors_email_unique` (`email`),
  UNIQUE KEY `vendors_store_name_unique` (`store_name`),
  UNIQUE KEY `vendors_store_slug_unique` (`store_slug`),
  UNIQUE KEY `vendors_phone_unique` (`phone`),
  KEY `vendors_business_address_id_index` (`business_address_id`),
  KEY `vendors_default_warehouse_id_index` (`default_warehouse_id`),
  KEY `vendors_country_id_index` (`country_id`),
  KEY `vendors_approved_by_admin_id_index` (`approved_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_inventories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouse_inventories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_on_hand` int NOT NULL DEFAULT '0',
  `quantity_reserved` int NOT NULL DEFAULT '0',
  `quantity_available` int GENERATED ALWAYS AS ((`quantity_on_hand` - `quantity_reserved`)) VIRTUAL,
  `quantity_inbound` int NOT NULL DEFAULT '0',
  `quantity_damaged` int NOT NULL DEFAULT '0',
  `bin_location` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reorder_point` int DEFAULT NULL,
  `last_counted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warehouse_inventories_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `warehouse_inventories_warehouse_id_index` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('platform_fbn','seller_owned','third_party') COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_vendor_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `total_capacity_m3` decimal(10,2) DEFAULT NULL,
  `used_capacity_m3` decimal(10,2) DEFAULT NULL,
  `storage_rate_per_m3_price` bigint DEFAULT NULL,
  `storage_currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `manager_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouses_code_unique` (`code`),
  KEY `warehouses_country_id_index` (`country_id`),
  KEY `warehouses_owner_vendor_id_index` (`owner_vendor_id`),
  KEY `warehouses_address_id_index` (`address_id`),
  KEY `warehouses_manager_admin_id_index` (`manager_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhook_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_deliveries` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `received_from` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `signature` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('received','processed','failed','retry') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `attempts` int NOT NULL DEFAULT '0',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `webhook_deliveries_event_type_index` (`event_type`),
  KEY `webhook_deliveries_status_index` (`status`),
  KEY `webhook_deliveries_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whishlists` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `added_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whishlists_customer_id_index` (`customer_id`),
  KEY `whishlists_product_id_index` (`product_id`),
  KEY `whishlists_vendor_listing_id_index` (`vendor_listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlists` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_05_23_105938_create_admins_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_05_23_110151_create_customers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_05_23_110655_create_vendors_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_05_23_111912_create_vendor_documents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_05_23_112130_create_files_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_05_23_112328_create_vendor_strikes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_05_23_112554_create_addresses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_05_23_113239_create_countries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_05_23_113524_create_cities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_05_23_113645_create_languages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_05_23_113904_create_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_05_23_122306_create_brands_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_05_23_122552_create_attributes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_05_23_122812_create_attribute_values_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_05_23_122902_create_category_attributes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_05_23_123046_create_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_05_23_125704_create_product_variants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_05_23_125934_create_product_countries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_05_23_130225_create_product_variant_attributes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_05_23_132010_create_vendor_listings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_05_23_132856_create_warehouses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_05_23_133152_create_warehouse_inventories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_05_23_133421_create_inventory_movements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_05_23_133617_create_inventory_transfers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_05_23_133919_create_inbound_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_05_23_205717_create_carts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_05_23_205842_create_cart_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_05_23_211450_create_cart_inventory_locks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_05_24_122855_create_whishlists_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_05_24_123128_create_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_05_24_123406_create_sub_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_05_24_123858_create_order_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_05_24_124109_create_order_status_histories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_05_24_124233_create_shipping_carriers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_05_24_124356_create_shipping_zones_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_05_24_124747_create_shipping_methods_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_05_24_124925_create_shipping_rates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_05_24_125219_create_shipments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_05_24_125354_create_shipment_tracking_events_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_05_24_125446_create_payment_methods_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_05_24_125704_create_payment_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_05_24_130522_create_idempotency_keys_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_05_24_130601_create_refunds_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_05_24_130755_create_ledger_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_05_24_130942_create_commissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_05_24_131036_create_payouts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_05_24_131228_create_payout_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_05_24_131311_create_vendor_bank_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_05_24_131421_create_tax_rules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_05_24_131535_create_tax_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_05_24_131659_create_coupons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_05_24_132725_create_coupon_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_05_24_132759_create_coupon_usages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_05_24_133006_create_flash_sales_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_05_24_135029_create_flash_sale_vendor_invititions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_05_24_135152_create_flash_sale_submissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_05_24_135315_create_flash_sale_submission_histories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_05_24_135405_create_flash_sale_price_histories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_05_24_135506_create_flash_sale_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_05_24_135637_create_flash_sale_analytics_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_05_24_140103_create_reviews_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_05_24_140627_create_banners_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_05_24_141000_create_banner_placement_definitions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_05_24_141056_create_paid_ad_slots_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_05_24_141224_create_paid_ad_bookings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_05_24_141410_create_paid_ad_creatives_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_05_24_141512_create_ad_campaigns_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_05_24_141559_create_ad_campaign_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_05_24_141706_create_ad_campaign_keywords_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_05_24_141823_create_ad_campaign_category_targets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_05_24_141900_create_ad_impressions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_05_24_142003_create_ad_clicks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_05_24_142043_create_ad_daily_stats_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_05_24_142128_create_ad_quality_scores_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_05_24_142214_create_ad_fraud_patterns_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_05_24_142321_create_wishlists_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_05_24_142715_create_review_vendor_replies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_05_24_142748_create_review_votes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_05_24_143000_create_return_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_05_24_143100_create_return_request_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_05_24_143200_create_disputes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_05_24_143300_create_dispute_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_05_24_143400_create_dispute_evidence_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_05_24_143500_create_support_tickets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2026_05_24_143600_create_ticket_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2026_05_24_143700_create_ticket_attachments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2026_05_24_143800_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2026_05_24_150000_create_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_05_24_150100_create_admin_login_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_05_24_150200_create_search_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2026_05_24_150300_create_product_views_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2026_05_24_150400_create_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2026_05_24_150500_create_webhook_deliveries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2026_05_25_000001_add_geo_columns_to_countries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2026_05_25_000002_create_currencies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2026_05_25_000003_create_country_payment_methods_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2026_05_25_000004_create_country_shipping_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2026_05_25_100001_create_product_images_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2026_05_25_100002_create_product_country_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2026_05_25_100003_add_fields_to_brands_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2026_05_25_100004_add_missing_columns_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2026_05_25_155910_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2026_05_25_210001_create_pages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2026_05_25_210002_create_block_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2026_05_25_210003_create_page_sections_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2026_05_25_210004_create_ab_tests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2026_05_25_210005_create_page_blocks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2026_05_25_210006_create_page_block_revisions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2026_05_25_210007_create_page_revisions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2026_05_25_210008_create_ab_test_results_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2026_05_25_210009_create_slider_slides_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2026_05_25_210010_create_ad_image_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2026_05_25_210011_create_page_block_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2026_05_25_210012_create_page_block_sellers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2026_05_25_210013_create_page_block_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2026_05_25_210014_create_paid_banner_bookings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2026_05_25_210015_create_block_analytics_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2026_05_25_210016_create_block_click_events_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2026_05_25_210017_add_file_ids_to_page_builder_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2026_05_25_230001_create_vendor_admins_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2026_05_25_230002_add_vendor_management_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2026_05_28_144218_make_product_id_nullable_on_product_images',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2026_05_28_174036_alter_files_model_id_to_varchar',3);
