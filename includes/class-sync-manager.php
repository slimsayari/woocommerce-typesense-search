<?php
/**
 * Sync Manager Class
 * Gère la synchronisation complète entre WordPress/WooCommerce et Typesense
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Sync_Manager Class
 */
class WTS_Sync_Manager
{

    /**
     * Single instance
     *
     * @var WTS_Sync_Manager
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
     * @return WTS_Sync_Manager
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

        // Auto-sync hooks
        $this->init_auto_sync_hooks();

        // AJAX handlers for bulk sync
        add_action('wp_ajax_wts_sync_products', array($this, 'ajax_sync_products'));
        add_action('wp_ajax_wts_sync_posts', array($this, 'ajax_sync_posts'));
        add_action('wp_ajax_wts_sync_all', array($this, 'ajax_sync_all'));
        add_action('wp_ajax_wts_get_sync_status', array($this, 'ajax_get_sync_status'));
    }

    /**
     * Initialize auto-sync hooks
     */
    private function init_auto_sync_hooks()
    {
        $settings = WooCommerce_Typesense_Search::get_settings();
        $auto_sync = isset($settings['auto_sync']) && $settings['auto_sync'] === 'yes';

        if (!$auto_sync) {
            return;
        }

        // Product hooks
        add_action('woocommerce_new_product', array($this, 'sync_product'), 10, 1);
        add_action('woocommerce_update_product', array($this, 'sync_product'), 10, 1);
        add_action('woocommerce_delete_product', array($this, 'delete_product'), 10, 1);

        // Post hooks
        add_action('save_post', array($this, 'sync_post'), 10, 1);
        add_action('delete_post', array($this, 'delete_post'), 10, 1);

        // Category hooks
        add_action('created_product_cat', array($this, 'sync_category'), 10, 1);
        add_action('edited_product_cat', array($this, 'sync_category'), 10, 1);
        add_action('delete_product_cat', array($this, 'delete_category'), 10, 1);
    }

    /**
     * Sync a single product
     *
     * @param int $product_id
     * @return bool|WP_Error
     */
    public function sync_product($product_id)
    {
        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_Error('invalid_product', 'Product not found');
        }

        $document = $this->prepare_product_document($product);

