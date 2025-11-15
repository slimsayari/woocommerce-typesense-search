<?php
/**
 * Search Widget Class
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Search_Widget Class
 */
class WTS_Search_Widget {
    
    /**
     * Single instance
     *
     * @var WTS_Search_Widget
     */
    protected static $_instance = null;
    
    /**
     * Main Instance
     *
     * @return WTS_Search_Widget
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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('widgets_init', array($this, 'register_widget'));
        
        // Replace default WooCommerce search
        add_filter('get_product_search_form', array($this, 'replace_search_form'), 10, 1);
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        if (!WooCommerce_Typesense_Search::get_setting('enabled', false)) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'wts-search',
            WTS_PLUGIN_URL . 'assets/css/search.css',
            array(),
            WTS_VERSION
        );
        
        // Enqueue main search JS
        wp_enqueue_script(
            'wts-search',
            WTS_PLUGIN_URL . 'assets/js/search.js',
            array('jquery'),
            WTS_VERSION,
            true
        );
        
        // Enqueue voice search if enabled
        if (WooCommerce_Typesense_Search::get_setting('voice_search_enabled', false)) {
            wp_enqueue_script(
                'wts-voice-search',
                WTS_PLUGIN_URL . 'assets/js/voice-search.js',
                array('jquery', 'wts-search'),
                WTS_VERSION,
                true
            );
        }
        
        // Enqueue image search if enabled
        if (WooCommerce_Typesense_Search::get_setting('image_search_enabled', false)) {
            wp_enqueue_script(
                'wts-image-search',
                WTS_PLUGIN_URL . 'assets/js/image-search.js',
                array('jquery', 'wts-search'),
                WTS_VERSION,
                true
            );
        }
        
        // Localize script
        wp_localize_script('wts-search', 'wtsConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('wts/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'voiceEnabled' => WooCommerce_Typesense_Search::get_setting('voice_search_enabled', false),
            'imageEnabled' => WooCommerce_Typesense_Search::get_setting('image_search_enabled', false),
            'i18n' => array(
                'searching' => __('Searching...', 'woocommerce-typesense-search'),
                'noResults' => __('No products found', 'woocommerce-typesense-search'),
                'voiceStart' => __('Click to start voice search', 'woocommerce-typesense-search'),
                'voiceListening' => __('Listening...', 'woocommerce-typesense-search'),
                'imageUpload' => __('Upload image to search', 'woocommerce-typesense-search'),
                'loadMore' => __('Load more', 'woocommerce-typesense-search'),
            ),
        ));
    }
    
    /**
     * Register widget
     */
    public function register_widget() {
        register_widget('WTS_Search_Widget_Class');
    }
    
    /**
     * Replace default search form
     *
     * @param string $form
     * @return string
     */
    public function replace_search_form($form) {
        if (!WooCommerce_Typesense_Search::get_setting('enabled', false)) {
            return $form;
        }
        
        ob_start();
        wc_get_template(
            'search-form.php',
            array('atts' => array()),
            'woocommerce-typesense-search',
            WTS_PLUGIN_DIR . 'templates/'
        );
        return ob_get_clean();
    }
}

/**
 * WTS Search Widget Class
 */
class WTS_Search_Widget_Class extends WP_Widget {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'wts_search_widget',
            __('Typesense Product Search', 'woocommerce-typesense-search'),
            array(
                'description' => __('Advanced product search powered by Typesense', 'woocommerce-typesense-search'),
            )
        );
    }
    
    /**
     * Widget output
     *
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance) {
        if (!WooCommerce_Typesense_Search::get_setting('enabled', false)) {
            return;
        }
        
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        $atts = array(
            'placeholder' => $instance['placeholder'] ?? __('Search products...', 'woocommerce-typesense-search'),
            'show_filters' => $instance['show_filters'] ?? 'yes',
            'show_voice' => $instance['show_voice'] ?? 'yes',
            'show_image' => $instance['show_image'] ?? 'yes',
        );
        
        wc_get_template(
            'search-form.php',
            array('atts' => $atts),
            'woocommerce-typesense-search',
            WTS_PLUGIN_DIR . 'templates/'
        );
        
        echo $args['after_widget'];
    }
    
    /**
     * Widget form
     *
     * @param array $instance
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $placeholder = !empty($instance['placeholder']) ? $instance['placeholder'] : __('Search products...', 'woocommerce-typesense-search');
        $show_filters = !empty($instance['show_filters']) ? $instance['show_filters'] : 'yes';
        $show_voice = !empty($instance['show_voice']) ? $instance['show_voice'] : 'yes';
        $show_image = !empty($instance['show_image']) ? $instance['show_image'] : 'yes';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php _e('Title:', 'woocommerce-typesense-search'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('placeholder')); ?>">
                <?php _e('Placeholder:', 'woocommerce-typesense-search'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('placeholder')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('placeholder')); ?>" 
                   type="text" value="<?php echo esc_attr($placeholder); ?>">
        </p>
        <p>
            <input class="checkbox" type="checkbox" 
                   <?php checked($show_filters, 'yes'); ?> 
                   id="<?php echo esc_attr($this->get_field_id('show_filters')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_filters')); ?>" 
                   value="yes">
            <label for="<?php echo esc_attr($this->get_field_id('show_filters')); ?>">
                <?php _e('Show filters', 'woocommerce-typesense-search'); ?>
            </label>
        </p>
        <p>
            <input class="checkbox" type="checkbox" 
                   <?php checked($show_voice, 'yes'); ?> 
                   id="<?php echo esc_attr($this->get_field_id('show_voice')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_voice')); ?>" 
                   value="yes">
            <label for="<?php echo esc_attr($this->get_field_id('show_voice')); ?>">
                <?php _e('Show voice search', 'woocommerce-typesense-search'); ?>
            </label>
        </p>
        <p>
            <input class="checkbox" type="checkbox" 
                   <?php checked($show_image, 'yes'); ?> 
                   id="<?php echo esc_attr($this->get_field_id('show_image')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('show_image')); ?>" 
                   value="yes">
            <label for="<?php echo esc_attr($this->get_field_id('show_image')); ?>">
                <?php _e('Show image search', 'woocommerce-typesense-search'); ?>
            </label>
        </p>
        <?php
    }
    
    /**
     * Update widget
     *
     * @param array $new_instance
     * @param array $old_instance
     * @return array
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['placeholder'] = (!empty($new_instance['placeholder'])) ? sanitize_text_field($new_instance['placeholder']) : '';
        $instance['show_filters'] = (!empty($new_instance['show_filters'])) ? 'yes' : 'no';
        $instance['show_voice'] = (!empty($new_instance['show_voice'])) ? 'yes' : 'no';
        $instance['show_image'] = (!empty($new_instance['show_image'])) ? 'yes' : 'no';
        return $instance;
    }
}
