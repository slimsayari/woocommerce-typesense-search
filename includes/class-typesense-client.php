<?php
/**
 * Typesense Client Class
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Typesense_Client Class
 */
class WTS_Typesense_Client
{

    /**
     * Single instance
     *
     * @var WTS_Typesense_Client
     */
    protected static $_instance = null;

    /**
     * Typesense configuration
     *
     * @var array
     */
    private $config = array();

    /**
     * Main Instance
     *
     * @return WTS_Typesense_Client
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
        $this->load_config();
    }

    /**
     * Load configuration from settings
     */
    private function load_config()
    {
        $settings = WooCommerce_Typesense_Search::get_settings();

        $this->config = array(
            'host' => $settings['host'] ?? '',
            'port' => $settings['port'] ?? '8108',
            'protocol' => $settings['protocol'] ?? 'https',
            'api_key' => $settings['api_key'] ?? '',
            'collection_name' => $settings['collection_name'] ?? 'products',
        );
    }

    /**
     * Get base URL
     *
     * @return string
     */
    private function get_base_url()
    {
        return sprintf(
            '%s://%s:%s',
            $this->config['protocol'],
            $this->config['host'],
            $this->config['port']
        );
    }

    /**
     * Make HTTP request to Typesense
     *
     * @param string $endpoint
     * @param string $method
     * @param array $data
     * @return array|WP_Error
     */
    private function request($endpoint, $method = 'GET', $data = null)
    {
        $url = $this->get_base_url() . $endpoint;

        $args = array(
            'method' => $method,
            'headers' => array(
                'X-TYPESENSE-API-KEY' => $this->config['api_key'],
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );

        if ($data !== null && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status_code >= 400) {
            return new WP_Error(
                'typesense_error',
                $decoded['message'] ?? 'Unknown error',
                array('status' => $status_code)
            );
        }

        return $decoded;
    }

    /**
     * Test connection to Typesense
     *
     * @return bool|WP_Error
     */
    public function test_connection()
    {
        $result = $this->request('/health');

        if (is_wp_error($result)) {
            return $result;
        }

        return isset($result['ok']) && $result['ok'] === true;
    }

    /**
     * Create collection
     *
     * @param array $schema
     * @return array|WP_Error
     */
    public function create_collection($schema)
    {
        return $this->request('/collections', 'POST', $schema);
    }

    /**
     * Get collection
     *
     * @param string $name
     * @return array|WP_Error
     */
    public function get_collection($name = null)
    {
        $collection_name = $name ?? $this->config['collection_name'];
        return $this->request("/collections/{$collection_name}");
    }

    /**
     * Delete collection
     *
     * @param string $name
     * @return array|WP_Error
     */
    public function delete_collection($name = null)
    {
        $collection_name = $name ?? $this->config['collection_name'];
        return $this->request("/collections/{$collection_name}", 'DELETE');
    }

    /**
     * Index document
     *
     * @param array $document
     * @param string $collection
     * @return array|WP_Error
     */
    public function index_document($document, $collection = null)
    {
        $collection_name = $collection ?? $this->config['collection_name'];
        return $this->request(
            "/collections/{$collection_name}/documents",
            'POST',
            $document
        );
    }

    /**
     * Update document
     *
     * @param string $id
     * @param array $document
     * @param string $collection
     * @return array|WP_Error
     */
    public function update_document($id, $document, $collection = null)
    {
        $collection_name = $collection ?? $this->config['collection_name'];
        return $this->request(
            "/collections/{$collection_name}/documents/{$id}",
            'PATCH',
            $document
        );
    }

    /**
     * Upsert document
     *
     * @param string $collection
     * @param array $document
     * @return array|WP_Error
     */
    public function upsert_document($collection, $document)
    {
        return $this->request(
            "/collections/{$collection}/documents?action=upsert",
            'POST',
            $document
        );
    }

    /**
     * Delete document
     *
     * @param string $id
     * @param string $collection
     * @return array|WP_Error
     */
    public function delete_document($id, $collection = null)
    {
        $collection_name = $collection ?? $this->config['collection_name'];
        return $this->request(
            "/collections/{$collection_name}/documents/{$id}",
            'DELETE'
        );
    }

    /**
     * Search documents
     *
     * @param array $params
     * @param string $collection
     * @return array|WP_Error
     */
    public function search($params, $collection = null)
    {
        $collection_name = $collection ?? $this->config['collection_name'];

        $query_string = http_build_query($params);
        return $this->request("/collections/{$collection_name}/documents/search?{$query_string}");
    }

    /**
     * Multi-search
     *
     * @param array $searches
     * @return array|WP_Error
     */
    public function multi_search($searches)
    {
        return $this->request('/multi_search', 'POST', array('searches' => $searches));
    }

    /**
     * Import documents in bulk
     *
     * @param array $documents
     * @param string $collection
     * @return array|WP_Error
     */
    public function import_documents($documents, $collection = null)
    {
        error_log('WTS: Entering import_documents with ' . count($documents) . ' docs');
        $collection_name = $collection ?? $this->config['collection_name'];

        // Convert to JSONL format
        $jsonl = '';
        foreach ($documents as $doc) {
            // Force created_at to ensure schema validation passes
            if (empty($doc['created_at'])) {
                $doc['created_at'] = time();
            }
            // Ensure strict int type
            $doc['created_at'] = (int) $doc['created_at'];

            $jsonl .= json_encode($doc) . "\n";
        }

        error_log('WTS JSONL DEBUG (start): ' . substr($jsonl, 0, 500));

        $url = $this->get_base_url() . "/collections/{$collection_name}/documents/import?action=upsert";

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'X-TYPESENSE-API-KEY' => $this->config['api_key'],
                'Content-Type' => 'text/plain',
            ),
            'body' => $jsonl,
            'timeout' => 120,
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);

