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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ab_test_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `visitors` int NOT NULL DEFAULT '0',
  `impressions` int NOT NULL DEFAULT '0',
  `clicks` int NOT NULL DEFAULT '0',
  `add_to_cart_count` int NOT NULL DEFAULT '0',
  `orders` int NOT NULL DEFAULT '0',
  `revenue` bigint NOT NULL DEFAULT '0',
  `conversion_rate` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `revenue_per_visitor` bigint NOT NULL DEFAULT '0',
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hypothesis` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `traffic_split_pct` int NOT NULL DEFAULT '50',
  `winner` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_metric` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'conversion_rate',
  `min_sample_size` int NOT NULL DEFAULT '1000',
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `result_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `log_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `properties` json NOT NULL,
  `event` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `keyword` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `keyword_normalized` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `match_type` enum('broad','phrase','exact') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ad_campaign_products_ad_campaign_id_index` (`ad_campaign_id`),
  KEY `ad_campaign_products_product_variant_id_index` (`product_variant_id`),
  KEY `ad_campaign_products_vendor_id_index` (`vendor_id`),
  KEY `ad_campaign_products_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `ad_campaign_products_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `ad_campaign_products_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_acp_listing_xor` CHECK ((((`vendor_listing_id` is not null) and (`admin_listing_id` is null)) or ((`vendor_listing_id` is null) and (`admin_listing_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_campaigns` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('cpc','cpm') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `budget_total` bigint NOT NULL,
  `budget_daily` bigint DEFAULT NULL,
  `budget_spent_total` bigint NOT NULL DEFAULT '0',
  `budget_spent_today` bigint NOT NULL DEFAULT '0',
  `bid` bigint NOT NULL,
  `targeting_type` enum('auto','keyword','category','mixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `quality_score` decimal(3,2) NOT NULL,
  `approved_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_impression_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_fraud_suspect` tinyint(1) NOT NULL DEFAULT '0',
  `fraud_reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` bigint NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ad_clicks_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `ad_clicks_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_ac_listing_xor` CHECK ((((`vendor_listing_id` is not null) and (`admin_listing_id` is null)) or ((`vendor_listing_id` is null) and (`admin_listing_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_daily_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_daily_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ad_campaign_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `impressions` int NOT NULL DEFAULT '0',
  `clicks` int NOT NULL DEFAULT '0',
  `valid_clicks` int NOT NULL DEFAULT '0',
  `conversions` int NOT NULL DEFAULT '0',
  `spend` bigint NOT NULL DEFAULT '0',
  `revenue_attributed` bigint NOT NULL DEFAULT '0',
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `clicks_last_hour` int NOT NULL DEFAULT '0',
  `clicks_last_24h` int NOT NULL DEFAULT '0',
  `is_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `blocked_at` timestamp NULL DEFAULT NULL,
  `block_reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_image_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_image_items` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `file_id` bigint unsigned DEFAULT NULL,
  `title_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_label_en` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_label_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_open_new_tab` tinyint(1) NOT NULL DEFAULT '0',
  `alt_text_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_title_overlay` tinyint(1) NOT NULL DEFAULT '1',
  `aspect_ratio` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '4:3',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_campaign_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `placement_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_query` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_shown` int NOT NULL,
  `bid_at_impression` bigint NOT NULL,
  `quality_score_at_impression` decimal(3,2) NOT NULL,
  `was_clicked` tinyint(1) NOT NULL DEFAULT '0',
  `was_converted` tinyint(1) NOT NULL DEFAULT '0',
  `cost_charged` bigint NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `ad_campaign_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
