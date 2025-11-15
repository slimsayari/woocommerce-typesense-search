<?php
/**
 * REST API Class
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Rest_API Class
 */
class WTS_Rest_API {
    
    /**
     * Single instance
     *
     * @var WTS_Rest_API
     */
    protected static $_instance = null;
    
    /**
     * Namespace
     *
     * @var string
     */
    private $namespace = 'wts/v1';
    
    /**
     * Typesense client
     *
     * @var WTS_Typesense_Client
     */
    private $client;
    
    /**
     * Main Instance
     *
     * @return WTS_Rest_API
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
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Search endpoint
        register_rest_route($this->namespace, '/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'search'),
            'permission_callback' => '__return_true',
            'args' => array(
                'q' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'per_page' => array(
                    'default' => 12,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'page' => array(
                    'default' => 1,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'categories' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'min_price' => array(
                    'type' => 'number',
                    'sanitize_callback' => 'floatval',
                ),
                'max_price' => array(
                    'type' => 'number',
                    'sanitize_callback' => 'floatval',
                ),
                'in_stock' => array(
                    'type' => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ),
                'on_sale' => array(
                    'type' => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ),
                'sort_by' => array(
                    'default' => 'relevance',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // Suggest endpoint
        register_rest_route($this->namespace, '/suggest', array(
            'methods' => 'GET',
            'callback' => array($this, 'suggest'),
            'permission_callback' => '__return_true',
            'args' => array(
                'q' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'limit' => array(
                    'default' => 5,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // Sync endpoint
        register_rest_route($this->namespace, '/sync', array(
            'methods' => 'POST',
            'callback' => array($this, 'sync'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));
        
        // Stats endpoint
        register_rest_route($this->namespace, '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'stats'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));
        
        // Image search endpoint
        register_rest_route($this->namespace, '/image-search', array(
            'methods' => 'POST',
            'callback' => array($this, 'image_search'),
            'permission_callback' => '__return_true',
        ));
        
        // Track click endpoint
        register_rest_route($this->namespace, '/track-click', array(
            'methods' => 'POST',
            'callback' => array($this, 'track_click'),
            'permission_callback' => '__return_true',
            'args' => array(
                'search_term' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'product_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }
    
    /**
     * Check admin permission
     *
     * @return bool
     */
    public function check_admin_permission() {
        return current_user_can('manage_woocommerce');
    }
    