        // Parse JSONL response
        $results = array();
        $lines = explode("\n", trim($body));
        foreach ($lines as $line) {
            if (!empty($line)) {
                $results[] = json_decode($line, true);
            }
        }

        return $results;
    }

    /**
     * Get default schema for products
     *
     * @return array
     */
    public function get_default_schema()
    {
        $schema = array(
            'name' => $this->config['collection_name'],
            'fields' => array(
                array('name' => 'id', 'type' => 'string', 'facet' => false),
                array('name' => 'sku', 'type' => 'string', 'facet' => false, 'optional' => true),
                array('name' => 'title', 'type' => 'string', 'facet' => false),
                array('name' => 'description', 'type' => 'string', 'facet' => false, 'optional' => true),
                array('name' => 'short_description', 'type' => 'string', 'facet' => false, 'optional' => true),
                array('name' => 'price', 'type' => 'float', 'facet' => true),
                array('name' => 'regular_price', 'type' => 'float', 'facet' => false, 'optional' => true),
                array('name' => 'sale_price', 'type' => 'float', 'facet' => false, 'optional' => true),
                array('name' => 'on_sale', 'type' => 'bool', 'facet' => true, 'optional' => true),
                array('name' => 'categories', 'type' => 'string[]', 'facet' => true, 'optional' => true),
                array('name' => 'tags', 'type' => 'string[]', 'facet' => true, 'optional' => true),
                array('name' => 'attributes', 'type' => 'string[]', 'facet' => true, 'optional' => true),
                array('name' => 'stock_status', 'type' => 'string', 'facet' => true),
                array('name' => 'stock_quantity', 'type' => 'int32', 'facet' => false, 'optional' => true),
                array('name' => 'image', 'type' => 'string', 'facet' => false, 'optional' => true),
                array('name' => 'images', 'type' => 'string[]', 'facet' => false, 'optional' => true),
                array('name' => 'permalink', 'type' => 'string', 'facet' => false),
                array('name' => 'rating', 'type' => 'float', 'facet' => true, 'optional' => true),
                array('name' => 'review_count', 'type' => 'int32', 'facet' => false, 'optional' => true),
                array('name' => 'created_at', 'type' => 'int64', 'facet' => false),
                array('name' => 'updated_at', 'type' => 'int64', 'facet' => false),
            ),
            'default_sorting_field' => 'created_at',
        );

        // Add semantic search support if enabled
        $semantic_enabled = WooCommerce_Typesense_Search::get_setting('semantic_search_enabled', false);
        $openai_key = WooCommerce_Typesense_Search::get_setting('openai_api_key', '');

        if ($semantic_enabled && !empty($openai_key)) {
            $schema['fields'][] = array(
                'name' => 'embedding',
                'type' => 'float[]',
                'embed' => array(
                    'from' => array('title', 'description'),
                    'model_config' => array(
                        'model_name' => 'openai/text-embedding-ada-002',
                        'api_key' => $openai_key,
                    ),
                ),
            );
        }

        return apply_filters('wts_collection_schema', $schema);
    }

    /**
     * Check if collection exists
     *
     * @param string $name
     * @return bool
     */
    public function collection_exists($name = null)
    {
        $result = $this->get_collection($name);
        return !is_wp_error($result);
    }

    /**
     * Ensure collection exists
     *
     * @return bool|WP_Error
     */
    public function ensure_collection()
    {
        if ($this->collection_exists()) {
            return true;
        }

        $schema = $this->get_default_schema();
        $result = $this->create_collection($schema);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }
}
