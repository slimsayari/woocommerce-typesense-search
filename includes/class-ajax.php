<?php
/**
 * AJAX Handler Class
 *
 * @package WooCommerce_Typesense_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WTS_Ajax Class
 */
class WTS_Ajax
{

    /**
     * Single instance
     *
     * @var WTS_Ajax
     */
    protected static $_instance = null;

    /**
     * Main Instance
     *
     * @return WTS_Ajax
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
        add_action('wp_ajax_wts_ajax_filter_products', array($this, 'filter_products'));
        add_action('wp_ajax_nopriv_wts_ajax_filter_products', array($this, 'filter_products'));
    }

    /**
     * Handle AJAX filter request - Direct Typesense query
     */
    public function filter_products()
    {
        $client = WTS_Typesense_Client::instance();

        // Get search term
        $search_term = isset($_POST['s']) && !empty($_POST['s']) ? sanitize_text_field($_POST['s']) : '*';
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 16;
        $page = isset($_POST['paged']) ? intval($_POST['paged']) : 1;

        // Build filter_by clauses
        $filters = $this->build_filters();

        // Build Typesense search parameters for products
        $search_params = array(
            'q' => $search_term,
            'query_by' => 'name,description,short_description,sku',
            'per_page' => $per_page,
            'page' => $page,
            'facet_by' => 'categories,stock_status,attributes',
            'max_facet_values' => 100,
        );

        // Apply filters to main query
        if (!empty($filters)) {
            $search_params['filter_by'] = implode(' && ', $filters);
        }

        // Sorting
        if (isset($_POST['orderby'])) {
            switch ($_POST['orderby']) {
                case 'price':
                    $search_params['sort_by'] = 'price:asc';
                    break;
                case 'price-desc':
                    $search_params['sort_by'] = 'price:desc';
                    break;
                case 'date':
                    $search_params['sort_by'] = 'created_at:desc';
                    break;
                case 'rating':
                    $search_params['sort_by'] = 'rating:desc';
                    break;
                default:
                    if ($search_term !== '*') {
                        $search_params['sort_by'] = '_text_match:desc';
                    }
                    break;
            }
        }

        // Debug log
        error_log('WTS AJAX Filter - Search params: ' . print_r($search_params, true));

        // Query Typesense for products
        $result = $client->search($search_params, 'products');

        if (is_wp_error($result)) {
            error_log('WTS AJAX Filter - Error: ' . $result->get_error_message());
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        $found_count = $result['found'] ?? 0;
        $hits = $result['hits'] ?? array();

        error_log('WTS AJAX Filter - Found: ' . $found_count . ' products');

        // Render products HTML
        $html = $this->render_products_html($hits);

        // Build facets response from filtered results
        // These facets show what's available WITHIN the current filter context
        $facets = $this->build_facets_response($result);

        wp_send_json_success(array(
            'html' => $html,
            'count' => $found_count,
            'facets' => $facets,
            'max_num_pages' => ceil($found_count / $per_page),
            'search_term' => $search_term,
            'filters_applied' => !empty($filters),
        ));
    }

    /**
     * Build filter clauses from POST data
     * 
     * @return array
     */
    private function build_filters()
    {
        $filters = array();

        // Categories filter - Convert slugs to names
        if (!empty($_POST['product_cat'])) {
            $cat_slugs = array_map('sanitize_text_field', (array) $_POST['product_cat']);
            $cat_names = array();

            foreach ($cat_slugs as $slug) {
                $term = get_term_by('slug', $slug, 'product_cat');
                if ($term && !is_wp_error($term)) {
                    $cat_names[] = '`' . $term->name . '`';
                } else {
                    // Try direct name match if slug doesn't work
                    $cat_names[] = '`' . $slug . '`';
                }
            }

            if (!empty($cat_names)) {
                $filters[] = 'categories:=[' . implode(',', $cat_names) . ']';
            }
        }

        // Price filters
        $price_min = isset($_POST['price_min']) ? floatval($_POST['price_min']) : 0;
        $price_max = isset($_POST['price_max']) ? floatval($_POST['price_max']) : 0;

        if ($price_min > 0) {
            $filters[] = 'price:>=' . $price_min;
        }
        if ($price_max > 0) {
            $filters[] = 'price:<=' . $price_max;
        }

        // Stock status filter
        if (!empty($_POST['stock_status']) && $_POST['stock_status'] === 'instock') {
            $filters[] = 'stock_status:=instock';
        }

        // On sale filter
        if (!empty($_POST['on_sale'])) {
            $filters[] = 'on_sale:=true';
        }

        // Min rating filter
        if (!empty($_POST['min_rating'])) {
            $filters[] = 'rating:>=' . floatval($_POST['min_rating']);
        }

        // Attributes filter
        $taxonomies = get_object_taxonomies('product', 'objects');
        foreach ($taxonomies as $slug => $tax) {
            if (in_array($slug, array('product_cat', 'product_tag', 'product_type', 'product_visibility', 'product_shipping_class'))) {
                continue;
            }

            if (isset($_POST[$slug]) && !empty($_POST[$slug])) {
                $terms = (array) $_POST[$slug];
                $attr_label = $tax->labels->singular_name;
                $attr_filters = array();

                foreach ($terms as $term_slug) {
                    $term = get_term_by('slug', $term_slug, $slug);
                    if ($term && !is_wp_error($term)) {
                        $attr_filters[] = '`' . $attr_label . ': ' . $term->name . '`';
                    }
                }

                if (!empty($attr_filters)) {
                    $filters[] = 'attributes:=[' . implode(',', $attr_filters) . ']';
                }
            }
        }

        return $filters;
    }

    /**
     * Render products HTML
     * 
     * @param array $hits
     * @return string
     */
    private function render_products_html($hits)
    {
        ob_start();

        if (!empty($hits)) {
            // Start the product loop
            if (function_exists('woocommerce_product_loop_start')) {
                woocommerce_product_loop_start();
            } else {
                echo '<ul class="products columns-4">';
            }

            foreach ($hits as $hit) {
                $product_id = isset($hit['document']['id']) ? intval($hit['document']['id']) : 0;

                if ($product_id) {
                    $post = get_post($product_id);

                    if ($post && $post->post_status === 'publish') {
                        $GLOBALS['post'] = $post;
                        setup_postdata($post);
                        wc_get_template_part('content', 'product');
                    }
                }
            }

            wp_reset_postdata();

            // End the product loop
            if (function_exists('woocommerce_product_loop_end')) {
                woocommerce_product_loop_end();
            } else {
                echo '</ul>';
            }
        } else {
            // No products found - but don't use the template as it may include extra markup
            echo '<p class="woocommerce-info wts-no-products-message">' .
                esc_html__('Aucun produit ne correspond à votre sélection.', 'woocommerce') .
                '</p>';
        }

        return ob_get_clean();
    }

    /**
     * Build facets response from search result
     * 
     * @param array $result
     * @return array
     */
    private function build_facets_response($result)
    {
        $facets = array(
            'categories' => array(),
            'stock_status' => array(),
            'attributes' => array(),
        );

        if (!isset($result['facet_counts']) || empty($result['facet_counts'])) {
            return $facets;
        }

        foreach ($result['facet_counts'] as $facet) {
            $field_name = $facet['field_name'];

            if (isset($facet['counts']) && !empty($facet['counts'])) {
                $facets[$field_name] = array();

                foreach ($facet['counts'] as $count_item) {
                    $facets[$field_name][] = array(
                        'value' => $count_item['value'],
                        'count' => $count_item['count'],
                        'highlighted' => $count_item['highlighted'] ?? $count_item['value'],
                    );
                }
            }
        }

        return $facets;
    }
}
