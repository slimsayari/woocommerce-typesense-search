<?php
/**
 * Plugin Name: WooCommerce Typesense Search
 * Plugin URI: https://webntricks.com
 * Description: Recherche instantanée et intelligente pour WooCommerce avec Typesense (recherche textuelle, vocale, visuelle et sémantique)
 * Version: 1.0.0
 * Author: Slim Sayari
 * Author URI: https://webntricks.com
 * Text Domain: woocommerce-typesense-search
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WTS_VERSION', '1.0.0');
define('WTS_PLUGIN_FILE', __FILE__);
define('WTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main WooCommerce Typesense Search Class
 */
class WooCommerce_Typesense_Search {
    
    /**
     * Single instance of the class
     *
     * @var WooCommerce_Typesense_Search
     */
    protected static $_instance = null;
    
    /**
     * Main Instance
     *
     * @return WooCommerce_Typesense_Search
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
        $this->init_hooks();
        $this->includes();
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        
        // Check for WooCommerce dependency
        add_action('admin_init', array($this, 'check_dependencies'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once WTS_PLUGIN_DIR . 'includes/class-typesense-client.php';
        require_once WTS_PLUGIN_DIR . 'includes/class-product-indexer.php';
        require_once WTS_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once WTS_PLUGIN_DIR . 'includes/class-search-widget.php';
        require_once WTS_PLUGIN_DIR . 'includes/class-rest-api.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize admin settings
        if (is_admin()) {
            WTS_Admin_Settings::instance();
        }
        
        // Initialize REST API
        WTS_Rest_API::instance();
        
        // Initialize search widget
        WTS_Search_Widget::instance();
        
        // Initialize product indexer
        WTS_Product_Indexer::instance();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create default options
        $default_options = array(
            'enabled' => false,
            'host' => '',
            'port' => '8108',
            'protocol' => 'https',
            'api_key' => '',
            'collection_name' => 'products',
            'auto_sync' => true,
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'typo_tolerance' => true,
            'voice_search_enabled' => true,
            'image_search_enabled' => true,
            'semantic_search_enabled' => false,
            'openai_api_key' => '',
        );
        
        add_option('wts_settings', $default_options);
        
        // Create analytics table
        $this->create_analytics_table();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create analytics table
     */
    private function create_analytics_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wts_analytics';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            search_term varchar(255) NOT NULL,
            results_count int(11) NOT NULL DEFAULT 0,
            clicked_product_id bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            session_id varchar(100) DEFAULT NULL,
            search_type varchar(50) DEFAULT 'text',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY search_term (search_term),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'woocommerce-typesense-search',
            false,
            dirname(WTS_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Register shortcodes
        add_shortcode('wts_search', array($this, 'search_shortcode'));
        
        // Hook for WPML/Polylang support
        do_action('wts_init');
    }
    
    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            deactivate_plugins(WTS_PLUGIN_BASENAME);
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('WooCommerce Typesense Search requires WooCommerce to be installed and activated.', 'woocommerce-typesense-search'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Search shortcode
     *
     * @param array $atts
     * @return string
     */
    public function search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => __('Search products...', 'woocommerce-typesense-search'),
            'show_filters' => 'yes',
            'show_voice' => 'yes',
            'show_image' => 'yes',
            'results_per_page' => 12,
        ), $atts);
        
        ob_start();
        wc_get_template(
            'search-form.php',
            array('atts' => $atts),
            'woocommerce-typesense-search',
            WTS_PLUGIN_DIR . 'templates/'
        );
        return ob_get_clean();
    }
    
    /**
     * Get plugin settings
     *
     * @return array
     */
    public static function get_settings() {
        return get_option('wts_settings', array());
    }
    
    /**
     * Get specific setting
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get_setting($key, $default = null) {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}

/**
 * Returns the main instance of WooCommerce_Typesense_Search
 *
 * @return WooCommerce_Typesense_Search
 */
function WTS() {
    return WooCommerce_Typesense_Search::instance();
}

// Initialize the plugin
WTS();
