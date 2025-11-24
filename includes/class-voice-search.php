<?php
/**
 * Voice Search Class
 * Gère la recherche vocale et l'analyse d'intention
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Voice_Search Class
 */
class WTS_Voice_Search {
    
    /**
     * Single instance
     *
     * @var WTS_Voice_Search
     */
    protected static $_instance = null;
    
    /**
     * Main Instance
     *
     * @return WTS_Voice_Search
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
        
        // AJAX handler for intent analysis (optional, requires OpenAI)
        add_action('wp_ajax_wts_analyze_intent', array($this, 'analyze_intent'));
        add_action('wp_ajax_nopriv_wts_analyze_intent', array($this, 'analyze_intent'));
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!WooCommerce_Typesense_Search::get_setting('voice_search_enabled', false)) {
            return;
        }
        
        wp_register_script(
            'wts-voice-search',
            WTS_PLUGIN_URL . 'assets/js/voice-search.js',
            array('jquery'),
            WTS_VERSION,
            true
        );
        
        wp_localize_script('wts-voice-search', 'wtsVoice', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wts_voice_search'),
            'analyzeIntent' => WooCommerce_Typesense_Search::get_setting('semantic_search_enabled', false), // Use semantic setting for intent
            'i18n' => array(
                'listening' => __('Écoute en cours...', 'woocommerce-typesense-search'),
                'processing' => __('Analyse...', 'woocommerce-typesense-search'),
                'error' => __('Erreur lors de la reconnaissance vocale.', 'woocommerce-typesense-search'),
                'notSupported' => __('Votre navigateur ne supporte pas la recherche vocale.', 'woocommerce-typesense-search'),
            ),
        ));
        
        wp_enqueue_script('wts-voice-search');
    }
    
    /**
     * Analyze intent using OpenAI
     */
    public function analyze_intent() {
        check_ajax_referer('wts_voice_search', 'nonce');
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $api_key = WooCommerce_Typesense_Search::get_setting('openai_api_key', '');
        
        if (empty($query) || empty($api_key)) {
            wp_send_json_error(array('message' => 'Query or API key missing'));
        }
        
        // Call OpenAI Chat Completion API
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $system_prompt = "You are a search assistant for an e-commerce store. 
        Extract search terms and filters from the user query.
        Return JSON format: { \"q\": \"search terms\", \"filters\": { \"price_min\": null, \"price_max\": null, \"color\": null, \"category\": null } }
        Example: 'Show me red shoes under 50€' -> { \"q\": \"shoes\", \"filters\": { \"price_max\": 50, \"color\": \"red\" } }";
        
        $data = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user', 'content' => $query)
            ),
            'temperature' => 0,
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($data),
            'timeout' => 15,
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            $intent = json_decode($result['choices'][0]['message']['content'], true);
            wp_send_json_success($intent);
        } else {
            wp_send_json_error(array('message' => 'Failed to analyze intent'));
        }
    }
}
