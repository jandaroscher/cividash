CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "settings"(
  "id" integer primary key autoincrement not null,
  "group" varchar not null,
  "name" varchar not null,
  "locked" tinyint(1) not null default '0',
  "payload" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  "tenant_id" integer not null default '0'
);
CREATE TABLE IF NOT EXISTS "category_tile"(
  "category_id" integer not null,
  "tile_id" integer not null,
  foreign key("category_id") references "categories"("id") on delete cascade,
  foreign key("tile_id") references "tiles"("id") on delete cascade,
  primary key("category_id", "tile_id")
);
CREATE TABLE IF NOT EXISTS "tile_sdg_ziel"(
  "tile_id" integer not null,
  "sdg_ziel_id" integer not null,
  foreign key("tile_id") references "tiles"("id") on delete cascade,
  foreign key("sdg_ziel_id") references "sdg_ziele"("id") on delete cascade,
  primary key("tile_id", "sdg_ziel_id")
);
CREATE TABLE IF NOT EXISTS "handlungsdimension_handlungsfeld"(
  "handlungsdimension_id" integer not null,
  "handlungsfeld_id" integer not null,
  foreign key("handlungsdimension_id") references "handlungsdimensionen"("id") on delete cascade,
  foreign key("handlungsfeld_id") references "categories"("id") on delete cascade,
  primary key("handlungsdimension_id", "handlungsfeld_id")
);
CREATE TABLE IF NOT EXISTS "tenant_user"(
  "id" integer primary key autoincrement not null,
  "tenant_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("tenant_id") references "tenants"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "tenant_user_tenant_id_user_id_unique" on "tenant_user"(
  "tenant_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "default_tenant_id" integer,
  "locale" varchar not null default 'de',
  "avatar_path" varchar,
  "first_name" varchar,
  "last_name" varchar,
  "phone" varchar,
  "is_active" tinyint(1) not null default '1',
  "is_admin" tinyint(1) not null default '0',
  "keycloak_id" varchar,
  foreign key("default_tenant_id") references "tenants"("id") on delete set null
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "tiles"(
  "id" integer primary key autoincrement not null,
  "title" varchar not null,
  "description" text,
  "icon" varchar,
  "position" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "background_blocks" text,
  "last_synced_at" datetime,
  "source_hash" varchar,
  "handlungsdimension_id" integer,
  "tenant_id" integer,
  "slug" text,
  "is_public" tinyint(1) not null default '1',
  "meta_description" text,
  "meta_title" text,
  "meta_image" varchar,
  "hint" text,
  "external_source" varchar,
  "external_id" varchar,
  "time_granularity" varchar not null default 'year',
  foreign key("handlungsdimension_id") references handlungsdimensionen("id") on delete set null on update no action,
  foreign key("tenant_id") references "tenants"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "sdg_ziele"(
  "id" integer primary key autoincrement not null,
  "number" integer not null,
  "title" text not null,
  "position" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "icon" text,
  "tenant_id" integer,
  foreign key("tenant_id") references "tenants"("id") on delete cascade
);
CREATE UNIQUE INDEX "sdg_ziele_number_unique" on "sdg_ziele"("number");
CREATE TABLE IF NOT EXISTS "handlungsdimensionen"(
  "id" integer primary key autoincrement not null,
  "key" varchar not null,
  "title" text not null,
  "icon" varchar,
  "position" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "color" varchar,
  "tenant_id" integer,
  foreign key("tenant_id") references "tenants"("id") on delete cascade
);
CREATE UNIQUE INDEX "handlungsdimensionen_key_unique" on "handlungsdimensionen"(
  "key"
);
CREATE TABLE IF NOT EXISTS "navigations"(
  "id" integer primary key autoincrement not null,
  "navigation_items" text,
  "dropdown_enabled" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "tenant_id" integer,
  foreign key("tenant_id") references "tenants"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "footer_navigations"(
  "id" integer primary key autoincrement not null,
  "footer_navigation_items" text,
  "social_links" text,
  "layout_type" varchar not null default('single-row'),
  "columns" integer not null default('3'),
  "social_links_enabled" tinyint(1) not null default('1'),
  "copyright_text" text,
  "created_at" datetime,
  "updated_at" datetime,
  "tenant_id" integer,
  "sponsors" text,
  foreign key("tenant_id") references "tenants"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "metric_definitions"(
  "id" integer primary key autoincrement not null,
  "tile_id" integer not null,
  "metric_key" varchar not null,
  "label" text not null,
  "unit" text,
  "icon" varchar,
  "indicator_type" varchar not null default('small'),
  "created_at" datetime,
  "updated_at" datetime,
  "tenant_id" integer,
  "is_active" tinyint(1) not null default '1',
  "sort_order" integer not null default '0',
  "external_source" varchar,
  "external_id" varchar,
  "last_synced_at" datetime,
  "source_hash" varchar,
  foreign key("tile_id") references tiles("id") on delete cascade on update no action,
  foreign key("tenant_id") references "tenants"("id") on delete cascade
);
CREATE UNIQUE INDEX "metric_definitions_tile_id_metric_key_unique" on "metric_definitions"(
  "tile_id",
  "metric_key"
);
CREATE TABLE IF NOT EXISTS "background_pages"(
  "id" integer primary key autoincrement not null,
  "slug" varchar,
  "content" text,
  "position" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "tile_id" integer,
  "tenant_id" integer,
  foreign key("tile_id") references tiles("id") on delete cascade on update no action,
  foreign key("tenant_id") references "tenants"("id") on delete cascade
);
CREATE UNIQUE INDEX "background_pages_slug_unique" on "background_pages"(
  "slug"
);
CREATE TABLE IF NOT EXISTS "pages"(
  "id" integer primary key autoincrement not null,
  "title" varchar not null,
  "slug" varchar not null,
  "layout" varchar not null default('default'),
  "blocks" text not null,
  "parent_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "meta_description" text,
  "tenant_id" integer,
  "is_public" tinyint(1) not null default '1',
  "meta_title" text,
  "meta_image" varchar,
  foreign key("parent_id") references pages("id") on delete cascade on update cascade,
  foreign key("tenant_id") references "tenants"("id") on delete cascade
);
CREATE INDEX "pages_layout_index" on "pages"("layout");
CREATE INDEX "pages_title_index" on "pages"("title");
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" varchar not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "tenant_id" integer,
  "is_active" tinyint(1) not null default '1',
  foreign key("tenant_id") references "tenants"("id") on delete cascade
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE INDEX "pages_is_public_index" on "pages"("is_public");
CREATE INDEX "tiles_is_public_index" on "tiles"("is_public");
CREATE TABLE IF NOT EXISTS "category_groups"(
  "id" integer primary key autoincrement not null,
  "tenant_id" integer,
  "key" varchar not null,
  "title" text not null,
  "position" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "is_active" tinyint(1) not null default '1',
  "external_source" varchar,
  "external_id" varchar,
  "last_synced_at" datetime,
  "source_hash" varchar,
  foreign key("tenant_id") references "tenants"("id") on delete cascade
);
CREATE UNIQUE INDEX "category_groups_tenant_id_key_unique" on "category_groups"(
  "tenant_id",
  "key"
);
CREATE TABLE IF NOT EXISTS "categories"(
  "id" integer primary key autoincrement not null,
  "slug" varchar not null,
  "position" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "icon" varchar,
  "last_synced_at" datetime,
  "source_hash" varchar,
  "tenant_id" integer,
  "category_group_id" integer,
  "key" varchar,
  "color" varchar,
  "is_active" tinyint(1) not null default '1',
  "external_source" varchar,
  "external_id" varchar,
  foreign key("tenant_id") references tenants("id") on delete cascade on update no action,
  foreign key("category_group_id") references "category_groups"("id") on delete set null
);
CREATE INDEX "categories_category_group_id_index" on "categories"(
  "category_group_id"
);
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "tenant_id" integer,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "roles_team_foreign_key_index" on "roles"("tenant_id");
CREATE UNIQUE INDEX "roles_tenant_id_name_guard_name_unique" on "roles"(
  "tenant_id",
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  "tenant_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("tenant_id", "permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE INDEX "model_has_permissions_team_foreign_key_index" on "model_has_permissions"(
  "tenant_id"
);
CREATE TABLE IF NOT EXISTS "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  "tenant_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("tenant_id", "role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE INDEX "model_has_roles_team_foreign_key_index" on "model_has_roles"(
  "tenant_id"
);
CREATE TABLE IF NOT EXISTS "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);
CREATE UNIQUE INDEX "settings_group_name_tenant_id_unique" on "settings"(
  "group",
  "name",
  "tenant_id"
);
CREATE INDEX "tiles_external_source_external_id_index" on "tiles"(
  "external_source",
  "external_id"
);
CREATE INDEX "categories_external_source_external_id_index" on "categories"(
  "external_source",
  "external_id"
);
CREATE INDEX "category_groups_external_source_external_id_index" on "category_groups"(
  "external_source",
  "external_id"
);
CREATE INDEX "metric_definitions_external_source_external_id_index" on "metric_definitions"(
  "external_source",
  "external_id"
);
CREATE TABLE IF NOT EXISTS "metric_values"(
  "id" integer primary key autoincrement not null,
  "metric_definition_id" integer not null,
  "time_period_id" integer not null,
  "value" numeric,
  "created_at" datetime,
  "updated_at" datetime,
  "tenant_id" integer,
  "is_active" tinyint(1) not null default('1'),
  "sort_order" integer not null default('0'),
  foreign key("tenant_id") references tenants("id") on delete cascade on update no action,
  foreign key("metric_definition_id") references metric_definitions("id") on delete cascade on update no action,
  foreign key("time_period_id") references "time_periods"("id") on delete cascade on update no action
);
CREATE UNIQUE INDEX "metric_values_metric_definition_id_tile_year_id_unique" on "metric_values"(
  "metric_definition_id",
  "time_period_id"
);
CREATE UNIQUE INDEX "users_keycloak_id_unique" on "users"("keycloak_id");
CREATE TABLE IF NOT EXISTS "time_periods"(
  "id" integer primary key autoincrement not null,
  "tile_id" integer not null,
  "sort" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "tenant_id" integer,
  "granularity" varchar not null default('year'),
  "period_key" varchar not null,
  "label" varchar,
  foreign key("tenant_id") references tenants("id") on delete cascade on update no action,
  foreign key("tile_id") references tiles("id") on delete cascade on update no action
);
CREATE UNIQUE INDEX "time_periods_tile_id_period_key_unique" on "time_periods"(
  "tile_id",
  "period_key"
);
CREATE INDEX "time_periods_granularity_index" on "time_periods"("granularity");
CREATE TABLE IF NOT EXISTS "import_runs"(
  "id" integer primary key autoincrement not null,
  "tenant_id" integer not null,
  "user_id" integer,
  "mode" varchar check("mode" in('dry_run', 'commit')) not null,
  "format" varchar not null default 'json',
  "filename" varchar not null,
  "byte_size" integer not null default '0',
  "status" varchar check("status" in('success', 'failed')) not null,
  "diff_summary" text,
  "error_count" integer not null default '0',
  "warning_count" integer not null default '0',
  "duration_ms" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("tenant_id") references "tenants"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "import_runs_tenant_id_created_at_index" on "import_runs"(
  "tenant_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "themes"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "settings" text,
  "parent_theme_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("parent_theme_id") references "themes"("id") on delete set null
);
CREATE UNIQUE INDEX "themes_slug_unique" on "themes"("slug");
CREATE TABLE IF NOT EXISTS "tenants"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "domain" varchar,
  "frontend_base_url" varchar,
  "description" varchar,
  "theme_id" integer,
  foreign key("theme_id") references "themes"("id") on delete set null
);
CREATE UNIQUE INDEX "tenants_domain_unique" on "tenants"("domain");
CREATE UNIQUE INDEX "tenants_slug_unique" on "tenants"("slug");

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2022_12_14_083707_create_settings_table',1);
INSERT INTO migrations VALUES(5,'2025_01_15_120000_add_indicator_type_to_metrics_table',1);
INSERT INTO migrations VALUES(6,'2025_04_22_083703_create_categories_table',1);
INSERT INTO migrations VALUES(7,'2025_04_22_083712_create_tiles_table',1);
INSERT INTO migrations VALUES(8,'2025_04_22_083719_create_background_pages_table',1);
INSERT INTO migrations VALUES(9,'2025_04_22_084143_create_category_tile_table',1);
INSERT INTO migrations VALUES(10,'2025_04_22_084149_add_tile_id_to_background_pages_table',1);
INSERT INTO migrations VALUES(11,'2025_04_22_125659_create_tile_years_table',1);
INSERT INTO migrations VALUES(12,'2025_04_22_125748_create_metrics_table',1);
INSERT INTO migrations VALUES(13,'2025_04_22_132538_make_background_pages_slug_nullable',1);
INSERT INTO migrations VALUES(14,'2025_04_22_132710_make_background_pages_content_nullable',1);
INSERT INTO migrations VALUES(15,'2025_04_22_140439_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(18,'2025_04_25_100921_create_pages_table',1);
INSERT INTO migrations VALUES(19,'2025_04_25_100922_fix_slug_unique_constraint_on_pages_table',1);
INSERT INTO migrations VALUES(24,'2025_10_21_081431_add_icon_to_categories_table',1);
INSERT INTO migrations VALUES(25,'2025_11_25_102800_convert_pages_to_translatable',1);
INSERT INTO migrations VALUES(26,'2025_11_27_114957_add_background_blocks_to_tiles_table',1);
INSERT INTO migrations VALUES(27,'2025_11_28_120000_add_seeding_tracking_to_tiles_table',1);
INSERT INTO migrations VALUES(28,'2025_11_28_120001_add_seeding_tracking_to_categories_table',1);
INSERT INTO migrations VALUES(29,'2025_12_01_120452_create_handlungsdimensionen_table',1);
INSERT INTO migrations VALUES(30,'2025_12_01_120454_create_sdg_ziele_table',1);
INSERT INTO migrations VALUES(31,'2025_12_01_120456_create_tile_sdg_ziel_table',1);
INSERT INTO migrations VALUES(32,'2025_12_01_120458_create_handlungsdimension_handlungsfeld_table',1);
INSERT INTO migrations VALUES(33,'2025_12_01_120500_add_handlungsdimension_id_to_tiles_table',1);
INSERT INTO migrations VALUES(34,'2025_12_01_124512_modify_sdg_ziele_icon_to_translatable',1);
INSERT INTO migrations VALUES(35,'2025_12_01_142528_add_metric_key_to_metrics_table',1);
INSERT INTO migrations VALUES(36,'2025_12_02_120833_create_metric_definitions_table',1);
INSERT INTO migrations VALUES(37,'2025_12_02_120834_create_metric_values_table',1);
INSERT INTO migrations VALUES(38,'2025_12_02_120835_drop_metrics_table',1);
INSERT INTO migrations VALUES(39,'2025_12_02_154648_add_color_to_handlungsdimensionen_table',1);
INSERT INTO migrations VALUES(52,'2025_12_08_081906_convert_header_footer_to_translatable',1);
INSERT INTO migrations VALUES(53,'2025_12_08_085223_create_footer_navigations_table',1);
INSERT INTO migrations VALUES(54,'2025_12_08_085223_create_navigations_table',1);
INSERT INTO migrations VALUES(55,'2025_12_08_085223_migrate_header_footer_settings_to_models',1);
INSERT INTO migrations VALUES(56,'2025_12_10_000001_create_tenants_table',1);
INSERT INTO migrations VALUES(57,'2025_12_10_000002_create_tenant_user_table',1);
INSERT INTO migrations VALUES(58,'2025_12_10_000003_add_tenant_columns_to_domain_tables',1);
INSERT INTO migrations VALUES(59,'2026_01_14_120446_add_admin_api_enabled_to_users_table',1);
INSERT INTO migrations VALUES(60,'2026_01_14_121552_add_domain_columns_to_tenants',1);
INSERT INTO migrations VALUES(61,'2026_01_14_121552_add_tenant_id_to_personal_access_tokens',1);
INSERT INTO migrations VALUES(62,'2026_01_14_152543_drop_theme_config_from_tenants',1);
INSERT INTO migrations VALUES(63,'2026_01_19_090000_add_visibility_and_seo_fields_to_pages_table',1);
INSERT INTO migrations VALUES(64,'2026_01_19_120000_add_visibility_and_seo_fields_to_tiles_table',1);
INSERT INTO migrations VALUES(70,'2026_01_19_130000_backfill_tile_slugs',1);
INSERT INTO migrations VALUES(71,'2026_01_20_120001_backfill_block_active_flags',1);
INSERT INTO migrations VALUES(72,'2026_01_20_120002_add_is_active_to_metric_definitions_table',1);
INSERT INTO migrations VALUES(73,'2026_01_20_120003_add_is_active_to_metric_values_table',1);
INSERT INTO migrations VALUES(74,'2026_01_20_120100_create_category_groups_table',1);
INSERT INTO migrations VALUES(75,'2026_01_20_120101_add_group_and_color_to_categories_table',1);
INSERT INTO migrations VALUES(76,'2026_01_20_120200_backfill_dynamic_category_groups',1);
INSERT INTO migrations VALUES(77,'2026_01_21_150357_add_is_active_to_categories_tables',1);
INSERT INTO migrations VALUES(78,'2026_01_27_103758_create_permission_tables',1);
INSERT INTO migrations VALUES(79,'2026_01_27_130717_add_locale_to_users_table',1);
INSERT INTO migrations VALUES(80,'2026_01_27_150427_add_avatar_path_to_users_table',1);
INSERT INTO migrations VALUES(82,'2026_02_04_151700_backfill_navigation_active_flags',1);
INSERT INTO migrations VALUES(83,'2026_02_06_120000_fix_navigation_active_flags_corruption',1);
INSERT INTO migrations VALUES(84,'2026_02_16_000000_add_tenant_id_to_settings_table',1);
INSERT INTO migrations VALUES(85,'2026_02_18_000000_add_description_to_tenants',1);
INSERT INTO migrations VALUES(86,'2026_02_19_100000_simplify_user_management',1);
INSERT INTO migrations VALUES(87,'2026_02_19_100002_simplify_roles',1);
INSERT INTO migrations VALUES(88,'2026_02_19_200000_add_is_admin_to_users_table',1);
INSERT INTO migrations VALUES(89,'2026_02_24_000000_drop_admin_api_enabled_from_users_table',1);
INSERT INTO migrations VALUES(91,'2026_03_02_000000_drop_show_language_switcher_from_navigations_table',1);
INSERT INTO migrations VALUES(92,'2026_03_02_100000_add_sort_order_to_metric_definitions_table',1);
INSERT INTO migrations VALUES(93,'2026_03_02_100001_add_sort_order_to_metric_values_table',1);
INSERT INTO migrations VALUES(94,'2026_03_03_000000_add_is_active_to_personal_access_tokens',1);
INSERT INTO migrations VALUES(95,'2026_03_04_135703_backfill_category_keys_from_slug',1);
INSERT INTO migrations VALUES(97,'2026_03_06_100000_migrate_color_source_to_branding_settings',1);
INSERT INTO migrations VALUES(98,'2026_03_06_120000_remove_filterable_and_selection_type_from_category_groups',1);
INSERT INTO migrations VALUES(99,'2026_03_09_100000_convert_tile_background_blocks_to_translatable',1);
INSERT INTO migrations VALUES(100,'2026_03_10_100000_add_sort_order_and_nav_placement_to_pages_table',1);
INSERT INTO migrations VALUES(101,'2026_03_10_120000_remove_heading_en_from_page_blocks',1);
INSERT INTO migrations VALUES(102,'2026_03_10_140000_remove_sort_order_and_nav_placement_from_pages_table',1);
INSERT INTO migrations VALUES(103,'2026_03_25_100000_add_sponsors_to_footer_navigations_table',1);
INSERT INTO migrations VALUES(104,'2026_03_25_150000_add_hint_to_tiles_table',1);
INSERT INTO migrations VALUES(107,'2026_04_10_111227_add_external_tracking_fields_to_models',1);
INSERT INTO migrations VALUES(109,'2026_04_10_142053_make_metric_values_value_nullable',1);
INSERT INTO migrations VALUES(110,'2026_04_11_000000_add_keycloak_id_to_users_table',1);
INSERT INTO migrations VALUES(111,'2026_04_15_100000_rename_tile_years_to_time_periods',1);
INSERT INTO migrations VALUES(112,'2026_04_20_132107_create_import_runs_table',1);
INSERT INTO migrations VALUES(114,'2026_08_20_100000_create_themes_table',1);
INSERT INTO migrations VALUES(116,'2026_08_20_133940_add_theme_id_to_tenants',1);
