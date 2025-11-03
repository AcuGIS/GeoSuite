-- Create categories table
CREATE TABLE IF NOT EXISTS public.categories (
    id SERIAL PRIMARY KEY,
    name character varying(255) NOT NULL,
    description text,
    color character varying(7) DEFAULT '#667eea'::character varying,
    icon character varying(50),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE public.dashboards ( id SERIAL PRIMARY KEY,
    title character varying(255) NOT NULL,
    description text,
    config jsonb NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    thumbnail character varying(255),
    category_id integer DEFAULT NULL REFERENCES public.categories(id) ON DELETE CASCADE
);

CREATE TABLE public.documents ( id SERIAL PRIMARY KEY,
    title character varying(255) NOT NULL,
    description text,
    filename character varying(255) NOT NULL,
    original_filename character varying(255) NOT NULL,
    file_size integer NOT NULL,
    mime_type character varying(100) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    thumbnail character varying(255),
    category_id integer DEFAULT NULL REFERENCES public.categories(id) ON DELETE CASCADE
);

CREATE TABLE public.groups ( id SERIAL PRIMARY KEY,
    name character varying(100) NOT NULL,
    description text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE public.html_pages ( id SERIAL PRIMARY KEY,
    title character varying(255) NOT NULL,
    description text,
    html_content text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    thumbnail character varying(255),
    category_id integer DEFAULT NULL REFERENCES public.categories(id) ON DELETE CASCADE
);

CREATE TABLE public.maps (  id SERIAL PRIMARY KEY,
    title character varying(255) NOT NULL,
    description text,
    html_content text NOT NULL,
    basemaps jsonb,
    layers jsonb,
    features jsonb,
    initial_extent jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    thumbnail character varying(255),
    category_id integer DEFAULT NULL REFERENCES public.categories(id) ON DELETE CASCADE,
    filters jsonb DEFAULT '{}'::jsonb
);

CREATE TABLE public.permissions (   id SERIAL PRIMARY KEY,
    group_id integer NOT NULL REFERENCES public.groups(id) ON DELETE CASCADE,
    item_type character varying(50) NOT NULL,
    item_id integer,
    can_view boolean DEFAULT false NOT NULL,
    can_edit boolean DEFAULT false NOT NULL,
    can_delete boolean DEFAULT false NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    UNIQUE (group_id, item_type, item_id)
);

CREATE TABLE public.users ( id SERIAL PRIMARY KEY,
    username character varying(100) NOT NULL,
    password_hash character varying(255) NOT NULL,
    full_name character varying(255),
    email character varying(255),
    group_id integer NOT NULL REFERENCES public.groups(id) ON DELETE RESTRICT,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_login timestamp without time zone
);

CREATE TABLE public.app_settings (  id SERIAL PRIMARY KEY,
    site_name text NOT NULL DEFAULT 'GeoLite'::text,
    primary_color text NOT NULL DEFAULT '#667eea'::text,
    footer_text text NOT NULL DEFAULT '2025 Cited, Inc.'::text,
    logo_url text ,
    hero_image text ,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE public.geoserver_config (  id SERIAL PRIMARY KEY,
    geoserver_url text,
    geoserver_username text,
    geoserver_password text
);

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE TRIGGER update_dashboards_updated_at BEFORE UPDATE ON public.dashboards  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
CREATE TRIGGER update_documents_updated_at  BEFORE UPDATE ON public.documents   FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
CREATE TRIGGER update_html_pages_updated_at BEFORE UPDATE ON public.html_pages  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
CREATE TRIGGER update_maps_updated_at       BEFORE UPDATE ON public.maps        FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
CREATE TRIGGER update_users_updated_at      BEFORE UPDATE ON public.users       FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
CREATE TRIGGER update_categories_updated_at BEFORE UPDATE ON public.categories  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
    
CREATE INDEX idx_dashboards_created_at ON dashboards(created_at);
CREATE INDEX idx_dashboards_title ON dashboards(title);
CREATE INDEX idx_documents_created_at ON documents(created_at);
CREATE INDEX idx_documents_title ON documents(title);
CREATE INDEX idx_html_pages_created_at ON html_pages(created_at);
CREATE INDEX idx_html_pages_title ON html_pages(title);
CREATE INDEX idx_categories_name ON categories(name);
CREATE INDEX idx_categories_created_at ON categories(created_at);

COMMENT ON TABLE public.categories IS 'Categories for organizing maps, dashboards, and other content';
COMMENT ON COLUMN public.categories.name IS 'Category name';
COMMENT ON COLUMN public.categories.description IS 'Category description';
COMMENT ON COLUMN public.categories.color IS 'Hex color code for category display';
COMMENT ON COLUMN public.categories.icon IS 'Bootstrap icon class for category display';
