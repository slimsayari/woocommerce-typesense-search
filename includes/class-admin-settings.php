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
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_wts_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_wts_export_analytics', array($this, 'ajax_export_analytics'));
        
        // Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Typesense Search', 'woocommerce-typesense-search'),
            __('Typesense', 'woocommerce-typesense-search'),
            'manage_options',
            'wts-settings',
            array($this, 'render_settings_page'),
            'dashicons-search',
            56
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wts_settings_group', 'wts_settings');
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('wts_settings_group');
                $this->render_settings_fields();
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render settings fields
     */
    public function render_settings_fields() {
        $settings = $this->get_settings();
        
        echo '<table class="form-table" role="presentation">';
        
        foreach ($settings as $setting) {
            if ($setting['type'] === 'title') {
                if ($setting['id'] !== 'wts_config') {
                    echo '</table>';
                }
                echo '<h2>' . esc_html($setting['title']) . '</h2>';
                if (!empty($setting['desc'])) {
                    echo '<p>' . esc_html($setting['desc']) . '</p>';
                }
                echo '<table class="form-table" role="presentation">';
                continue;
            }
            
            if ($setting['type'] === 'sectionend') {
                continue;
            }

            // Custom fields
            if ($setting['type'] === 'wts_test_connection') {
                $this->test_connection_field();
                continue;
            }
            if ($setting['type'] === 'wts_bulk_sync') {
                $this->bulk_sync_field();
                continue;
            }
            if ($setting['type'] === 'wts_analytics') {
                $this->analytics_field();
                continue;
            }

            // Standard fields
            $id = $setting['id'];
            $key = str_replace('wts_', '', $id);
            $name = "wts_settings[$key]";
            $value = isset($setting['value']) ? $setting['value'] : '';
            
            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($setting['title']) . '</label></th>';
            echo '<td>';
            
            switch ($setting['type']) {
                case 'text':
                case 'password':
                    $type = $setting['type'];
                    $readonly = isset($setting['custom_attributes']['readonly']) ? 'readonly' : '';
                    $placeholder = isset($setting['placeholder']) ? 'placeholder="' . esc_attr($setting['placeholder']) . '"' : '';
                    echo '<input name="' . esc_attr($name) . '" type="' . $type . '" id="' . esc_attr($id) . '" value="' . esc_attr($value) . '" class="regular-text" ' . $readonly . ' ' . $placeholder . ' />';
                    break;
                    
                case 'checkbox':
                    echo '<input type="hidden" name="' . esc_attr($name) . '" value="no">';
                    echo '<input name="' . esc_attr($name) . '" type="checkbox" id="' . esc_attr($id) . '" value="yes" ' . checked('yes', $value, false) . ' />';
                    break;
                    
                case 'select':
                    echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '">';
                    foreach ($setting['options'] as $opt_val => $opt_label) {
                        echo '<option value="' . esc_attr($opt_val) . '" ' . selected($opt_val, $value, false) . '>' . esc_html($opt_label) . '</option>';
                    }
                    echo '</select>';
                    break;
                    
                case 'number':
                    $min = isset($setting['custom_attributes']['min']) ? 'min="' . $setting['custom_attributes']['min'] . '"' : '';
                    $step = isset($setting['custom_attributes']['step']) ? 'step="' . $setting['custom_attributes']['step'] . '"' : '';
                    echo '<input name="' . esc_attr($name) . '" type="number" id="' . esc_attr($id) . '" value="' . esc_attr($value) . '" class="small-text" ' . $min . ' ' . $step . ' />';
                    break;
            }
            
            if (!empty($setting['desc'])) {
                echo '<p class="description">' . esc_html($setting['desc']) . '</p>';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    /**
     * Get settings array
     *
     * @return array
     */
    public function get_settings() {
        $opts = get_option('wts_settings', array());
        
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
                'value' => isset($opts['enabled']) ? $opts['enabled'] : 'no',
            ),
            array(
                'title' => __('Host', 'woocommerce-typesense-search'),
                'desc' => __('Typesense server host (auto-detected from Docker)', 'woocommerce-typesense-search'),
                'id' => 'wts_host',
                'type' => 'text',
                'default' => $this->get_default_host(),
                'placeholder' => 'typesense',
                'value' => isset($opts['host']) ? $opts['host'] : $this->get_default_host(),
            ),
            array(
                'title' => __('Port', 'woocommerce-typesense-search'),
                'desc' => __('Typesense server port (auto-detected from Docker)', 'woocommerce-typesense-search'),
                'id' => 'wts_port',
                'type' => 'text',
                'default' => $this->get_default_port(),
                'placeholder' => '8108',
                'value' => isset($opts['port']) ? $opts['port'] : $this->get_default_port(),
            ),
            array(
                'title' => __('Protocol', 'woocommerce-typesense-search'),
                'desc' => __('Connection protocol (auto-detected from Docker)', 'woocommerce-typesense-search'),
                'id' => 'wts_protocol',
                'type' => 'select',
                'default' => $this->get_default_protocol(),
                'options' => array(
                    'http' => 'HTTP',
                    'https' => 'HTTPS',
                ),
                'value' => isset($opts['protocol']) ? $opts['protocol'] : $this->get_default_protocol(),
            ),
            array(
                'title' => __('Connection URL', 'woocommerce-typesense-search'),
                'desc' => __('Generated URL based on settings', 'woocommerce-typesense-search'),
                'id' => 'wts_connection_url',
                'type' => 'text',
                'custom_attributes' => array('readonly' => 'readonly'),
                'value' => (isset($opts['protocol']) ? $opts['protocol'] : $this->get_default_protocol()) . '://' . (isset($opts['host']) ? $opts['host'] : $this->get_default_host()) . ':' . (isset($opts['port']) ? $opts['port'] : $this->get_default_port()),
            ),
            array(
                'title' => __('API Key', 'woocommerce-typesense-search'),
                'desc' => __('Typesense API key (auto-detected from Docker)', 'woocommerce-typesense-search'),
                'id' => 'wts_api_key',
                'type' => 'password',
                'default' => $this->get_default_api_key(),
                'value' => isset($opts['api_key']) ? $opts['api_key'] : $this->get_default_api_key(),
            ),
            array(
                'title' => __('Collection Name', 'woocommerce-typesense-search'),
                'desc' => __('Name of the Typesense collection for products', 'woocommerce-typesense-search'),
                'id' => 'wts_collection_name',
                'type' => 'text',
                'default' => 'products',
                'placeholder' => 'products',
                'value' => isset($opts['collection_name']) ? $opts['collection_name'] : 'products',
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
                'value' => isset($opts['auto_sync']) ? $opts['auto_sync'] : 'yes',
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
                'value' => isset($opts['typo_tolerance']) ? $opts['typo_tolerance'] : 'yes',
            ),
            array(
                'title' => __('Voice Search', 'woocommerce-typesense-search'),
                'desc' => __('Enable voice search functionality', 'woocommerce-typesense-search'),
                'id' => 'wts_voice_search_enabled',
                'default' => 'yes',
                'type' => 'checkbox',
                'value' => isset($opts['voice_search_enabled']) ? $opts['voice_search_enabled'] : 'yes',
            ),
            array(
                'title' => __('Image Search', 'woocommerce-typesense-search'),
                'desc' => __('Enable image-based search', 'woocommerce-typesense-search'),
                'id' => 'wts_image_search_enabled',
                'default' => 'yes',
                'type' => 'checkbox',
                'value' => isset($opts['image_search_enabled']) ? $opts['image_search_enabled'] : 'yes',
            ),
            array(
                'title' => __('Semantic Search', 'woocommerce-typesense-search'),
                'desc' => __('Enable semantic search with embeddings (requires OpenAI API key)', 'woocommerce-typesense-search'),
                'id' => 'wts_semantic_search_enabled',
                'default' => 'no',
                'type' => 'checkbox',
                'value' => isset($opts['semantic_search_enabled']) ? $opts['semantic_search_enabled'] : 'no',
            ),
            array(
                'title' => __('OpenAI API Key', 'woocommerce-typesense-search'),
                'desc' => __('Required for semantic search and image analysis', 'woocommerce-typesense-search'),
                'id' => 'wts_openai_api_key',
                'type' => 'password',
                'default' => '',
                'value' => isset($opts['openai_api_key']) ? $opts['openai_api_key'] : '',
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
                'value' => isset($opts['cache_enabled']) ? $opts['cache_enabled'] : 'yes',
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
                'value' => isset($opts['cache_ttl']) ? $opts['cache_ttl'] : '3600',
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
     * Get default host from environment
     *
     * @return string
     */
    private function get_default_host() {
        // Try to detect from Docker environment
        // In Docker, the service name is 'typesense'
        return 'typesense';
    }
    
    /**
     * Get default port from environment
     *
     * @return string
     */
    private function get_default_port() {
        // Try to get from environment variable
        $port = getenv('TYPESENSE_PORT');
        return $port ? $port : '8108';
    }
    
    /**
     * Get default protocol from environment
     *
     * @return string
     */
    private function get_default_protocol() {
        // In Docker local environment, use HTTP
        // In production, use HTTPS
        return (defined('WP_ENV') && WP_ENV === 'production') ? 'https' : 'http';
    }
    
    /**
     * Get default API key from environment
     *
     * @return string
     */
    private function get_default_api_key() {
        // Try to get from environment variable
        $api_key = getenv('TYPESENSE_API_KEY');
        return $api_key ? $api_key : '';
    }
    
    /**
     * Get connection URL for display
     *
     * @param array $opts
     * @return string
     */
    private function get_connection_url($opts) {
        $protocol = isset($opts['protocol']) ? $opts['protocol'] : $this->get_default_protocol();
        $host = isset($opts['host']) ? $opts['host'] : $this->get_default_host();
        $port = isset($opts['port']) ? $opts['port'] : $this->get_default_port();
        return "$protocol://$host:$port";
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
        $sync_manager = WTS_Sync_Manager::instance();
        $totals = $sync_manager->get_totals();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php _e('Synchronisation', 'woocommerce-typesense-search'); ?>
            </th>
            <td class="forminp">
                <div style="margin-bottom: 20px;">
                    <h4><?php _e('Statistiques', 'woocommerce-typesense-search'); ?></h4>
                    <table class="widefat" style="max-width: 500px;">
                        <thead>
                            <tr>
                                <th><?php _e('Type', 'woocommerce-typesense-search'); ?></th>
                                <th><?php _e('Total', 'woocommerce-typesense-search'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php _e('Produits', 'woocommerce-typesense-search'); ?></td>
                                <td><strong><?php echo number_format($totals['products']); ?></strong></td>
                            </tr>
                            <tr>
                                <td><?php _e('Articles', 'woocommerce-typesense-search'); ?></td>
                                <td><strong><?php echo number_format($totals['posts']); ?></strong></td>
                            </tr>
                            <tr>
                                <td><?php _e('Catégories', 'woocommerce-typesense-search'); ?></td>
                                <td><strong><?php echo number_format($totals['categories']); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <button type="button" id="wts-sync-products" class="button button-primary">
                        <?php _e('🔄 Synchroniser les Produits', 'woocommerce-typesense-search'); ?>
                    </button>
                    <button type="button" id="wts-sync-posts" class="button button-primary" style="margin-left: 10px;">
                        <?php _e('📝 Synchroniser les Articles', 'woocommerce-typesense-search'); ?>
                    </button>
                    <button type="button" id="wts-sync-all" class="button button-secondary" style="margin-left: 10px;">
                        <?php _e('⚡ Tout Synchroniser', 'woocommerce-typesense-search'); ?>
                    </button>
                </div>
                
                <div id="wts-sync-progress" style="display:none; margin-top: 15px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
                    <p id="wts-sync-status" style="margin: 0 0 10px 0; font-weight: bold;"></p>
                    <progress id="wts-progress-bar" max="100" value="0" style="width: 100%; height: 30px;"></progress>
                    <p id="wts-progress-text" style="margin: 10px 0 0 0;">0 / 0</p>
                </div>
                
                <p class="description">
                    <?php _e('La synchronisation se fait par lots de 50 éléments pour éviter les timeouts.', 'woocommerce-typesense-search'); ?>
                </p>
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
        if ($hook !== 'toplevel_page_wts-settings') {
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
