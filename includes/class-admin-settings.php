<?php
/**
 * Admin Settings Class
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Admin_Settings Class
 */
class WTS_Admin_Settings {
    
    /**
     * Single instance
     *
     * @var WTS_Admin_Settings
     */
    protected static $_instance = null;
    
    /**
     * Main Instance
     *
     * @return WTS_Admin_Settings
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
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_typesense', array($this, 'settings_tab'));
        add_action('woocommerce_update_options_typesense', array($this, 'update_settings'));
        add_action('woocommerce_admin_field_wts_test_connection', array($this, 'test_connection_field'));
        add_action('woocommerce_admin_field_wts_bulk_sync', array($this, 'bulk_sync_field'));
        add_action('woocommerce_admin_field_wts_analytics', array($this, 'analytics_field'));
        
        // AJAX handlers
        add_action('wp_ajax_wts_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_wts_export_analytics', array($this, 'ajax_export_analytics'));
        
        // Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add settings tab to WooCommerce
     *
     * @param array $tabs
     * @return array
     */
    public function add_settings_tab($tabs) {
        $tabs['typesense'] = __('Typesense', 'woocommerce-typesense-search');
        return $tabs;
    }
    
    /**
     * Settings tab content
     */
    public function settings_tab() {
        woocommerce_admin_fields($this->get_settings());
    }
    
    /**
     * Update settings
     */
    public function update_settings() {
        $settings = $this->get_settings();
        
        // Collect all setting values
        $values = array();
        foreach ($settings as $setting) {
            if (isset($setting['id']) && !in_array($setting['type'], array('title', 'sectionend', 'wts_test_connection', 'wts_bulk_sync', 'wts_analytics'))) {
                $option_value = null;
                
                if (isset($_POST[$setting['id']])) {
                    $option_value = $_POST[$setting['id']];
                } elseif ($setting['type'] === 'checkbox') {
                    $option_value = 'no';
                }
                
                if ($option_value !== null) {
                    $key = str_replace('wts_', '', $setting['id']);
                    $values[$key] = $option_value;
                }
            }
        }
        
        update_option('wts_settings', $values);
    }
    
