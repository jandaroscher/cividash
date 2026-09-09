/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `background_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tile_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `background_pages_slug_unique` (`slug`),
  KEY `background_pages_tile_id_foreign` (`tile_id`),
  KEY `background_pages_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `background_pages_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `background_pages_tile_id_foreign` FOREIGN KEY (`tile_id`) REFERENCES `tiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `external_source` varchar(255) DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `category_group_id` bigint(20) unsigned DEFAULT NULL,
  `key` varchar(255) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `icon` varchar(255) DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `source_hash` varchar(64) DEFAULT NULL COMMENT 'SHA256 hash of the source JSON data for this category',
  PRIMARY KEY (`id`),
  KEY `categories_tenant_id_foreign` (`tenant_id`),
  KEY `categories_category_group_id_index` (`category_group_id`),
  KEY `categories_external_source_external_id_index` (`external_source`,`external_id`),
  CONSTRAINT `categories_category_group_id_foreign` FOREIGN KEY (`category_group_id`) REFERENCES `category_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `categories_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `external_source` varchar(255) DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `key` varchar(255) NOT NULL,
  `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`title`)),
  `position` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `source_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_groups_tenant_id_key_unique` (`tenant_id`,`key`),
  KEY `category_groups_external_source_external_id_index` (`external_source`,`external_id`),
  CONSTRAINT `category_groups_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_tile` (
  `category_id` bigint(20) unsigned NOT NULL,
  `tile_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`category_id`,`tile_id`),
  KEY `category_tile_tile_id_foreign` (`tile_id`),
  CONSTRAINT `category_tile_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `category_tile_tile_id_foreign` FOREIGN KEY (`tile_id`) REFERENCES `tiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `footer_navigations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `footer_navigation_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`footer_navigation_items`)),
  `social_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_links`)),
  `layout_type` varchar(255) NOT NULL DEFAULT 'single-row',
  `columns` int(11) NOT NULL DEFAULT 3,
  `social_links_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `copyright_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`copyright_text`)),
  `sponsors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sponsors`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `footer_navigations_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `footer_navigations_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `handlungsdimension_handlungsfeld` (
  `handlungsdimension_id` bigint(20) unsigned NOT NULL,
  `handlungsfeld_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`handlungsdimension_id`,`handlungsfeld_id`),
  KEY `handlungsdimension_handlungsfeld_handlungsfeld_id_foreign` (`handlungsfeld_id`),
  CONSTRAINT `handlungsdimension_handlungsfeld_handlungsdimension_id_foreign` FOREIGN KEY (`handlungsdimension_id`) REFERENCES `handlungsdimensionen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `handlungsdimension_handlungsfeld_handlungsfeld_id_foreign` FOREIGN KEY (`handlungsfeld_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `handlungsdimensionen` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `key` varchar(255) NOT NULL,
  `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`title`)),
  `icon` varchar(255) DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `handlungsdimensionen_key_unique` (`key`),
  KEY `handlungsdimensionen_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `handlungsdimensionen_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `mode` enum('dry_run','commit') NOT NULL,
  `format` varchar(16) NOT NULL DEFAULT 'json',
  `filename` varchar(255) NOT NULL,
  `byte_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `status` enum('success','failed') NOT NULL,
  `diff_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`diff_summary`)),
  `error_count` int(10) unsigned NOT NULL DEFAULT 0,
  `warning_count` int(10) unsigned NOT NULL DEFAULT 0,
  `duration_ms` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_runs_user_id_foreign` (`user_id`),
  KEY `import_runs_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  CONSTRAINT `import_runs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `import_runs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `metric_definitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `external_source` varchar(255) DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `tile_id` bigint(20) unsigned NOT NULL,
  `metric_key` varchar(255) NOT NULL,
  `label` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`label`)),
  `unit` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`unit`)),
  `icon` varchar(255) DEFAULT NULL,
  `indicator_type` varchar(255) NOT NULL DEFAULT 'small',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `source_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `metric_definitions_tile_id_metric_key_unique` (`tile_id`,`metric_key`),
  KEY `metric_definitions_tenant_id_foreign` (`tenant_id`),
  KEY `metric_definitions_external_source_external_id_index` (`external_source`,`external_id`),
  CONSTRAINT `metric_definitions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `metric_definitions_tile_id_foreign` FOREIGN KEY (`tile_id`) REFERENCES `tiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `metric_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `metric_definition_id` bigint(20) unsigned NOT NULL,
  `time_period_id` bigint(20) unsigned NOT NULL,
  `value` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `metric_values_metric_definition_id_time_period_id_unique` (`metric_definition_id`,`time_period_id`),
  KEY `metric_values_tenant_id_foreign` (`tenant_id`),
  KEY `metric_values_time_period_id_foreign` (`time_period_id`),
  CONSTRAINT `metric_values_metric_definition_id_foreign` FOREIGN KEY (`metric_definition_id`) REFERENCES `metric_definitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `metric_values_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `metric_values_time_period_id_foreign` FOREIGN KEY (`time_period_id`) REFERENCES `time_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`tenant_id`,`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  KEY `model_has_permissions_permission_id_foreign` (`permission_id`),
  KEY `model_has_permissions_team_foreign_key_index` (`tenant_id`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`tenant_id`,`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  KEY `model_has_roles_role_id_foreign` (`role_id`),
  KEY `model_has_roles_team_foreign_key_index` (`tenant_id`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `navigations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `navigation_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`navigation_items`)),
  `dropdown_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `navigations_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `navigations_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`title`)),
  `slug` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`slug`)),
  `layout` varchar(255) NOT NULL DEFAULT 'default',
  `blocks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`blocks`)),
  `meta_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_description`)),
  `meta_title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_title`)),
  `meta_image` varchar(255) DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pages_parent_id_foreign` (`parent_id`),
  KEY `pages_title_index` (`title`(768)),
  KEY `pages_layout_index` (`layout`),
  KEY `pages_tenant_id_foreign` (`tenant_id`),
  KEY `pages_is_public_index` (`is_public`),
  CONSTRAINT `pages_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pages_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `personal_access_tokens_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_tenant_id_name_guard_name_unique` (`tenant_id`,`name`,`guard_name`),
  KEY `roles_team_foreign_key_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sdg_ziele` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `number` int(11) NOT NULL,
  `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`title`)),
  `icon` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`icon`)),
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sdg_ziele_number_unique` (`number`),
  KEY `sdg_ziele_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `sdg_ziele_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `group` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_group_name_tenant_id_unique` (`group`,`name`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_user_tenant_id_user_id_unique` (`tenant_id`,`user_id`),
  KEY `tenant_user_user_id_foreign` (`user_id`),
  CONSTRAINT `tenant_user_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `frontend_base_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `theme_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`),
  UNIQUE KEY `tenants_domain_unique` (`domain`),
  KEY `tenants_theme_id_foreign` (`theme_id`),
  CONSTRAINT `tenants_theme_id_foreign` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `themes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `parent_theme_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `themes_slug_unique` (`slug`),
  KEY `themes_parent_theme_id_foreign` (`parent_theme_id`),
  CONSTRAINT `themes_parent_theme_id_foreign` FOREIGN KEY (`parent_theme_id`) REFERENCES `themes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tile_sdg_ziel` (
  `tile_id` bigint(20) unsigned NOT NULL,
  `sdg_ziel_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`tile_id`,`sdg_ziel_id`),
  KEY `tile_sdg_ziel_sdg_ziel_id_foreign` (`sdg_ziel_id`),
  CONSTRAINT `tile_sdg_ziel_sdg_ziel_id_foreign` FOREIGN KEY (`sdg_ziel_id`) REFERENCES `sdg_ziele` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tile_sdg_ziel_tile_id_foreign` FOREIGN KEY (`tile_id`) REFERENCES `tiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `external_source` varchar(255) DEFAULT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`slug`)),
  `description` text DEFAULT NULL,
  `hint` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hint`)),
  `meta_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_description`)),
  `meta_title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_title`)),
  `meta_image` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `time_granularity` varchar(10) NOT NULL DEFAULT 'year',
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `handlungsdimension_id` bigint(20) unsigned DEFAULT NULL,
  `background_blocks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`background_blocks`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `source_hash` varchar(64) DEFAULT NULL COMMENT 'SHA256 hash of the source JSON data for this tile',
  PRIMARY KEY (`id`),
  KEY `tiles_handlungsdimension_id_foreign` (`handlungsdimension_id`),
  KEY `tiles_tenant_id_foreign` (`tenant_id`),
  KEY `tiles_is_public_index` (`is_public`),
  KEY `tiles_external_source_external_id_index` (`external_source`,`external_id`),
  CONSTRAINT `tiles_handlungsdimension_id_foreign` FOREIGN KEY (`handlungsdimension_id`) REFERENCES `handlungsdimensionen` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tiles_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `time_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `tile_id` bigint(20) unsigned NOT NULL,
  `granularity` varchar(10) NOT NULL DEFAULT 'year',
  `period_key` varchar(20) NOT NULL,
  `label` varchar(30) DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `time_periods_tile_id_period_key_unique` (`tile_id`,`period_key`),
  KEY `tile_years_tenant_id_foreign` (`tenant_id`),
  KEY `time_periods_granularity_index` (`granularity`),
  CONSTRAINT `tile_years_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tile_years_tile_id_foreign` FOREIGN KEY (`tile_id`) REFERENCES `tiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `keycloak_id` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `locale` varchar(5) NOT NULL DEFAULT 'de',
  `avatar_path` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `default_tenant_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_keycloak_id_unique` (`keycloak_id`),
  KEY `users_default_tenant_id_foreign` (`default_tenant_id`),
  CONSTRAINT `users_default_tenant_id_foreign` FOREIGN KEY (`default_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` VALUES (4,'2022_12_14_083707_create_settings_table',1);
INSERT INTO `migrations` VALUES (5,'2025_01_15_120000_add_indicator_type_to_metrics_table',1);
INSERT INTO `migrations` VALUES (6,'2025_04_22_083703_create_categories_table',1);
INSERT INTO `migrations` VALUES (7,'2025_04_22_083712_create_tiles_table',1);
INSERT INTO `migrations` VALUES (8,'2025_04_22_083719_create_background_pages_table',1);
INSERT INTO `migrations` VALUES (9,'2025_04_22_084143_create_category_tile_table',1);
INSERT INTO `migrations` VALUES (10,'2025_04_22_084149_add_tile_id_to_background_pages_table',1);
INSERT INTO `migrations` VALUES (11,'2025_04_22_125659_create_tile_years_table',1);
INSERT INTO `migrations` VALUES (12,'2025_04_22_125748_create_metrics_table',1);
INSERT INTO `migrations` VALUES (13,'2025_04_22_132538_make_background_pages_slug_nullable',1);
INSERT INTO `migrations` VALUES (14,'2025_04_22_132710_make_background_pages_content_nullable',1);
INSERT INTO `migrations` VALUES (15,'2025_04_22_140439_create_personal_access_tokens_table',1);
INSERT INTO `migrations` VALUES (18,'2025_04_25_100921_create_pages_table',1);
INSERT INTO `migrations` VALUES (19,'2025_04_25_100922_fix_slug_unique_constraint_on_pages_table',1);
INSERT INTO `migrations` VALUES (24,'2025_10_21_081431_add_icon_to_categories_table',1);
INSERT INTO `migrations` VALUES (25,'2025_11_25_102800_convert_pages_to_translatable',1);
INSERT INTO `migrations` VALUES (26,'2025_11_27_114957_add_background_blocks_to_tiles_table',1);
INSERT INTO `migrations` VALUES (27,'2025_11_28_120000_add_seeding_tracking_to_tiles_table',1);
INSERT INTO `migrations` VALUES (28,'2025_11_28_120001_add_seeding_tracking_to_categories_table',1);
INSERT INTO `migrations` VALUES (29,'2025_12_01_120452_create_handlungsdimensionen_table',1);
INSERT INTO `migrations` VALUES (30,'2025_12_01_120454_create_sdg_ziele_table',1);
INSERT INTO `migrations` VALUES (31,'2025_12_01_120456_create_tile_sdg_ziel_table',1);
INSERT INTO `migrations` VALUES (32,'2025_12_01_120458_create_handlungsdimension_handlungsfeld_table',1);
INSERT INTO `migrations` VALUES (33,'2025_12_01_120500_add_handlungsdimension_id_to_tiles_table',1);
INSERT INTO `migrations` VALUES (34,'2025_12_01_124512_modify_sdg_ziele_icon_to_translatable',1);
INSERT INTO `migrations` VALUES (35,'2025_12_01_142528_add_metric_key_to_metrics_table',1);
INSERT INTO `migrations` VALUES (36,'2025_12_02_120833_create_metric_definitions_table',1);
INSERT INTO `migrations` VALUES (37,'2025_12_02_120834_create_metric_values_table',1);
INSERT INTO `migrations` VALUES (38,'2025_12_02_120835_drop_metrics_table',1);
INSERT INTO `migrations` VALUES (39,'2025_12_02_154648_add_color_to_handlungsdimensionen_table',1);
INSERT INTO `migrations` VALUES (52,'2025_12_08_081906_convert_header_footer_to_translatable',1);
INSERT INTO `migrations` VALUES (53,'2025_12_08_085223_create_footer_navigations_table',1);
INSERT INTO `migrations` VALUES (54,'2025_12_08_085223_create_navigations_table',1);
INSERT INTO `migrations` VALUES (55,'2025_12_08_085223_migrate_header_footer_settings_to_models',1);
INSERT INTO `migrations` VALUES (56,'2025_12_10_000001_create_tenants_table',1);
INSERT INTO `migrations` VALUES (57,'2025_12_10_000002_create_tenant_user_table',1);
INSERT INTO `migrations` VALUES (58,'2025_12_10_000003_add_tenant_columns_to_domain_tables',1);
INSERT INTO `migrations` VALUES (59,'2026_01_14_120446_add_admin_api_enabled_to_users_table',1);
INSERT INTO `migrations` VALUES (60,'2026_01_14_121552_add_domain_columns_to_tenants',1);
INSERT INTO `migrations` VALUES (61,'2026_01_14_121552_add_tenant_id_to_personal_access_tokens',1);
INSERT INTO `migrations` VALUES (62,'2026_01_14_152543_drop_theme_config_from_tenants',1);
INSERT INTO `migrations` VALUES (63,'2026_01_19_090000_add_visibility_and_seo_fields_to_pages_table',1);
INSERT INTO `migrations` VALUES (64,'2026_01_19_120000_add_visibility_and_seo_fields_to_tiles_table',1);
INSERT INTO `migrations` VALUES (70,'2026_01_19_130000_backfill_tile_slugs',1);
INSERT INTO `migrations` VALUES (71,'2026_01_20_120001_backfill_block_active_flags',1);
INSERT INTO `migrations` VALUES (72,'2026_01_20_120002_add_is_active_to_metric_definitions_table',1);
INSERT INTO `migrations` VALUES (73,'2026_01_20_120003_add_is_active_to_metric_values_table',1);
INSERT INTO `migrations` VALUES (74,'2026_01_20_120100_create_category_groups_table',1);
INSERT INTO `migrations` VALUES (75,'2026_01_20_120101_add_group_and_color_to_categories_table',1);
INSERT INTO `migrations` VALUES (76,'2026_01_20_120200_backfill_dynamic_category_groups',1);
INSERT INTO `migrations` VALUES (77,'2026_01_21_150357_add_is_active_to_categories_tables',1);
INSERT INTO `migrations` VALUES (78,'2026_01_27_103758_create_permission_tables',1);
INSERT INTO `migrations` VALUES (79,'2026_01_27_130717_add_locale_to_users_table',1);
INSERT INTO `migrations` VALUES (80,'2026_01_27_150427_add_avatar_path_to_users_table',1);
INSERT INTO `migrations` VALUES (82,'2026_02_04_151700_backfill_navigation_active_flags',1);
INSERT INTO `migrations` VALUES (83,'2026_02_06_120000_fix_navigation_active_flags_corruption',1);
INSERT INTO `migrations` VALUES (84,'2026_02_16_000000_add_tenant_id_to_settings_table',1);
INSERT INTO `migrations` VALUES (85,'2026_02_18_000000_add_description_to_tenants',1);
INSERT INTO `migrations` VALUES (86,'2026_02_19_100000_simplify_user_management',1);
INSERT INTO `migrations` VALUES (87,'2026_02_19_100002_simplify_roles',1);
INSERT INTO `migrations` VALUES (88,'2026_02_19_200000_add_is_admin_to_users_table',1);
INSERT INTO `migrations` VALUES (89,'2026_02_24_000000_drop_admin_api_enabled_from_users_table',1);
INSERT INTO `migrations` VALUES (91,'2026_03_02_000000_drop_show_language_switcher_from_navigations_table',1);
INSERT INTO `migrations` VALUES (92,'2026_03_02_100000_add_sort_order_to_metric_definitions_table',1);
INSERT INTO `migrations` VALUES (93,'2026_03_02_100001_add_sort_order_to_metric_values_table',1);
INSERT INTO `migrations` VALUES (94,'2026_03_03_000000_add_is_active_to_personal_access_tokens',1);
INSERT INTO `migrations` VALUES (95,'2026_03_04_135703_backfill_category_keys_from_slug',1);
INSERT INTO `migrations` VALUES (97,'2026_03_06_100000_migrate_color_source_to_branding_settings',1);
INSERT INTO `migrations` VALUES (98,'2026_03_06_120000_remove_filterable_and_selection_type_from_category_groups',1);
INSERT INTO `migrations` VALUES (99,'2026_03_09_100000_convert_tile_background_blocks_to_translatable',1);
INSERT INTO `migrations` VALUES (100,'2026_03_10_100000_add_sort_order_and_nav_placement_to_pages_table',1);
INSERT INTO `migrations` VALUES (101,'2026_03_10_120000_remove_heading_en_from_page_blocks',1);
INSERT INTO `migrations` VALUES (102,'2026_03_10_140000_remove_sort_order_and_nav_placement_from_pages_table',1);
INSERT INTO `migrations` VALUES (103,'2026_03_25_100000_add_sponsors_to_footer_navigations_table',1);
INSERT INTO `migrations` VALUES (104,'2026_03_25_150000_add_hint_to_tiles_table',1);
INSERT INTO `migrations` VALUES (107,'2026_04_10_111227_add_external_tracking_fields_to_models',1);
INSERT INTO `migrations` VALUES (109,'2026_04_10_142053_make_metric_values_value_nullable',1);
INSERT INTO `migrations` VALUES (110,'2026_04_11_000000_add_keycloak_id_to_users_table',1);
INSERT INTO `migrations` VALUES (111,'2026_04_15_100000_rename_tile_years_to_time_periods',1);
INSERT INTO `migrations` VALUES (112,'2026_04_20_132107_create_import_runs_table',1);
INSERT INTO `migrations` VALUES (114,'2026_08_20_100000_create_themes_table',1);
INSERT INTO `migrations` VALUES (116,'2026_08_20_133940_add_theme_id_to_tenants',1);
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
