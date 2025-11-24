<?php
/**
 * Semantic Search Class
 * Gère les fonctionnalités de recherche sémantique et vectorielle
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Semantic_Search Class
 */
class WTS_Semantic_Search {
    
    /**
     * Single instance
     *
     * @var WTS_Semantic_Search
     */
    protected static $_instance = null;
    
    /**
     * Main Instance
     *
     * @return WTS_Semantic_Search
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
        // Add settings validation
        add_filter('woocommerce_admin_settings_sanitize_option_wts_openai_api_key', array($this, 'validate_openai_key'));
        
        // Modify search params for hybrid search
        add_filter('wts_search_params', array($this, 'modify_search_params'));
    }
    
    /**
     * Validate OpenAI API Key
     *
     * @param string $key
     * @return string
     */
    public function validate_openai_key($key) {
        if (empty($key)) {
            return $key;
        }
        
        // Simple validation check (starts with sk-)
        if (strpos($key, 'sk-') !== 0) {
            add_settings_error(
                'wts_openai_api_key',
                'invalid_openai_key',
                __('La clé API OpenAI semble invalide. Elle doit commencer par "sk-".', 'woocommerce-typesense-search')
            );
        }
        
        return $key;
    }
    
    /**
     * Modify search parameters to enable hybrid search
     *
     * @param array $params
     * @return array
     */
    public function modify_search_params($params) {
        if (!WooCommerce_Typesense_Search::get_setting('semantic_search_enabled', false)) {
            return $params;
        }
        
        // If we have a query, Typesense will automatically use the embedding field
        // if it's defined in the schema and configured for embedding.
        // However, we can tweak the hybrid search parameters here if needed.
        
        // Example: Set alpha for hybrid search (0 = keyword only, 1 = vector only)
        // $params['vector_query'] = 'embedding:([], k:100, alpha: 0.5)';
        
        // For now, we rely on Typesense's default behavior for 'embed' fields
        // which automatically generates the vector query from the 'q' parameter.
        
        return $params;
    }
    
    /**
     * Check if semantic search is configured correctly
     *
     * @return bool|WP_Error
     */
    public function check_configuration() {
        $enabled = WooCommerce_Typesense_Search::get_setting('semantic_search_enabled', false);
        $api_key = WooCommerce_Typesense_Search::get_setting('openai_api_key', '');
        
        if (!$enabled) {
            return false;
        }
        
        if (empty($api_key)) {
            return new WP_Error('missing_key', __('Clé API OpenAI manquante.', 'woocommerce-typesense-search'));
        }
        
        return true;
    }
}