        return $this->client->upsert_document('products', $document);
    }

    /**
     * Prepare product document for Typesense
     *
     * @param WC_Product $product
     * @return array
     */
    private function prepare_product_document($product)
    {
        $categories = array();
        $category_ids = array();
        $terms = get_the_terms($product->get_id(), 'product_cat');

        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[] = $term->name;
                $category_ids[] = (string) $term->term_id;
            }
        }

        $tags = array();
        $tag_terms = get_the_terms($product->get_id(), 'product_tag');

        if ($tag_terms && !is_wp_error($tag_terms)) {
            foreach ($tag_terms as $term) {
                $tags[] = $term->name;
            }
        }

        // Get attributes
        $attributes = array();
        $product_attributes = $product->get_attributes();

        foreach ($product_attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product->get_id(), $attribute->get_name());
                if ($terms && !is_wp_error($terms)) {
                    $values = array_map(function ($term) {
                        return $term->name;
                    }, $terms);

                    $attributes[] = array(
                        'name' => wc_attribute_label($attribute->get_name()),
                        'values' => $values
                    );
                }
            }
        }

        // Get images
        $image_url = '';
        $gallery_urls = array();

        if ($product->get_image_id()) {
            $image_url = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
        }

        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gallery_id) {
            $gallery_urls[] = wp_get_attachment_image_url($gallery_id, 'woocommerce_thumbnail');
        }

        // Get rating
        $rating = $product->get_average_rating();
        $reviews_count = $product->get_review_count();


        // Failsafe: ensure created_at is always set
        $created_at = $product->get_date_created();
        $updated_at = $product->get_date_modified();

        $document = array(
            'id' => (string) $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku() ?: '',
            'description' => wp_strip_all_tags($product->get_description()),
            'short_description' => wp_strip_all_tags($product->get_short_description()),
            'price' => (float) $product->get_price(),
            'regular_price' => (float) $product->get_regular_price(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity() ?: 0,
            'categories' => $categories,
            'category_ids' => $category_ids,
            'tags' => $tags,
            'attributes' => $attributes,
            'image_url' => $image_url,
            'gallery_urls' => $gallery_urls,
            'url' => get_permalink($product->get_id()),
            'rating' => (float) $rating,
            'reviews_count' => (int) $reviews_count,
            'created_at' => $created_at ? strtotime($created_at) : time(),
            'updated_at' => $updated_at ? strtotime($updated_at) : time(),
        );

        // Add sale price if exists
        if ($product->get_sale_price()) {
            $document['sale_price'] = (float) $product->get_sale_price();
        }

        return $document;
    }

    /**
     * Delete a product from Typesense
     *
     * @param int $product_id
     * @return bool|WP_Error
     */
    public function delete_product($product_id)
    {
        return $this->client->delete_document('products', (string) $product_id);
    }

    /**
     * Sync a single post
     *
     * @param int $post_id
     * @return bool|WP_Error
     */
    public function sync_post($post_id)
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
            return false;
        }

        $document = $this->prepare_post_document($post);

        return $this->client->upsert_document('posts', $document);
    }

    /**
     * Prepare post document for Typesense
     *
     * @param WP_Post $post
     * @return array
     */
    private function prepare_post_document($post)
    {
        $categories = array();
        $terms = get_the_terms($post->ID, 'category');

        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[] = $term->name;
            }
        }

        $tags = array();
        $tag_terms = get_the_terms($post->ID, 'post_tag');

        if ($tag_terms && !is_wp_error($tag_terms)) {
            foreach ($tag_terms as $term) {
                $tags[] = $term->name;
            }
        }

        $author = get_the_author_meta('display_name', $post->post_author);
        $image_url = get_the_post_thumbnail_url($post->ID, 'medium');

        return array(
            'id' => (string) $post->ID,
            'title' => $post->post_title,
            'content' => wp_strip_all_tags($post->post_content),
            'excerpt' => wp_strip_all_tags($post->post_excerpt),
            'author' => $author,
            'categories' => $categories,
            'tags' => $tags,
            'image_url' => $image_url ?: '',
            'url' => get_permalink($post->ID),
            'published_at' => strtotime($post->post_date),
        );
    }

    /**
     * Delete a post from Typesense
     *
     * @param int $post_id
     * @return bool|WP_Error
     */
    public function delete_post($post_id)
    {
        return $this->client->delete_document('posts', (string) $post_id);
    }

    /**
     * Sync a category
     *
     * @param int $term_id
     * @return bool|WP_Error
     */
    public function sync_category($term_id)
    {
        $term = get_term($term_id, 'product_cat');

        if (!$term || is_wp_error($term)) {
            return false;
        }

        // Categories are embedded in products, so we need to re-index all products in this category
        $products = wc_get_products(array(
            'category' => array($term->slug),
            'limit' => -1,
        ));

        foreach ($products as $product) {
            $this->sync_product($product->get_id());
        }

        return true;
    }

    /**
     * Delete a category
     *
     * @param int $term_id
     * @return bool
     */
    public function delete_category($term_id)
    {
        // Re-index all products that had this category
        return $this->sync_category($term_id);
    }

    /**
     * Bulk sync all products
     *
     * @param int $batch_size
     * @param int $offset
     * @return array
     */
    public function bulk_sync_products($batch_size = 50, $offset = 0)
    {
        // Use get_posts to avoid potential WooCommerce query filtering issues
        $product_ids = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        $synced = 0;
        $errors = array();

        foreach ($product_ids as $product_id) {
            $result = $this->sync_product($product_id);

            if (is_wp_error($result)) {
                $errors[] = array(
                    'id' => $product_id,
                    'error' => $result->get_error_message()
                );
            } else {
                $synced++;
            }
        }

        return array(
            'synced' => $synced,
            'errors' => $errors,
            'has_more' => count($product_ids) === $batch_size
        );
    }

    /**
     * Bulk sync all posts
     *
     * @param int $batch_size
     * @param int $offset
     * @return array
     */
    public function bulk_sync_posts($batch_size = 50, $offset = 0)
    {
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
        ));

        $synced = 0;
        $errors = array();

        foreach ($posts as $post) {
            $result = $this->sync_post($post->ID);

            if (is_wp_error($result)) {
                $errors[] = array(
                    'id' => $post->ID,
                    'error' => $result->get_error_message()
                );
            } else {
                $synced++;
            }
        }

        return array(
            'synced' => $synced,
            'errors' => $errors,
            'has_more' => count($posts) === $batch_size
        );
    }

    /**
     * Get total counts
     *
     * @return array
     */
    public function get_totals()
    {
        $total_products = wp_count_posts('product');
        $total_posts = wp_count_posts('post');
        $total_categories = wp_count_terms('product_cat');

        return array(
            'products' => $total_products->publish,
            'posts' => $total_posts->publish,
            'categories' => $total_categories,
        );
    }

    /**
     * AJAX: Sync products
     */
    public function ajax_sync_products()
    {
        check_ajax_referer('wts_bulk_sync', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        if ($offset === 0) {
            $schema_result = $this->create_collection_schema('products');
            if (is_wp_error($schema_result)) {
                wp_send_json_error(array('message' => $schema_result->get_error_message()));
            }
        }

        $result = $this->bulk_sync_products($batch_size, $offset);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Sync posts
     */
    public function ajax_sync_posts()
    {
        check_ajax_referer('wts_bulk_sync', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        if ($offset === 0) {
            $schema_result = $this->create_collection_schema('posts');
            if (is_wp_error($schema_result)) {
                wp_send_json_error(array('message' => $schema_result->get_error_message()));
            }
        }

        $result = $this->bulk_sync_posts($batch_size, $offset);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Sync all
     */
    public function ajax_sync_all()
    {
        check_ajax_referer('wts_bulk_sync', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        // This will be called multiple times by the frontend
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'products';
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        if ($type === 'products') {
            if ($offset === 0) {
                $schema_result = $this->create_collection_schema('products');
                if (is_wp_error($schema_result)) {
                    wp_send_json_error(array('message' => $schema_result->get_error_message()));
                }
            }
            $result = $this->bulk_sync_products($batch_size, $offset);
        } else {
            if ($offset === 0) {
                $schema_result = $this->create_collection_schema('posts');
                if (is_wp_error($schema_result)) {
                    wp_send_json_error(array('message' => $schema_result->get_error_message()));
                }
            }
            $result = $this->bulk_sync_posts($batch_size, $offset);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Get sync status
     */
    public function ajax_get_sync_status()
    {
        check_ajax_referer('wts_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $totals = $this->get_totals();

        wp_send_json_success($totals);
    }

    /**
     * Create or update collection schema
     *
     * @param string $collection_name
     * @return bool|WP_Error
     */
    public function create_collection_schema($collection_name = 'products')
    {
        if ($collection_name === 'products') {
            $schema = array(
                'name' => 'products',
                'enable_nested_fields' => true,
                'fields' => array(
                    array('name' => 'id', 'type' => 'string'),
                    array('name' => 'name', 'type' => 'string'),
                    array('name' => 'sku', 'type' => 'string'),
                    array('name' => 'description', 'type' => 'string'),
                    array('name' => 'short_description', 'type' => 'string'),
                    array('name' => 'price', 'type' => 'float', 'facet' => true),
                    array('name' => 'regular_price', 'type' => 'float'),
                    array('name' => 'sale_price', 'type' => 'float', 'optional' => true),
                    array('name' => 'stock_status', 'type' => 'string', 'facet' => true),
                    array('name' => 'stock_quantity', 'type' => 'int32'),
                    array('name' => 'categories', 'type' => 'string[]', 'facet' => true),
                    array('name' => 'category_ids', 'type' => 'string[]'),
                    array('name' => 'tags', 'type' => 'string[]', 'facet' => true),
                    array('name' => 'attributes', 'type' => 'object[]', 'facet' => true, 'optional' => true),
                    array('name' => 'image_url', 'type' => 'string'),
                    array('name' => 'gallery_urls', 'type' => 'string[]'),
                    array('name' => 'url', 'type' => 'string'),
                    array('name' => 'rating', 'type' => 'float', 'facet' => true),
                    array('name' => 'reviews_count', 'type' => 'int32'),
                    array('name' => 'created_at', 'type' => 'int64'),
                    array('name' => 'updated_at', 'type' => 'int64'),
                ),
                'default_sorting_field' => 'created_at'
            );
        } else {
            $schema = array(
                'name' => 'posts',
                'fields' => array(
                    array('name' => 'id', 'type' => 'string'),
                    array('name' => 'title', 'type' => 'string'),
                    array('name' => 'content', 'type' => 'string'),
                    array('name' => 'excerpt', 'type' => 'string'),
                    array('name' => 'author', 'type' => 'string', 'facet' => true),
                    array('name' => 'categories', 'type' => 'string[]', 'facet' => true),
                    array('name' => 'tags', 'type' => 'string[]', 'facet' => true),
                    array('name' => 'image_url', 'type' => 'string'),
                    array('name' => 'url', 'type' => 'string'),
                    array('name' => 'published_at', 'type' => 'int64'),
                ),
                'default_sorting_field' => 'published_at'
            );
        }

        // Delete existing collection to ensure fresh schema
        $this->client->delete_collection($collection_name);
        // Wait for deletion to complete
        usleep(500000); // 0.5 seconds

        $result = $this->client->create_collection($schema);

        if (is_wp_error($result)) {
            return $result;
        }


        return true;
    }
}
