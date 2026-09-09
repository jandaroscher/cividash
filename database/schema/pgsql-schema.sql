--
-- PostgreSQL database dump
--

\restrict JL1NqkZabgrmPxnRZcEqAy32xFrm5FELRkvnpkGno5fhaSmeGodXVQ82Q7ot3tf

-- Dumped from database version 16.14 (Debian 16.14-1.pgdg13+1)
-- Dumped by pg_dump version 16.14 (Debian 16.14-1.pgdg12+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: background_pages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.background_pages (
    id bigint NOT NULL,
    slug character varying(255),
    content text,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tile_id bigint,
    tenant_id bigint
);


--
-- Name: background_pages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.background_pages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: background_pages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.background_pages_id_seq OWNED BY public.background_pages.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categories (
    id bigint NOT NULL,
    slug character varying(255) NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    icon character varying(255),
    last_synced_at timestamp(0) without time zone,
    source_hash character varying(64),
    tenant_id bigint,
    category_group_id bigint,
    key character varying(255),
    color character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    external_source character varying(255),
    external_id character varying(255)
);


--
-- Name: COLUMN categories.source_hash; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.categories.source_hash IS 'SHA256 hash of the source JSON data for this category';


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- Name: category_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.category_groups (
    id bigint NOT NULL,
    tenant_id bigint,
    key character varying(255) NOT NULL,
    title json NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    external_source character varying(255),
    external_id character varying(255),
    last_synced_at timestamp(0) without time zone,
    source_hash character varying(255)
);


--
-- Name: category_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.category_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: category_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.category_groups_id_seq OWNED BY public.category_groups.id;


--
-- Name: category_tile; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.category_tile (
    category_id bigint NOT NULL,
    tile_id bigint NOT NULL
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: footer_navigations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.footer_navigations (
    id bigint NOT NULL,
    footer_navigation_items json,
    social_links json,
    layout_type character varying(255) DEFAULT 'single-row'::character varying NOT NULL,
    columns integer DEFAULT 3 NOT NULL,
    social_links_enabled boolean DEFAULT true NOT NULL,
    copyright_text json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tenant_id bigint,
    sponsors json
);


--
-- Name: footer_navigations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.footer_navigations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: footer_navigations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.footer_navigations_id_seq OWNED BY public.footer_navigations.id;


--
-- Name: handlungsdimension_handlungsfeld; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.handlungsdimension_handlungsfeld (
    handlungsdimension_id bigint NOT NULL,
    handlungsfeld_id bigint NOT NULL
);


--
-- Name: handlungsdimensionen; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.handlungsdimensionen (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    title json NOT NULL,
    icon character varying(255),
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    color character varying(7),
    tenant_id bigint
);


--
-- Name: handlungsdimensionen_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.handlungsdimensionen_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: handlungsdimensionen_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.handlungsdimensionen_id_seq OWNED BY public.handlungsdimensionen.id;


--
-- Name: import_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_runs (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    user_id bigint,
    mode character varying(255) NOT NULL,
    format character varying(16) DEFAULT 'json'::character varying NOT NULL,
    filename character varying(255) NOT NULL,
    byte_size bigint DEFAULT '0'::bigint NOT NULL,
    status character varying(255) NOT NULL,
    diff_summary json,
    error_count integer DEFAULT 0 NOT NULL,
    warning_count integer DEFAULT 0 NOT NULL,
    duration_ms integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT import_runs_mode_check CHECK (((mode)::text = ANY ((ARRAY['dry_run'::character varying, 'commit'::character varying])::text[]))),
    CONSTRAINT import_runs_status_check CHECK (((status)::text = ANY ((ARRAY['success'::character varying, 'failed'::character varying])::text[])))
);


--
-- Name: import_runs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.import_runs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: import_runs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.import_runs_id_seq OWNED BY public.import_runs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: metric_definitions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.metric_definitions (
    id bigint NOT NULL,
    tile_id bigint NOT NULL,
    metric_key character varying(255) NOT NULL,
    label json NOT NULL,
    unit json,
    icon character varying(255),
    indicator_type character varying(255) DEFAULT 'small'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tenant_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    external_source character varying(255),
    external_id character varying(255),
    last_synced_at timestamp(0) without time zone,
    source_hash character varying(255)
);


--
-- Name: metric_definitions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.metric_definitions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: metric_definitions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.metric_definitions_id_seq OWNED BY public.metric_definitions.id;


--
-- Name: metric_values; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.metric_values (
    id bigint NOT NULL,
    metric_definition_id bigint NOT NULL,
    time_period_id bigint NOT NULL,
    value numeric(15,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tenant_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL
);


--
-- Name: metric_values_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.metric_values_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: metric_values_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.metric_values_id_seq OWNED BY public.metric_values.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL,
    tenant_id bigint NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL,
    tenant_id bigint NOT NULL
);


--
-- Name: navigations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.navigations (
    id bigint NOT NULL,
    navigation_items json,
    dropdown_enabled boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tenant_id bigint
);


--
-- Name: navigations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.navigations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: navigations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.navigations_id_seq OWNED BY public.navigations.id;


--
-- Name: pages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pages (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    layout character varying(255) DEFAULT 'default'::character varying NOT NULL,
    blocks json NOT NULL,
    parent_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    meta_description json,
    tenant_id bigint,
    is_public boolean DEFAULT true NOT NULL,
    meta_title json,
    meta_image character varying(255)
);


--
-- Name: pages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pages_id_seq OWNED BY public.pages.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tenant_id bigint,
    is_active boolean DEFAULT true NOT NULL
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    tenant_id bigint,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sdg_ziele; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sdg_ziele (
    id bigint NOT NULL,
    number integer NOT NULL,
    title json NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    icon json,
    tenant_id bigint
);


--
-- Name: sdg_ziele_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sdg_ziele_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sdg_ziele_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sdg_ziele_id_seq OWNED BY public.sdg_ziele.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.settings (
    id bigint NOT NULL,
    "group" character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    locked boolean DEFAULT false NOT NULL,
    payload json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tenant_id bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.settings_id_seq OWNED BY public.settings.id;


--
-- Name: tenant_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tenant_user (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tenant_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tenant_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tenant_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tenant_user_id_seq OWNED BY public.tenant_user.id;


--
-- Name: tenants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tenants (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    domain character varying(255),
    frontend_base_url character varying(255),
    description character varying(255),
    theme_id bigint
);


--
-- Name: tenants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tenants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tenants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tenants_id_seq OWNED BY public.tenants.id;


--
-- Name: themes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.themes (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    settings json,
    parent_theme_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: themes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.themes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: themes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.themes_id_seq OWNED BY public.themes.id;


--
-- Name: tile_sdg_ziel; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tile_sdg_ziel (
    tile_id bigint NOT NULL,
    sdg_ziel_id bigint NOT NULL
);


--
-- Name: time_periods; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.time_periods (
    id bigint NOT NULL,
    tile_id bigint NOT NULL,
    sort integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tenant_id bigint,
    granularity character varying(10) DEFAULT 'year'::character varying NOT NULL,
    period_key character varying(20) NOT NULL,
    label character varying(30)
);


--
-- Name: tile_years_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tile_years_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tile_years_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tile_years_id_seq OWNED BY public.time_periods.id;


--
-- Name: tiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tiles (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    icon character varying(255),
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    background_blocks json,
    last_synced_at timestamp(0) without time zone,
    source_hash character varying(64),
    handlungsdimension_id bigint,
    tenant_id bigint,
    slug json,
    is_public boolean DEFAULT true NOT NULL,
    meta_description json,
    meta_title json,
    meta_image character varying(255),
    hint json,
    external_source character varying(255),
    external_id character varying(255),
    time_granularity character varying(10) DEFAULT 'year'::character varying NOT NULL
);


--
-- Name: COLUMN tiles.source_hash; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tiles.source_hash IS 'SHA256 hash of the source JSON data for this tile';


--
-- Name: tiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tiles_id_seq OWNED BY public.tiles.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    default_tenant_id bigint,
    locale character varying(5) DEFAULT 'de'::character varying NOT NULL,
    avatar_path character varying(255),
    first_name character varying(255),
    last_name character varying(255),
    phone character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    is_admin boolean DEFAULT false NOT NULL,
    keycloak_id character varying(255)
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: background_pages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.background_pages ALTER COLUMN id SET DEFAULT nextval('public.background_pages_id_seq'::regclass);


--
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: category_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_groups ALTER COLUMN id SET DEFAULT nextval('public.category_groups_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: footer_navigations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.footer_navigations ALTER COLUMN id SET DEFAULT nextval('public.footer_navigations_id_seq'::regclass);


--
-- Name: handlungsdimensionen id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.handlungsdimensionen ALTER COLUMN id SET DEFAULT nextval('public.handlungsdimensionen_id_seq'::regclass);


--
-- Name: import_runs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_runs ALTER COLUMN id SET DEFAULT nextval('public.import_runs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: metric_definitions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_definitions ALTER COLUMN id SET DEFAULT nextval('public.metric_definitions_id_seq'::regclass);


--
-- Name: metric_values id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_values ALTER COLUMN id SET DEFAULT nextval('public.metric_values_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: navigations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.navigations ALTER COLUMN id SET DEFAULT nextval('public.navigations_id_seq'::regclass);


--
-- Name: pages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages ALTER COLUMN id SET DEFAULT nextval('public.pages_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: sdg_ziele id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sdg_ziele ALTER COLUMN id SET DEFAULT nextval('public.sdg_ziele_id_seq'::regclass);


--
-- Name: settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.settings ALTER COLUMN id SET DEFAULT nextval('public.settings_id_seq'::regclass);


--
-- Name: tenant_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_user ALTER COLUMN id SET DEFAULT nextval('public.tenant_user_id_seq'::regclass);


--
-- Name: tenants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants ALTER COLUMN id SET DEFAULT nextval('public.tenants_id_seq'::regclass);


--
-- Name: themes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.themes ALTER COLUMN id SET DEFAULT nextval('public.themes_id_seq'::regclass);


--
-- Name: tiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiles ALTER COLUMN id SET DEFAULT nextval('public.tiles_id_seq'::regclass);


--
-- Name: time_periods id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.time_periods ALTER COLUMN id SET DEFAULT nextval('public.tile_years_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: background_pages background_pages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.background_pages
    ADD CONSTRAINT background_pages_pkey PRIMARY KEY (id);


--
-- Name: background_pages background_pages_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.background_pages
    ADD CONSTRAINT background_pages_slug_unique UNIQUE (slug);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: category_groups category_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_groups
    ADD CONSTRAINT category_groups_pkey PRIMARY KEY (id);


--
-- Name: category_groups category_groups_tenant_id_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_groups
    ADD CONSTRAINT category_groups_tenant_id_key_unique UNIQUE (tenant_id, key);


--
-- Name: category_tile category_tile_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_tile
    ADD CONSTRAINT category_tile_pkey PRIMARY KEY (category_id, tile_id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: footer_navigations footer_navigations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.footer_navigations
    ADD CONSTRAINT footer_navigations_pkey PRIMARY KEY (id);


--
-- Name: handlungsdimension_handlungsfeld handlungsdimension_handlungsfeld_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.handlungsdimension_handlungsfeld
    ADD CONSTRAINT handlungsdimension_handlungsfeld_pkey PRIMARY KEY (handlungsdimension_id, handlungsfeld_id);


--
-- Name: handlungsdimensionen handlungsdimensionen_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.handlungsdimensionen
    ADD CONSTRAINT handlungsdimensionen_key_unique UNIQUE (key);


--
-- Name: handlungsdimensionen handlungsdimensionen_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.handlungsdimensionen
    ADD CONSTRAINT handlungsdimensionen_pkey PRIMARY KEY (id);


--
-- Name: import_runs import_runs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_runs
    ADD CONSTRAINT import_runs_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: metric_definitions metric_definitions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_definitions
    ADD CONSTRAINT metric_definitions_pkey PRIMARY KEY (id);


--
-- Name: metric_definitions metric_definitions_tile_id_metric_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_definitions
    ADD CONSTRAINT metric_definitions_tile_id_metric_key_unique UNIQUE (tile_id, metric_key);


--
-- Name: metric_values metric_values_metric_definition_id_time_period_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_values
    ADD CONSTRAINT metric_values_metric_definition_id_time_period_id_unique UNIQUE (metric_definition_id, time_period_id);


--
-- Name: metric_values metric_values_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_values
    ADD CONSTRAINT metric_values_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (tenant_id, permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (tenant_id, role_id, model_id, model_type);


--
-- Name: navigations navigations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.navigations
    ADD CONSTRAINT navigations_pkey PRIMARY KEY (id);


--
-- Name: pages pages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_tenant_id_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_tenant_id_name_guard_name_unique UNIQUE (tenant_id, name, guard_name);


--
-- Name: sdg_ziele sdg_ziele_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sdg_ziele
    ADD CONSTRAINT sdg_ziele_number_unique UNIQUE (number);


--
-- Name: sdg_ziele sdg_ziele_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sdg_ziele
    ADD CONSTRAINT sdg_ziele_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: settings settings_group_name_tenant_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_group_name_tenant_id_unique UNIQUE ("group", name, tenant_id);


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (id);


--
-- Name: tenant_user tenant_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_user
    ADD CONSTRAINT tenant_user_pkey PRIMARY KEY (id);


--
-- Name: tenant_user tenant_user_tenant_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_user
    ADD CONSTRAINT tenant_user_tenant_id_user_id_unique UNIQUE (tenant_id, user_id);


--
-- Name: tenants tenants_domain_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_domain_unique UNIQUE (domain);


--
-- Name: tenants tenants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_pkey PRIMARY KEY (id);


--
-- Name: tenants tenants_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_slug_unique UNIQUE (slug);


--
-- Name: themes themes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.themes
    ADD CONSTRAINT themes_pkey PRIMARY KEY (id);


--
-- Name: themes themes_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.themes
    ADD CONSTRAINT themes_slug_unique UNIQUE (slug);


--
-- Name: tile_sdg_ziel tile_sdg_ziel_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tile_sdg_ziel
    ADD CONSTRAINT tile_sdg_ziel_pkey PRIMARY KEY (tile_id, sdg_ziel_id);


--
-- Name: time_periods tile_years_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.time_periods
    ADD CONSTRAINT tile_years_pkey PRIMARY KEY (id);


--
-- Name: tiles tiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiles
    ADD CONSTRAINT tiles_pkey PRIMARY KEY (id);


--
-- Name: time_periods time_periods_tile_id_period_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.time_periods
    ADD CONSTRAINT time_periods_tile_id_period_key_unique UNIQUE (tile_id, period_key);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_keycloak_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_keycloak_id_unique UNIQUE (keycloak_id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: categories_category_group_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX categories_category_group_id_index ON public.categories USING btree (category_group_id);


--
-- Name: categories_external_source_external_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX categories_external_source_external_id_index ON public.categories USING btree (external_source, external_id);


--
-- Name: category_groups_external_source_external_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX category_groups_external_source_external_id_index ON public.category_groups USING btree (external_source, external_id);


--
-- Name: import_runs_tenant_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_runs_tenant_id_created_at_index ON public.import_runs USING btree (tenant_id, created_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: metric_definitions_external_source_external_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX metric_definitions_external_source_external_id_index ON public.metric_definitions USING btree (external_source, external_id);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_permissions_team_foreign_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_team_foreign_key_index ON public.model_has_permissions USING btree (tenant_id);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: model_has_roles_team_foreign_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_team_foreign_key_index ON public.model_has_roles USING btree (tenant_id);


--
-- Name: pages_is_public_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pages_is_public_index ON public.pages USING btree (is_public);


--
-- Name: pages_layout_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pages_layout_index ON public.pages USING btree (layout);


--
-- Name: pages_title_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pages_title_index ON public.pages USING btree (title);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: roles_team_foreign_key_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX roles_team_foreign_key_index ON public.roles USING btree (tenant_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: tiles_external_source_external_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tiles_external_source_external_id_index ON public.tiles USING btree (external_source, external_id);


--
-- Name: tiles_is_public_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tiles_is_public_index ON public.tiles USING btree (is_public);


--
-- Name: time_periods_granularity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX time_periods_granularity_index ON public.time_periods USING btree (granularity);


--
-- Name: background_pages background_pages_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.background_pages
    ADD CONSTRAINT background_pages_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: background_pages background_pages_tile_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.background_pages
    ADD CONSTRAINT background_pages_tile_id_foreign FOREIGN KEY (tile_id) REFERENCES public.tiles(id) ON DELETE CASCADE;


--
-- Name: categories categories_category_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_category_group_id_foreign FOREIGN KEY (category_group_id) REFERENCES public.category_groups(id) ON DELETE SET NULL;


--
-- Name: categories categories_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: category_groups category_groups_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_groups
    ADD CONSTRAINT category_groups_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: category_tile category_tile_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_tile
    ADD CONSTRAINT category_tile_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: category_tile category_tile_tile_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.category_tile
    ADD CONSTRAINT category_tile_tile_id_foreign FOREIGN KEY (tile_id) REFERENCES public.tiles(id) ON DELETE CASCADE;


--
-- Name: footer_navigations footer_navigations_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.footer_navigations
    ADD CONSTRAINT footer_navigations_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: handlungsdimension_handlungsfeld handlungsdimension_handlungsfeld_handlungsdimension_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.handlungsdimension_handlungsfeld
    ADD CONSTRAINT handlungsdimension_handlungsfeld_handlungsdimension_id_foreign FOREIGN KEY (handlungsdimension_id) REFERENCES public.handlungsdimensionen(id) ON DELETE CASCADE;


--
-- Name: handlungsdimension_handlungsfeld handlungsdimension_handlungsfeld_handlungsfeld_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.handlungsdimension_handlungsfeld
    ADD CONSTRAINT handlungsdimension_handlungsfeld_handlungsfeld_id_foreign FOREIGN KEY (handlungsfeld_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: handlungsdimensionen handlungsdimensionen_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.handlungsdimensionen
    ADD CONSTRAINT handlungsdimensionen_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: import_runs import_runs_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_runs
    ADD CONSTRAINT import_runs_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: import_runs import_runs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_runs
    ADD CONSTRAINT import_runs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: metric_definitions metric_definitions_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_definitions
    ADD CONSTRAINT metric_definitions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: metric_definitions metric_definitions_tile_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_definitions
    ADD CONSTRAINT metric_definitions_tile_id_foreign FOREIGN KEY (tile_id) REFERENCES public.tiles(id) ON DELETE CASCADE;


--
-- Name: metric_values metric_values_metric_definition_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_values
    ADD CONSTRAINT metric_values_metric_definition_id_foreign FOREIGN KEY (metric_definition_id) REFERENCES public.metric_definitions(id) ON DELETE CASCADE;


--
-- Name: metric_values metric_values_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_values
    ADD CONSTRAINT metric_values_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: metric_values metric_values_time_period_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.metric_values
    ADD CONSTRAINT metric_values_time_period_id_foreign FOREIGN KEY (time_period_id) REFERENCES public.time_periods(id) ON DELETE CASCADE;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: navigations navigations_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.navigations
    ADD CONSTRAINT navigations_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: pages pages_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.pages(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: pages pages_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: personal_access_tokens personal_access_tokens_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: sdg_ziele sdg_ziele_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sdg_ziele
    ADD CONSTRAINT sdg_ziele_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: tenant_user tenant_user_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_user
    ADD CONSTRAINT tenant_user_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: tenant_user tenant_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenant_user
    ADD CONSTRAINT tenant_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: tenants tenants_theme_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_theme_id_foreign FOREIGN KEY (theme_id) REFERENCES public.themes(id) ON DELETE SET NULL;


--
-- Name: themes themes_parent_theme_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.themes
    ADD CONSTRAINT themes_parent_theme_id_foreign FOREIGN KEY (parent_theme_id) REFERENCES public.themes(id) ON DELETE SET NULL;


--
-- Name: tile_sdg_ziel tile_sdg_ziel_sdg_ziel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tile_sdg_ziel
    ADD CONSTRAINT tile_sdg_ziel_sdg_ziel_id_foreign FOREIGN KEY (sdg_ziel_id) REFERENCES public.sdg_ziele(id) ON DELETE CASCADE;


--
-- Name: tile_sdg_ziel tile_sdg_ziel_tile_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tile_sdg_ziel
    ADD CONSTRAINT tile_sdg_ziel_tile_id_foreign FOREIGN KEY (tile_id) REFERENCES public.tiles(id) ON DELETE CASCADE;


--
-- Name: time_periods tile_years_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.time_periods
    ADD CONSTRAINT tile_years_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: time_periods tile_years_tile_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.time_periods
    ADD CONSTRAINT tile_years_tile_id_foreign FOREIGN KEY (tile_id) REFERENCES public.tiles(id) ON DELETE CASCADE;


--
-- Name: tiles tiles_handlungsdimension_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiles
    ADD CONSTRAINT tiles_handlungsdimension_id_foreign FOREIGN KEY (handlungsdimension_id) REFERENCES public.handlungsdimensionen(id) ON DELETE SET NULL;


--
-- Name: tiles tiles_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiles
    ADD CONSTRAINT tiles_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: users users_default_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_default_tenant_id_foreign FOREIGN KEY (default_tenant_id) REFERENCES public.tenants(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict JL1NqkZabgrmPxnRZcEqAy32xFrm5FELRkvnpkGno5fhaSmeGodXVQ82Q7ot3tf

--
-- PostgreSQL database dump
--

\restrict F2tDBOB7aNwv6UdeklpKJU641qn4tAZNqrE5QUuUYRuO4H6VfTKQ1JODyfs15IQ

-- Dumped from database version 16.14 (Debian 16.14-1.pgdg13+1)
-- Dumped by pg_dump version 16.14 (Debian 16.14-1.pgdg12+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2022_12_14_083707_create_settings_table	1
5	2025_01_15_120000_add_indicator_type_to_metrics_table	1
6	2025_04_22_083703_create_categories_table	1
7	2025_04_22_083712_create_tiles_table	1
8	2025_04_22_083719_create_background_pages_table	1
9	2025_04_22_084143_create_category_tile_table	1
10	2025_04_22_084149_add_tile_id_to_background_pages_table	1
11	2025_04_22_125659_create_tile_years_table	1
12	2025_04_22_125748_create_metrics_table	1
13	2025_04_22_132538_make_background_pages_slug_nullable	1
14	2025_04_22_132710_make_background_pages_content_nullable	1
15	2025_04_22_140439_create_personal_access_tokens_table	1
18	2025_04_25_100921_create_pages_table	1
19	2025_04_25_100922_fix_slug_unique_constraint_on_pages_table	1
24	2025_10_21_081431_add_icon_to_categories_table	1
25	2025_11_25_102800_convert_pages_to_translatable	1
26	2025_11_27_114957_add_background_blocks_to_tiles_table	1
27	2025_11_28_120000_add_seeding_tracking_to_tiles_table	1
28	2025_11_28_120001_add_seeding_tracking_to_categories_table	1
29	2025_12_01_120452_create_handlungsdimensionen_table	1
30	2025_12_01_120454_create_sdg_ziele_table	1
31	2025_12_01_120456_create_tile_sdg_ziel_table	1
32	2025_12_01_120458_create_handlungsdimension_handlungsfeld_table	1
33	2025_12_01_120500_add_handlungsdimension_id_to_tiles_table	1
34	2025_12_01_124512_modify_sdg_ziele_icon_to_translatable	1
35	2025_12_01_142528_add_metric_key_to_metrics_table	1
36	2025_12_02_120833_create_metric_definitions_table	1
37	2025_12_02_120834_create_metric_values_table	1
38	2025_12_02_120835_drop_metrics_table	1
39	2025_12_02_154648_add_color_to_handlungsdimensionen_table	1
52	2025_12_08_081906_convert_header_footer_to_translatable	1
53	2025_12_08_085223_create_footer_navigations_table	1
54	2025_12_08_085223_create_navigations_table	1
55	2025_12_08_085223_migrate_header_footer_settings_to_models	1
56	2025_12_10_000001_create_tenants_table	1
57	2025_12_10_000002_create_tenant_user_table	1
58	2025_12_10_000003_add_tenant_columns_to_domain_tables	1
59	2026_01_14_120446_add_admin_api_enabled_to_users_table	1
60	2026_01_14_121552_add_domain_columns_to_tenants	1
61	2026_01_14_121552_add_tenant_id_to_personal_access_tokens	1
62	2026_01_14_152543_drop_theme_config_from_tenants	1
63	2026_01_19_090000_add_visibility_and_seo_fields_to_pages_table	1
64	2026_01_19_120000_add_visibility_and_seo_fields_to_tiles_table	1
70	2026_01_19_130000_backfill_tile_slugs	1
71	2026_01_20_120001_backfill_block_active_flags	1
72	2026_01_20_120002_add_is_active_to_metric_definitions_table	1
73	2026_01_20_120003_add_is_active_to_metric_values_table	1
74	2026_01_20_120100_create_category_groups_table	1
75	2026_01_20_120101_add_group_and_color_to_categories_table	1
76	2026_01_20_120200_backfill_dynamic_category_groups	1
77	2026_01_21_150357_add_is_active_to_categories_tables	1
78	2026_01_27_103758_create_permission_tables	1
79	2026_01_27_130717_add_locale_to_users_table	1
80	2026_01_27_150427_add_avatar_path_to_users_table	1
82	2026_02_04_151700_backfill_navigation_active_flags	1
83	2026_02_06_120000_fix_navigation_active_flags_corruption	1
84	2026_02_16_000000_add_tenant_id_to_settings_table	1
85	2026_02_18_000000_add_description_to_tenants	1
86	2026_02_19_100000_simplify_user_management	1
87	2026_02_19_100002_simplify_roles	1
88	2026_02_19_200000_add_is_admin_to_users_table	1
89	2026_02_24_000000_drop_admin_api_enabled_from_users_table	1
91	2026_03_02_000000_drop_show_language_switcher_from_navigations_table	1
92	2026_03_02_100000_add_sort_order_to_metric_definitions_table	1
93	2026_03_02_100001_add_sort_order_to_metric_values_table	1
94	2026_03_03_000000_add_is_active_to_personal_access_tokens	1
95	2026_03_04_135703_backfill_category_keys_from_slug	1
97	2026_03_06_100000_migrate_color_source_to_branding_settings	1
98	2026_03_06_120000_remove_filterable_and_selection_type_from_category_groups	1
99	2026_03_09_100000_convert_tile_background_blocks_to_translatable	1
100	2026_03_10_100000_add_sort_order_and_nav_placement_to_pages_table	1
101	2026_03_10_120000_remove_heading_en_from_page_blocks	1
102	2026_03_10_140000_remove_sort_order_and_nav_placement_from_pages_table	1
103	2026_03_25_100000_add_sponsors_to_footer_navigations_table	1
104	2026_03_25_150000_add_hint_to_tiles_table	1
107	2026_04_10_111227_add_external_tracking_fields_to_models	1
109	2026_04_10_142053_make_metric_values_value_nullable	1
110	2026_04_11_000000_add_keycloak_id_to_users_table	1
111	2026_04_15_100000_rename_tile_years_to_time_periods	1
112	2026_04_20_132107_create_import_runs_table	1
115	2026_08_20_100000_create_themes_table	4
116	2026_08_20_133940_add_theme_id_to_tenants	5
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 117, true);


--
-- PostgreSQL database dump complete
--

\unrestrict F2tDBOB7aNwv6UdeklpKJU641qn4tAZNqrE5QUuUYRuO4H6VfTKQ1JODyfs15IQ

