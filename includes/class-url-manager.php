<?php
/**
 * URL Rewrite Manager
 * Gère les URLs SEO pour les catégories et attributs
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_URL_Manager Class
 */
class WTS_URL_Manager
{

    /**
     * Single instance
     *
     * @var WTS_URL_Manager
     */
    protected static $_instance = null;

    /**
     * Main Instance
     *
     * @return WTS_URL_Manager
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_custom_urls'));
    }

    /**
     * Add custom rewrite rules
     */
    public function add_rewrite_rules()
    {
        // Category pages: /shop/categorie/[slug]
        add_rewrite_rule(
            '^shop/categorie/([^/]+)/?$',
            'index.php?wts_category=$matches[1]',
            'top'
        );

        // Attribute pages: /shop/attribut/[attribute-slug]/[term-slug]
        // Example: /shop/attribut/couleur/rouge
        add_rewrite_rule(
            '^shop/attribut/([^/]+)/([^/]+)/?$',
            'index.php?wts_attribute=$matches[1]&wts_attribute_value=$matches[2]',
            'top'
        );

        // Generic attribute filter: /shop/[attribute-name]
        // Example: /shop/cheveux-lisses
        add_rewrite_rule(
            '^shop/([a-z0-9\-]+)/?$',
            'index.php?wts_filter=$matches[1]',
            'top'
        );
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'wts_category';
        $vars[] = 'wts_attribute';
        $vars[] = 'wts_attribute_value';
        $vars[] = 'wts_filter';
        return $vars;
    }

    /**
     * Handle custom URLs
     */
    public function handle_custom_urls()
    {
        global $wp_query;

        // Handle category URLs
        if (get_query_var('wts_category')) {
            $this->handle_category_page(get_query_var('wts_category'));
            return;
        }

        // Handle attribute URLs
        if (get_query_var('wts_attribute') && get_query_var('wts_attribute_value')) {
            $this->handle_attribute_page(
                get_query_var('wts_attribute'),
                get_query_var('wts_attribute_value')
            );
            return;
        }

        // Handle generic filter URLs
        if (get_query_var('wts_filter')) {
            $this->handle_filter_page(get_query_var('wts_filter'));
            return;
        }
    }

    /**
     * Handle category page
     */
    private function handle_category_page($category_slug)
    {
        $category = get_term_by('slug', $category_slug, 'product_cat');

        if (!$category) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // Set up query for this category
        global $wp_query;
        $wp_query->set('post_type', 'product');
        $wp_query->set('product_cat', $category_slug);
        $wp_query->is_archive = true;
        $wp_query->is_post_type_archive = true;
        $wp_query->is_tax = true;

        // Load template
        $template = WTS_PLUGIN_DIR . 'templates/archive-product.php';
        if (file_exists($template)) {
            load_template($template);
            exit;
        }
    }

    /**
     * Handle attribute page
     */
    private function handle_attribute_page($attribute_slug, $term_slug)
    {
        // Find the attribute taxonomy
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $taxonomy = null;

        foreach ($attribute_taxonomies as $attr) {
            if (sanitize_title($attr->attribute_name) === $attribute_slug) {
                $taxonomy = wc_attribute_taxonomy_name($attr->attribute_name);
                break;
            }
        }

        if (!$taxonomy) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        $term = get_term_by('slug', $term_slug, $taxonomy);

        if (!$term) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // Set up query for this attribute
        global $wp_query;
        $wp_query->set('post_type', 'product');
        $wp_query->set($taxonomy, $term_slug);
        $wp_query->is_archive = true;
        $wp_query->is_post_type_archive = true;
        $wp_query->is_tax = true;

        // Load template
        $template = WTS_PLUGIN_DIR . 'templates/archive-product.php';
        if (file_exists($template)) {
            load_template($template);
            exit;
        }
    }

    /**
     * Handle generic filter page
     */
    private function handle_filter_page($filter_slug)
    {
        // Try to find matching term in any product taxonomy
        $taxonomies = get_object_taxonomies('product', 'objects');
        $found_term = null;
        $found_taxonomy = null;

        foreach ($taxonomies as $taxonomy) {
            $term = get_term_by('slug', $filter_slug, $taxonomy->name);
            if ($term) {
                $found_term = $term;
                $found_taxonomy = $taxonomy->name;
                break;
            }
        }

        if (!$found_term) {
            // Not found, let WordPress handle it
            return;
        }

        // Set up query for this filter
        global $wp_query;
        $wp_query->set('post_type', 'product');
        $wp_query->set($found_taxonomy, $filter_slug);
        $wp_query->is_archive = true;
        $wp_query->is_post_type_archive = true;
        $wp_query->is_tax = true;

        // Load template
        $template = WTS_PLUGIN_DIR . 'templates/archive-product.php';
        if (file_exists($template)) {
            load_template($template);
            exit;
        }
    }

    /**
     * Get category URL
     *
     * @param string $category_slug
     * @return string
     */
    public static function get_category_url($category_slug)
    {
        return home_url('/shop/categorie/' . $category_slug . '/');
    }

    /**
     * Get attribute URL
     *
     * @param string $attribute_slug
     * @param string $term_slug
     * @return string
     */
    public static function get_attribute_url($attribute_slug, $term_slug)
    {
        return home_url('/shop/attribut/' . $attribute_slug . '/' . $term_slug . '/');
    }

    /**
     * Get filter URL (generic)
     *
     * @param string $filter_slug
     * @return string
     */
    public static function get_filter_url($filter_slug)
    {
        return home_url('/shop/' . $filter_slug . '/');
    }
}
