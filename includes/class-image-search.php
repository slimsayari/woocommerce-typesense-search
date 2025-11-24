<?php
/**
 * Image Search Class
 * Gère la recherche par image via OpenAI Vision
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Image_Search Class
 */
class WTS_Image_Search {
    
    /**
     * Single instance
     *
     * @var WTS_Image_Search
     */
    protected static $_instance = null;
    
    /**
     * Main Instance
     *
     * @return WTS_Image_Search
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
        
        // AJAX handler for image analysis
        add_action('wp_ajax_wts_analyze_image', array($this, 'analyze_image'));
        add_action('wp_ajax_nopriv_wts_analyze_image', array($this, 'analyze_image'));
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!WooCommerce_Typesense_Search::get_setting('image_search_enabled', false)) {
            return;
        }
        
        wp_register_script(
            'wts-image-search',
            WTS_PLUGIN_URL . 'assets/js/image-search.js',
            array('jquery'),
            WTS_VERSION,
            true
        );
        
        wp_localize_script('wts-image-search', 'wtsImage', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wts_image_search'),
            'maxSize' => 5 * 1024 * 1024, // 5MB
            'i18n' => array(
                'dropHere' => __('Déposez une image ici ou cliquez pour uploader', 'woocommerce-typesense-search'),
                'analyzing' => __('Analyse de l\'image...', 'woocommerce-typesense-search'),
                'error' => __('Erreur lors de l\'analyse.', 'woocommerce-typesense-search'),
                'fileTooBig' => __('L\'image est trop volumineuse (max 5MB).', 'woocommerce-typesense-search'),
            ),
        ));
        
        wp_enqueue_script('wts-image-search');
    }
    
    /**
     * Analyze image using OpenAI Vision
     */
    public function analyze_image() {
        check_ajax_referer('wts_image_search', 'nonce');
        
        if (empty($_FILES['image'])) {
            wp_send_json_error(array('message' => 'No image uploaded'));
        }
        
        $file = $_FILES['image'];
        $api_key = WooCommerce_Typesense_Search::get_setting('openai_api_key', '');
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'OpenAI API key missing'));
        }
        
        // Validate file
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'File too large'));
        }
        
        $allowed_types = array('image/jpeg', 'image/png', 'image/webp');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(array('message' => 'Invalid file type'));
        }
        
        // Encode image to base64
        $image_data = file_get_contents($file['tmp_name']);
        $base64_image = base64_encode($image_data);
        $data_uri = 'data:' . $file['type'] . ';base64,' . $base64_image;
        
        // Call OpenAI Vision API
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $prompt = "Analyze this product image for an e-commerce search. 
        Describe the product visually (color, type, material, style). 
        Return a JSON object with:
        - q: main search terms (e.g. 'red leather handbag')
        - filters: suggested filters (e.g. {'color': 'red', 'category': 'bags'})
        Keep it concise.";
        
        $data = array(
            'model' => 'gpt-4o-mini', // Use a vision-capable model
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array('type' => 'text', 'text' => $prompt),
                        array(
                            'type' => 'image_url',
                            'image_url' => array('url' => $data_uri)
                        )
                    )
                )
            ),
            'max_tokens' => 300,
            'response_format' => array('type' => 'json_object')
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($data),
            'timeout' => 30,
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            $analysis = json_decode($result['choices'][0]['message']['content'], true);
            wp_send_json_success($analysis);
        } else {
            wp_send_json_error(array('message' => 'Failed to analyze image'));
        }
    }
}
