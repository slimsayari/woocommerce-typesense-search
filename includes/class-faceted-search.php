<?php
/**
 * Faceted Search Class
 * Gère les filtres à facettes pour la recherche Typesense
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Faceted_Search Class
 */
class WTS_Faceted_Search {
    
    /**
     * Single instance
     *
     * @var WTS_Faceted_Search
     */
    protected static $_instance = null;
    
    /**
     * Typesense client
     *
     * @var WTS_Typesense_Client
     */
    private $client;
    
    /**
     * Main Instance
     *
     * @return WTS_Faceted_Search
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->client = WTS_Typesense_Client::instance();
        
        // Override WooCommerce product query
        add_action('pre_get_posts', array($this, 'override_product_query'), 999);
        
        // Add facets to query vars
        add_filter('query_vars', array($this, 'add_query_vars'));
    }
    
    /**
     * Add custom query vars
     *
     * @param array $vars
     * @return array
     */
    public function add_query_vars($vars) {
        $vars[] = 'wts_search';
        $vars[] = 'wts_min_price';
        $vars[] = 'wts_max_price';
        $vars[] = 'wts_categories';
        $vars[] = 'wts_attributes';
        $vars[] = 'wts_in_stock';
        $vars[] = 'wts_on_sale';
        $vars[] = 'wts_min_rating';
        $vars[] = 'wts_sort';
        
        return $vars;
    }
    
    /**
     * Override product query to use Typesense
     *
     * @param WP_Query $query
     */
    public function override_product_query($query) {
        // Only for main query on shop pages
        if (!$query->is_main_query() || is_admin()) {
            return;
        }
        
        // Check if this is a product query
        if (!is_shop() && !is_product_category() && !is_product_tag() && !is_product_taxonomy()) {
            return;
        }
        
        // Check if Typesense is enabled
        $settings = WooCommerce_Typesense_Search::get_settings();
        if (!isset($settings['enabled']) || $settings['enabled'] !== 'yes') {
            return;
        }
        
        // Get search parameters
        $search_params = $this->get_search_params($query);
        
        // Perform Typesense search
        $results = $this->search($search_params);
        
        if (is_wp_error($results)) {
            return; // Fallback to default WP query
        }
        
        // Override query with Typesense results
        $product_ids = array_map(function($hit) {
            return isset($hit['document']['id']) ? $hit['document']['id'] : 0;
        }, $results['hits']);
        
        if (empty($product_ids)) {
            $query->set('post__in', array(0)); // No results
        } else {
            $query->set('post__in', $product_ids);
            $query->set('orderby', 'post__in');
        }
        
        // Store facets for display
        $query->wts_facets = $results['facets'] ?? array();
        $query->wts_total = $results['found'] ?? 0;
    }
    
    /**
     * Get search parameters from query
     *
     * @param WP_Query $query
     * @return array
     */
    private function get_search_params($query) {
        $params = array(
            'q' => get_query_var('wts_search') ?: '*',
            'query_by' => 'name,description,short_description,sku',
            'facet_by' => 'categories,price,stock_status,rating',
            'max_facet_values' => 100,
            'per_page' => $query->get('posts_per_page') ?: 12,
            'page' => max(1, $query->get('paged') ?: 1),
        );
        
        // Build filter query
        $filters = array();
        
        // Price filter
        $min_price = get_query_var('wts_min_price');
        $max_price = get_query_var('wts_max_price');
        
        if ($min_price || $max_price) {
            $min = $min_price ? floatval($min_price) : 0;
            $max = $max_price ? floatval($max_price) : 999999;
            $filters[] = "price:[$min..$max]";
        }
        
        // Category filter
        if (is_product_category()) {
            $category = get_queried_object();
            if ($category) {
                $filters[] = "categories:=[" . $category->name . "]";
            }
        }
        
        $categories = get_query_var('wts_categories');
        if ($categories) {
            $cats = is_array($categories) ? $categories : explode(',', $categories);
            $cats_filter = array_map(function($cat) {
                return "categories:=[" . sanitize_text_field($cat) . "]";
            }, $cats);
            $filters[] = '(' . implode(' || ', $cats_filter) . ')';
        }
        
        // Stock status filter
        $in_stock = get_query_var('wts_in_stock');
        if ($in_stock) {
            $filters[] = "stock_status:=instock";
        }
        
        // On sale filter
        $on_sale = get_query_var('wts_on_sale');
        if ($on_sale) {
            $filters[] = "sale_price:>0";
        }
        
        // Rating filter
        $min_rating = get_query_var('wts_min_rating');
        if ($min_rating) {
            $filters[] = "rating:>=" . floatval($min_rating);
        }
        
        if (!empty($filters)) {
            $params['filter_by'] = implode(' && ', $filters);
        }
        
        // Sorting
        $sort = get_query_var('wts_sort') ?: 'relevance';
        
        switch ($sort) {
            case 'price_asc':
                $params['sort_by'] = 'price:asc';
                break;
            case 'price_desc':
                $params['sort_by'] = 'price:desc';
                break;
            case 'date_desc':
                $params['sort_by'] = 'created_at:desc';
                break;
            case 'rating_desc':
                $params['sort_by'] = 'rating:desc';
                break;
            default:
                // Relevance (default Typesense sorting)
                break;
        }
        
        return $params;
    }
    
    /**
     * Perform search
     *
     * @param array $params
     * @return array|WP_Error
     */
    public function search($params) {
        $result = $this->client->search($params, 'products');
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return array(
            'hits' => $result['hits'] ?? array(),
            'found' => $result['found'] ?? 0,
            'facets' => $result['facet_counts'] ?? array(),
        );
    }
    
    /**
     * Get available facets
     *
     * @return array
     */
    public function get_facets() {
        global $wp_query;
        
        return $wp_query->wts_facets ?? array();
    }
    
    /**
     * Get price range facet
     *
     * @return array
     */
    public function get_price_range() {
        $facets = $this->get_facets();
        
        if (empty($facets)) {
            return array('min' => 0, 'max' => 1000);
        }
        
        foreach ($facets as $facet) {
            if ($facet['field_name'] === 'price') {
                $stats = $facet['stats'] ?? array();
                return array(
                    'min' => floor($stats['min'] ?? 0),
                    'max' => ceil($stats['max'] ?? 1000),
                );
            }
        }
        
        return array('min' => 0, 'max' => 1000);
    }
    
    /**
     * Get category facets
     *
     * @return array
     */
    public function get_category_facets() {
        $facets = $this->get_facets();
        
        foreach ($facets as $facet) {
            if ($facet['field_name'] === 'categories') {
                return $facet['counts'] ?? array();
            }
        }
        
        return array();
    }
    
    /**
     * Render filters sidebar
     */
    public function render_filters() {
        $price_range = $this->get_price_range();
        $categories = $this->get_category_facets();
        
        include WTS_PLUGIN_DIR . 'templates/search-filters.php';
    }
}