    /**
     * Get settings array
     *
     * @return array
     */
    public function get_settings() {
        $settings = array(
            array(
                'title' => __('Typesense Configuration', 'woocommerce-typesense-search'),
                'type' => 'title',
                'desc' => __('Configure your Typesense server connection.', 'woocommerce-typesense-search'),
                'id' => 'wts_config',
            ),
            array(
                'title' => __('Enable Typesense Search', 'woocommerce-typesense-search'),
                'desc' => __('Enable Typesense search functionality', 'woocommerce-typesense-search'),
                'id' => 'wts_enabled',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('Host', 'woocommerce-typesense-search'),
                'desc' => __('Typesense server host (e.g., localhost or typesense.example.com)', 'woocommerce-typesense-search'),
                'id' => 'wts_host',
                'type' => 'text',
                'default' => '',
                'placeholder' => 'localhost',
            ),
            array(
                'title' => __('Port', 'woocommerce-typesense-search'),
                'desc' => __('Typesense server port', 'woocommerce-typesense-search'),
                'id' => 'wts_port',
                'type' => 'text',
                'default' => '8108',
                'placeholder' => '8108',
            ),
            array(
                'title' => __('Protocol', 'woocommerce-typesense-search'),
                'desc' => __('Connection protocol', 'woocommerce-typesense-search'),
                'id' => 'wts_protocol',
                'type' => 'select',
                'default' => 'https',
                'options' => array(
                    'http' => 'HTTP',
                    'https' => 'HTTPS',
                ),
            ),
            array(
                'title' => __('API Key', 'woocommerce-typesense-search'),
                'desc' => __('Typesense API key', 'woocommerce-typesense-search'),
                'id' => 'wts_api_key',
                'type' => 'password',
                'default' => '',
            ),
            array(
                'title' => __('Collection Name', 'woocommerce-typesense-search'),
                'desc' => __('Name of the Typesense collection for products', 'woocommerce-typesense-search'),
                'id' => 'wts_collection_name',
                'type' => 'text',
                'default' => 'products',
                'placeholder' => 'products',
            ),
            array(
                'type' => 'wts_test_connection',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wts_config',
            ),
            array(
                'title' => __('Synchronization Settings', 'woocommerce-typesense-search'),
                'type' => 'title',
                'desc' => __('Configure product synchronization with Typesense.', 'woocommerce-typesense-search'),
                'id' => 'wts_sync',
            ),
            array(
                'title' => __('Auto Sync', 'woocommerce-typesense-search'),
                'desc' => __('Automatically sync products when they are created, updated, or deleted', 'woocommerce-typesense-search'),
                'id' => 'wts_auto_sync',
                'default' => 'yes',
                'type' => 'checkbox',
            ),
            array(
                'type' => 'wts_bulk_sync',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wts_sync',
            ),
            array(
                'title' => __('Search Features', 'woocommerce-typesense-search'),
                'type' => 'title',
                'desc' => __('Enable or disable advanced search features.', 'woocommerce-typesense-search'),
                'id' => 'wts_features',
            ),
            array(
                'title' => __('Typo Tolerance', 'woocommerce-typesense-search'),
                'desc' => __('Enable typo-tolerant search', 'woocommerce-typesense-search'),
                'id' => 'wts_typo_tolerance',
                'default' => 'yes',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('Voice Search', 'woocommerce-typesense-search'),
                'desc' => __('Enable voice search functionality', 'woocommerce-typesense-search'),
                'id' => 'wts_voice_search_enabled',
                'default' => 'yes',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('Image Search', 'woocommerce-typesense-search'),
                'desc' => __('Enable image-based search', 'woocommerce-typesense-search'),
                'id' => 'wts_image_search_enabled',
                'default' => 'yes',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('Semantic Search', 'woocommerce-typesense-search'),
                'desc' => __('Enable semantic search with embeddings (requires OpenAI API key)', 'woocommerce-typesense-search'),
                'id' => 'wts_semantic_search_enabled',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('OpenAI API Key', 'woocommerce-typesense-search'),
                'desc' => __('Required for semantic search and image analysis', 'woocommerce-typesense-search'),
                'id' => 'wts_openai_api_key',
                'type' => 'password',
                'default' => '',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wts_features',
            ),
            array(
                'title' => __('Performance Settings', 'woocommerce-typesense-search'),
                'type' => 'title',
                'desc' => __('Optimize search performance.', 'woocommerce-typesense-search'),
                'id' => 'wts_performance',
            ),
            array(
                'title' => __('Enable Cache', 'woocommerce-typesense-search'),
                'desc' => __('Cache frequent search results', 'woocommerce-typesense-search'),
                'id' => 'wts_cache_enabled',
                'default' => 'yes',
                'type' => 'checkbox',
            ),
            array(
                'title' => __('Cache TTL', 'woocommerce-typesense-search'),
                'desc' => __('Cache time-to-live in seconds', 'woocommerce-typesense-search'),
                'id' => 'wts_cache_ttl',
                'type' => 'number',
                'default' => '3600',
                'custom_attributes' => array(
                    'min' => '60',
                    'step' => '60',
                ),
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wts_performance',
            ),
            array(
                'title' => __('Analytics', 'woocommerce-typesense-search'),
                'type' => 'title',
                'desc' => __('View search analytics and statistics.', 'woocommerce-typesense-search'),
                'id' => 'wts_analytics_section',
            ),
            array(
                'type' => 'wts_analytics',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wts_analytics_section',
            ),
        );
        
        return apply_filters('wts_settings', $settings);
    }
    
