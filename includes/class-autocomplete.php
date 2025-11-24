<?php
/**
 * Autocomplete Class
 * Gère la recherche autocomplete multi-sources (produits, articles, catégories)
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Autocomplete Class
 */
class WTS_Autocomplete {
    
    /**
     * Single instance
     *
     * @var WTS_Autocomplete
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
     * @return WTS_Autocomplete
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
        
        // Register shortcode
        add_shortcode('wts_search', array($this, 'render_shortcode'));
        
        // AJAX handler for autocomplete
        add_action('wp_ajax_wts_autocomplete', array($this, 'ajax_autocomplete'));
        add_action('wp_ajax_nopriv_wts_autocomplete', array($this, 'ajax_autocomplete'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        wp_register_script(
            'wts-autocomplete',
            WTS_PLUGIN_URL . 'assets/js/autocomplete.js',
            array('jquery'),
            WTS_VERSION,
            true
        );
        
        wp_localize_script('wts-autocomplete', 'wtsAutocomplete', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wts_autocomplete'),
            'minChars' => 2,
            'delay' => 300,
        ));
    }
    
    /**
     * Render search shortcode
     *
     * @param array $atts
     * @return string
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => __('Rechercher...', 'woocommerce-typesense-search'),
            'show_voice' => 'yes',
            'show_image' => 'yes',
        ), $atts, 'wts_search');
        
        wp_enqueue_script('wts-autocomplete');
        wp_enqueue_style('wts-search'); // Ensure CSS is loaded
        
        ob_start();
        ?>
        <div class="wts-search-container">
            <form role="search" method="get" class="wts-search-form" action="<?php echo esc_url(home_url('/')); ?>">
                <div class="wts-search-input-wrapper">
                    <input type="search" 
                           class="wts-search-input" 
                           name="s" 
                           placeholder="<?php echo esc_attr($atts['placeholder']); ?>" 
                           autocomplete="off"
                           value="<?php echo get_search_query(); ?>">
                    
                    <div class="wts-search-actions">
                        <?php if ($atts['show_voice'] === 'yes') : ?>
                            <button type="button" class="wts-voice-trigger" title="<?php _e('Recherche vocale', 'woocommerce-typesense-search'); ?>">
                                🎤
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_image'] === 'yes') : ?>
                            <button type="button" class="wts-image-trigger" title="<?php _e('Recherche par image', 'woocommerce-typesense-search'); ?>">
                                📷
                            </button>
                        <?php endif; ?>
                        
                        <button type="submit" class="wts-search-submit">
                            🔍
                        </button>
                    </div>
                </div>
                
                <!-- Autocomplete Dropdown -->
                <div class="wts-autocomplete-dropdown" style="display:none;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX Autocomplete
     */
    public function ajax_autocomplete() {
        check_ajax_referer('wts_autocomplete', 'nonce');
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        
        if (strlen($query) < 2) {
            wp_send_json_success(array('results' => array()));
        }
        
        // Multi-search request
        // Multi-search request
        $searches = array(
            array(
                'collection' => 'products',
                'q' => $query,
                'query_by' => 'name,description,sku',
                'per_page' => 5,
                'highlight_full_fields' => 'name',
            ),
            array(
                'collection' => 'posts',
                'q' => $query,
                'query_by' => 'title,content',
                'per_page' => 3,
                'highlight_full_fields' => 'title',
            )
        );
        
        // Perform multi-search
        $results = $this->client->multi_search($searches);
        
        if (is_wp_error($results)) {
            wp_send_json_error(array('message' => $results->get_error_message()));
        }
        
        // Process results
        $processed_results = array(
            'products' => array(),
            'posts' => array(),
            'categories' => array(), // Derived from facets or separate search
        );
        
        // Products
        if (isset($results['results'][0]['hits'])) {
            foreach ($results['results'][0]['hits'] as $hit) {
                $document = $hit['document'];
                $highlight = isset($hit['highlight']['name']['snippet']) ? $hit['highlight']['name']['snippet'] : $document['name'];
                
                $processed_results['products'][] = array(
                    'id' => $document['id'],
                    'title' => $highlight,
                    'url' => $document['url'],
                    'price' => $document['price'],
                    'image' => $document['image_url'],
                    'type' => 'product'
                );
            }
        }
        
        // Posts
        if (isset($results['results'][1]['hits'])) {
            foreach ($results['results'][1]['hits'] as $hit) {
                $document = $hit['document'];
                $highlight = isset($hit['highlight']['title']['snippet']) ? $hit['highlight']['title']['snippet'] : $document['title'];
                
                $processed_results['posts'][] = array(
                    'id' => $document['id'],
                    'title' => $highlight,
                    'url' => $document['url'],
                    'image' => $document['image_url'],
                    'type' => 'post'
                );
            }
        }
        
        // Categories (Search for categories separately or extract from facets)
        // For now, let's do a simple search on products to get category facets matching the query
        // Or we could index categories as a separate collection.
        // Let's use facet extraction from products for now as it's simpler.
        
        wp_send_json_success($processed_results);
    }
}
