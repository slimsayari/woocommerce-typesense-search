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
class WTS_Faceted_Search
{

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
    public function add_query_vars($vars)
    {
        $vars[] = 'wts_search';
        $vars[] = 'wts_min_price';
        $vars[] = 'wts_max_price';
        $vars[] = 'wts_categories';
        $vars[] = 'wts_attributes';
        $vars[] = 'wts_in_stock';
        $vars[] = 'wts_on_sale';
        $vars[] = 'wts_min_rating';
        $vars[] = 'wts_sort';
        $vars[] = 'wts_core_search';

        return $vars;
    }

    /**
     * Override product query to use Typesense
     *
     * @param WP_Query $query
     */
    public function override_product_query($query)
    {
        // Check for specific core search flag
        $is_core_search = $query->get('wts_core_search');

        // Only for main query on shop pages, unless it's our core search
        if ((!$query->is_main_query() && !$is_core_search) || (is_admin() && !$is_core_search)) {
            return;
        }

        // Check if this is a product query
        if (!$is_core_search && !is_shop() && !is_product_category() && !is_product_tag() && !is_product_taxonomy()) {
            return;
        }

        // Check if Typesense is enabled
        $settings = WooCommerce_Typesense_Search::get_settings();
        if (!isset($settings['enabled']) || $settings['enabled'] !== 'yes') {
            return;
        }

        // Get search parameters
        $search_params = $this->get_search_params($query);
        error_log('WTS SEARCH PARAMS: ' . print_r($search_params, true));

        // Perform Typesense search
        $results = $this->search($search_params);
        error_log('WTS CORE SEARCH: Results: ' . print_r($results, true));

        if (is_wp_error($results)) {
            return; // Fallback to default WP query
        }

        // Override query with Typesense results
        $product_ids = array_map(function ($hit) {
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

        // Fix WP Pagination: Overwrite found_posts with Typesense total
        $query->found_posts = $results['found'] ?? 0;
        $posts_per_page = $query->get('posts_per_page') ?: get_option('posts_per_page');
        if ($posts_per_page > 0) {
            $query->max_num_pages = ceil($query->found_posts / $posts_per_page);
        } else {
            $query->max_num_pages = 0;
        }
    }

    /**
     * Get search parameters from query
     *
     * @param WP_Query $query
     * @return array
     */
    private function get_search_params($query)
    {
        $params = array(
            'q' => $query->get('wts_search') ?: '*',
            'query_by' => 'title,description,short_description,sku',
            'facet_by' => 'categories,price,stock_status,rating,attributes',
            'max_facet_values' => 100,
            'per_page' => $query->get('posts_per_page') ?: 12,
            'page' => max(1, $query->get('paged') ?: 1),
        );

        // Build filter query
        $filters = array();

        // Price filter
        $min_price = $query->get('wts_min_price');
        $max_price = $query->get('wts_max_price');

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

        // Taxonomy Archive (Attribute Page)
        if (is_product_taxonomy()) {
            $term = get_queried_object();
            if ($term) {
                // Indexer formats as "TaxonomyLabel: TermName"
                // Exception: Product Tags use 'tags' field.
                if (is_product_tag()) {
                    $filters[] = "tags:=[" . $term->name . "]";
                } else {
                    // For attributes/custom taxonomies
                    $tax_obj = get_taxonomy($term->taxonomy);
                    $label = $tax_obj ? $tax_obj->labels->singular_name : $term->taxonomy;
                    $filters[] = "attributes:=[" . $label . ": " . $term->name . "]";
                }
            }
        }

        $categories = $query->get('wts_categories');
        if ($categories) {
            $cats = is_array($categories) ? $categories : explode(',', $categories);
            $cats_filter = array_map(function ($cat) {
                return "categories:=[" . sanitize_text_field($cat) . "]";
            }, $cats);
            $filters[] = '(' . implode(' || ', $cats_filter) . ')';
        }

        // Stock status filter
        $in_stock = $query->get('wts_in_stock');
        if ($in_stock) {
            $filters[] = "stock_status:=instock";
        }

        // On sale filter
        $on_sale = $query->get('wts_on_sale');
        if ($on_sale) {
            $filters[] = "sale_price:>0";
        }

        // Rating filter
        $min_rating = $query->get('wts_min_rating');
        if ($min_rating) {
            $filters[] = "rating:>=" . floatval($min_rating);
        }

        // Attributes filter
        $attributes_filter = $query->get('wts_attributes');
        if ($attributes_filter) {
            $attrs = is_array($attributes_filter) ? $attributes_filter : explode(',', $attributes_filter);
            // Each attribute selection is usually OR within the same attribute, AND between different attributes.
            // But here we receive a flat list possibly.
            // If we receive ["Type de cheveux: Lisses", "Type de cheveux: Bouclés", "Color: Red"]
            // We want (Type...Lisses || Type...Bouclés) && (Color...Red)

            // Group by attribute name
            $grouped_attrs = array();
            foreach ($attrs as $attr_str) {
                // $attr_str is "Name: Value"
                $parts = explode(':', $attr_str, 2);
                if (count($parts) === 2) {
                    $name = trim($parts[0]);
                    $val = trim($parts[1]);
                    if (!isset($grouped_attrs[$name])) {
                        $grouped_attrs[$name] = array();
                    }
                    $grouped_attrs[$name][] = $attr_str;
                }
            }

            foreach ($grouped_attrs as $name => $values) {
                $attr_query = array_map(function ($val) {
                    return "attributes:=[" . sanitize_text_field($val) . "]";
                }, $values);
                $filters[] = '(' . implode(' || ', $attr_query) . ')';
            }
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
    public function search($params)
    {
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
    public function get_facets()
    {
        global $wp_query;

        return $wp_query->wts_facets ?? array();
    }

    /**
     * Get price range facet
     *
     * @return array
     */
    public function get_price_range()
    {
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
    public function get_category_facets()
    {
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
    public function render_filters()
    {
        $price_range = $this->get_price_range();
        $categories = $this->get_category_facets();

        include WTS_PLUGIN_DIR . 'templates/search-filters.php';
    }
}