    /**
     * Test connection field
     */
    public function test_connection_field() {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php _e('Test Connection', 'woocommerce-typesense-search'); ?>
            </th>
            <td class="forminp">
                <button type="button" id="wts-test-connection" class="button button-secondary">
                    <?php _e('Test Connection', 'woocommerce-typesense-search'); ?>
                </button>
                <span id="wts-connection-status"></span>
                <p class="description">
                    <?php _e('Test the connection to your Typesense server.', 'woocommerce-typesense-search'); ?>
                </p>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Bulk sync field
     */
    public function bulk_sync_field() {
        $total_products = WTS_Product_Indexer::instance()->get_total_products();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php _e('Bulk Sync', 'woocommerce-typesense-search'); ?>
            </th>
            <td class="forminp">
                <button type="button" id="wts-bulk-sync" class="button button-primary">
                    <?php _e('Sync All Products', 'woocommerce-typesense-search'); ?>
                </button>
                <p class="description">
                    <?php printf(__('Total products: %d', 'woocommerce-typesense-search'), $total_products); ?>
                </p>
                <div id="wts-sync-progress" style="display:none; margin-top: 10px;">
                    <progress id="wts-progress-bar" max="100" value="0" style="width: 100%; height: 30px;"></progress>
                    <p id="wts-progress-text">0 / 0</p>
                </div>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Analytics field
     */
    public function analytics_field() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wts_analytics';
        
        // Get popular searches
        $popular_searches = $wpdb->get_results(
            "SELECT search_term, COUNT(*) as count, AVG(results_count) as avg_results
            FROM $table_name
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY search_term
            ORDER BY count DESC
            LIMIT 10",
            ARRAY_A
        );
        
        // Get searches with no results
        $no_results = $wpdb->get_results(
            "SELECT search_term, COUNT(*) as count
            FROM $table_name
            WHERE results_count = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY search_term
            ORDER BY count DESC
            LIMIT 10",
            ARRAY_A
        );
        
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php _e('Popular Searches (Last 30 Days)', 'woocommerce-typesense-search'); ?>
            </th>
            <td class="forminp">
                <?php if (!empty($popular_searches)) : ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Search Term', 'woocommerce-typesense-search'); ?></th>
                                <th><?php _e('Count', 'woocommerce-typesense-search'); ?></th>
                                <th><?php _e('Avg Results', 'woocommerce-typesense-search'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popular_searches as $search) : ?>
                                <tr>
                                    <td><?php echo esc_html($search['search_term']); ?></td>
                                    <td><?php echo esc_html($search['count']); ?></td>
                                    <td><?php echo esc_html(round($search['avg_results'], 1)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php _e('No search data available yet.', 'woocommerce-typesense-search'); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php _e('Searches with No Results', 'woocommerce-typesense-search'); ?>
            </th>
            <td class="forminp">
                <?php if (!empty($no_results)) : ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Search Term', 'woocommerce-typesense-search'); ?></th>
                                <th><?php _e('Count', 'woocommerce-typesense-search'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($no_results as $search) : ?>
                                <tr>
                                    <td><?php echo esc_html($search['search_term']); ?></td>
                                    <td><?php echo esc_html($search['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php _e('No searches without results.', 'woocommerce-typesense-search'); ?></p>
                <?php endif; ?>
                <p>
                    <button type="button" id="wts-export-analytics" class="button button-secondary">
                        <?php _e('Export Analytics (CSV)', 'woocommerce-typesense-search'); ?>
                    </button>
                </p>
            </td>
        </tr>
        <?php
    }
    
    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('wts_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'woocommerce-typesense-search')));
        }
        
        $client = WTS_Typesense_Client::instance();
        $result = $client->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } elseif ($result === true) {
            wp_send_json_success(array('message' => __('Connection successful!', 'woocommerce-typesense-search')));
        } else {
            wp_send_json_error(array('message' => __('Connection failed.', 'woocommerce-typesense-search')));
        }
    }
    
    /**
     * AJAX export analytics
     */
    public function ajax_export_analytics() {
        check_ajax_referer('wts_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'woocommerce-typesense-search'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wts_analytics';
        
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10000",
            ARRAY_A
        );
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=wts-analytics-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        if (!empty($results)) {
            fputcsv($output, array_keys($results[0]));
            foreach ($results as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Enqueue admin scripts
     *
     * @param string $hook
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }
        
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'typesense') {
            return;
        }
        
        wp_enqueue_script(
            'wts-admin',
            WTS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WTS_VERSION,
            true
        );
        
        wp_localize_script('wts-admin', 'wtsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wts_admin'),
            'bulkSyncNonce' => wp_create_nonce('wts_bulk_sync'),
        ));
    }
}