DROP TABLE IF EXISTS `ad_support_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_support_articles` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_support_collection_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Short summary — also used as the meta description on the portal',
  `excerpt_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `excerpt_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full article body — HTML from a rich text editor',
  `body_en` longtext COLLATE utf8mb4_unicode_ci,
  `body_ar` longtext COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','published') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL COMMENT 'Set automatically the first time status becomes published',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'The one article linked from the Knowledge Hub home page "New to ads?" callout — enforce single featured article at app layer',
  `related_article_ids` json DEFAULT NULL COMMENT 'Manually curated related-articles list — array of ad_support_articles ids',
  `views_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ad_support_articles_slug_unique` (`slug`),
  KEY `ad_support_articles_author_admin_id_foreign` (`author_admin_id`),
  KEY `ad_support_articles_status_published_at_index` (`status`,`published_at`),
  KEY `ad_support_articles_ad_support_collection_id_status_index` (`ad_support_collection_id`,`status`),
  KEY `ad_support_articles_is_featured_status_index` (`is_featured`,`status`),
  CONSTRAINT `ad_support_articles_ad_support_collection_id_foreign` FOREIGN KEY (`ad_support_collection_id`) REFERENCES `ad_support_collections` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ad_support_articles_author_admin_id_foreign` FOREIGN KEY (`author_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ad_support_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ad_support_collections` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Supports one level of sub-collections — a collection with a parent cannot itself be a parent (enforce at app layer, not DB constraint, for simplicity)',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Icon URL shown on the collection card, e.g. an intercom.help svg icon link',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ad_support_collections_slug_unique` (`slug`),
  KEY `ad_support_collections_is_active_sort_order_index` (`is_active`,`sort_order`),
  KEY `ad_support_collections_parent_id_foreign` (`parent_id`),
  CONSTRAINT `ad_support_collections_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `ad_support_collections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `addressable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `addressable_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `building` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `floor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apartment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landmark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `address_type` enum('home','work','shipping','billing','both') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_country_id_index` (`country_id`),
  KEY `addresses_city_id_index` (`city_id`),
  KEY `addresses_addressable_type_addressable_id_index` (`addressable_type`,`addressable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_listings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` bigint NOT NULL,
  `compare_at_price` bigint DEFAULT NULL COMMENT 'Strikethrough "was" price shown on PDP',
  `cost_price` bigint DEFAULT NULL COMMENT 'Internal cost — NEVER exposed to customers, API, or vendor panel',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `condition` enum('new','like_new','good','acceptable','refurbished') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `condition_notes` text COLLATE utf8mb4_unicode_ci,
  `fulfillment_model` enum('fbm','fbn','cross_dock') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fbn' COMMENT 'Always fbn — enforced by model boot, no form field',
  `global_system_type` enum('express_fbn','merchant_fbp','marketplace') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'express_fbn' COMMENT 'Always express_fbn — enforced by model boot',
  `primary_shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_global_shipping` tinyint NOT NULL DEFAULT '0',
  `vendor_covers_delivery` tinyint NOT NULL DEFAULT '0' COMMENT 'Platform absorbs remaining delivery gap — customer sees Free Delivery',
  `max_order_quantity` int DEFAULT NULL,
  `low_stock_threshold` int NOT NULL DEFAULT '5',
  `total_sold` int NOT NULL DEFAULT '0',
  `buy_box_eligible` tinyint(1) NOT NULL DEFAULT '1',
  `buy_box_won_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','paused','out_of_stock','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `campaign_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `sold_by_label_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nawy' COMMENT 'Shown on PDP as "Sold by [name]". Default = platform name.',
  `sold_by_label_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'نوي',
  `express_badge_label_en` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Noon Express' COMMENT 'Yellow Express badge label — always present, label customisable',
  `express_badge_label_ar` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'نون إكسبرس',
  `search_boost` tinyint unsigned NOT NULL DEFAULT '10' COMMENT 'Score bonus added during search ranking. Range 0-20. Default 10 = inherent advantage over vendor listings.',
  `is_daily_deal` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Pin in Deal of the Day homepage slots',
  `daily_deal_ends_at` timestamp NULL DEFAULT NULL COMMENT 'Auto-disables is_daily_deal when reached. NULL = no expiry.',
  `weight_class` enum('light','medium','heavy') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Display only: light ≤1000g, medium 1001–5000g, heavy >5000g',
  `handling_class` enum('standard','refrigerated','fragile','special_tech') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `declared_weight_grams` int unsigned DEFAULT NULL,
  `declared_length_cm` decimal(8,2) DEFAULT NULL,
  `declared_width_cm` decimal(8,2) DEFAULT NULL,
  `declared_height_cm` decimal(8,2) DEFAULT NULL,
  `rating_avg` decimal(3,2) DEFAULT NULL,
  `rating_count` int NOT NULL DEFAULT '0',
  `score` decimal(8,4) DEFAULT NULL,
  `price_score` decimal(5,4) DEFAULT NULL,
  `fulfillment_score` decimal(5,4) DEFAULT NULL,
  `rating_score` decimal(5,4) DEFAULT NULL,
  `availability_score` decimal(5,4) DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT NULL,
  `next_recalculate_at` timestamp NULL DEFAULT NULL,
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_listings_warehouse_id_foreign` (`warehouse_id`),
  KEY `admin_listings_primary_shipping_method_id_foreign` (`primary_shipping_method_id`),
  KEY `admin_listings_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `admin_listings_updated_by_admin_id_foreign` (`updated_by_admin_id`),
  KEY `admin_listings_country_id_status_index` (`country_id`,`status`),
  KEY `admin_listings_product_variant_id_country_id_index` (`product_variant_id`,`country_id`),
  KEY `admin_listings_status_index` (`status`),
  KEY `admin_listings_buy_box_eligible_index` (`buy_box_eligible`),
  KEY `admin_listings_is_daily_deal_index` (`is_daily_deal`),
  KEY `admin_listings_search_boost_index` (`search_boost`),
  CONSTRAINT `admin_listings_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_listings_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_listings_primary_shipping_method_id_foreign` FOREIGN KEY (`primary_shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_listings_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_listings_updated_by_admin_id_foreign` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_listings_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_login_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_login_sessions` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `impersonating_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_email_unique` (`email`),
  KEY `admins_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `advertise_inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `advertise_inquiries` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('new','contacted','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `advertise_inquiries_country_status_index` (`country`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `agent_location_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_location_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `agent_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `agent_location_history_agent_id_recorded_at_index` (`agent_id`,`recorded_at`),
  CONSTRAINT `agent_location_history_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_feature_credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_feature_credits` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_type` enum('vendor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `feature` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '''image_enhancement'', ''virtual_tryon'', ''video_generation''',
  `credits_remaining` int NOT NULL DEFAULT '0',
  `credits_used_total` int NOT NULL DEFAULT '0',
  `reset_at` date DEFAULT NULL COMMENT 'Monthly reset date if plan-based',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_owner_feature` (`owner_type`,`owner_id`,`feature`),
  KEY `ai_feature_credits_owner_type_owner_id_index` (`owner_type`,`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_image_enhancement_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_image_enhancement_jobs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_image_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enhanced_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Which AI service was used',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `requested_by_type` enum('vendor','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied` tinyint NOT NULL DEFAULT '0' COMMENT 'Did vendor accept and apply the enhanced version',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_image_enhancement_jobs_product_image_id_foreign` (`product_image_id`),
  KEY `ai_jobs_requested_by_index` (`requested_by_type`,`requested_by_id`),
  KEY `ai_image_enhancement_jobs_status_index` (`status`),
  CONSTRAINT `ai_image_enhancement_jobs_product_image_id_foreign` FOREIGN KEY (`product_image_id`) REFERENCES `product_images` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_video_generation_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_video_generation_jobs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by_type` enum('vendor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prompt` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_images` json DEFAULT NULL COMMENT 'Array of image paths used as input',
  `result_video_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_seconds` int DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Which AI service was used',
  `credits_consumed` int NOT NULL DEFAULT '1',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_video_generation_jobs_vendor_listing_id_foreign` (`vendor_listing_id`),
  KEY `ai_video_generation_jobs_requested_by_type_requested_by_id_index` (`requested_by_type`,`requested_by_id`),
  KEY `ai_video_generation_jobs_status_index` (`status`),
  KEY `ai_video_generation_jobs_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `ai_video_generation_jobs_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_video_generation_jobs_vendor_listing_id_foreign` FOREIGN KEY (`vendor_listing_id`) REFERENCES `vendor_listings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `announcement_bars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcement_bars` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `priority` int NOT NULL DEFAULT '0',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcement_bars_country_id_index` (`country_id`),
  KEY `announcement_bars_created_by_admin_id_index` (`created_by_admin_id`),
  KEY `announcement_bars_updated_by_admin_id_index` (`updated_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `app_bottom_nav_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_bottom_nav_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `app_context_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` tinyint NOT NULL,
  `nav_type` enum('home','categories','featured','account','cart','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_en` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_ar` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deep_link` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_center_featured` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` tinyint NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_bottom_nav_items_app_context_id_country_id_position_unique` (`app_context_id`,`country_id`,`position`),
  KEY `app_bottom_nav_items_country_id_foreign` (`country_id`),
  CONSTRAINT `app_bottom_nav_items_app_context_id_foreign` FOREIGN KEY (`app_context_id`) REFERENCES `app_contexts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `app_bottom_nav_items_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `app_context_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_context_countries` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `app_context_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `home_page_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_context_countries_app_context_id_country_id_unique` (`app_context_id`,`country_id`),
  KEY `app_context_countries_country_id_foreign` (`country_id`),
  KEY `app_context_countries_home_page_id_foreign` (`home_page_id`),
  CONSTRAINT `app_context_countries_app_context_id_foreign` FOREIGN KEY (`app_context_id`) REFERENCES `app_contexts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `app_context_countries_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `app_context_countries_home_page_id_foreign` FOREIGN KEY (`home_page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `app_contexts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_contexts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_hex` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_contexts_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attribute_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attribute_values` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_hex` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `swatch_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attribute_values_slug_unique` (`slug`),
  KEY `attribute_values_attribute_id_index` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attributes` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('text','number','boolean','select','multi_select','color') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `width_px` int NOT NULL,
  `height_px` int NOT NULL,
  `max_file_size_kb` int NOT NULL,
  `allowed_formats` json NOT NULL,
  `device_restriction` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_simultaneous` int NOT NULL,
  `supports_vendor_ads` tinyint(1) NOT NULL,
  `base_rate_weekly` bigint DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `placement_code` enum('homepage_hero','homepage_secondary_left','homepage_secondary_right','homepage_midpage','category_top_{slug}','category_sidebar','search_top','cart_banner','checkout_banner','product_page_bottom','app_splash','app_home_top','email_header') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_en` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_ar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_en` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_ar` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_reference_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_target` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `audience` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` int NOT NULL DEFAULT '0',
  `impressions_count` bigint NOT NULL DEFAULT '0',
  `clicks_count` bigint NOT NULL DEFAULT '0',
  `created_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `impressions` int NOT NULL DEFAULT '0',
  `clicks` int NOT NULL DEFAULT '0',
  `unique_visitors` int NOT NULL DEFAULT '0',
  `add_to_cart_count` int NOT NULL DEFAULT '0',
  `orders_attributed` int NOT NULL DEFAULT '0',
  `revenue_attributed` bigint NOT NULL DEFAULT '0',
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `click_target` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `click_target_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_en` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label_ar` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `config_schema` json DEFAULT NULL,
  `default_config` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `requires_permission` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_per_page` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `block_types_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_categories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Supports one level of sub-categories — a category with a parent cannot itself be a parent (enforce at app layer, not DB constraint, for simplicity)',
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `cover_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_hex` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Brand accent color for this category, e.g. #3B82F6 — used for category pill/badge on the portal',
  `icon_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Heroicon or similar name for category badge',
  `seo_title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description_en` text COLLATE utf8mb4_unicode_ci,
  `seo_description_ar` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_categories_slug_unique` (`slug`),
  KEY `blog_categories_is_active_sort_order_index` (`is_active`,`sort_order`),
  KEY `blog_categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `blog_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_posts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `blog_category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL slug — shared across both languages, language is determined by the portal request locale',
  `excerpt_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Short summary shown on listing cards, max 300 chars',
  `excerpt_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_en` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full article body — HTML from a rich text editor',
  `body_ar` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `featured_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hero image shown at top of post and on listing cards',
  `featured_image_alt_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `featured_image_alt_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','scheduled','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL COMMENT 'When status became published — set automatically on first publish action, not manually editable',
  `scheduled_for` timestamp NULL DEFAULT NULL COMMENT 'When status=scheduled, auto-publishes at this datetime via a scheduled job',
  `archived_at` timestamp NULL DEFAULT NULL,
  `published_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description_en` text COLLATE utf8mb4_unicode_ci,
  `seo_description_ar` text COLLATE utf8mb4_unicode_ci,
  `og_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Social share image — may differ from featured_image',
  `views_count` int unsigned NOT NULL DEFAULT '0',
  `allow_comments` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Reserved for future use — blog launches without comments, toggle ready for when/if it is added',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Pinned/featured post shown in a hero slot on the blog index — only one featured post per country should be active at a time; enforce at app layer',
  `reading_time_minutes` int unsigned NOT NULL DEFAULT '0' COMMENT 'Computed from body word count at save time — ~200 words per minute average',
  `tags` json DEFAULT NULL COMMENT 'Free-form string tags, e.g. ["ecommerce","tips"]',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_posts_slug_unique` (`slug`),
  KEY `blog_posts_author_admin_id_foreign` (`author_admin_id`),
  KEY `blog_posts_published_by_admin_id_foreign` (`published_by_admin_id`),
  KEY `blog_posts_status_published_at_index` (`status`,`published_at`),
  KEY `blog_posts_blog_category_id_status_index` (`blog_category_id`,`status`),
  KEY `blog_posts_country_id_status_published_at_index` (`country_id`,`status`,`published_at`),
  KEY `blog_posts_is_featured_status_index` (`is_featured`,`status`),
  CONSTRAINT `blog_posts_author_admin_id_foreign` FOREIGN KEY (`author_admin_id`) REFERENCES `admins` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `blog_posts_blog_category_id_foreign` FOREIGN KEY (`blog_category_id`) REFERENCES `blog_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `blog_posts_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blog_posts_published_by_admin_id_foreign` FOREIGN KEY (`published_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `brands` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo_media_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_ar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `website_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `carrier_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carrier_claims` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `claim_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipment_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_company_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_agent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claim_type` enum('lost','damaged','delayed','wrong_item','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `claimed_amount` bigint unsigned NOT NULL,
  `evidence_files` json DEFAULT NULL,
  `status` enum('submitted','under_review','approved','rejected','compensated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `compensated_amount` bigint unsigned DEFAULT NULL,
  `resolved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `carrier_claims_claim_number_unique` (`claim_number`),
  KEY `carrier_claims_shipment_id_foreign` (`shipment_id`),
  KEY `carrier_claims_delivery_agent_id_foreign` (`delivery_agent_id`),
  KEY `carrier_claims_resolved_by_admin_id_foreign` (`resolved_by_admin_id`),
  KEY `carrier_claims_status_created_at_index` (`status`,`created_at`),
  KEY `carrier_claims_shipping_company_id_index` (`shipping_company_id`),
  CONSTRAINT `carrier_claims_delivery_agent_id_foreign` FOREIGN KEY (`delivery_agent_id`) REFERENCES `delivery_agents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `carrier_claims_resolved_by_admin_id_foreign` FOREIGN KEY (`resolved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `carrier_claims_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`),
  CONSTRAINT `carrier_claims_shipping_company_id_foreign` FOREIGN KEY (`shipping_company_id`) REFERENCES `shipping_companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `carrier_performance_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carrier_performance_ratings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_company_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_agent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rated_by_type` enum('customer','vendor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rated_by_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `on_time` tinyint(1) DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `visible_to_customer` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `carrier_performance_ratings_shipping_company_id_created_at_index` (`shipping_company_id`,`created_at`),
  KEY `carrier_performance_ratings_delivery_agent_id_created_at_index` (`delivery_agent_id`,`created_at`),
  KEY `carrier_performance_ratings_sub_order_id_rated_by_type_index` (`sub_order_id`,`rated_by_type`),
  CONSTRAINT `carrier_performance_ratings_delivery_agent_id_foreign` FOREIGN KEY (`delivery_agent_id`) REFERENCES `delivery_agents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `carrier_performance_ratings_shipping_company_id_foreign` FOREIGN KEY (`shipping_company_id`) REFERENCES `shipping_companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `carrier_performance_ratings_sub_order_id_foreign` FOREIGN KEY (`sub_order_id`) REFERENCES `sub_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cart_card_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_card_offers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `card_name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `card_name_ar` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name_en` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_image_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cashback_type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cashback_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `cashback_fixed_amount` bigint unsigned NOT NULL DEFAULT '0',
  `label_template_en` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Earn {amount} CA$HBACK with {card_name}',
  `label_template_ar` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apply_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apply_label_en` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Apply',
  `apply_label_ar` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'قدم الآن',
  `min_order_amount` bigint unsigned NOT NULL DEFAULT '0',
  `max_cashback_amount` bigint unsigned DEFAULT NULL,
  `valid_from` timestamp NULL DEFAULT NULL,
  `valid_until` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cart_card_offers_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `cart_card_offers_country_id_is_active_sort_order_index` (`country_id`,`is_active`,`sort_order`),
  CONSTRAINT `cart_card_offers_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cart_card_offers_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cart_inventory_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_inventory_locks` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cart_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_inventory_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cart_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selected_shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` bigint NOT NULL,
  `added_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cart_items_cart_id_index` (`cart_id`),
  KEY `cart_items_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `cart_items_admin_product_listing_id_index` (`admin_listing_id`),
  KEY `cart_items_selected_shipping_method_id_foreign` (`selected_shipping_method_id`),
  CONSTRAINT `cart_items_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cart_items_selected_shipping_method_id_foreign` FOREIGN KEY (`selected_shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carts` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` bigint NOT NULL,
  `discount` bigint NOT NULL,
  `wallet_amount_to_use` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'BIGINT base-currency. How much of the wallet balance the customer wants to apply at checkout. Deducted from estimated_total display. No /100.',
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_ar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `commission_fbp_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Percentage commission for merchant_fbp listings (e.g. 8.00 = 8%)',
  `commission_fbp_fixed` bigint NOT NULL DEFAULT '0' COMMENT 'Fixed fee per item per unit for merchant_fbp, in platform base currency cents',
  `commission_fbn_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Percentage commission for express_fbn listings',
  `commission_fbn_fixed` bigint NOT NULL DEFAULT '0' COMMENT 'Fixed fee per item per unit for express_fbn, in platform base currency cents',
  `lft` int DEFAULT NULL,
  `rgt` int DEFAULT NULL,
  `product_count` int unsigned NOT NULL DEFAULT '0' COMMENT 'Subtree product count, maintained by RecalculateCategoryStatsJob',
  `depth` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `supports_virtual_tryon` tinyint NOT NULL DEFAULT '0' COMMENT 'Enable for apparel/shoes categories',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `influencer_sample_qty` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Samples per influencer marketer per campaign for this category',
  `affiliate_sample_qty` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Samples per affiliate marketer per campaign for this category',
  `platform_sample_qty` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Platform-owned non-refundable samples per campaign for this category',
  `min_stock_for_campaign` smallint unsigned NOT NULL DEFAULT '10' COMMENT 'Minimum vendor listing stock required to start a campaign',
  `seo_title_ar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `seo_title_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `seo_description_ar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `seo_description_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_parent_id_index` (`parent_id`),
  KEY `idx_categories_lft_rgt` (`lft`,`rgt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `category_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_attributes` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_attributes_category_id_index` (`category_id`),
  KEY `category_attributes_attribute_id_index` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `category_shipping_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_shipping_methods` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Primary method shown as the main delivery badge on listing cards — one per category',
  `is_available_for_express_fbn` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Offered for express_fbn (platform-warehoused) listings in this category',
  `is_available_for_merchant_fbp` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Offered for merchant_fbp (vendor-fulfilled) listings — typically scheduled/pickup only',
  `display_priority` int unsigned NOT NULL DEFAULT '0' COMMENT 'Order this method appears for the category — lower = shown first',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_shipping_methods_category_id_shipping_method_id_unique` (`category_id`,`shipping_method_id`),
  KEY `category_shipping_methods_shipping_method_id_foreign` (`shipping_method_id`),
  KEY `category_shipping_methods_category_id_is_default_index` (`category_id`,`is_default`),
  CONSTRAINT `category_shipping_methods_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `category_shipping_methods_shipping_method_id_foreign` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `classified_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classified_categories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_location_map` tinyint NOT NULL DEFAULT '0',
  `requires_sketch_upload` tinyint NOT NULL DEFAULT '0',
  `contract_template_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `required_attachment_types` json DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classified_categories_slug_unique` (`slug`),
  KEY `classified_categories_parent_id_foreign` (`parent_id`),
  KEY `classified_categories_contract_template_id_foreign` (`contract_template_id`),
  CONSTRAINT `classified_categories_contract_template_id_foreign` FOREIGN KEY (`contract_template_id`) REFERENCES `classified_contract_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `classified_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `classified_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classified_contract_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classified_contract_templates` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classified_category_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  `content_en` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_ar` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classified_contract_templates_created_by_admin_id_foreign` (`created_by_admin_id`),
  CONSTRAINT `classified_contract_templates_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classified_inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classified_inquiries` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classified_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('new','contacted','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classified_inquiries_classified_listing_id_foreign` (`classified_listing_id`),
  KEY `classified_inquiries_customer_id_foreign` (`customer_id`),
  CONSTRAINT `classified_inquiries_classified_listing_id_foreign` FOREIGN KEY (`classified_listing_id`) REFERENCES `classified_listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classified_inquiries_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classified_listing_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classified_listing_attachments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classified_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','verified','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classified_listing_attachments_classified_listing_id_foreign` (`classified_listing_id`),
  KEY `classified_listing_attachments_verified_by_admin_id_foreign` (`verified_by_admin_id`),
  CONSTRAINT `classified_listing_attachments_classified_listing_id_foreign` FOREIGN KEY (`classified_listing_id`) REFERENCES `classified_listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classified_listing_attachments_verified_by_admin_id_foreign` FOREIGN KEY (`verified_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classified_listing_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classified_listing_images` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classified_listing_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `is_primary` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `classified_listing_images_classified_listing_id_foreign` (`classified_listing_id`),
  CONSTRAINT `classified_listing_images_classified_listing_id_foreign` FOREIGN KEY (`classified_listing_id`) REFERENCES `classified_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classified_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classified_listings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `listing_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seller_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seller_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classified_category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `listing_purpose` enum('sale','rent') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `price` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_negotiable` tinyint NOT NULL DEFAULT '0',
  `attributes` json DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `sketch_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','pending_contract','pending_review','active','paused','sold','expired','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `contract_template_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_accepted_at` timestamp NULL DEFAULT NULL,
  `contract_signature_data` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `views_count` int NOT NULL DEFAULT '0',
  `expires_at` date DEFAULT NULL,
  `barcode_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_vendor_listing` tinyint NOT NULL DEFAULT '0',
  `vendor_listing_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classified_listings_listing_number_unique` (`listing_number`),
  UNIQUE KEY `classified_listings_slug_unique` (`slug`),
  KEY `classified_listings_classified_category_id_foreign` (`classified_category_id`),
  KEY `classified_listings_country_id_foreign` (`country_id`),
  KEY `classified_listings_city_id_foreign` (`city_id`),
  KEY `classified_listings_contract_template_id_foreign` (`contract_template_id`),
  KEY `classified_listings_approved_by_admin_id_foreign` (`approved_by_admin_id`),
  KEY `classified_listings_seller_index` (`seller_type`,`seller_id`),
  CONSTRAINT `classified_listings_approved_by_admin_id_foreign` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `classified_listings_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `classified_listings_classified_category_id_foreign` FOREIGN KEY (`classified_category_id`) REFERENCES `classified_categories` (`id`),
  CONSTRAINT `classified_listings_contract_template_id_foreign` FOREIGN KEY (`contract_template_id`) REFERENCES `classified_contract_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `classified_listings_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commissions` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rate_pct` decimal(5,2) NOT NULL,
  `rate_type` enum('flat','tiered') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
DROP TABLE IF EXISTS `content_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci,
  `options` json DEFAULT NULL,
  `default_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allowed_extensions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_size_kb` int unsigned DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_settings_key_unique` (`key`),
  KEY `content_settings_updated_by_admin_id_foreign` (`updated_by_admin_id`),
  KEY `content_settings_group_section_index` (`group`,`section`),
  CONSTRAINT `content_settings_updated_by_admin_id_foreign` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code_2` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code_3` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `site_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_domain` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flag_emoji` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_prefix` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_locale` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `timezone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'UTC',
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `commission_fbp_pct` decimal(5,2) DEFAULT NULL COMMENT 'Country-level FBP commission % override. NULL = inherit category rate.',
  `commission_fbp_fixed` bigint DEFAULT NULL COMMENT 'Country-level FBP fixed fee per unit override (base currency units). NULL = inherit.',
  `commission_fbn_pct` decimal(5,2) DEFAULT NULL COMMENT 'Country-level FBN commission % override. NULL = inherit category rate.',
  `commission_fbn_fixed` bigint DEFAULT NULL COMMENT 'Country-level FBN fixed fee per unit override (base currency units). NULL = inherit.',
  `unavailable_reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `country_categories_country_id_index` (`country_id`),
  KEY `country_categories_category_id_index` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `country_payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `country_payment_gateways` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name_en` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `environment` enum('sandbox','production') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sandbox',
  `credentials_encrypted` text COLLATE utf8mb4_unicode_ci,
  `webhook_secret_encrypted` text COLLATE utf8mb4_unicode_ci,
  `fee_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `fee_fixed` bigint NOT NULL DEFAULT '0',
  `min_order` bigint NOT NULL DEFAULT '0',
  `max_order` bigint DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `last_verified_at` timestamp NULL DEFAULT NULL,
  `last_verification_status` enum('success','failed') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_verification_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `country_payment_gateways_country_id_gateway_id_unique` (`country_id`,`gateway_id`),
  KEY `country_payment_gateways_country_id_index` (`country_id`),
  KEY `country_payment_gateways_gateway_id_index` (`gateway_id`),
  CONSTRAINT `country_payment_gateways_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `country_payment_gateways_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `payment_gateways` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `country_shipping_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `country_shipping_settings` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `free_shipping_threshold` bigint unsigned DEFAULT NULL,
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
  `coupon_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `coupon_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_ar` json DEFAULT NULL,
  `terms_en` json DEFAULT NULL,
  `type` enum('percentage','fixed_amount','free_shipping','bogo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(15,2) NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scope` enum('platform','vendor','category','product') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_ids` json DEFAULT NULL COMMENT 'JSON array of country UUIDs. NULL = all countries.',
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_order_amount` bigint DEFAULT NULL,
  `max_discount` bigint DEFAULT NULL,
  `usage_limit_total` int DEFAULT NULL,
  `usage_limit_per_customer` int NOT NULL DEFAULT '1',
  `max_orders_per_customer_per_month` int unsigned DEFAULT NULL,
  `times_used` int NOT NULL DEFAULT '0',
  `customer_eligibility` enum('all','new_customers','specific_segment','specific_users') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `eligible_customer_ids` json DEFAULT NULL COMMENT 'JSON array of customer UUIDs. Used when customer_eligibility = specific_users.',
  `valid_from` timestamp NOT NULL,
  `valid_until` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_stackable` tinyint(1) NOT NULL DEFAULT '0',
  `funded_by` enum('platform','vendor','shared') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `vendor_share_pct` tinyint unsigned DEFAULT NULL COMMENT '0–100 integer. Platform absorbs the remainder.',
  `created_by_user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `decimal_places` tinyint NOT NULL DEFAULT '2',
  `base_currency_code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `exchange_rate_to_base` decimal(15,6) NOT NULL DEFAULT '1.000000',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_manually_overridden` tinyint(1) NOT NULL DEFAULT '0',
  `rate_updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_otp_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_otp_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('email_verification','phone_verification','password_reset') COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_otp_tokens_customer_id_index` (`customer_id`),
  KEY `customer_otp_tokens_token_index` (`token`),
  CONSTRAINT `customer_otp_tokens_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_receivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_receivers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_receivers_customer_id_is_default_index` (`customer_id`,`is_default`),
  CONSTRAINT `customer_receivers_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_wallets` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` bigint unsigned NOT NULL DEFAULT '0',
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_wallets_customer_id_unique` (`customer_id`),
  CONSTRAINT `customer_wallets_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locale` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ar',
  `marketing_email_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `marketing_sms_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `marketing_whatsapp_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('active','suspended','banned','deleted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_tourist` tinyint(1) NOT NULL DEFAULT '0',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_orders` int NOT NULL DEFAULT '0',
  `total_spent` decimal(10,2) NOT NULL DEFAULT '0.00',
  `referral_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_code_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referred_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `delivery_agent_cod_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_agent_cod_settlements` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agent_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_cod_collected` bigint NOT NULL,
  `total_earnings_owed` bigint NOT NULL,
  `net_to_remit` bigint NOT NULL,
  `status` enum('pending','settled','disputed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `settled_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `has_collection_discrepancy` tinyint(1) NOT NULL DEFAULT '0',
  `discrepancy_notes` text COLLATE utf8mb4_unicode_ci,
  `discrepancy_amount` bigint NOT NULL DEFAULT '0',
  `discrepancy_resolution` enum('pending','deducted_from_earnings','written_off','vendor_chargeback') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_agent_cod_settlements_agent_id_period_start_index` (`agent_id`,`period_start`),
  KEY `delivery_agent_cod_settlements_has_collection_discrepancy_index` (`has_collection_discrepancy`),
  CONSTRAINT `delivery_agent_cod_settlements_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_agent_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_agent_documents` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agent_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` enum('national_id','driving_license','vehicle_registration','insurance','profile_photo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','verified','rejected','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_agent_documents_agent_id_document_type_index` (`agent_id`,`document_type`),
  KEY `delivery_agent_documents_verified_by_admin_id_index` (`verified_by_admin_id`),
  CONSTRAINT `delivery_agent_documents_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_agent_earnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_agent_earnings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agent_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_assignment_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `earning_type` enum('base_fee','cod_handling','bonus','tip','deduction') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','paid','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_agent_earnings_delivery_assignment_id_foreign` (`delivery_assignment_id`),
  KEY `delivery_agent_earnings_order_id_foreign` (`order_id`),
  KEY `delivery_agent_earnings_agent_id_status_index` (`agent_id`,`status`),
  CONSTRAINT `delivery_agent_earnings_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`id`),
  CONSTRAINT `delivery_agent_earnings_delivery_assignment_id_foreign` FOREIGN KEY (`delivery_assignment_id`) REFERENCES `delivery_assignments` (`id`),
  CONSTRAINT `delivery_agent_earnings_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_agent_payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_agent_payouts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payout_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agent_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_deliveries` int NOT NULL DEFAULT '0',
  `gross_earnings` bigint NOT NULL DEFAULT '0',
  `deductions` bigint NOT NULL DEFAULT '0',
  `net_amount` bigint NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','paid','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_agent_payouts_payout_number_unique` (`payout_number`),
  KEY `delivery_agent_payouts_agent_id_status_index` (`agent_id`,`status`),
  KEY `delivery_agent_payouts_approved_by_admin_id_index` (`approved_by_admin_id`),
  CONSTRAINT `delivery_agent_payouts_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_agent_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_agent_shifts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agent_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift_date` date NOT NULL,
  `zone_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_start` time NOT NULL,
  `scheduled_end` time NOT NULL,
  `actual_start` timestamp NULL DEFAULT NULL,
  `actual_end` timestamp NULL DEFAULT NULL,
  `status` enum('scheduled','active','completed','no_show','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `total_deliveries` int NOT NULL DEFAULT '0',
  `total_earnings` bigint NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_agent_shifts_agent_id_shift_date_index` (`agent_id`,`shift_date`),
  KEY `delivery_agent_shifts_zone_id_index` (`zone_id`),
  CONSTRAINT `delivery_agent_shifts_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`id`),
  CONSTRAINT `delivery_agent_shifts_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_agents` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','on_shift','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `agent_type` enum('platform','third_party') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `shipping_company_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NULL = platform-employed agent',
  `added_by_supervisor_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_type` enum('motorcycle','car','van','bicycle') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'motorcycle',
  `vehicle_plate` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_salary` int unsigned DEFAULT NULL,
  `current_latitude` decimal(10,7) DEFAULT NULL,
  `current_longitude` decimal(10,7) DEFAULT NULL,
  `last_location_at` timestamp NULL DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '0',
  `rating_avg` decimal(3,2) NOT NULL DEFAULT '5.00',
  `total_deliveries` int unsigned NOT NULL DEFAULT '0',
  `per_delivery_fee` int unsigned DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_agents_email_unique` (`email`),
  UNIQUE KEY `delivery_agents_phone_unique` (`phone`),
  KEY `delivery_agents_dispatch_idx` (`country_id`,`is_available`,`status`),
  KEY `delivery_agents_shipping_company_id_foreign` (`shipping_company_id`),
  KEY `delivery_agents_added_by_supervisor_id_foreign` (`added_by_supervisor_id`),
  KEY `delivery_agents_zone_id_foreign` (`zone_id`),
  CONSTRAINT `delivery_agents_added_by_supervisor_id_foreign` FOREIGN KEY (`added_by_supervisor_id`) REFERENCES `shipping_company_supervisors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_agents_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `delivery_agents_shipping_company_id_foreign` FOREIGN KEY (`shipping_company_id`) REFERENCES `shipping_companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_agents_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_assignments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipment_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agent_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('assigned','accepted','picked_up','delivered','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `assigned_at` timestamp NOT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `failure_reason` enum('customer_not_home','wrong_address','customer_refused','damaged_package','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_notes` text COLLATE utf8mb4_unicode_ci,
  `delivery_otp` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_amount_collected` bigint DEFAULT NULL,
  `discrepancy_note` text COLLATE utf8mb4_unicode_ci,
  `cod_settlement_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `otp_verified` tinyint(1) NOT NULL DEFAULT '0',
  `proof_file_id` bigint unsigned DEFAULT NULL,
  `agent_notes` text COLLATE utf8mb4_unicode_ci,
  `pickup_latitude` decimal(10,7) DEFAULT NULL,
  `pickup_longitude` decimal(10,7) DEFAULT NULL,
  `delivery_latitude` decimal(10,7) DEFAULT NULL,
  `delivery_longitude` decimal(10,7) DEFAULT NULL,
  `customer_rating` tinyint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `customer_rejection_reason` text COLLATE utf8mb4_unicode_ci COMMENT 'Written by customer or delivery agent on customer behalf',
  `rejected_by_customer_at` timestamp NULL DEFAULT NULL,
  `rejection_reason_mandatory` tinyint NOT NULL DEFAULT '0' COMMENT 'Set to 1 by system when payment was electronic',
  PRIMARY KEY (`id`),
  KEY `delivery_assignments_shipment_id_foreign` (`shipment_id`),
  KEY `delivery_assignments_sub_order_id_foreign` (`sub_order_id`),
  KEY `delivery_assignments_proof_file_id_foreign` (`proof_file_id`),
  KEY `delivery_assignments_agent_id_status_index` (`agent_id`,`status`),
  KEY `delivery_assignments_status_assigned_at_index` (`status`,`assigned_at`),
  KEY `delivery_assignments_cod_settlement_id_foreign` (`cod_settlement_id`),
  CONSTRAINT `delivery_assignments_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`id`),
  CONSTRAINT `delivery_assignments_cod_settlement_id_foreign` FOREIGN KEY (`cod_settlement_id`) REFERENCES `delivery_agent_cod_settlements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_assignments_proof_file_id_foreign` FOREIGN KEY (`proof_file_id`) REFERENCES `files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `delivery_assignments_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`),
  CONSTRAINT `delivery_assignments_sub_order_id_foreign` FOREIGN KEY (`sub_order_id`) REFERENCES `sub_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_zones` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city_ids` json DEFAULT NULL COMMENT 'Array of city IDs this zone covers',
  `polygon_coordinates` json DEFAULT NULL COMMENT 'GeoJSON polygon for map display',
  `base_delivery_fee` bigint NOT NULL DEFAULT '0',
  `cod_fee` bigint NOT NULL DEFAULT '0',
  `max_active_agents` int DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_zones_code_unique` (`code`),
  KEY `delivery_zones_country_id_foreign` (`country_id`),
  CONSTRAINT `delivery_zones_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `device_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `device_tokens` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` enum('ios','android','web') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_tokens_token_unique` (`token`),
  KEY `device_tokens_tokenable_idx` (`tokenable_type`,`tokenable_id`),
  KEY `device_tokens_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dispute_evidence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispute_evidence` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispute_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_by_user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispute_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_role` enum('customer','seller','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispute_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `return_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` enum('item_not_received','item_damaged','item_not_as_described','counterfeit','wrong_item','quality_issue','seller_unresponsive','refund_not_received','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','seller_responded','under_review','escalated','resolved','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolution` enum('favor_customer','favor_seller','split','no_action') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `compensation` bigint DEFAULT NULL,
  `assigned_to_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faqs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'seller',
  `question_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer_en` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer_ar` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `faqs_updated_by_admin_id_foreign` (`updated_by_admin_id`),
  KEY `faqs_context_index` (`context`),
  CONSTRAINT `faqs_updated_by_admin_id_foreign` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fbn_daily_overage_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fbn_daily_overage_fees` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_inventory_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `received_at` date NOT NULL,
  `free_period_ends_at` date NOT NULL,
  `fee_date` date NOT NULL,
  `units` int unsigned NOT NULL,
  `fee_per_unit` bigint NOT NULL,
  `total_fee` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','invoiced','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inventory_fee_date` (`warehouse_inventory_id`,`fee_date`),
  KEY `fbn_daily_overage_fees_vendor_id_foreign` (`vendor_id`),
  KEY `fbn_daily_overage_fees_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `fbn_daily_overage_fees_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fbn_daily_overage_fees_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fbn_daily_overage_fees_warehouse_inventory_id_foreign` FOREIGN KEY (`warehouse_inventory_id`) REFERENCES `warehouse_inventories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fbn_inbound_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fbn_inbound_requests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_requested` int NOT NULL,
  `quantity_received` int NOT NULL DEFAULT '0',
  `status` enum('draft','submitted','approved','shipped','received','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `admin_approved_by` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `expected_arrival` date DEFAULT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `vendor_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fbn_inbound_requests_request_number_unique` (`request_number`),
  KEY `fbn_inbound_requests_vendor_id_index` (`vendor_id`),
  KEY `fbn_inbound_requests_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `fbn_inbound_requests_warehouse_id_index` (`warehouse_id`),
  KEY `fbn_inbound_requests_admin_approved_by_index` (`admin_approved_by`),
  KEY `fbn_inbound_requests_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `fbn_inbound_requests_admin_approved_by_foreign` FOREIGN KEY (`admin_approved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fbn_inbound_requests_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fbn_inbound_requests_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fbn_inbound_requests_vendor_listing_id_foreign` FOREIGN KEY (`vendor_listing_id`) REFERENCES `vendor_listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fbn_inbound_requests_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_fir_listing_xor` CHECK ((((`vendor_listing_id` is not null) and (`admin_listing_id` is null)) or ((`vendor_listing_id` is null) and (`admin_listing_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fbn_storage_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fbn_storage_fees` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_inventory_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `month` date NOT NULL COMMENT 'First day of the billed month e.g. 2026-06-01',
  `units_stored` int NOT NULL,
  `rate_per_unit` bigint NOT NULL,
  `total_fee` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','invoiced','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fbn_storage_fees_vendor_id_warehouse_inventory_id_month_unique` (`vendor_id`,`warehouse_inventory_id`,`month`),
  KEY `fbn_storage_fees_vendor_id_index` (`vendor_id`),
  KEY `fbn_storage_fees_warehouse_inventory_id_index` (`warehouse_inventory_id`),
  CONSTRAINT `fbn_storage_fees_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fbn_storage_fees_warehouse_inventory_id_foreign` FOREIGN KEY (`warehouse_inventory_id`) REFERENCES `warehouse_inventories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `file_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extension` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned NOT NULL DEFAULT '0',
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `md5_hash` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `perceptual_hash` char(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_submission_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `units_sold` int NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_submission_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_item_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` bigint NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_at` timestamp NOT NULL,
  `recorded_by` enum('system','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flash_sale_price_histories_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `flash_sale_price_histories_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `flash_sale_price_histories_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_fsph_listing_xor` CHECK ((((`vendor_listing_id` is not null) and (`admin_listing_id` is null)) or ((`vendor_listing_id` is null) and (`admin_listing_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sale_submission_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sale_submission_histories` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_submission_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by_user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by_role` enum('admin','vendor','system') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_price` bigint NOT NULL,
  `original_price` bigint NOT NULL,
  `calculated_discount_pct` decimal(5,2) NOT NULL,
  `reference_price_30d` bigint DEFAULT NULL,
  `max_quantity_total` int NOT NULL,
  `max_quantity_per_customer` int NOT NULL DEFAULT '1',
  `quantity_sold` int NOT NULL DEFAULT '0',
  `quantity_remaining` int GENERATED ALWAYS AS ((`max_quantity_total` - `quantity_sold`)) VIRTUAL,
  `flash_price_currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rejection_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `sold_out_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `vendor_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flash_sale_submissions_flash_sale_id_index` (`flash_sale_id`),
  KEY `flash_sale_submissions_vendor_id_index` (`vendor_id`),
  KEY `flash_sale_submissions_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `flash_sale_submissions_reviewed_by_admin_id_index` (`reviewed_by_admin_id`),
  KEY `flash_sale_submissions_flash_sale_status_created_index` (`flash_sale_id`,`status`,`created_at`),
  KEY `flash_sale_submissions_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `flash_sale_submissions_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_fss_listing_xor` CHECK ((((`vendor_listing_id` is not null) and (`admin_listing_id` is null)) or ((`vendor_listing_id` is null) and (`admin_listing_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flash_sale_vendor_invititions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sale_vendor_invititions` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flash_sale_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invitation_type` enum('auto','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','accepted','declined','submitted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `invited_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notified_at` timestamp NULL DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `decline_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_en` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_ar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `created_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flash_sales_country_id_index` (`country_id`),
  KEY `flash_sales_created_by_admin_id_index` (`created_by_admin_id`),
  KEY `flash_sales_updated_by_admin_id_index` (`updated_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `frequently_bought_together_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frequently_bought_together_items` (
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `freq_b_t_unique` (`product_id`,`related_product_id`),
  KEY `frequently_bought_together_items_product_id_index` (`product_id`),
  KEY `frequently_bought_together_items_related_product_id_index` (`related_product_id`),
  CONSTRAINT `frequently_bought_together_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `frequently_bought_together_items_related_product_id_foreign` FOREIGN KEY (`related_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gift_card_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gift_card_batches` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Card design image URL shown on storefront',
  `amount` bigint unsigned NOT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL,
  `is_purchasable` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True = listed on customer storefront for purchase',
  `min_quantity` tinyint unsigned NOT NULL DEFAULT '1',
  `max_quantity` tinyint unsigned NOT NULL DEFAULT '10',
  `sort_order` tinyint unsigned NOT NULL DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gift_card_batches_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `gift_card_batches_currency_code_index` (`currency_code`),
  CONSTRAINT `gift_card_batches_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gift_card_purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gift_card_purchases` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gift_card_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'One purchase per gift card — 1:1 relationship.',
  `gift_card_batch_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'The order placed to buy this gift card.',
  `buyer_customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_paid` bigint unsigned NOT NULL COMMENT 'What the customer paid. BIGINT base-currency — no /100.',
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_gift` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True = buyer entered a different recipient email.',
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gift_message` text COLLATE utf8mb4_unicode_ci,
  `delivery_status` enum('pending','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `delivered_at` timestamp NULL DEFAULT NULL,
  `delivery_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gift_card_purchases_gift_card_id_unique` (`gift_card_id`),
  KEY `gift_card_purchases_gift_card_batch_id_index` (`gift_card_batch_id`),
  KEY `gift_card_purchases_order_id_index` (`order_id`),
  KEY `gift_card_purchases_buyer_customer_id_index` (`buyer_customer_id`),
  CONSTRAINT `gift_card_purchases_buyer_customer_id_foreign` FOREIGN KEY (`buyer_customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `gift_card_purchases_gift_card_batch_id_foreign` FOREIGN KEY (`gift_card_batch_id`) REFERENCES `gift_card_batches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `gift_card_purchases_gift_card_id_foreign` FOREIGN KEY (`gift_card_id`) REFERENCES `gift_cards` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `gift_card_purchases_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gift_card_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gift_card_transactions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gift_card_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint NOT NULL,
  `balance_after` bigint NOT NULL,
  `type` enum('issuance','redemption','refund','admin_adjustment','expiry') COLLATE utf8mb4_unicode_ci NOT NULL,
  `performed_by_customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `gift_card_transactions_gift_card_id_foreign` (`gift_card_id`),
  KEY `gift_card_transactions_order_id_foreign` (`order_id`),
  KEY `gift_card_transactions_performed_by_customer_id_foreign` (`performed_by_customer_id`),
  KEY `gift_card_transactions_performed_by_admin_id_foreign` (`performed_by_admin_id`),
  CONSTRAINT `gift_card_transactions_gift_card_id_foreign` FOREIGN KEY (`gift_card_id`) REFERENCES `gift_cards` (`id`),
  CONSTRAINT `gift_card_transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gift_card_transactions_performed_by_admin_id_foreign` FOREIGN KEY (`performed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gift_card_transactions_performed_by_customer_id_foreign` FOREIGN KEY (`performed_by_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gift_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gift_cards` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gift_card_batch_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` enum('batch','purchased','gifted','refund_credit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'batch',
  `code` char(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `remaining_balance` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'BIGINT base-currency. Initialized = amount. Decremented on redemption.',
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('inactive','active','redeemed','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inactive',
  `redeemed_by_customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_to_customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'If set, only this customer can redeem this card.',
  `purchased_by_customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Customer who paid for this card. NULL = admin-generated, not purchased.',
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email to deliver code+PIN to. NULL = deliver to buyer.',
  `recipient_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_order_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'The orders.id where this gift card was purchased.',
  `delivery_sent_at` timestamp NULL DEFAULT NULL COMMENT 'When the code+PIN email/SMS was sent to the recipient.',
  `redeemed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gift_cards_code_unique` (`code`),
  KEY `gift_cards_gift_card_batch_id_foreign` (`gift_card_batch_id`),
  KEY `gift_cards_redeemed_by_customer_id_foreign` (`redeemed_by_customer_id`),
  KEY `gift_cards_status_index` (`status`),
  KEY `gift_cards_currency_code_index` (`currency_code`),
  CONSTRAINT `gift_cards_gift_card_batch_id_foreign` FOREIGN KEY (`gift_card_batch_id`) REFERENCES `gift_card_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gift_cards_redeemed_by_customer_id_foreign` FOREIGN KEY (`redeemed_by_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `help_center_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `help_center_articles` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `help_center_category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Matches countries.site_code (the {country} portal route segment). Null = shown for every country.',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Short summary — also used as the meta description on the portal',
  `excerpt_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `excerpt_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full article body — HTML from a rich text editor',
  `body_en` longtext COLLATE utf8mb4_unicode_ci,
  `body_ar` longtext COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','published') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL COMMENT 'Set automatically the first time status becomes published',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'The one article linked from the Help Center home page featured callout — enforce single featured article at app layer',
  `related_article_ids` json DEFAULT NULL COMMENT 'Manually curated related-articles list — array of help_center_articles ids',
  `views_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `help_center_articles_slug_unique` (`slug`),
  KEY `help_center_articles_author_admin_id_foreign` (`author_admin_id`),
  KEY `help_center_articles_status_published_at_index` (`status`,`published_at`),
  KEY `help_center_articles_help_center_category_id_status_index` (`help_center_category_id`,`status`),
  KEY `help_center_articles_is_featured_status_index` (`is_featured`,`status`),
  KEY `help_center_articles_country_id_index` (`country_id`),
  CONSTRAINT `help_center_articles_author_admin_id_foreign` FOREIGN KEY (`author_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `help_center_articles_help_center_category_id_foreign` FOREIGN KEY (`help_center_category_id`) REFERENCES `help_center_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `help_center_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `help_center_categories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Supports one level of sub-categories — a category with a parent cannot itself be a parent (enforce at app layer, not DB constraint, for simplicity)',
  `country_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Matches countries.site_code (the {country} portal route segment). Null = shown for every country.',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Icon URL shown on the category card (defaults to a noon-style svg icon)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `help_center_categories_slug_unique` (`slug`),
  KEY `help_center_categories_is_active_sort_order_index` (`is_active`,`sort_order`),
  KEY `help_center_categories_country_id_index` (`country_id`),
  KEY `help_center_categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `help_center_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `help_center_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idempotency_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idempotency_keys` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `operation_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `inbound_shipment_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_quantity` int NOT NULL,
  `received_quantity` int NOT NULL DEFAULT '0',
  `damaged_quantity` int NOT NULL DEFAULT '0',
  `condition_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inbound_shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inbound_shipments` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipment_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','submitted','in_transit','arrived','receiving','received','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_arrival_date` date DEFAULT NULL,
  `arrived_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `received_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_inventory_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `movement_type` enum('inbound','outbound','reservation','release','adjustment','damage','return','transfer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_delta` int NOT NULL,
  `quantity_after` int NOT NULL,
  `reference_type` enum('order','inbound_shipment','transfer','adjustment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `inventory_transfer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity_requested` int NOT NULL,
  `quantity_received` int NOT NULL DEFAULT '0',
  `damaged_quantity` int NOT NULL DEFAULT '0',
  `condition_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_transfer_items_inventory_transfer_id_index` (`inventory_transfer_id`),
  KEY `inventory_transfer_items_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `inventory_transfer_items_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `inventory_transfer_items_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_iti_listing_xor` CHECK ((((`vendor_listing_id` is not null) and (`admin_listing_id` is null)) or ((`vendor_listing_id` is null) and (`admin_listing_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_transfers` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transfer_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_warehouse_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_warehouse_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','in_transit','received','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `initiated_by_user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_arrival_date` date DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `native_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `english_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('ltr','rtl') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ltr',
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_group_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` enum('customer_payment','platform_revenue','platform_commission','seller_payable','gateway_fee','tax_payable','refund_liability','shipping_revenue','cod_clearing') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_holder_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_holder_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `debit` bigint NOT NULL DEFAULT '0',
  `credit` bigint NOT NULL DEFAULT '0',
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ledger_entries_transaction_group_id_index` (`transaction_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketer_campaign_conversions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketer_campaign_conversions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invitation_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_item_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referral_clicked_at` timestamp NULL DEFAULT NULL,
  `commission_amount` bigint NOT NULL DEFAULT '0' COMMENT 'Commission earned for this conversion. BIGINT base-currency. No /100.',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commissioned` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Has this conversion been paid out?',
  `paid_at` timestamp NULL DEFAULT NULL,
  `sale_number_in_campaign` int unsigned DEFAULT NULL COMMENT 'Marketer sale sequence number in this campaign — used for tiered commission',
  `tiered_rule_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketer_campaign_conversions_invitation_id_foreign` (`invitation_id`),
  KEY `marketer_campaign_conversions_order_item_id_foreign` (`order_item_id`),
  KEY `marketer_campaign_conversions_tiered_rule_id_foreign` (`tiered_rule_id`),
  KEY `marketer_campaign_conversions_campaign_id_invitation_id_index` (`campaign_id`,`invitation_id`),
  KEY `marketer_campaign_conversions_order_id_index` (`order_id`),
  CONSTRAINT `marketer_campaign_conversions_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `marketer_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketer_campaign_conversions_invitation_id_foreign` FOREIGN KEY (`invitation_id`) REFERENCES `marketer_campaign_invitations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketer_campaign_conversions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketer_campaign_conversions_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketer_campaign_conversions_tiered_rule_id_foreign` FOREIGN KEY (`tiered_rule_id`) REFERENCES `marketer_campaign_tiered_rules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketer_campaign_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketer_campaign_invitations` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marketer_vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','accepted','rejected','timed_out','replaced','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `acceptance_window_hours` smallint unsigned NOT NULL DEFAULT '12',
  `expires_at` timestamp NULL DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `marketer_note` text COLLATE utf8mb4_unicode_ci,
  `decline_reason` text COLLATE utf8mb4_unicode_ci,
  `referral_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referral_link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_code_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_sent` tinyint(1) NOT NULL DEFAULT '0',
  `whatsapp_sent_at` timestamp NULL DEFAULT NULL,
  `replaced_invitation_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_commission_earned` bigint NOT NULL DEFAULT '0' COMMENT 'BIGINT base-currency. No /100.',
  `platform_fee_amount` bigint NOT NULL DEFAULT '0' COMMENT 'Platform fee charged for this influencer slot. 0 for affiliate. BIGINT base-currency. No /100.',
  `platform_fee_currency` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_fee_status` enum('not_applicable','pending','paid','waived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_applicable' COMMENT 'not_applicable = affiliate type (always free)',
  `platform_fee_recorded_at` timestamp NULL DEFAULT NULL,
  `total_conversions` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketer_campaign_invitations_referral_code_unique` (`referral_code`),
  KEY `marketer_campaign_invitations_marketer_vendor_id_foreign` (`marketer_vendor_id`),
  KEY `marketer_campaign_invitations_replaced_invitation_id_foreign` (`replaced_invitation_id`),
  KEY `mci_campaign_marketer_idx` (`campaign_id`,`marketer_vendor_id`),
  KEY `mci_status_expires_idx` (`status`,`expires_at`),
  CONSTRAINT `marketer_campaign_invitations_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `marketer_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketer_campaign_invitations_marketer_vendor_id_foreign` FOREIGN KEY (`marketer_vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketer_campaign_invitations_replaced_invitation_id_foreign` FOREIGN KEY (`replaced_invitation_id`) REFERENCES `marketer_campaign_invitations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketer_campaign_samples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketer_campaign_samples` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invitation_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sample_owner` enum('platform','marketer') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'platform = admin-owned non-refundable; marketer = allocated to specific marketer',
  `quantity` smallint unsigned NOT NULL,
  `status` enum('pending','dispatched','delivered','returned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `delivery_address_snapshot` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketer_campaign_samples_campaign_id_foreign` (`campaign_id`),
  KEY `marketer_campaign_samples_invitation_id_foreign` (`invitation_id`),
  CONSTRAINT `marketer_campaign_samples_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `marketer_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketer_campaign_samples_invitation_id_foreign` FOREIGN KEY (`invitation_id`) REFERENCES `marketer_campaign_invitations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketer_campaign_tiered_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketer_campaign_tiered_rules` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `campaign_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_sale_number` int unsigned NOT NULL COMMENT 'Commission tier activates when marketer reaches this sale number. e.g. 10, 35, 75, 120',
  `commission_amount` bigint NOT NULL COMMENT 'Commission per sale at this tier. BIGINT base-currency. No /100.',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mctr_campaign_sale_number_unique` (`campaign_id`,`from_sale_number`),
  CONSTRAINT `marketer_campaign_tiered_rules_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `marketer_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketer_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketer_campaigns` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commission_type` enum('fixed','tiered','last_click') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'fixed: flat per sale; tiered: per order milestone; last_click: last referral wins',
  `max_commission_budget` bigint NOT NULL DEFAULT '0' COMMENT 'Max total commission vendor will pay. BIGINT base-currency. No /100.',
  `platform_commission_amount` bigint NOT NULL DEFAULT '0' COMMENT 'Admin-determined platform cut. BIGINT base-currency. No /100.',
  `marketer_commission_amount` bigint NOT NULL DEFAULT '0' COMMENT 'Commission per marketer per sale. BIGINT base-currency. No /100.',
  `requested_marketer_vendor_ids` json DEFAULT NULL COMMENT 'Marketer vendor IDs selected by the vendor at campaign creation, before admin review',
  `reviewed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending_admin','active','auto_approved','rejected','paused','done','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_admin',
  `auto_approve_at` timestamp NULL DEFAULT NULL COMMENT 'Campaign auto-approves at this timestamp if admin has not acted',
  `auto_approved` tinyint(1) NOT NULL DEFAULT '0',
  `platform_sample_qty_snapshot` smallint unsigned NOT NULL DEFAULT '0',
  `per_marketer_sample_qty_snapshot` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Samples per marketer (influencer or affiliate qty from category)',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketer_campaigns_vendor_id_foreign` (`vendor_id`),
  KEY `marketer_campaigns_vendor_listing_id_foreign` (`vendor_listing_id`),
  KEY `marketer_campaigns_admin_listing_id_foreign` (`admin_listing_id`),
  KEY `marketer_campaigns_country_id_foreign` (`country_id`),
  KEY `marketer_campaigns_reviewed_by_admin_id_foreign` (`reviewed_by_admin_id`),
  CONSTRAINT `marketer_campaigns_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `marketer_campaigns_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `marketer_campaigns_reviewed_by_admin_id_foreign` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketer_campaigns_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketer_campaigns_vendor_listing_id_foreign` FOREIGN KEY (`vendor_listing_id`) REFERENCES `vendor_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_campaign_listing_xor` CHECK ((((`vendor_listing_id` is not null) and (`admin_listing_id` is null)) or ((`vendor_listing_id` is null) and (`admin_listing_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketer_commission_country_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketer_commission_country_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `influencer_commission_amount` bigint NOT NULL DEFAULT '0' COMMENT 'Commission influencer earns per conversion. BIGINT base-currency. No /100.',
  `affiliate_commission_amount` bigint NOT NULL DEFAULT '0' COMMENT 'Commission affiliate earns per conversion. BIGINT base-currency. No /100.',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mkt_comm_country_cat_uniq` (`category_id`,`country_id`),
  KEY `marketer_commission_country_settings_country_id_foreign` (`country_id`),
  KEY `marketer_commission_country_settings_updated_by_admin_id_foreign` (`updated_by_admin_id`),
  CONSTRAINT `marketer_commission_country_settings_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketer_commission_country_settings_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketer_commission_country_settings_updated_by_admin_id_foreign` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketer_influencer_fee_country_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketer_influencer_fee_country_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fee_per_influencer` bigint NOT NULL DEFAULT '0' COMMENT 'Platform fee per influencer selected. BIGINT base-currency. No /100.',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketer_influencer_fee_country_settings_country_id_unique` (`country_id`),
  KEY `mkt_inf_fee_upd_admin_fk` (`updated_by_admin_id`),
  CONSTRAINT `marketer_influencer_fee_country_settings_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mkt_inf_fee_upd_admin_fk` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketer_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketer_profiles` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `banner_file_id` bigint unsigned DEFAULT NULL,
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'YouTube/Instagram reel URL',
  `bio_ar` text COLLATE utf8mb4_unicode_ci,
  `bio_en` text COLLATE utf8mb4_unicode_ci,
  `social_links` json DEFAULT NULL,
  `contact_details` json DEFAULT NULL,
  `qr_code_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_slug` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_campaigns` int unsigned NOT NULL DEFAULT '0',
  `total_conversions` int unsigned NOT NULL DEFAULT '0',
  `total_earnings` bigint NOT NULL DEFAULT '0' COMMENT 'BIGINT base-currency. No /100.',
  `earnings_currency` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketer_profiles_vendor_id_unique` (`vendor_id`),
  UNIQUE KEY `marketer_profiles_profile_slug_unique` (`profile_slug`),
  KEY `marketer_profiles_banner_file_id_foreign` (`banner_file_id`),
  CONSTRAINT `marketer_profiles_banner_file_id_foreign` FOREIGN KEY (`banner_file_id`) REFERENCES `files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketer_profiles_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_shipping_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_shipping_rules` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_special_vehicle` tinyint NOT NULL DEFAULT '0',
  `requires_refrigeration` tinyint NOT NULL DEFAULT '0',
  `max_weight_kg` decimal(8,2) DEFAULT NULL,
  `max_dimensions_cm` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'LxWxH e.g. 200x100x80',
  `special_handling_notes` text COLLATE utf8mb4_unicode_ci,
  `commission_type` enum('fixed','percentage','mixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `commission_value` decimal(10,2) NOT NULL,
  `extra_delivery_fee` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_msr_vendor_listing` (`vendor_listing_id`),
  UNIQUE KEY `uq_msr_admin_listing` (`admin_listing_id`),
  CONSTRAINT `marketplace_shipping_rules_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `marketplace_shipping_rules_vendor_listing_id_foreign` FOREIGN KEY (`vendor_listing_id`) REFERENCES `vendor_listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_msr_listing_owner` CHECK ((((`vendor_listing_id` is not null) and (`admin_listing_id` is null)) or ((`vendor_listing_id` is null) and (`admin_listing_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`role_id`,`model_uuid`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_uuid`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_subscribers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'website',
  `locale` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ar',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `unsubscribe_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `newsletter_subscribers_email_country_id_unique` (`email`,`country_id`),
  UNIQUE KEY `newsletter_subscribers_unsubscribe_token_unique` (`unsubscribe_token`),
  KEY `newsletter_subscribers_email_index` (`email`),
  KEY `newsletter_subscribers_country_id_index` (`country_id`),
  KEY `newsletter_subscribers_customer_id_index` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json NOT NULL,
  `channel` enum('database','email','sms','push','whatsapp') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_read_at_index` (`notifiable_type`,`notifiable_id`,`read_at`),
  KEY `notifications_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_snapshot` json NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` bigint NOT NULL,
  `unit_cost_price` bigint DEFAULT NULL,
  `line_subtotal` bigint NOT NULL,
  `line_discount` bigint NOT NULL DEFAULT '0',
  `line_tax` bigint NOT NULL,
  `line_total` bigint NOT NULL,
  `commission_rate_pct` decimal(5,2) NOT NULL,
  `commission_fixed` bigint NOT NULL DEFAULT '0' COMMENT 'Fixed fee component snapshotted at order time, per unit, in the order''s currency cents',
  `commission_category_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'The category from which commission rates were resolved (may be an ancestor if inherited)',
  `commission_amount` bigint NOT NULL,
  `fulfillment_status` enum('pending','picked','packed','shipped','delivered','returned','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_method_snapshot` json DEFAULT NULL COMMENT 'Snapshot of method name, badge, delivery_label at order time',
  `return_eligible_until` date DEFAULT NULL,
  `warranty_purchase_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_index` (`order_id`),
  KEY `order_items_sub_order_id_index` (`sub_order_id`),
  KEY `order_items_product_variant_id_index` (`product_variant_id`),
  KEY `order_items_vendor_id_index` (`vendor_id`),
  KEY `order_items_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `order_items_admin_product_listing_id_index` (`admin_listing_id`),
  KEY `order_items_warranty_purchase_id_foreign` (`warranty_purchase_id`),
  KEY `order_items_shipping_method_id_foreign` (`shipping_method_id`),
  CONSTRAINT `order_items_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_shipping_method_id_foreign` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_warranty_purchase_id_foreign` FOREIGN KEY (`warranty_purchase_id`) REFERENCES `warranty_purchases` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_status_histories` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('placed','confirmed','partially_shipped','shipped','partially_delivered','delivered','completed','cancelled','refunded','disputed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` bigint NOT NULL,
  `discount` bigint NOT NULL DEFAULT '0',
  `loyalty_discount` bigint NOT NULL DEFAULT '0',
  `loyalty_points_used` decimal(10,2) NOT NULL DEFAULT '0.00',
  `loyalty_points_earned` decimal(10,2) NOT NULL DEFAULT '0.00',
  `shipping` bigint NOT NULL,
  `tax` bigint NOT NULL,
  `cod_fee` bigint NOT NULL DEFAULT '0',
  `warranty_total` bigint NOT NULL DEFAULT '0',
  `total` bigint NOT NULL,
  `wallet_amount_used` bigint unsigned NOT NULL DEFAULT '0',
  `coupon_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coupon_code_used` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` enum('card','wallet','cod','bnpl','bank_transfer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_status` enum('pending','authorized','captured','failed','refunded','partially_refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_address_snapshot` json NOT NULL,
  `billing_address_snapshot` json DEFAULT NULL,
  `customer_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `delivery_instruction` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `device_fingerprint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risk_score` decimal(5,2) DEFAULT NULL,
  `placed_at` timestamp NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  KEY `orders_customer_id_index` (`customer_id`),
  KEY `orders_country_id_index` (`country_id`),
  CONSTRAINT `orders_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `packaging_supplies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packaging_supplies` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `type` enum('box','bag','tape','label','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_cost` bigint unsigned NOT NULL DEFAULT '0',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SAR',
  `stock_available` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint NOT NULL DEFAULT '0',
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `packaging_supplies_country_id_foreign` (`country_id`),
  CONSTRAINT `packaging_supplies_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `packaging_supply_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packaging_supply_countries` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `packaging_supply_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_cost` bigint unsigned NOT NULL DEFAULT '0',
  `stock_available` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `packaging_supply_countries_packaging_supply_id_country_id_unique` (`packaging_supply_id`,`country_id`),
  KEY `packaging_supply_countries_country_id_foreign` (`country_id`),
  CONSTRAINT `packaging_supply_countries_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `packaging_supply_countries_packaging_supply_id_foreign` FOREIGN KEY (`packaging_supply_id`) REFERENCES `packaging_supplies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `packaging_supply_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packaging_supply_request_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `packaging_supply_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL,
  `unit_cost` bigint unsigned NOT NULL,
  `line_total` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `packaging_supply_request_items_request_id_foreign` (`request_id`),
  KEY `packaging_supply_request_items_packaging_supply_id_foreign` (`packaging_supply_id`),
  CONSTRAINT `packaging_supply_request_items_packaging_supply_id_foreign` FOREIGN KEY (`packaging_supply_id`) REFERENCES `packaging_supplies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `packaging_supply_request_items_request_id_foreign` FOREIGN KEY (`request_id`) REFERENCES `packaging_supply_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `packaging_supply_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packaging_supply_requests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','shipped','delivered','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `total_cost` bigint unsigned NOT NULL DEFAULT '0',
  `delivery_fee` bigint unsigned NOT NULL DEFAULT '0',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SAR',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `packaging_supply_requests_request_number_unique` (`request_number`),
  KEY `packaging_supply_requests_vendor_id_foreign` (`vendor_id`),
  KEY `packaging_supply_requests_warehouse_id_foreign` (`warehouse_id`),
  KEY `packaging_supply_requests_approved_by_admin_id_foreign` (`approved_by_admin_id`),
  CONSTRAINT `packaging_supply_requests_approved_by_admin_id_foreign` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `packaging_supply_requests_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `packaging_supply_requests_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_block_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_block_brands` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `page_block_brands_page_block_id_foreign` (`page_block_id`),
  KEY `page_block_brands_brand_id_foreign` (`brand_id`),
  CONSTRAINT `page_block_brands_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_block_brands_page_block_id_foreign` FOREIGN KEY (`page_block_id`) REFERENCES `page_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_block_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_block_categories` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tab_index` int unsigned NOT NULL DEFAULT '0',
  `product_variant_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `added_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_block_products_block_tab_variant_unique` (`page_block_id`,`tab_index`,`product_variant_id`),
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `revision_number` int NOT NULL,
  `config_snapshot` json NOT NULL,
  `is_visible_snapshot` tinyint(1) NOT NULL,
  `position_snapshot` int NOT NULL,
  `changed_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seller_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `column_index` tinyint unsigned NOT NULL DEFAULT '0',
  `block_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `app_context_key` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Restricts block to an app context (e.g. nawy_now). NULL = shown in every context.',
  `position` int NOT NULL DEFAULT '0',
  `config` json DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `visible_from` timestamp NULL DEFAULT NULL,
  `visible_until` timestamp NULL DEFAULT NULL,
  `device_target` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `audience` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `country_override` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ab_test_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ab_variant` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cache_ttl_seconds` int NOT NULL DEFAULT '60',
  `created_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `page_blocks_app_context_key_index` (`app_context_key`),
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` int NOT NULL,
  `blocks_snapshot` json NOT NULL,
  `sections_snapshot` json DEFAULT NULL,
  `published_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `publish_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `background_color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_image_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'section' COMMENT 'section = covers whole section, header = covers title area only',
  `padding_top` int NOT NULL DEFAULT '0',
  `padding_bottom` int NOT NULL DEFAULT '0',
  `max_width` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `layout` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'stack',
  `columns_config` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `app_context_key` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Links page to an app context (main, nawy_now, classifieds, travel). NULL = general/admin use.',
  `reference_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `publish_at` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `unpublish_at` timestamp NULL DEFAULT NULL,
  `published_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_edited_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `seo_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `og_image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `pages_app_context_key_index` (`app_context_key`),
  CONSTRAINT `pages_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paid_ad_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paid_ad_bookings` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `booking_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid_ad_slot_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing_model` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `booked_from` date NOT NULL,
  `booked_until` date NOT NULL,
  `agreed_rate` bigint NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payment_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_transaction_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoiced_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `impressions_delivered` int NOT NULL DEFAULT '0',
  `clicks_delivered` int NOT NULL DEFAULT '0',
  `cpm_impressions_billed` int NOT NULL DEFAULT '0',
  `total_charged` bigint NOT NULL DEFAULT '0',
  `approved_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid_ad_booking_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_en` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_ar` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_reference_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rejection_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '0',
  `reviewed_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `placement_definition_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slot_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing_model` enum('fixed_weekly','fixed_monthly','cpm','cpc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_rate` bigint NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_booking_days` int NOT NULL,
  `max_booking_days` int NOT NULL,
  `is_available` tinyint(1) NOT NULL,
  `requires_approval` tinyint(1) NOT NULL,
  `min_seller_tier` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes_for_vendors` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seller_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `booking_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing_model` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` bigint NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_charged` bigint NOT NULL DEFAULT '0',
  `booked_from` date NOT NULL,
  `booked_until` date NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `impressions_delivered` int NOT NULL DEFAULT '0',
  `clicks_delivered` int NOT NULL DEFAULT '0',
  `booked_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
DROP TABLE IF EXISTS `payment_gateway_webhook_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_gateway_webhook_logs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_payment_gateway_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json NOT NULL,
  `headers` json DEFAULT NULL,
  `signature_valid` tinyint DEFAULT NULL,
  `processed` tinyint NOT NULL DEFAULT '0',
  `processing_error` text COLLATE utf8mb4_unicode_ci,
  `payment_transaction_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payment_gateway_webhook_logs_payment_transaction_id_index` (`payment_transaction_id`),
  KEY `payment_gateway_webhook_logs_country_payment_gateway_id_index` (`country_payment_gateway_id`),
  CONSTRAINT `payment_gateway_webhook_logs_country_payment_gateway_id_foreign` FOREIGN KEY (`country_payment_gateway_id`) REFERENCES `country_payment_gateways` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_gateway_webhook_logs_payment_transaction_id_foreign` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_gateways` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('redirect','direct','offline','internal') COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `required_fields` json DEFAULT NULL,
  `supports_webhook` tinyint(1) NOT NULL DEFAULT '0',
  `supports_refund` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_gateways_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_transactions` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('authorization','capture','sale','refund','void','chargeback') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_transaction_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `idempotency_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_fee` bigint NOT NULL DEFAULT '0',
  `status` enum('pending','succeeded','failed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failure_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payment_method_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payout_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `marketer_conversion_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_type` enum('sub_order','marketer_commission') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sub_order',
  `gross` bigint NOT NULL,
  `commission` bigint NOT NULL,
  `net` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payout_items_payout_id_index` (`payout_id`),
  KEY `payout_items_sub_order_id_index` (`sub_order_id`),
  KEY `payout_items_marketer_conversion_id_foreign` (`marketer_conversion_id`),
  CONSTRAINT `payout_items_marketer_conversion_id_foreign` FOREIGN KEY (`marketer_conversion_id`) REFERENCES `marketer_campaign_conversions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payouts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payout_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `gross_sales` bigint NOT NULL,
  `commission` bigint NOT NULL,
  `gateway_fee_deducted` bigint NOT NULL DEFAULT '0' COMMENT 'Sum of sub_orders.gateway_fee for this vendor/period, already netted out of vendor_payout. Shown as a distinct breakdown line.',
  `refunds_deducted` bigint NOT NULL,
  `chargebacks_deducted` bigint NOT NULL,
  `storage_fees` bigint NOT NULL DEFAULT '0',
  `ad_fees` bigint NOT NULL DEFAULT '0',
  `other_adjustments` bigint NOT NULL DEFAULT '0',
  `net_amount` bigint NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','processing','completed','failed','on_hold') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payout_method` enum('bank_transfer','wallet','paypal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `failed_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `receipt_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `platform_shipping_subsidies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_shipping_subsidies` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_zone_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subsidy_cap` bigint NOT NULL DEFAULT '0' COMMENT 'Max amount platform covers per delivery, base currency units',
  `split_type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage' COMMENT 'percentage = vendor_share_pct + admin covers rest; fixed = fixed amounts',
  `vendor_share_pct` smallint unsigned NOT NULL DEFAULT '50' COMMENT 'Percentage of gap vendor absorbs (0-100). Only used when split_type=percentage',
  `vendor_fixed_amount` bigint NOT NULL DEFAULT '0' COMMENT 'Fixed amount vendor pays per delivery. Only used when split_type=fixed. BIGINT base currency.',
  `admin_fixed_amount` bigint NOT NULL DEFAULT '0' COMMENT 'Fixed amount admin absorbs per delivery. Only used when split_type=fixed. BIGINT base currency.',
  `max_subsidy_weight_grams` int unsigned DEFAULT NULL COMMENT 'Max billable weight in grams platform subsidy applies to; NULL = no weight cap',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_warehouse_zone_method_carrier_subsidy` (`warehouse_id`,`shipping_zone_id`,`shipping_method_id`,`carrier_id`),
  KEY `platform_shipping_subsidies_shipping_method_id_foreign` (`shipping_method_id`),
  KEY `platform_shipping_subsidies_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `platform_shipping_subsidies_shipping_zone_id_foreign` (`shipping_zone_id`),
  KEY `platform_shipping_subsidies_carrier_id_foreign` (`carrier_id`),
  CONSTRAINT `platform_shipping_subsidies_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `shipping_carriers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `platform_shipping_subsidies_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `platform_shipping_subsidies_shipping_method_id_foreign` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`),
  CONSTRAINT `platform_shipping_subsidies_shipping_zone_id_foreign` FOREIGN KEY (`shipping_zone_id`) REFERENCES `shipping_zones` (`id`),
  CONSTRAINT `platform_shipping_subsidies_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_contents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_contents` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `block_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('text','richtext','link','image') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `value_en` text COLLATE utf8mb4_unicode_ci,
  `value_ar` text COLLATE utf8mb4_unicode_ci,
  `value_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `portal_contents_page_key_block_key_field_key_unique` (`page_key`,`block_key`,`field_key`),
  KEY `portal_contents_updated_by_admin_id_foreign` (`updated_by_admin_id`),
  KEY `portal_contents_page_key_index` (`page_key`),
  CONSTRAINT `portal_contents_updated_by_admin_id_foreign` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_bestseller_rankings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_bestseller_rankings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rank` int unsigned NOT NULL,
  `units_sold` int unsigned NOT NULL DEFAULT '0',
  `period` enum('daily','weekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ranking` (`product_id`,`category_id`,`country_id`,`period`),
  KEY `product_bestseller_rankings_country_id_foreign` (`country_id`),
  KEY `idx_category_country_rank` (`category_id`,`country_id`,`rank`),
  CONSTRAINT `product_bestseller_rankings_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_bestseller_rankings_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_bestseller_rankings_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_cost_references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_cost_references` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manufacturer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manufacturer_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manufacturer_sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manufacturer_cost` bigint unsigned DEFAULT NULL COMMENT 'Factory price in smallest currency unit',
  `shipping_cost` bigint unsigned DEFAULT NULL COMMENT 'Freight / logistics cost',
  `landed_cost` bigint unsigned DEFAULT NULL COMMENT 'manufacturer_cost + shipping + duties (computed or manual override)',
  `platform_margin_pct` decimal(5,2) DEFAULT NULL COMMENT 'Calculated: (selling_price - landed_cost) / selling_price * 100',
  `competitor_links` json DEFAULT NULL COMMENT 'Array of {name, url, price_cents, last_checked}',
  `competitor_last_checked` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_confidential` tinyint NOT NULL DEFAULT '1' COMMENT 'Always 1 — hidden from vendors/customers. Kept for future granularity.',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_cost_references_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `product_cost_references_updated_by_admin_id_foreign` (`updated_by_admin_id`),
  KEY `product_cost_references_product_id_index` (`product_id`),
  KEY `product_cost_references_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `product_cost_references_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `product_cost_references_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_cost_references_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `product_cost_references_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_cost_references_updated_by_admin_id_foreign` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_cost_references_vendor_listing_id_foreign` FOREIGN KEY (`vendor_listing_id`) REFERENCES `vendor_listings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_countries` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `unavailable_reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_override_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_override_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_override_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_override_ar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requires_local_cert` tinyint(1) NOT NULL DEFAULT '0',
  `is_age_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `min_age` tinyint DEFAULT NULL,
  `seo_title_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `seo_description_ar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `unavailable_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_override_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_override_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_local_cert` tinyint(1) NOT NULL DEFAULT '0',
  `seo_title` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_country_settings_product_id_country_id_unique` (`product_id`,`country_id`),
  KEY `product_country_settings_country_id_foreign` (`country_id`),
  CONSTRAINT `product_country_settings_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_country_settings_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_highlights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_highlights` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_en` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_ar` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_highlights_product_id_foreign` (`product_id`),
  CONSTRAINT `product_highlights_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_image_hashes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_image_hashes` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_image_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `md5_hash` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `perceptual_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_variant_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `mime_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `alt_text_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `product_specifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_specifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_en` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_ar` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_specifications_product_id_foreign` (`product_id`),
  CONSTRAINT `product_specifications_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_variant_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variant_attributes` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_value_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_text_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_text_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  UNIQUE KEY `product_variants_product_id_slug_unique` (`product_id`,`slug`),
  KEY `product_variants_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_views` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` enum('search','category','recommendation','direct','ad','social') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `referrer_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gtin` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_ar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `short_desc_en` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_desc_ar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','active','discontinued','restricted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `requires_brand_auth` tinyint(1) NOT NULL DEFAULT '0',
  `is_age_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `min_age` tinyint DEFAULT NULL,
  `is_hazardous` tinyint(1) NOT NULL DEFAULT '0',
  `has_variants` tinyint(1) NOT NULL DEFAULT '0',
  `seller_count` int NOT NULL DEFAULT '0',
  `total_sold` int NOT NULL DEFAULT '0',
  `view_count` bigint NOT NULL DEFAULT '0',
  `ai_quality_score` tinyint unsigned DEFAULT NULL,
  `seo_title_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `seo_description_ar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `radio_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `radio_channels` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('audio','video') COLLATE utf8mb4_unicode_ci NOT NULL,
  `stream_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fallback_media_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumbnail_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `radio_channels_country_id_foreign` (`country_id`),
  CONSTRAINT `radio_channels_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `radio_listen_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `radio_listen_sessions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `radio_channel_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_at` timestamp NOT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `duration_seconds` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `radio_listen_sessions_radio_channel_id_foreign` (`radio_channel_id`),
  KEY `radio_listen_sessions_customer_id_foreign` (`customer_id`),
  CONSTRAINT `radio_listen_sessions_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `radio_listen_sessions_radio_channel_id_foreign` FOREIGN KEY (`radio_channel_id`) REFERENCES `radio_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `radio_schedule_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `radio_schedule_slots` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `radio_channel_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `recurrence` enum('once','daily','weekly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'once',
  `recurrence_days` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `radio_schedule_slots_radio_channel_id_foreign` (`radio_channel_id`),
  KEY `radio_schedule_slots_created_by_admin_id_foreign` (`created_by_admin_id`),
  CONSTRAINT `radio_schedule_slots_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `radio_schedule_slots_radio_channel_id_foreign` FOREIGN KEY (`radio_channel_id`) REFERENCES `radio_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refunds` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_transaction_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refund_transaction_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` enum('customer_request','out_of_stock','damaged','wrong_item','not_as_described','late_delivery','duplicate_order','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `refund_type` enum('full','partial','shipping_only') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `initiated_by_customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `approved_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_charged_back` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','approved','processing','completed','failed','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `gateway_fee_deducted` bigint unsigned NOT NULL DEFAULT '0',
  `tax_deducted` bigint unsigned NOT NULL DEFAULT '0',
  `net_refund` bigint unsigned GENERATED ALWAYS AS (((`amount` - `gateway_fee_deducted`) - `tax_deducted`)) VIRTUAL COMMENT 'Actual amount returned to customer after deductions',
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `return_request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_item_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `condition_received` enum('new','opened','used','damaged') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `restock_decision` enum('restock','dispose','return_to_seller','liquidate') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `return_request_items_return_request_id_index` (`return_request_id`),
  KEY `return_request_items_order_item_id_index` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `return_request_message_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_request_message_attachments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `return_request_message_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `rrma_message_id_idx` (`return_request_message_id`),
  CONSTRAINT `rrma_message_id_foreign` FOREIGN KEY (`return_request_message_id`) REFERENCES `return_request_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `return_request_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_request_messages` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `return_request_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_role` enum('customer','seller','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_internal_note` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `rrm_request_note_created_idx` (`return_request_id`,`is_internal_note`,`created_at`),
  KEY `return_request_messages_sender_user_id_index` (`sender_user_id`),
  CONSTRAINT `return_request_messages_return_request_id_foreign` FOREIGN KEY (`return_request_id`) REFERENCES `return_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `return_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_requests` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `return_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` enum('changed_mind','wrong_item','defective','damaged','not_as_described','size_issue','quality_issue','arrived_late','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `return_type` enum('refund','exchange','store_credit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('requested','approved','rejected','awaiting_pickup','in_transit','received','inspecting','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_address_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_scheduled_at` timestamp NULL DEFAULT NULL,
  `received_at_warehouse_at` timestamp NULL DEFAULT NULL,
  `inspection_result` enum('good','damaged','missing','counterfeit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspection_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `liability` enum('customer','seller','platform','carrier') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refund_amount` bigint DEFAULT NULL,
  `refund_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reviewed_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `review_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('published','hidden') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `review_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vote` enum('helpful','not_helpful') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_product_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_item_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ai_flag_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moderated_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `reviews_moderated_by_admin_id_index` (`moderated_by_admin_id`),
  KEY `reviews_admin_product_listing_id_index` (`admin_product_listing_id`),
  KEY `reviews_admin_listing_id_foreign` (`admin_listing_id`),
  CONSTRAINT `reviews_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE SET NULL
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
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_agency_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`),
  KEY `roles_travel_agency_id_foreign` (`travel_agency_id`),
  KEY `roles_guard_name_travel_agency_id_index` (`guard_name`,`travel_agency_id`),
  CONSTRAINT `roles_travel_agency_id_foreign` FOREIGN KEY (`travel_agency_id`) REFERENCES `travel_agencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `search_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_logs` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `query` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `query_normalized` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `filters_json` json NOT NULL,
  `results_count` int NOT NULL,
  `clicked_product_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clicked_position` int DEFAULT NULL,
  `converted_order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `search_logs_query_normalized_index` (`query_normalized`),
  KEY `search_logs_created_at_index` (`created_at`),
  KEY `search_logs_country_id_created_at_index` (`country_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `search_suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_suggestions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keyword` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keyword_normalized` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_count` bigint unsigned NOT NULL DEFAULT '1',
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `is_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_keyword_country` (`keyword_normalized`,`country_id`),
  KEY `idx_keyword_prefix` (`country_id`,`keyword_normalized`),
  KEY `idx_trending` (`country_id`,`search_count`,`is_blocked`),
  CONSTRAINT `search_suggestions_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipment_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tracking_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `awb_label_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight_grams` int NOT NULL,
  `dimensions` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost_actual` bigint NOT NULL,
  `status` enum('label_created','picked_up','in_transit','out_for_delivery','delivered','failed','returned') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `delivery_otp` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_company_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credentials_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tracking_url_pattern` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supports_cod` tinyint(1) NOT NULL DEFAULT '0',
  `supports_returns` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipping_carriers_code_unique` (`code`),
  KEY `shipping_carriers_shipping_company_id_index` (`shipping_company_id`),
  KEY `shipping_carriers_country_id_index` (`country_id`),
  CONSTRAINT `shipping_carriers_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipping_carriers_shipping_company_id_foreign` FOREIGN KEY (`shipping_company_id`) REFERENCES `shipping_companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_companies` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `served_countries` json DEFAULT NULL,
  `served_cities` json DEFAULT NULL,
  `status` enum('pending','active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `can_supervisors_receive_all_notifications` tinyint NOT NULL DEFAULT '1',
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipping_companies_country_id_foreign` (`country_id`),
  CONSTRAINT `shipping_companies_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_company_supervisors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_company_supervisors` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_company_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions` json DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `receives_all_notifications` tinyint NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipping_company_supervisors_email_unique` (`email`),
  KEY `shipping_company_supervisors_shipping_company_id_index` (`shipping_company_id`),
  KEY `shipping_company_supervisors_country_id_index` (`country_id`),
  CONSTRAINT `shipping_company_supervisors_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipping_company_supervisors_shipping_company_id_foreign` FOREIGN KEY (`shipping_company_id`) REFERENCES `shipping_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_fallback_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_fallback_rules` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unserved_city_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fallback_shipping_company_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fallback_lookup_idx` (`unserved_city_id`,`priority`),
  KEY `shipping_fallback_rules_unserved_city_id_index` (`unserved_city_id`),
  KEY `shipping_fallback_rules_fallback_shipping_company_id_index` (`fallback_shipping_company_id`),
  CONSTRAINT `shipping_fallback_rules_fallback_shipping_company_id_foreign` FOREIGN KEY (`fallback_shipping_company_id`) REFERENCES `shipping_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipping_fallback_rules_unserved_city_id_foreign` FOREIGN KEY (`unserved_city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_methods` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `badge_label_en` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Short label shown on listing card badge, e.g. "express", "supermall"',
  `badge_label_ar` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_color_hex` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#1a1a2e' COMMENT 'Background color for the badge pill on listing cards',
  `badge_text_color_hex` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#FFFFFF',
  `badge_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_label_en` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Full label shown in the Delivery Information panel on product detail',
  `delivery_label_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_express_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True for same_day and express — drives speed-highlight badge treatment on listing cards',
  `show_estimated_price` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether to show the calculated shipping price in the delivery info panel',
  `display_priority` int unsigned NOT NULL DEFAULT '0' COMMENT 'Order methods appear in the Delivery Information panel — lower = shown first',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `min_delivery_days` int DEFAULT NULL,
  `max_delivery_days` int DEFAULT NULL,
  `order_cutoff_time` time DEFAULT NULL COMMENT 'Daily cutoff for next-slot-delivery eligibility. NULL = no cutoff applies for this method.',
  `handling_time_hours` tinyint unsigned NOT NULL DEFAULT '24' COMMENT 'Vendor handling time added to delivery estimate before shipping transit begins',
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `origin_zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_zone_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_fee` bigint NOT NULL DEFAULT '0',
  `carrier_rate` bigint NOT NULL DEFAULT '0' COMMENT 'What the carrier charges platform per delivery in this zone, base currency units. 0 = same as base_fee (non-exceptional zone)',
  `carrier_rate_per_kg` bigint NOT NULL DEFAULT '0' COMMENT 'Additional carrier cost per kg over min_weight_grams, base currency units',
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
DROP TABLE IF EXISTS `shipping_weight_slabs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_weight_slabs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_weight_grams` int unsigned NOT NULL,
  `max_weight_grams` int unsigned DEFAULT NULL,
  `extra_fee` bigint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `weight_slabs_composite_idx` (`shipping_method_id`,`country_id`,`min_weight_grams`,`max_weight_grams`),
  KEY `shipping_weight_slabs_shipping_method_id_index` (`shipping_method_id`),
  KEY `shipping_weight_slabs_country_id_index` (`country_id`),
  CONSTRAINT `shipping_weight_slabs_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipping_weight_slabs_shipping_method_id_foreign` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shipping_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_zones` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_block_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `desktop_file_id` bigint unsigned DEFAULT NULL,
  `mobile_file_id` bigint unsigned DEFAULT NULL,
  `title_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_ar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_en` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_ar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_en` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label_ar` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_open_new_tab` tinyint(1) NOT NULL DEFAULT '0',
  `text_color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#ffffff',
  `text_position` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `overlay_opacity` decimal(3,2) NOT NULL DEFAULT '0.30',
  `link_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_reference_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_number` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('placed','confirmed','processing','packed','shipped','out_for_delivery','delivered','completed','cancelled','returned','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fulfillment_model` enum('fbm','fbn') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` bigint NOT NULL,
  `shipping` bigint NOT NULL,
  `carrier_shipping_cost` bigint NOT NULL DEFAULT '0' COMMENT 'Actual carrier cost for this delivery. = customer shipping in normal zones; > customer shipping in exceptional zones. BIGINT base currency.',
  `shipping_gap` bigint NOT NULL DEFAULT '0' COMMENT 'carrier_shipping_cost - shipping (customer paid). Gap to be split. 0 in normal zones. BIGINT base currency.',
  `exceptional_zone_subsidy_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_subsidy_amount` bigint NOT NULL DEFAULT '0' COMMENT 'Platform-covered share of this sub-order delivery fee, base currency units',
  `vendor_contribution_amount` bigint NOT NULL DEFAULT '0' COMMENT 'Vendor-covered share of this sub-order delivery fee (vendor_covers_delivery gap), base currency units',
  `billable_weight_grams` int unsigned DEFAULT NULL COMMENT 'Snapshot of MAX(actual, volumetric) weight used at order time for shipping subsidy calculation',
  `subsidy_ledgered` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Set true once admin_subsidy_amount/vendor_contribution_amount have been posted to ledger_entries, to prevent double-posting',
  `tax` bigint NOT NULL,
  `platform_commission` bigint NOT NULL,
  `gateway_fee` bigint NOT NULL DEFAULT '0' COMMENT 'Vendor-borne share of payment gateway fee, deducted from vendor_payout. In cents.',
  `gateway_fee_rate` decimal(8,6) NOT NULL DEFAULT '0.000000' COMMENT 'Effective gateway fee rate applied to this sub-order, stored for audit/recalculation transparency.',
  `vendor_payout` bigint NOT NULL,
  `cod_remittance_confirmed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'For COD orders: true only after the delivery agent''s COD settlement covering this order has been marked settled. Vendor payout is blocked until this is true.',
  `cod_remittance_confirmed_at` timestamp NULL DEFAULT NULL,
  `cod_settlement_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'The settlement that confirmed cash receipt for this sub_order.',
  `shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_delivery_date` date DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  KEY `sub_orders_carrier_id_index` (`carrier_id`),
  KEY `sub_orders_cod_settlement_id_foreign` (`cod_settlement_id`),
  KEY `sub_orders_exceptional_zone_subsidy_id_foreign` (`exceptional_zone_subsidy_id`),
  CONSTRAINT `sub_orders_cod_settlement_id_foreign` FOREIGN KEY (`cod_settlement_id`) REFERENCES `delivery_agent_cod_settlements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sub_orders_exceptional_zone_subsidy_id_foreign` FOREIGN KEY (`exceptional_zone_subsidy_id`) REFERENCES `platform_shipping_subsidies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_plans` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `price` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_cycle` enum('monthly','quarterly','annual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `max_listings` int DEFAULT NULL COMMENT 'NULL = unlimited',
  `free_shipping_included` tinyint NOT NULL DEFAULT '0',
  `commission_discount_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Reduces standard commission by this %',
  `features` json DEFAULT NULL COMMENT 'Array of feature strings',
  `is_active` tinyint NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_role` enum('customer','seller','delivery_agent','shipping_supervisor','travel_agency') COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('order_issue','payment_issue','account','technical','product_inquiry','policy','payout','catalog','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','normal','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','in_progress','waiting_customer','resolved','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_to_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_product_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_response_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `satisfaction_rating` tinyint DEFAULT NULL,
  `satisfaction_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `related_assignment_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_ticket_number_unique` (`ticket_number`),
  KEY `support_tickets_requester_user_id_index` (`requester_user_id`),
  KEY `support_tickets_assigned_to_admin_id_index` (`assigned_to_admin_id`),
  KEY `support_tickets_related_order_id_index` (`related_order_id`),
  KEY `support_tickets_related_product_id_index` (`related_product_id`),
  KEY `support_tickets_related_assignment_id_index` (`related_assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_invoices` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` bigint NOT NULL,
  `tax` bigint NOT NULL,
  `total` bigint NOT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pdf_media_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_to_authority` tinyint(1) NOT NULL DEFAULT '0',
  `authority_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_type` enum('vat','gst','sales_tax') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_pct` decimal(5,2) NOT NULL,
  `applies_to` enum('product','shipping','both') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_message_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_attachments_ticket_message_id_index` (`ticket_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_messages` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_internal_note` tinyint(1) NOT NULL DEFAULT '0',
  `is_ai_generated` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_messages_ticket_id_index` (`ticket_id`),
  KEY `ticket_messages_sender_type_sender_id_index` (`sender_type`,`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_agencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_agencies` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `travel_agencies_email_unique` (`email`),
  KEY `travel_agencies_country_id_foreign` (`country_id`),
  KEY `travel_agencies_approved_by_admin_id_foreign` (`approved_by_admin_id`),
  CONSTRAINT `travel_agencies_approved_by_admin_id_foreign` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `travel_agencies_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_agency_bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_agency_bank_accounts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_agency_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  KEY `travel_agency_bank_accounts_travel_agency_id_index` (`travel_agency_id`),
  KEY `travel_agency_bank_accounts_verified_by_admin_id_index` (`verified_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_agency_campaign_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_agency_campaign_invitations` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_agency_campaign_offer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marketer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','accepted','declined','expired','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `marketer_note` text COLLATE utf8mb4_unicode_ci COMMENT 'Optional note from marketer when accepting or declining',
  `vendor_note` text COLLATE utf8mb4_unicode_ci COMMENT 'Private note from the travel agency visible only to the agency and admin — column name mirrors vendor_campaign_invitations for schema parity',
  `responded_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Copied from offer.invitation_deadline at invite time — individual override possible',
  `resulting_campaign_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'The marketer_campaigns row auto-created when this invitation is accepted',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ta_c_invitations_unique` (`travel_agency_campaign_offer_id`,`marketer_id`),
  KEY `m_ta_c_invitations_status_index` (`marketer_id`,`status`),
  KEY `ta_c_invitations_status_index` (`travel_agency_campaign_offer_id`,`status`),
  CONSTRAINT `ta_c_invitations_offer_id_foreign` FOREIGN KEY (`travel_agency_campaign_offer_id`) REFERENCES `travel_agency_campaign_offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_agency_campaign_offer_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_agency_campaign_offer_packages` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_agency_campaign_offer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_package_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `commission_override` decimal(5,2) DEFAULT NULL COMMENT 'Per-package commission override — mirrors vendor_campaign_offer_products.commission_override',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ta_c_o_packages_unique` (`travel_agency_campaign_offer_id`,`travel_package_id`),
  KEY `travel_agency_campaign_offer_packages_travel_package_id_foreign` (`travel_package_id`),
  CONSTRAINT `ta_c_o_packages_offer_id_foreign` FOREIGN KEY (`travel_agency_campaign_offer_id`) REFERENCES `travel_agency_campaign_offers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `travel_agency_campaign_offer_packages_travel_package_id_foreign` FOREIGN KEY (`travel_package_id`) REFERENCES `travel_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_agency_campaign_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_agency_campaign_offers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_agency_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Brief for the marketer — what the campaign is about, what content they should create, any brand guidelines',
  `requirements` text COLLATE utf8mb4_unicode_ci COMMENT 'What the agency expects: post format, minimum posts, hashtags, disclosure requirements — shown to invited marketers',
  `campaign_type` enum('product_promotion','store_promotion','brand_deal','product_specific','flash_sale') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'product_promotion' COMMENT 'Travel-agency-originated offers reuse the shared CampaignType value set — no classified_promotion here',
  `offered_commission_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'The commission % the agency is willing to pay to marketers who accept this offer, earned per confirmed booking',
  `commission_type` enum('percentage','flat_per_order','flat_per_click') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage' COMMENT 'flat_per_order is interpreted as flat-per-confirmed-booking for travel campaigns',
  `budget_per_marketer_cents` bigint DEFAULT NULL COMMENT 'Optional per-marketer budget cap — null = unlimited per marketer',
  `total_budget_cents` bigint DEFAULT NULL COMMENT 'Total budget across ALL marketers who accept this offer',
  `total_spent_cents` bigint NOT NULL DEFAULT '0',
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `invitation_deadline` timestamp NULL DEFAULT NULL COMMENT 'Deadline for marketers to accept/decline — after this, pending invitations are auto-declined',
  `status` enum('draft','pending_admin','active','paused','ended','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `attribution_model` enum('last_click','first_click','linear') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'last_click',
  `whatsapp_sharing_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `travel_agency_campaign_offers_approved_by_admin_id_foreign` (`approved_by_admin_id`),
  KEY `travel_agency_campaign_offers_rejected_by_admin_id_foreign` (`rejected_by_admin_id`),
  KEY `travel_agency_campaign_offers_travel_agency_id_status_index` (`travel_agency_id`,`status`),
  KEY `travel_agency_campaign_offers_status_ends_at_index` (`status`,`ends_at`),
  CONSTRAINT `travel_agency_campaign_offers_approved_by_admin_id_foreign` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `travel_agency_campaign_offers_rejected_by_admin_id_foreign` FOREIGN KEY (`rejected_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `travel_agency_campaign_offers_travel_agency_id_foreign` FOREIGN KEY (`travel_agency_id`) REFERENCES `travel_agencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_agency_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_agency_change_requests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_agency_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by_travel_agency_member_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` enum('bank_accounts') COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_type` enum('add','edit','delete') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'edit',
  `current_data` json NOT NULL,
  `requested_data` json NOT NULL,
  `agency_note` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `applied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `travel_agency_change_requests_reviewed_by_admin_id_foreign` (`reviewed_by_admin_id`),
  KEY `travel_agency_change_requests_travel_agency_id_status_index` (`travel_agency_id`,`status`),
  CONSTRAINT `travel_agency_change_requests_reviewed_by_admin_id_foreign` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_agency_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_agency_members` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_agency_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_owner` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `travel_agency_members_email_unique` (`email`),
  KEY `travel_agency_members_travel_agency_id_index` (`travel_agency_id`),
  CONSTRAINT `travel_agency_members_travel_agency_id_foreign` FOREIGN KEY (`travel_agency_id`) REFERENCES `travel_agencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_agency_password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_agency_password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `travel_agency_password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_agency_section_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_agency_section_locks` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_agency_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` enum('bank_accounts') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT '1',
  `locked_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locked_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locked_at` timestamp NOT NULL,
  `unlocked_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unlocked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `travel_agency_section_locks_travel_agency_id_section_unique` (`travel_agency_id`,`section`),
  KEY `travel_agency_section_locks_locked_by_admin_id_foreign` (`locked_by_admin_id`),
  KEY `travel_agency_section_locks_unlocked_by_admin_id_foreign` (`unlocked_by_admin_id`),
  CONSTRAINT `travel_agency_section_locks_locked_by_admin_id_foreign` FOREIGN KEY (`locked_by_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `travel_agency_section_locks_travel_agency_id_foreign` FOREIGN KEY (`travel_agency_id`) REFERENCES `travel_agencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `travel_agency_section_locks_unlocked_by_admin_id_foreign` FOREIGN KEY (`unlocked_by_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_bookings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `booking_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_package_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travelers_count` int unsigned NOT NULL DEFAULT '1',
  `total_price` bigint unsigned NOT NULL,
  `passport_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_signed_at` timestamp NULL DEFAULT NULL,
  `contract_signature_data` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending_documents','confirmed','cancelled','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_documents',
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `travel_bookings_booking_number_unique` (`booking_number`),
  KEY `travel_bookings_travel_package_id_foreign` (`travel_package_id`),
  KEY `travel_bookings_customer_id_foreign` (`customer_id`),
  CONSTRAINT `travel_bookings_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `travel_bookings_travel_package_id_foreign` FOREIGN KEY (`travel_package_id`) REFERENCES `travel_packages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_categories` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `travel_categories_slug_unique` (`slug`),
  KEY `travel_categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `travel_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `travel_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_cities` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `travel_cities_travel_country_id_is_active_index` (`travel_country_id`,`is_active`),
  CONSTRAINT `travel_cities_travel_country_id_foreign` FOREIGN KEY (`travel_country_id`) REFERENCES `travel_countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_countries` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code_2` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code_3` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `flag_emoji` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `continent` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `travel_countries_iso_code_2_unique` (`iso_code_2`),
  UNIQUE KEY `travel_countries_iso_code_3_unique` (`iso_code_3`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_inclusions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_inclusions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `travel_inclusions_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_package_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_package_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `travel_package_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tp_categories_unique` (`travel_package_id`,`travel_category_id`),
  KEY `travel_package_categories_travel_category_id_foreign` (`travel_category_id`),
  CONSTRAINT `travel_package_categories_travel_category_id_foreign` FOREIGN KEY (`travel_category_id`) REFERENCES `travel_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `travel_package_categories_travel_package_id_foreign` FOREIGN KEY (`travel_package_id`) REFERENCES `travel_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_package_inclusions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_package_inclusions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `travel_package_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_inclusion_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tp_inclusions_unique` (`travel_package_id`,`travel_inclusion_id`),
  KEY `travel_package_inclusions_travel_inclusion_id_foreign` (`travel_inclusion_id`),
  CONSTRAINT `travel_package_inclusions_travel_inclusion_id_foreign` FOREIGN KEY (`travel_inclusion_id`) REFERENCES `travel_inclusions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `travel_package_inclusions_travel_package_id_foreign` FOREIGN KEY (`travel_package_id`) REFERENCES `travel_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_package_inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_package_inquiries` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_package_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `travelers_count` int unsigned DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `status` enum('new','contacted','converted','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `close_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `converted_to_booking_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `travel_package_inquiries_converted_to_booking_id_foreign` (`converted_to_booking_id`),
  KEY `travel_package_inquiries_travel_package_id_status_index` (`travel_package_id`,`status`),
  CONSTRAINT `travel_package_inquiries_converted_to_booking_id_foreign` FOREIGN KEY (`converted_to_booking_id`) REFERENCES `travel_bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `travel_package_inquiries_travel_package_id_foreign` FOREIGN KEY (`travel_package_id`) REFERENCES `travel_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_package_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_package_media` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_package_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_type` enum('image','video') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `travel_package_media_travel_package_id_foreign` (`travel_package_id`),
  CONSTRAINT `travel_package_media_travel_package_id_foreign` FOREIGN KEY (`travel_package_id`) REFERENCES `travel_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_package_pricing_tiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_package_pricing_tiers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_package_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travelers_count` int unsigned NOT NULL,
  `price` bigint unsigned NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tp_pricing_tiers_package_count_unique` (`travel_package_id`,`travelers_count`),
  CONSTRAINT `travel_package_pricing_tiers_travel_package_id_foreign` FOREIGN KEY (`travel_package_id`) REFERENCES `travel_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_packages` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `travel_agency_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `destination_country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_travel_country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_travel_city_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` bigint unsigned NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing_tiers_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `show_pricing_tiers_to_customer` tinyint(1) NOT NULL DEFAULT '1',
  `duration_days` int unsigned NOT NULL,
  `duration_nights` int unsigned NOT NULL,
  `departure_date` date NOT NULL,
  `return_date` date NOT NULL,
  `available_seats` int unsigned DEFAULT NULL,
  `seats_booked` int unsigned NOT NULL DEFAULT '0',
  `status` enum('draft','pending_review','active','sold_out','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `contract_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_file_original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_uploaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `travel_packages_slug_unique` (`slug`),
  KEY `travel_packages_travel_agency_id_foreign` (`travel_agency_id`),
  KEY `travel_packages_approved_by_admin_id_foreign` (`approved_by_admin_id`),
  KEY `travel_packages_destination_travel_country_id_foreign` (`destination_travel_country_id`),
  KEY `travel_packages_destination_travel_city_id_foreign` (`destination_travel_city_id`),
  CONSTRAINT `travel_packages_approved_by_admin_id_foreign` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `travel_packages_destination_travel_city_id_foreign` FOREIGN KEY (`destination_travel_city_id`) REFERENCES `travel_cities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `travel_packages_destination_travel_country_id_foreign` FOREIGN KEY (`destination_travel_country_id`) REFERENCES `travel_countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `travel_packages_travel_agency_id_foreign` FOREIGN KEY (`travel_agency_id`) REFERENCES `travel_agencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_acquisition_commission_earnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_acquisition_commission_earnings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commission_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `month` date NOT NULL,
  `order_count_in_month` int unsigned NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vac_earnings_commission_sub_order_unique` (`commission_id`,`sub_order_id`),
  KEY `vendor_acquisition_commission_earnings_sub_order_id_foreign` (`sub_order_id`),
  KEY `vac_earnings_commission_month_index` (`commission_id`,`month`),
  CONSTRAINT `vendor_acquisition_commission_earnings_commission_id_foreign` FOREIGN KEY (`commission_id`) REFERENCES `vendor_acquisition_commissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_acquisition_commission_earnings_sub_order_id_foreign` FOREIGN KEY (`sub_order_id`) REFERENCES `sub_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_acquisition_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_acquisition_commissions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commission_rate` smallint unsigned NOT NULL,
  `monthly_min_sales` int unsigned NOT NULL DEFAULT '60',
  `monthly_max_sales` int unsigned NOT NULL DEFAULT '100',
  `valid_from` date NOT NULL,
  `valid_until` date NOT NULL,
  `status` enum('active','expired','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `total_earned` bigint unsigned NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_acquisition_commissions_vendor_id_admin_id_unique` (`vendor_id`,`admin_id`),
  KEY `vendor_acquisition_commissions_admin_id_foreign` (`admin_id`),
  KEY `vendor_acquisition_commissions_created_by_admin_id_foreign` (`created_by_admin_id`),
  CONSTRAINT `vendor_acquisition_commissions_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_acquisition_commissions_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_acquisition_commissions_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_admin_password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_admin_password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `vendor_admin_password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_admins` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_owner` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_holder_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iban` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number_encrypted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `swift_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `verification_status` enum('pending','verified','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `verified_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_bank_accounts_vendor_id_index` (`vendor_id`),
  KEY `vendor_bank_accounts_verified_by_admin_id_index` (`verified_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_change_requests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by_vendor_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` enum('store_profile','business_info','contact_info','documents','bank_accounts') COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_type` enum('edit','add','delete') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'edit',
  `current_data` json NOT NULL COMMENT 'Snapshot of what the values are right now',
  `requested_data` json NOT NULL COMMENT 'What the vendor wants them to change to',
  `vendor_note` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `applied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_change_requests_requested_by_vendor_admin_id_foreign` (`requested_by_vendor_admin_id`),
  KEY `vendor_change_requests_reviewed_by_admin_id_foreign` (`reviewed_by_admin_id`),
  KEY `vendor_change_requests_vendor_id_status_index` (`vendor_id`,`status`),
  CONSTRAINT `vendor_change_requests_requested_by_vendor_admin_id_foreign` FOREIGN KEY (`requested_by_vendor_admin_id`) REFERENCES `vendor_admins` (`id`),
  CONSTRAINT `vendor_change_requests_reviewed_by_admin_id_foreign` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `vendor_change_requests_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_city_shipping_surcharges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_city_shipping_surcharges` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extra_amount_cents` int unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_city_shipping_surcharges_vendor_id_warehouse_id_unique` (`vendor_id`,`warehouse_id`),
  KEY `vendor_city_shipping_surcharges_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `vendor_city_shipping_surcharges_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_city_shipping_surcharges_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_document_country_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_document_country_requirements` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_document_type_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requirement_level` enum('mandatory','optional','not_applicable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'optional',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vdcr_vdt_country_unique` (`vendor_document_type_id`,`country_id`),
  KEY `vdcr_country_id_fk` (`country_id`),
  CONSTRAINT `vdcr_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vdcr_vdt_id_fk` FOREIGN KEY (`vendor_document_type_id`) REFERENCES `vendor_document_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_document_types` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `accepted_file_types` json DEFAULT NULL,
  `max_file_size_kb` int unsigned NOT NULL DEFAULT '10240',
  `requires_expiry_date` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_document_types_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_documents` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_document_type_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','verified','rejected','expired') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `verified_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_documents_vendor_id_index` (`vendor_id`),
  KEY `vendor_documents_verified_by_admin_id_index` (`verified_by_admin_id`),
  KEY `vendor_documents_vendor_document_type_id_foreign` (`vendor_document_type_id`),
  CONSTRAINT `vendor_documents_vendor_document_type_id_foreign` FOREIGN KEY (`vendor_document_type_id`) REFERENCES `vendor_document_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_exceptional_zone_alert_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_exceptional_zone_alert_results` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alert_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_zone_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_subsidy_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_exceptional_zone_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_exceptional_zone_alert_results_alert_id_foreign` (`alert_id`),
  KEY `vendor_exceptional_zone_alert_results_shipping_zone_id_foreign` (`shipping_zone_id`),
  KEY `vendor_exceptional_zone_alert_results_shipping_method_id_foreign` (`shipping_method_id`),
  KEY `vendor_exceptional_zone_alert_results_created_subsidy_id_foreign` (`created_subsidy_id`),
  KEY `vezar_created_exceptional_zone_id_foreign` (`created_exceptional_zone_id`),
  CONSTRAINT `vendor_exceptional_zone_alert_results_alert_id_foreign` FOREIGN KEY (`alert_id`) REFERENCES `vendor_exceptional_zone_alerts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_exceptional_zone_alert_results_created_subsidy_id_foreign` FOREIGN KEY (`created_subsidy_id`) REFERENCES `platform_shipping_subsidies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_exceptional_zone_alert_results_shipping_method_id_foreign` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_exceptional_zone_alert_results_shipping_zone_id_foreign` FOREIGN KEY (`shipping_zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vezar_created_exceptional_zone_id_foreign` FOREIGN KEY (`created_exceptional_zone_id`) REFERENCES `warehouse_exceptional_zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_exceptional_zone_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_exceptional_zone_alerts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city_ids` json NOT NULL,
  `carrier_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reported_carrier_fee` bigint NOT NULL DEFAULT '0' COMMENT 'What the carrier actually charges the vendor for this zone. BIGINT base currency, no /100.',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Currency of the reported carrier fee',
  `vendor_note` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','accepted','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_exceptional_zone_alerts_carrier_id_foreign` (`carrier_id`),
  KEY `vendor_exceptional_zone_alerts_reviewed_by_admin_id_foreign` (`reviewed_by_admin_id`),
  KEY `vendor_zone_alerts_vendor_status_idx` (`vendor_id`,`status`),
  KEY `vendor_exceptional_zone_alerts_warehouse_id_index` (`warehouse_id`),
  CONSTRAINT `vendor_exceptional_zone_alerts_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `shipping_carriers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_exceptional_zone_alerts_reviewed_by_admin_id_foreign` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_exceptional_zone_alerts_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_exceptional_zone_alerts_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_listings` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` bigint NOT NULL,
  `compare_at_price` bigint DEFAULT NULL,
  `cost_price` bigint DEFAULT NULL,
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `condition` enum('new','like_new','good','acceptable','refurbished') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `condition_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fulfillment_model` enum('fbm','fbn','cross_dock') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_sku` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','pending_review','active','paused','rejected','out_of_stock','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `max_order_quantity` int DEFAULT NULL,
  `low_stock_threshold` int NOT NULL DEFAULT '5',
  `buy_box_eligible` tinyint(1) NOT NULL DEFAULT '1',
  `buy_box_won_at` timestamp NULL DEFAULT NULL,
  `total_sold` int NOT NULL DEFAULT '0',
  `rating_avg` decimal(3,2) DEFAULT NULL,
  `rating_count` int NOT NULL DEFAULT '0',
  `score` decimal(8,4) DEFAULT NULL,
  `price_score` decimal(5,4) DEFAULT NULL,
  `fulfillment_score` decimal(5,4) DEFAULT NULL,
  `rating_score` decimal(5,4) DEFAULT NULL,
  `availability_score` decimal(5,4) DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT NULL,
  `next_recalculate_at` timestamp NULL DEFAULT NULL,
  `approved_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `primary_shipping_method_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_covers_delivery` tinyint NOT NULL DEFAULT '0' COMMENT 'Vendor opts in to cover remaining delivery cost after admin subsidy — customer sees Free Delivery',
  `weight_class` enum('light','medium','heavy') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Display/filter helper only, not authoritative: light <= 1000g, medium 1001-5000g, heavy > 5000g',
  `handling_class` enum('standard','refrigerated','fragile','special_tech') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `declared_weight_grams` int unsigned DEFAULT NULL COMMENT 'Vendor-declared actual weight at listing time, used in shipping fee calculation; may differ from product_variants.weight_grams due to packaging',
  `declared_length_cm` decimal(8,2) DEFAULT NULL COMMENT 'Vendor-declared packaged dimension, may differ from product_variants (product-only) dimensions',
  `declared_width_cm` decimal(8,2) DEFAULT NULL,
  `declared_height_cm` decimal(8,2) DEFAULT NULL,
  `campaign_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Vendor opted this listing into a marketer campaign',
  PRIMARY KEY (`id`),
  KEY `vendor_listings_vendor_id_index` (`vendor_id`),
  KEY `vendor_listings_product_variant_id_index` (`product_variant_id`),
  KEY `vendor_listings_country_id_index` (`country_id`),
  KEY `vendor_listings_approved_by_admin_id_index` (`approved_by_admin_id`),
  KEY `vendor_listings_primary_shipping_method_id_foreign` (`primary_shipping_method_id`),
  KEY `vendor_listings_score_index` (`score`),
  KEY `vendor_listings_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `vendor_listings_primary_shipping_method_id_foreign` FOREIGN KEY (`primary_shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_listings_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_product_certifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_product_certifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Original uploaded filename, stored for display',
  `cert_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional: vendor-provided label e.g. "SFDA Certificate #12345"',
  `status` enum('pending','approved','rejected','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Admin sets expiry date on approval if cert has a validity period',
  `reviewed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vpc_vendor_product_country_unique` (`vendor_id`,`product_id`,`country_id`),
  KEY `vendor_product_certifications_reviewed_by_admin_id_foreign` (`reviewed_by_admin_id`),
  KEY `vendor_product_certifications_vendor_id_index` (`vendor_id`),
  KEY `vendor_product_certifications_product_id_index` (`product_id`),
  KEY `vendor_product_certifications_country_id_index` (`country_id`),
  KEY `vendor_product_certifications_status_index` (`status`),
  CONSTRAINT `vendor_product_certifications_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_product_certifications_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_product_certifications_reviewed_by_admin_id_foreign` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_product_certifications_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_section_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_section_locks` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` enum('store_profile','business_info','contact_info','documents','bank_accounts') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT '1',
  `locked_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Shown to vendor, e.g. "Under review"',
  `locked_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locked_at` timestamp NOT NULL,
  `unlocked_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unlocked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_section_locks_vendor_id_section_unique` (`vendor_id`,`section`),
  KEY `vendor_section_locks_locked_by_admin_id_foreign` (`locked_by_admin_id`),
  KEY `vendor_section_locks_unlocked_by_admin_id_foreign` (`unlocked_by_admin_id`),
  CONSTRAINT `vendor_section_locks_locked_by_admin_id_foreign` FOREIGN KEY (`locked_by_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `vendor_section_locks_unlocked_by_admin_id_foreign` FOREIGN KEY (`unlocked_by_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `vendor_section_locks_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_strikes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_strikes` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` enum('late_shipment','poor_quality','customer_complaint','policy_violation','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('warning','minor','major','critical') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'minor',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `issued_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
DROP TABLE IF EXISTS `vendor_subscription_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_subscription_invoices` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscription_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','open','paid','void','uncollectible') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_transaction_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_subscription_invoices_invoice_number_unique` (`invoice_number`),
  KEY `vendor_subscription_invoices_vendor_id_status_index` (`vendor_id`,`status`),
  KEY `vendor_subscription_invoices_subscription_id_index` (`subscription_id`),
  KEY `vendor_subscription_invoices_payment_transaction_id_index` (`payment_transaction_id`),
  CONSTRAINT `vendor_subscription_invoices_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `vendor_subscriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_subscription_invoices_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_subscriptions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','cancelled','expired','past_due','trialing') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `started_at` timestamp NOT NULL,
  `current_period_start` date NOT NULL,
  `current_period_end` date NOT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `auto_renew` tinyint NOT NULL DEFAULT '1',
  `listings_used` int NOT NULL DEFAULT '0',
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_subscriptions_plan_id_foreign` (`plan_id`),
  KEY `vendor_subscriptions_vendor_id_status_index` (`vendor_id`,`status`),
  KEY `vendor_subscriptions_approved_by_admin_id_index` (`approved_by_admin_id`),
  CONSTRAINT `vendor_subscriptions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`),
  CONSTRAINT `vendor_subscriptions_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendors` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_type` enum('individual','sole_prop','llc','corp') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual',
  `business_registration_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `default_warehouse_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payout_schedule` enum('weekly','biweekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `payout_hold_active` tinyint(1) NOT NULL DEFAULT '0',
  `payout_hold_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `store_rating_avg` decimal(3,2) NOT NULL DEFAULT '0.00',
  `store_rating_count` int NOT NULL DEFAULT '0',
  `total_sales` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_orders` int NOT NULL DEFAULT '0',
  `return_rate_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `cancellation_rate_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `sla_compliance_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `strikes_count` decimal(5,2) NOT NULL DEFAULT '0.00',
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `global_status` enum('pending','active','inactive','suspended','rejected','blacklisted','under_review') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_by_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_completed_at` timestamp NULL DEFAULT NULL,
  `account_manager_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marketer_type` enum('influencer','affiliate') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'null = vendor only; influencer/affiliate = vendor+marketer',
  `whatsapp_for_campaigns` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'WhatsApp number for campaign invitation messages',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `warranty_months` smallint unsigned DEFAULT NULL,
  `easy_returns_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `secure_payments_enabled` tinyint(1) NOT NULL DEFAULT '1',
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
DROP TABLE IF EXISTS `virtual_tryon_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `virtual_tryon_sessions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `result_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('queued','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Which AI service was used',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `virtual_tryon_sessions_customer_id_foreign` (`customer_id`),
  KEY `virtual_tryon_sessions_vendor_listing_id_foreign` (`vendor_listing_id`),
  KEY `virtual_tryon_sessions_status_index` (`status`),
  KEY `virtual_tryon_sessions_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `virtual_tryon_sessions_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `virtual_tryon_sessions_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `virtual_tryon_sessions_vendor_listing_id_foreign` FOREIGN KEY (`vendor_listing_id`) REFERENCES `vendor_listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_vts_listing_xor` CHECK ((((`vendor_listing_id` is not null) and (`admin_listing_id` is null)) or ((`vendor_listing_id` is null) and (`admin_listing_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `voucher_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voucher_redemptions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_wallet_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint unsigned NOT NULL COMMENT 'BIGINT base-currency units credited. No /100.',
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wallet_balance_after` bigint unsigned NOT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `voucher_redemptions_voucher_id_customer_id_index` (`voucher_id`,`customer_id`),
  KEY `voucher_redemptions_customer_wallet_id_foreign` (`customer_wallet_id`),
  KEY `voucher_redemptions_voucher_id_index` (`voucher_id`),
  KEY `voucher_redemptions_customer_id_index` (`customer_id`),
  CONSTRAINT `voucher_redemptions_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `voucher_redemptions_customer_wallet_id_foreign` FOREIGN KEY (`customer_wallet_id`) REFERENCES `customer_wallets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `voucher_redemptions_voucher_id_foreign` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vouchers` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `title_ar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title_en` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint unsigned NOT NULL COMMENT 'BIGINT base-currency. e.g. 5000 = 50 SAR. Never /100.',
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_eligibility` enum('all','new_customers','specific_users') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `eligible_customer_ids` json DEFAULT NULL COMMENT 'JSON array of customer UUIDs. Used when customer_eligibility = specific_users.',
  `usage_limit_total` int unsigned DEFAULT NULL COMMENT 'Max redemptions across all customers. NULL = unlimited.',
  `usage_limit_per_customer` int unsigned NOT NULL DEFAULT '1',
  `times_redeemed` int unsigned NOT NULL DEFAULT '0',
  `valid_from` timestamp NOT NULL,
  `valid_until` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vouchers_code_unique` (`code`),
  KEY `vouchers_currency_code_index` (`currency_code`),
  KEY `vouchers_is_active_index` (`is_active`),
  KEY `vouchers_country_id_foreign` (`country_id`),
  KEY `vouchers_created_by_admin_id_foreign` (`created_by_admin_id`),
  CONSTRAINT `vouchers_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallet_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_transactions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wallet_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('credit','debit') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint NOT NULL,
  `balance_after` bigint NOT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `wallet_transactions_wallet_id_created_at_index` (`wallet_id`,`created_at`),
  KEY `wallet_transactions_customer_id_created_at_index` (`customer_id`,`created_at`),
  CONSTRAINT `wallet_transactions_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_transactions_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallet_withdrawal_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_withdrawal_requests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wallet_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_iban` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','processed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wallet_withdrawal_requests_wallet_id_foreign` (`wallet_id`),
  KEY `wallet_withdrawal_requests_status_created_at_index` (`status`,`created_at`),
  CONSTRAINT `wallet_withdrawal_requests_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_type` enum('customer','vendor','marketer','delivery_agent','travel_agency') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` bigint NOT NULL DEFAULT '0',
  `pending_balance` bigint NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_frozen` tinyint(1) NOT NULL DEFAULT '0',
  `frozen_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_owner` (`owner_type`,`owner_id`,`currency`),
  KEY `wallets_owner_type_index` (`owner_type`),
  KEY `wallets_owner_id_index` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_exceptional_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouse_exceptional_zones` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_zone_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `source_alert_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_warehouse_zone_carrier` (`warehouse_id`,`destination_zone_id`,`carrier_id`),
  KEY `warehouse_exceptional_zones_destination_zone_id_foreign` (`destination_zone_id`),
  KEY `warehouse_exceptional_zones_carrier_id_foreign` (`carrier_id`),
  KEY `warehouse_exceptional_zones_source_alert_id_foreign` (`source_alert_id`),
  CONSTRAINT `warehouse_exceptional_zones_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `shipping_carriers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_exceptional_zones_destination_zone_id_foreign` FOREIGN KEY (`destination_zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warehouse_exceptional_zones_source_alert_id_foreign` FOREIGN KEY (`source_alert_id`) REFERENCES `vendor_exceptional_zone_alerts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_exceptional_zones_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_inventories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouse_inventories` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_on_hand` int NOT NULL DEFAULT '0',
  `quantity_reserved` int NOT NULL DEFAULT '0',
  `quantity_available` int GENERATED ALWAYS AS ((`quantity_on_hand` - `quantity_reserved`)) VIRTUAL,
  `quantity_inbound` int NOT NULL DEFAULT '0',
  `quantity_damaged` int NOT NULL DEFAULT '0',
  `bin_location` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reorder_point` int DEFAULT NULL,
  `last_counted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warehouse_inventories_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `warehouse_inventories_warehouse_id_index` (`warehouse_id`),
  KEY `warehouse_inventories_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `warehouse_inventories_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_wi_listing_xor` CHECK ((((`vendor_listing_id` is not null) and (`admin_listing_id` is null)) or ((`vendor_listing_id` is null) and (`admin_listing_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_shipping_surcharges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouse_shipping_surcharges` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extra_amount_cents` int unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouse_shipping_surcharges_warehouse_id_unique` (`warehouse_id`),
  CONSTRAINT `warehouse_shipping_surcharges_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouse_vendor_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouse_vendor_limits` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `limit_type` enum('quantity','capacity') COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_quantity` int DEFAULT NULL,
  `max_capacity_m3` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouse_vendor_limits_warehouse_id_vendor_id_unique` (`warehouse_id`,`vendor_id`),
  KEY `warehouse_vendor_limits_warehouse_id_index` (`warehouse_id`),
  KEY `warehouse_vendor_limits_vendor_id_index` (`vendor_id`),
  CONSTRAINT `warehouse_vendor_limits_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warehouse_vendor_limits_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('platform_fbn','seller_owned','third_party') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_vendor_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `total_capacity_m3` decimal(10,2) DEFAULT NULL,
  `used_capacity_m3` decimal(10,2) DEFAULT NULL,
  `default_limit_type` enum('quantity','capacity') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_max_quantity` int DEFAULT NULL,
  `default_max_capacity_m3` decimal(10,2) DEFAULT NULL,
  `free_storage_days` smallint unsigned NOT NULL DEFAULT '30',
  `daily_fee_per_unit` bigint NOT NULL DEFAULT '0',
  `daily_fee_currency` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_rate_per_m3_price` bigint DEFAULT NULL,
  `storage_currency` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_admin_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `warranty_claim_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warranty_claim_messages` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warranty_claim_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_user_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_role` enum('customer','vendor','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_internal_note` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `warranty_claim_messages_warranty_claim_id_foreign` (`warranty_claim_id`),
  KEY `warranty_claim_messages_sender_user_id_index` (`sender_user_id`),
  CONSTRAINT `warranty_claim_messages_warranty_claim_id_foreign` FOREIGN KEY (`warranty_claim_id`) REFERENCES `warranty_claims` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warranty_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warranty_claims` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `claim_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_item_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `listing_type` enum('vendor_listing','admin_listing') COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_type` enum('defective','not_working','physical_damage','missing_parts','software_issue','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_date` date NOT NULL,
  `warranty_expires_at` date NOT NULL,
  `covered_by_platform_warranty` tinyint(1) NOT NULL DEFAULT '0',
  `evidence_files` json DEFAULT NULL,
  `status` enum('submitted','under_review','approved','rejected','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `resolution` enum('repair','replace','refund','no_action') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `resolved_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_response` text COLLATE utf8mb4_unicode_ci,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warranty_claims_claim_number_unique` (`claim_number`),
  KEY `warranty_claims_customer_id_index` (`customer_id`),
  KEY `warranty_claims_order_item_id_index` (`order_item_id`),
  KEY `warranty_claims_product_id_index` (`product_id`),
  KEY `warranty_claims_vendor_id_index` (`vendor_id`),
  KEY `warranty_claims_resolved_by_admin_id_index` (`resolved_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warranty_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warranty_plans` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_months` tinyint unsigned NOT NULL COMMENT 'Coverage duration in months; applied after brand warranty ends',
  `features_en` json DEFAULT NULL,
  `features_ar` json DEFAULT NULL,
  `country_ids` json DEFAULT NULL,
  `price` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_by_admin_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warranty_plans_category_id_is_active_index` (`category_id`,`is_active`),
  KEY `warranty_plans_created_by_admin_id_foreign` (`created_by_admin_id`),
  CONSTRAINT `warranty_plans_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `warranty_plans_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warranty_purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warranty_purchases` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_item_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warranty_plan_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_snapshot` json NOT NULL COMMENT 'Immutable copy of plan at purchase time',
  `price_paid` bigint NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','active','expired','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `coverage_starts_at` date DEFAULT NULL,
  `coverage_ends_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warranty_purchases_order_item_id_unique` (`order_item_id`),
  KEY `warranty_purchases_customer_id_status_index` (`customer_id`,`status`),
  KEY `warranty_purchases_order_id_index` (`order_id`),
  KEY `warranty_purchases_warranty_plan_id_foreign` (`warranty_plan_id`),
  CONSTRAINT `warranty_purchases_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `warranty_purchases_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `warranty_purchases_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `warranty_purchases_warranty_plan_id_foreign` FOREIGN KEY (`warranty_plan_id`) REFERENCES `warranty_plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhook_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_deliveries` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `received_from` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `signature` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('received','processed','failed','retry') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `attempts` int NOT NULL DEFAULT '0',
  `last_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_variant_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `added_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whishlists_customer_id_index` (`customer_id`),
  KEY `whishlists_product_id_index` (`product_id`),
  KEY `whishlists_vendor_listing_id_index` (`vendor_listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wishlist_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlist_groups` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wishlist_groups_customer_id_sort_order_index` (`customer_id`,`sort_order`),
  KEY `wishlist_groups_customer_id_is_default_index` (`customer_id`,`is_default`),
  CONSTRAINT `wishlist_groups_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wishlist_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlist_items` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wishlist_group_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_listing_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_variant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wg_vendor_listing` (`wishlist_group_id`,`vendor_listing_id`),
  UNIQUE KEY `uq_wg_admin_listing` (`wishlist_group_id`,`admin_listing_id`),
  KEY `wishlist_items_product_variant_id_foreign` (`product_variant_id`),
  KEY `wishlist_items_customer_id_wishlist_group_id_index` (`customer_id`,`wishlist_group_id`),
  KEY `wishlist_items_vendor_listing_id_index` (`vendor_listing_id`),
  KEY `wishlist_items_admin_product_listing_id_index` (`admin_listing_id`),
  CONSTRAINT `wishlist_items_admin_listing_id_foreign` FOREIGN KEY (`admin_listing_id`) REFERENCES `admin_listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wishlist_items_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_items_vendor_listing_id_foreign` FOREIGN KEY (`vendor_listing_id`) REFERENCES `vendor_listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wishlist_items_wishlist_group_id_foreign` FOREIGN KEY (`wishlist_group_id`) REFERENCES `wishlist_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlists` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_listing_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wishlists_customer_id_vendor_listing_id_unique` (`customer_id`,`vendor_listing_id`)
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
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2026_05_28_210808_create_personal_access_tokens_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2026_05_29_000001_create_device_tokens_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2026_05_29_000002_create_delivery_agents_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2026_05_29_000003_create_delivery_assignments_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2026_05_29_000004_create_agent_location_history_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2026_05_29_143639_add_flag_emoji_to_countries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2026_05_29_153024_rename_status_to_global_status_on_vendors',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2026_05_31_000001_fix_vendor_approval_columns',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2026_05_31_000002_create_vendor_admin_password_resets_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2026_05_31_130325_make_warehouses_address_id_and_storage_currency_nullable',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2026_05_31_135443_fix_addresses_addressable_id_to_uuid',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2026_06_02_000001_create_delivery_zones_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2026_06_02_000002_create_delivery_agent_shifts_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2026_06_02_000003_create_delivery_agent_documents_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2026_06_02_000004_create_delivery_agent_earnings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2026_06_02_000005_create_delivery_agent_payouts_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2026_06_02_000006_create_vendor_subscriptions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2026_06_02_000011_create_admin_product_listings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2026_06_02_000013_create_fbn_fulfillment_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2026_06_02_000014_create_product_cost_references_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2026_06_02_183540_add_remember_token_to_admins_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2026_06_17_000001_add_nawy_columns_to_admin_product_listings',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2026_06_17_000002_create_classified_contract_templates_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2026_06_17_000003_create_classified_categories_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2026_06_17_000004_create_classified_listings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2026_06_17_000005_create_classified_listing_attachments_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2026_06_17_000006_create_classified_listing_images_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2026_06_17_000008_create_classified_inquiries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2026_06_17_000009_create_travel_agencies_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2026_06_17_000010_create_travel_packages_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2026_06_17_000011_create_travel_package_media_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2026_06_17_000012_create_travel_bookings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2026_06_18_000001_create_shipping_companies_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2026_06_18_000002_create_shipping_company_supervisors_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2026_06_18_000003_alter_delivery_agents_add_shipping_company',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2026_06_18_000004_create_shipping_fallback_rules_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2026_06_18_000005_create_wallets_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2026_06_18_000005_insert_nawy_carousel_block_type',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2026_06_18_000006_create_wallet_transactions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2026_06_18_000007_create_wallet_withdrawal_requests_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2026_06_18_000008_create_delivery_agent_cod_settlements_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2026_06_18_000009_add_supports_virtual_tryon_to_categories_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2026_06_18_000010_create_ai_image_enhancement_jobs_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2026_06_18_000011_create_virtual_tryon_sessions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2026_06_18_000012_create_ai_video_generation_jobs_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2026_06_18_000013_create_ai_feature_credits_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2026_06_18_000020_create_radio_channels_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2026_06_18_000021_create_radio_schedule_slots_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2026_06_18_000022_create_radio_listen_sessions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2026_06_18_000030_create_carrier_claims_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2026_06_18_000031_create_carrier_performance_ratings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2026_06_18_000040_create_packaging_supplies_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2026_06_18_000041_create_packaging_supply_requests_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2026_06_18_000042_create_packaging_supply_request_items_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2026_06_20_000001_add_gateway_credentials_to_country_payment_methods',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2026_06_20_000002_create_payment_gateway_webhook_logs_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2026_06_21_000001_add_cod_collected_to_delivery_assignments',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2026_06_21_000001_create_customer_otp_tokens_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2026_06_21_000002_add_deleted_to_customers_status_enum',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2026_06_21_000005_extend_support_tickets_for_delivery_agent',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2026_06_22_000001_extend_support_tickets_for_carrier',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2026_06_24_000001_create_return_request_messages_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2026_06_24_000002_create_return_request_message_attachments_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2026_06_25_000001_make_classified_listings_seller_polymorphic',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2026_06_25_000002_add_vendor_fields_to_classified_listings',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2026_06_25_000003_create_travel_categories_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2026_06_25_000004_create_travel_package_categories_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2026_06_25_000005_add_slug_to_classified_listings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2026_06_25_000006_add_slug_to_travel_packages_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2026_06_28_000002_make_refund_original_transaction_nullable',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2026_06_28_182006_make_model_type_nullable_in_files_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2026_06_29_000001_create_travel_countries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2026_06_29_000002_add_country_id_to_orders_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2026_06_29_000002_create_travel_cities_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2026_06_29_000003_add_travel_geography_fks_to_travel_packages_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2026_06_29_114626_add_rejection_reason_to_travel_packages',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2026_06_29_121200_make_destination_country_nullable_on_travel_packages',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2026_06_30_000001_add_contract_file_to_travel_packages_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2026_06_30_000001_create_vendor_document_types_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2026_06_30_000002_create_travel_package_inquiries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2026_06_30_000002_create_vendor_document_country_requirements_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2026_06_30_000003_add_vendor_document_type_id_to_vendor_documents',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2026_06_30_000004_finalise_vendor_document_type_fk',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2026_06_30_000006_add_sample_quotas_to_categories',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2026_06_30_000008_make_shipping_method_id_nullable_on_sub_orders',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2026_06_30_000009_add_gateway_fee_to_sub_orders_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2026_06_30_000010_add_gateway_fee_deducted_to_payouts_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2026_07_01_000001_add_fbp_fbn_commission_columns_to_categories',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2026_07_01_000002_add_commission_snapshot_columns_to_order_items',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2026_07_01_000003_add_cod_settlement_id_to_delivery_assignments',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2026_07_01_000004_add_cod_remittance_columns_to_sub_orders',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2026_07_01_000005_add_discrepancy_columns_to_cod_settlements',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2026_07_01_000006_add_discrepancy_note_to_delivery_assignments',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2026_07_01_000007_create_blog_categories_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2026_07_01_000008_create_blog_posts_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2026_07_01_000009_add_display_metadata_to_shipping_methods',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2026_07_01_000010_create_category_shipping_methods_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2026_07_01_000011_add_primary_shipping_method_to_vendor_listings',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2026_07_01_000012_create_vendor_campaign_offers_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2026_07_01_000013_create_vendor_campaign_offer_products_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2026_07_01_000014_create_vendor_campaign_invitations_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2026_07_04_163702_add_vendor_listing_id_to_order_items_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2026_07_05_000001_add_rating_count_to_vendor_listings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2026_07_05_000002_add_ratings_to_admin_product_listings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2026_07_05_000003_add_admin_product_listing_id_to_reviews_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2026_07_05_000004_add_admin_product_listing_id_to_order_items_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2026_07_05_000005_drop_rating_from_products_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2026_07_05_000006_add_buy_box_scoring_columns_to_vendor_listings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2026_07_05_000007_alter_wishlists_for_vendor_listings',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2026_07_07_130010_add_updated_at_to_notifications_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2026_07_08_000001_add_close_reason_to_travel_package_inquiries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2026_07_08_000002_create_travel_agency_password_resets_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2026_07_09_000001_create_advertise_inquiries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2026_07_09_122509_create_ad_support_collections_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2026_07_09_122510_create_ad_support_articles_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2026_07_10_134850_create_portal_contents_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2026_07_10_140000_create_faqs_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2026_07_10_160551_create_help_center_categories_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2026_07_10_160552_create_help_center_articles_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2026_07_10_164348_add_translations_to_ad_support_collections_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2026_07_10_164349_add_translations_to_ad_support_articles_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2026_07_10_164350_add_translations_to_help_center_categories_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2026_07_10_164351_add_translations_to_help_center_articles_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2026_07_10_170000_add_product_count_to_categories_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2026_07_11_120000_add_missing_page_block_indexes',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2026_07_11_133547_add_description_ar_to_block_types_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (253,'2026_07_12_000001_remove_sponsored_products_block_type',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2026_07_13_000001_add_installment_config_to_country_payment_methods',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2026_07_13_000002_add_order_cutoff_time_to_shipping_methods',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2026_07_13_000003_create_product_bestseller_rankings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2026_07_13_000004_add_lft_rgt_index_to_categories_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (258,'2026_07_13_141113_add_bank_offer_fields_to_coupons_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (259,'2026_07_13_142058_add_trust_badge_columns_to_vendors_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (260,'2026_07_13_150000_create_frequently_bought_together_items_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (261,'2026_07_13_150100_create_product_highlights_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (262,'2026_07_14_100000_create_warranty_claims_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (263,'2026_07_14_100001_create_warranty_claim_messages_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (264,'2026_07_14_115900_create_gift_card_batches_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (265,'2026_07_14_120000_create_gift_cards_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (266,'2026_07_14_120001_create_gift_card_transactions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (267,'2026_07_14_140000_add_qr_code_path_to_customers_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (268,'2026_07_15_000001_create_travel_package_pricing_tiers_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (269,'2026_07_15_000002_add_pricing_tiers_columns_to_travel_packages_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (270,'2026_07_15_100000_create_warranty_plans_and_purchases_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (271,'2026_07_15_100002_add_covered_by_platform_warranty_to_warranty_claims_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (272,'2026_07_16_000001_add_delivery_fee_cents_to_packaging_supply_requests_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (273,'2026_07_16_000001_add_vendor_covers_delivery_to_vendor_listings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (274,'2026_07_16_000002_add_rejection_fields_to_delivery_assignments_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (275,'2026_07_16_000002_create_vendor_fbp_subsidy_settings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (276,'2026_07_16_000003_add_refund_deduction_columns_to_refunds_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (277,'2026_07_16_000004_create_shipping_weight_slabs_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (278,'2026_07_16_000005_create_vendor_subsidy_settings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (279,'2026_07_16_000006_create_vendor_exceptional_zones_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (280,'2026_07_16_161854_add_subsidy_columns_to_sub_orders_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (281,'2026_07_16_170001_create_app_contexts_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (282,'2026_07_16_170002_create_app_context_countries_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (283,'2026_07_16_170003_add_app_context_key_to_pages_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (284,'2026_07_16_170003_create_app_bottom_nav_items_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (285,'2026_07_16_180001_add_nawy_sidebar_fields_to_categories',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (286,'2026_07_16_180500_rename_remaining_cents_columns',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (287,'2026_07_17_090000_create_travel_inclusions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (288,'2026_07_17_090001_create_travel_package_inclusions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (289,'2026_07_17_090002_seed_and_migrate_travel_inclusions_data',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (290,'2026_07_17_090003_drop_legacy_inclusions_column_from_travel_packages_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (291,'2026_07_17_193631_drop_vendor_subsidy_feature',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (292,'2026_07_17_193707_create_vendor_city_shipping_surcharges_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (293,'2026_07_17_200000_change_vendor_city_shipping_surcharges_to_warehouse',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (294,'2026_07_17_210000_create_warehouse_shipping_surcharges_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (295,'2026_07_18_090000_create_warehouse_vendor_limits_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (296,'2026_07_18_100000_add_default_vendor_limit_to_warehouses_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (297,'2026_07_19_110000_create_vendor_section_locks_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (298,'2026_07_19_110001_create_vendor_change_requests_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (299,'2026_07_19_120000_add_free_storage_config_to_warehouses_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (300,'2026_07_19_120001_create_fbn_daily_overage_fees_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (301,'2026_07_19_192255_add_cancellation_reason_to_travel_bookings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (302,'2026_07_20_000001_create_platform_shipping_subsidies_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (303,'2026_07_20_000002_add_weight_classification_to_vendor_listings',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (304,'2026_07_20_000003_add_subsidy_and_weight_columns_to_sub_orders_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (305,'2026_07_20_123909_update_vendor_admins_role_system',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (306,'2026_07_20_131818_create_content_settings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (307,'2026_07_20_140000_add_description_and_sort_order_to_packaging_supplies_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (308,'2026_07_20_140000_add_image_type_to_portal_contents_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (309,'2026_07_20_140100_seed_packaging_delivery_fee_settings',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (310,'2026_07_20_150000_create_travel_agency_campaign_offers_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (311,'2026_07_20_150001_create_travel_agency_campaign_offer_packages_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (312,'2026_07_20_150002_create_travel_agency_campaign_invitations_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (313,'2026_07_20_233000_create_travel_agency_members_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (314,'2026_07_20_233001_migrate_travel_agencies_to_owner_members',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (315,'2026_07_20_233500_add_travel_agency_scoping_to_roles_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (316,'2026_07_21_000000_add_notification_preferences_to_customers_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (317,'2026_07_21_090000_add_currency_and_shipping_timestamps_to_packaging_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (318,'2026_07_21_120001_create_travel_agency_bank_accounts_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (319,'2026_07_21_120002_create_travel_agency_section_locks_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (320,'2026_07_21_120003_create_travel_agency_change_requests_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (321,'2026_07_21_164821_add_phone_to_travel_agency_members_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (322,'2026_07_22_000000_add_travel_agency_to_wallets_owner_type',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (323,'2026_07_22_000001_extend_support_tickets_for_travel_agency',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (324,'2026_07_22_000002_add_carrier_rate_columns_to_shipping_rates_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (325,'2026_07_22_000005_add_split_columns_to_platform_shipping_subsidies_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (326,'2026_07_22_000006_add_gap_columns_to_sub_orders_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (327,'2026_07_22_000007_create_warehouse_exceptional_zones_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (328,'2026_07_22_000008_add_warehouse_id_to_vendor_listings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (329,'2026_07_23_000001_add_default_to_base_fee_on_shipping_rates_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (330,'2026_07_23_000001_create_vendor_acquisition_commissions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (331,'2026_07_23_000001_create_vendor_exceptional_zone_alerts_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (332,'2026_07_23_000002_add_carrier_and_source_alert_to_warehouse_exceptional_zones_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (333,'2026_07_23_000002_create_vendor_acquisition_commission_earnings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (334,'2026_07_23_000003_add_carrier_id_to_platform_shipping_subsidies_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (335,'2026_07_23_000004_create_vendor_exceptional_zone_alert_results_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (336,'2026_07_23_000005_convert_vendor_exceptional_zone_alerts_to_city_ids',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (337,'2026_07_24_000001_add_admin_product_listing_id_to_cart_items',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (338,'2026_07_24_000001_create_wishlist_groups_and_items_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (339,'2026_07_24_000002_create_cart_card_offers_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (340,'2026_07_24_000003_add_selected_shipping_method_id_to_cart_items',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (341,'2026_07_24_000003_convert_files_model_id_to_uuid',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (342,'2026_07_26_000001_add_vendor_parity_columns_to_admin_product_listings',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (343,'2026_07_26_000002_add_admin_listing_to_warehouse_inventories',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (344,'2026_07_26_000003_add_admin_listing_to_marketplace_shipping_rules',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (345,'2026_07_26_000004_add_admin_listing_to_flash_sale_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (346,'2026_07_26_000006_add_admin_listing_to_ad_campaign_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (347,'2026_07_26_000007_add_admin_listing_to_operational_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (348,'2026_07_26_010001_add_slug_to_product_variants',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (349,'2026_07_26_010002_add_slug_to_attribute_values',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (350,'2026_07_26_020001_add_shipping_method_id_to_order_items',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (351,'2026_07_26_170004_add_app_context_key_to_page_blocks_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (352,'2026_07_27_000001_add_display_priority_to_category_shipping_methods',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (353,'2026_07_27_000002_fix_warehouse_inventories_admin_listing_fk',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (354,'2026_07_27_170500_create_customer_wallets_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (355,'2026_07_27_170501_add_customer_gift_card_columns_to_wallet_transactions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (356,'2026_07_27_170502_add_wallet_amount_used_to_orders_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (357,'2026_07_28_000001_add_missing_columns_to_coupons_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (358,'2026_07_28_000002_add_missing_columns_to_gift_cards_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (359,'2026_07_28_000003_add_created_at_to_customer_wallets_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (360,'2026_07_28_000004_create_vouchers_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (361,'2026_07_28_000005_create_voucher_redemptions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (362,'2026_07_28_200001_add_storefront_columns_to_gift_card_batches_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (363,'2026_07_28_200002_add_purchase_columns_to_gift_cards_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (364,'2026_07_28_200003_create_gift_card_purchases_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (365,'2026_07_29_010003_create_influencer_monthly_minimums_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (366,'2026_07_29_010004_create_influencer_monthly_stats_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (367,'2026_07_30_900002_drop_redundant_influencer_monthly_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (368,'2026_07_30_900003_add_wallet_amount_to_use_to_carts_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (369,'2026_08_02_000001_remove_marketer_columns_from_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (370,'2026_08_02_000002_drop_all_marketer_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (371,'2026_08_02_000003_drop_influencer_celebrity_affiliate_promotion_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (372,'2026_08_02_000004_remove_influencer_affiliate_promotion_columns',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (373,'2026_08_02_000005_final_marketer_cleanup',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (374,'2026_08_04_000001_add_per_delivery_fee_to_delivery_agents_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (375,'2026_08_04_122624_expand_commission_columns_on_country_categories_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (376,'2026_08_04_134558_create_vendor_product_certifications_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (377,'2026_08_04_173548_rebuild_admin_listings_clean',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (378,'2026_08_04_185156_remove_aplus_columns_from_admin_listings',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (379,'2026_08_04_190000_add_admin_listing_id_to_reviews_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (380,'2026_08_10_000001_add_marketer_columns_to_vendors_categories_listings',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (381,'2026_08_10_000002_create_marketer_profiles_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (382,'2026_08_10_000003_create_marketer_campaigns_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (383,'2026_08_10_000004_create_marketer_campaign_tiered_rules_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (384,'2026_08_10_000005_create_marketer_campaign_invitations_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (385,'2026_08_10_000006_create_marketer_campaign_conversions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (386,'2026_08_10_000007_create_marketer_campaign_samples_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (387,'2026_08_10_000008_create_marketer_commission_country_settings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (388,'2026_08_10_000009_create_marketer_influencer_fee_country_settings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (389,'2026_08_10_000010_add_xor_constraint_to_marketer_campaigns',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (390,'2026_08_10_000011_add_marketer_commission_to_payout_items',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (391,'2026_08_10_000014_add_financial_columns_to_marketer_campaigns',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (392,'2026_08_10_000015_add_fee_columns_to_marketer_campaign_invitations',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (393,'2026_08_11_000001_add_requested_marketer_ids_to_marketer_campaigns',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (394,'2026_08_12_000001_drop_vendor_campaign_offers_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (395,'2026_08_13_000001_add_loyalty_columns_to_orders_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (396,'2026_08_08_000001_create_page_block_brands_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (397,'2026_08_08_000002_create_newsletter_subscribers_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (398,'2026_08_09_000001_add_layout_to_page_sections_and_column_span_to_page_blocks',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (399,'2026_08_08_152338_add_sections_snapshot_to_page_revisions_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (400,'2026_08_09_000002_add_subtitle_badge_to_ad_image_items',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (401,'2026_08_14_000001_create_announcement_bars_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (402,'2026_08_15_000001_create_customer_dismissed_announcement_bars_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (403,'2026_08_15_000002_add_indexes_to_customer_dismissed_announcement_bars_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (404,'2026_08_10_000001_add_country_id_to_packaging_supplies',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (405,'2026_08_16_000001_create_packaging_supply_countries_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (406,'2026_08_10_174715_ticket_fix',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (407,'2026_08_17_000001_fix_ticket_messages_sender_id_to_uuid',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (408,'2026_08_18_000001_add_is_paid_to_page_builder_items',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (409,'2026_08_19_000001_create_payment_gateways_tables',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (410,'2026_08_19_000002_seed_payment_gateways',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (411,'2026_08_19_000003_update_payment_webhook_logs_fk',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (412,'2026_08_19_000004_drop_old_payment_methods_tables',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (413,'2026_08_15_131014_fix_delivery_zones_cents_column_drift',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (414,'2026_08_20_000001_add_shipping_company_id_to_shipping_carriers',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (415,'2026_08_20_000002_add_country_id_to_shipping_carriers_and_supervisors',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (416,'2026_08_16_000001_add_soft_deletes_to_delivery_zones_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (417,'2026_08_21_000001_create_customer_receivers_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (418,'2026_08_21_000002_add_delivery_instruction_to_orders',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (419,'2026_08_22_000001_refactor_announcement_bars_to_image',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (420,'2026_08_24_000001_add_background_image_type_to_page_sections',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (421,'2026_08_24_160907_add_tab_index_to_page_block_products_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (422,'2026_08_24_135952_add_profile_fields_to_customers_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (423,'2026_08_24_140001_modify_address_type_and_device_token_platform_enums',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (424,'2026_08_25_000001_add_missing_columns_to_delivery_agents_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (425,'2026_08_27_000001_add_swatch_image_to_attribute_values',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (426,'2026_08_27_000002_add_badge_image_to_shipping_methods',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (427,'2026_08_27_164212_add_is_purchasable_to_gift_card_batches_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (428,'2026_08_28_000001_create_search_suggestions_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (429,'2026_08_30_000001_rename_cents_suffix_columns',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (430,'2026_08_30_000002_rename_all_remaining_cents_db_columns',32);
