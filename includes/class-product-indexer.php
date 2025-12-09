<?php
/**
 * Product Indexer Class
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Product_Indexer Class
 */
class WTS_Product_Indexer
{

    /**
     * Single instance
     *
     * @var WTS_Product_Indexer
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
     * @return WTS_Product_Indexer
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
        $this->client = WTS_Typesense_Client::instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Auto-sync hooks
        if (WooCommerce_Typesense_Search::get_setting('auto_sync', true)) {
            add_action('save_post_product', array($this, 'sync_product_on_save'), 10, 1);
            add_action('delete_post', array($this, 'delete_product_on_delete'), 10, 1);
            add_action('woocommerce_update_product', array($this, 'sync_product_on_update'), 10, 1);
            add_action('woocommerce_update_product_variation', array($this, 'sync_variation_on_update'), 10, 1);
            add_action('woocommerce_delete_product_variation', array($this, 'delete_variation_on_delete'), 10, 1);
        }

        // AJAX handlers for bulk sync
        add_action('wp_ajax_wts_bulk_sync', array($this, 'ajax_bulk_sync'));
        add_action('wp_ajax_wts_get_sync_progress', array($this, 'ajax_get_sync_progress'));
    }

    /**
     * Sync product on save
     *
     * @param int $post_id
     */
    public function sync_product_on_save($post_id)
    {
        if (!WooCommerce_Typesense_Search::get_setting('enabled', false)) {
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        $this->index_product($product);
    }

    /**
     * Sync product on update
     *
     * @param int $product_id
     */
    public function sync_product_on_update($product_id)
    {
        $this->sync_product_on_save($product_id);
    }

    /**
     * Sync variation on update
     *
     * @param int $variation_id
     */
    public function sync_variation_on_update($variation_id)
    {
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return;
        }

        // Index the variation
        $this->index_product($variation);

        // Also update parent product
        $parent_id = $variation->get_parent_id();
        if ($parent_id) {
            $this->sync_product_on_save($parent_id);
        }
    }

    /**
     * Delete product on delete
     *
     * @param int $post_id
     */
    public function delete_product_on_delete($post_id)
    {
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        if (!WooCommerce_Typesense_Search::get_setting('enabled', false)) {
            return;
        }

        $this->delete_product($post_id);
    }

    /**
     * Delete variation on delete
     *
     * @param int $variation_id
     */
    public function delete_variation_on_delete($variation_id)
    {
        $this->delete_product($variation_id);
    }