    /**
     * Search products
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function search($request) {
        if (!WooCommerce_Typesense_Search::get_setting('enabled', false)) {
            return new WP_Error('search_disabled', __('Typesense search is disabled', 'woocommerce-typesense-search'), array('status' => 503));
        }
        
        $query = $request->get_param('q');
        $per_page = $request->get_param('per_page');
        $page = $request->get_param('page');
        
        // Check cache
        $cache_key = 'wts_search_' . md5(json_encode($request->get_params()));
        if (WooCommerce_Typesense_Search::get_setting('cache_enabled', true)) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return rest_ensure_response($cached);
            }
        }
        
        // Build search parameters
        $params = array(
            'q' => $query,
            'query_by' => 'title,description,short_description,sku,categories,tags,attributes',
            'per_page' => $per_page,
            'page' => $page,
        );
        
        // Add filters
        $filter_by = array();
        
        if ($request->get_param('categories')) {
            $categories = explode(',', $request->get_param('categories'));
            $filter_by[] = 'categories:=[' . implode(',', array_map(function($cat) {
                return '`' . trim($cat) . '`';
            }, $categories)) . ']';
        }
        
        if ($request->get_param('min_price')) {
            $filter_by[] = 'price:>=' . $request->get_param('min_price');
        }
        
        if ($request->get_param('max_price')) {
            $filter_by[] = 'price:<=' . $request->get_param('max_price');
        }
        
        if ($request->get_param('in_stock')) {
            $filter_by[] = 'stock_status:=instock';
        }
        
        if ($request->get_param('on_sale')) {
            $filter_by[] = 'on_sale:=true';
        }
        
        if (!empty($filter_by)) {
            $params['filter_by'] = implode(' && ', $filter_by);
        }
        
        // Add sorting
        $sort_by = $request->get_param('sort_by');
        switch ($sort_by) {
            case 'price_asc':
                $params['sort_by'] = 'price:asc';
                break;
            case 'price_desc':
                $params['sort_by'] = 'price:desc';
                break;
            case 'date_desc':
                $params['sort_by'] = 'date_created:desc';
                break;
            case 'rating':
                $params['sort_by'] = 'rating:desc';
                break;
            default:
                $params['sort_by'] = '_text_match:desc';
        }
        
        // Add facets
        $params['facet_by'] = 'categories,stock_status,on_sale';
        
        // Enable typo tolerance
        if (WooCommerce_Typesense_Search::get_setting('typo_tolerance', true)) {
            $params['num_typos'] = 2;
        }
        
        $result = $this->client->search($params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Format response
        $response = array(
            'products' => array(),
            'total' => $result['found'] ?? 0,
            'page' => $page,
            'per_page' => $per_page,
            'facets' => $result['facet_counts'] ?? array(),
        );
        
        if (isset($result['hits'])) {
            foreach ($result['hits'] as $hit) {
                $response['products'][] = $hit['document'];
            }
        }
        
        // Track search
        $this->track_search($query, $response['total']);
        
        // Cache result
        if (WooCommerce_Typesense_Search::get_setting('cache_enabled', true)) {
            $ttl = WooCommerce_Typesense_Search::get_setting('cache_ttl', 3600);
            set_transient($cache_key, $response, $ttl);
        }
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get search suggestions
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function suggest($request) {
        if (!WooCommerce_Typesense_Search::get_setting('enabled', false)) {
            return new WP_Error('search_disabled', __('Typesense search is disabled', 'woocommerce-typesense-search'), array('status' => 503));
        }
        
        $query = $request->get_param('q');
        $limit = $request->get_param('limit');
        
        $params = array(
            'q' => $query,
            'query_by' => 'title,categories',
            'per_page' => $limit,
            'prefix' => 'true',
        );
        
        $result = $this->client->search($params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $suggestions = array();
        if (isset($result['hits'])) {
            foreach ($result['hits'] as $hit) {
                $suggestions[] = array(
                    'title' => $hit['document']['title'],
                    'id' => $hit['document']['id'],
                    'image' => $hit['document']['image'] ?? '',
                    'price' => $hit['document']['price'] ?? 0,
                );
            }
        }
        
        return rest_ensure_response($suggestions);
    }
    
    /**
     * Sync products
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function sync($request) {
        $indexer = WTS_Product_Indexer::instance();
        $result = $indexer->bulk_sync();
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('sync_failed', $result['error'] ?? __('Sync failed', 'woocommerce-typesense-search'), array('status' => 500));
        }
    }
    
    /**
     * Get search statistics
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function stats($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wts_analytics';
        
        $stats = array(
            'total_searches' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'unique_searches' => $wpdb->get_var("SELECT COUNT(DISTINCT search_term) FROM $table_name"),
            'avg_results' => $wpdb->get_var("SELECT AVG(results_count) FROM $table_name"),
            'popular_searches' => $wpdb->get_results(
                "SELECT search_term, COUNT(*) as count FROM $table_name GROUP BY search_term ORDER BY count DESC LIMIT 10",
                ARRAY_A
            ),
        );
        
        return rest_ensure_response($stats);
    }
    
    /**
     * Image search
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function image_search($request) {
        if (!WooCommerce_Typesense_Search::get_setting('image_search_enabled', false)) {
            return new WP_Error('image_search_disabled', __('Image search is disabled', 'woocommerce-typesense-search'), array('status' => 503));
        }
        
        $files = $request->get_file_params();
        if (empty($files['image'])) {
            return new WP_Error('no_image', __('No image provided', 'woocommerce-typesense-search'), array('status' => 400));
        }
        
        $image = $files['image'];
        
        // Upload image temporarily
        $upload = wp_handle_upload($image, array('test_form' => false));
        
        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error'], array('status' => 400));
        }
        
        // Analyze image with OpenAI Vision API
        $openai_key = WooCommerce_Typesense_Search::get_setting('openai_api_key', '');
        if (empty($openai_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'woocommerce-typesense-search'), array('status' => 500));
        }
        
        // Get image description from OpenAI
        $image_url = $upload['url'];
        $description = $this->analyze_image_with_openai($image_url, $openai_key);
        
        // Clean up uploaded file
        @unlink($upload['file']);
        
        if (is_wp_error($description)) {
            return $description;
        }
        
        // Search using the description
        $params = array(
            'q' => $description,
            'query_by' => 'title,description,categories,tags',
            'per_page' => 12,
        );
        
        $result = $this->client->search($params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $response = array(
            'query' => $description,
            'products' => array(),
            'total' => $result['found'] ?? 0,
        );
        
        if (isset($result['hits'])) {
            foreach ($result['hits'] as $hit) {
                $response['products'][] = $hit['document'];
            }
        }
        
        return rest_ensure_response($response);
    }
    
    /**
     * Analyze image with OpenAI
     *
     * @param string $image_url
     * @param string $api_key
     * @return string|WP_Error
     */
    private function analyze_image_with_openai($image_url, $api_key) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => array(
                            array(
                                'type' => 'text',
                                'text' => 'Describe this product image in a few keywords suitable for product search. Focus on the product type, color, style, and key features.',
                            ),
                            array(
                                'type' => 'image_url',
                                'image_url' => array(
                                    'url' => $image_url,
                                ),
                            ),
                        ),
                    ),
                ),
                'max_tokens' => 100,
            )),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('openai_error', $body['error']['message'] ?? 'Unknown error');
        }
        
        return $body['choices'][0]['message']['content'] ?? '';
    }
    
    /**
     * Track click
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function track_click($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wts_analytics';
        
        $wpdb->update(
            $table_name,
            array('clicked_product_id' => $request->get_param('product_id')),
            array('search_term' => $request->get_param('search_term')),
            array('%d'),
            array('%s')
        );
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Track search
     *
     * @param string $query
     * @param int $results_count
     */
    private function track_search($query, $results_count) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wts_analytics';
        
        $session_id = $this->get_session_id();
        
        $wpdb->insert(
            $table_name,
            array(
                'search_term' => $query,
                'results_count' => $results_count,
                'user_id' => get_current_user_id(),
                'session_id' => $session_id,
                'search_type' => 'text',
            ),
            array('%s', '%d', '%d', '%s', '%s')
        );
    }
    
    /**
     * Get or create session ID
     *
     * @return string
     */
    private function get_session_id() {
        if (isset($_COOKIE['wts_session_id'])) {
            return sanitize_text_field($_COOKIE['wts_session_id']);
        }
        
        $session_id = wp_generate_uuid4();
        setcookie('wts_session_id', $session_id, time() + (86400 * 30), '/');
        
        return $session_id;
    }
}