    /**
     * Index a single product
     *
     * @param WC_Product $product
     * @return bool|WP_Error
     */
    public function index_product($product)
    {
        if (!$product || $product->get_status() !== 'publish') {
            return false;
        }

        $document = $this->prepare_product_document($product);

        if (empty($document)) {
            return false;
        }

        $result = $this->client->index_document($document);

        if (is_wp_error($result)) {
            error_log('WTS: Failed to index product ' . $product->get_id() . ': ' . $result->get_error_message());
            return $result;
        }

        // Index variations if variable product
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation_data) {
                $variation = wc_get_product($variation_data['variation_id']);
                if ($variation) {
                    $this->index_product($variation);
                }
            }
        }

        return true;
    }

    /**
     * Delete a product from index
     *
     * @param int $product_id
     * @return bool|WP_Error
     */
    public function delete_product($product_id)
    {
        $result = $this->client->delete_document((string) $product_id);

        if (is_wp_error($result)) {
            error_log('WTS: Failed to delete product ' . $product_id . ': ' . $result->get_error_message());
            return $result;
        }

        return true;
    }

    /**
     * Prepare product document for indexing
     *
     * @param WC_Product $product
     * @return array
     */
    private function prepare_product_document($product)
    {
        $categories = array();
        $category_ids = $product->get_category_ids();
        foreach ($category_ids as $cat_id) {
            $term = get_term($cat_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $categories[] = $term->name;
            }
        }

        $tags = array();
        $tag_ids = $product->get_tag_ids();
        foreach ($tag_ids as $tag_id) {
            $term = get_term($tag_id, 'product_tag');
            if ($term && !is_wp_error($term)) {
                $tags[] = $term->name;
            }
        }

        $attributes = array();
        $product_attributes = $product->get_attributes();
        foreach ($product_attributes as $attribute) {
            if (is_a($attribute, 'WC_Product_Attribute')) {
                $values = $attribute->get_options();
                foreach ($values as $value) {
                    if (is_numeric($value)) {
                        $term = get_term($value);
                        if ($term && !is_wp_error($term)) {
                            $attributes[] = $attribute->get_name() . ': ' . $term->name;
                        }
                    } else {
                        $attributes[] = $attribute->get_name() . ': ' . $value;
                    }
                }
            }
        }

        $images = array();
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail');
            if ($image_url) {
                $images[] = $image_url;
            }
        }

        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gallery_id) {
            $gallery_url = wp_get_attachment_image_url($gallery_id, 'woocommerce_thumbnail');
            if ($gallery_url) {
                $images[] = $gallery_url;
            }
        }

        $document = array(
            'id' => (string) $product->get_id(),
            'title' => $product->get_name(),
            'description' => wp_strip_all_tags($product->get_description()),
            'short_description' => wp_strip_all_tags($product->get_short_description()),
            'price' => (float) $product->get_price(),
            'stock_status' => $product->get_stock_status(),
            'permalink' => $product->get_permalink(),
            'created_at' => $product->get_date_created() ? (int) $product->get_date_created()->getTimestamp() : time(),
            'updated_at' => $product->get_date_modified() ? (int) $product->get_date_modified()->getTimestamp() : time(),
        );

        // Optional fields
        if ($product->get_sku()) {
            $document['sku'] = $product->get_sku();
        }

        if ($product->get_regular_price()) {
            $document['regular_price'] = (float) $product->get_regular_price();
        }

        if ($product->get_sale_price()) {
            $document['sale_price'] = (float) $product->get_sale_price();
            $document['on_sale'] = true;
        } else {
            $document['on_sale'] = false;
        }

        if (!empty($categories)) {
            $document['categories'] = $categories;
        }

        if (!empty($tags)) {
            $document['tags'] = $tags;
        }

        if (!empty($attributes)) {
            $document['attributes'] = $attributes;
        }

        if ($product->managing_stock()) {
            $document['stock_quantity'] = (int) $product->get_stock_quantity();
        }

        if (!empty($images)) {
            $document['image'] = $images[0];
            $document['images'] = $images;
        }

        $rating = $product->get_average_rating();
        if ($rating) {
            $document['rating'] = (float) $rating;
        }

        $review_count = $product->get_review_count();
        if ($review_count) {
            $document['review_count'] = (int) $review_count;
        }

        $document = apply_filters('wts_product_document', $document, $product);

        // Failsafe: Ensure created_at is present and valid
        if (empty($document['created_at'])) {
            $document['created_at'] = time();
            error_log('WTS WARNING: created_at was missing/empty for product ' . $product->get_id() . ' after filters. Forced to current time.');
        }

        return $document;
    }

    /**
     * Bulk sync all products
     *
     * @param int $batch_size
     * @param int $offset
     * @return array
     */
    public function bulk_sync($batch_size = 50, $offset = 0)
    {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
        );

        $products = get_posts($args);
        $documents = array();

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $document = $this->prepare_product_document($product);
                if (!empty($document)) {
                    $documents[] = $document;
                }
            }
        }

        if (empty($documents)) {
            return array(
                'success' => true,
                'indexed' => 0,
                'total' => 0,
            );
        }

        $result = $this->client->import_documents($documents);

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message(),
            );
        }

        $success_count = 0;
        $errors = array();
        foreach ($result as $item) {
            if (isset($item['success']) && $item['success']) {
                $success_count++;
            } else {
                $errors[] = isset($item['error']) ? $item['error'] : 'Unknown error';
            }
        }

        return array(
            'success' => true,
            'indexed' => $success_count,
            'total' => count($documents),
            'errors' => $errors
        );
    }

    /**
     * Get total product count
     *
     * @return int
     */
    public function get_total_products()
    {
        $counts = wp_count_posts('product');
        return isset($counts->publish) ? (int) $counts->publish : 0;
    }

    /**
     * AJAX handler for bulk sync
     */
    public function ajax_bulk_sync()
    {
        check_ajax_referer('wts_bulk_sync', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'woocommerce-typesense-search')));
        }

        $batch_size = isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 50;
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;

        // Ensure collection exists
        if ($offset === 0) {
            // Delete existing collection to reset schema
            $delete_result = $this->client->delete_collection();
            // Wait a moment for deletion to propagate
            usleep(500000); // 0.5 seconds

            // Force recreation with fresh schema
            $schema = $this->client->get_default_schema();
            $create_result = $this->client->create_collection($schema);
            if (is_wp_error($create_result)) {
                wp_send_json_error(array('message' => $create_result->get_error_message()));
            }
        } else {
            // For subsequent batches, just ensure it exists
            $ensure_result = $this->client->ensure_collection();
            if (is_wp_error($ensure_result)) {
                wp_send_json_error(array('message' => $ensure_result->get_error_message()));
            }
        }

        $result = $this->bulk_sync($batch_size, $offset);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for sync progress
     */
    public function ajax_get_sync_progress()
    {
        check_ajax_referer('wts_bulk_sync', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'woocommerce-typesense-search')));
        }

        $total = $this->get_total_products();

        wp_send_json_success(array(
            'total' => $total,
        ));
    }
}
