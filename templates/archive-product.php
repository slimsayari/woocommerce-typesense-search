<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * This template is part of WooCommerce Typesense Search plugin
 * @package WooCommerce_Typesense_Search
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

get_header('shop'); ?>

<div class="catalog-page">

    <!-- Catalog Header -->
    <header class="catalog-header">
        <div class="container">
            <div class="catalog-header-content">
                <div class="catalog-info">
                    <h1 class="catalog-title">
                        <?php woocommerce_page_title(); ?>
                    </h1>
                    <?php if (wc_get_loop_prop('total')): ?>
                        <p class="catalog-count">
                            <?php
                            printf(
                                _n('%d produit trouvé', '%d produits trouvés', wc_get_loop_prop('total'), 'woocommerce-typesense-search'),
                                wc_get_loop_prop('total')
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="catalog-actions">
                    <!-- Search Field (AJAX) -->
                    <div class="product-search-wrapper">
                        <input type="search" class="search-field wts-search-input form-control"
                            placeholder="<?php _e('Rechercher des produits...', 'woocommerce-typesense-search'); ?>"
                            value="<?php echo get_search_query(); ?>" name="s" autocomplete="off">
                        <span class="search-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="11" cy="11" r="8" />
                                <path d="m21 21-4.35-4.35" />
                            </svg>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="catalog-main pt-4 pb-5">
        <div class="container">
            <div class="row">

                <!-- Sidebar with Filters (Left Column) -->
                <aside class="col-lg-3 col-md-4 mb-4 catalog-sidebar" id="catalog-sidebar">
                    <div class="sidebar-header d-md-none d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5"><?php _e('Filtrer par', 'woocommerce-typesense-search'); ?></h3>
                        <button class="sidebar-close btn btn-sm btn-outline-secondary">
                            <svg width="24" height="24" viewBox="0 0 24 24">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>

                    <?php
                    /**
                     * Hook: woocommerce_sidebar.
                     *
                     * @hooked woocommerce_get_sidebar - 10
                     */
                    dynamic_sidebar('shop-filters');
                    ?>

                    <!-- Custom Filters -->
                    <div class="filter-section mb-4">
                        <h4 class="filter-title h6 mb-3 fw-bold">
                            <?php _e('Catégories', 'woocommerce-typesense-search'); ?>
                        </h4>
                        <?php
                        $ts_categories = array();
                        if (class_exists('WTS_Faceted_Search')) {
                            $ts_counts = WTS_Faceted_Search::instance()->get_category_facets();
                            foreach ($ts_counts as $c) {
                                $ts_categories[$c['value']] = $c['count'];
                            }
                        }

                        $product_categories = get_terms('product_cat', array(
                            'hide_empty' => true,
                            'parent' => 0
                        ));

                        if ($product_categories):
                            ?>
                            <ul class="list-unstyled filter-list">
                                <?php foreach ($product_categories as $category): ?>
                                    <?php
                                    $count = $category->count;
                                    if (isset($ts_categories[$category->name])) {
                                        $count = $ts_categories[$category->name];
                                    } elseif (isset($ts_categories[$category->slug])) {
                                        $count = $ts_categories[$category->slug];
                                    }
                                    ?>
                                    <li class="mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="product_cat[]"
                                                value="<?php echo esc_attr($category->slug); ?>"
                                                id="cat-<?php echo esc_attr($category->slug); ?>">
                                            <label class="form-check-label d-flex justify-content-between w-100"
                                                for="cat-<?php echo esc_attr($category->slug); ?>">
                                                <span><?php echo esc_html($category->name); ?></span>
                                                <small class="text-muted ms-2">(<?php echo $count; ?>)</small>
                                            </label>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Price Filter (AJAX - no button needed) -->
                    <div class="filter-section mb-4">
                        <h4 class="filter-title h6 mb-3 fw-bold"><?php _e('Prix', 'woocommerce-typesense-search'); ?>
                        </h4>
                        <div class="price-filter">
                            <div class="input-group">
                                <input type="number" class="form-control price-input" name="price_min"
                                    placeholder="Min €" min="0" step="0.01">
                                <span class="input-group-text">-</span>
                                <input type="number" class="form-control price-input" name="price_max"
                                    placeholder="Max €" min="0" step="0.01">
                            </div>
                            <small
                                class="text-muted mt-1 d-block"><?php _e('Le filtre s\'applique automatiquement', 'woocommerce-typesense-search'); ?></small>
                        </div>
                    </div>

                    <!-- Dynamic Attribute Filters -->
                    <?php
                    $ts_counts_map = array();
                    if (class_exists('WTS_Faceted_Search')) {
                        $raw_facets = WTS_Faceted_Search::instance()->get_facets();
                        foreach ($raw_facets as $f) {
                            if (isset($f['field_name']) && isset($f['counts'])) {
                                $formatted_counts = array();
                                foreach ($f['counts'] as $c) {
                                    $formatted_counts[$c['value']] = $c['count'];
                                }
                                $ts_counts_map[$f['field_name']] = $formatted_counts;
                            }
                        }
                    }

                    if (isset($ts_counts_map['attributes'])) {
                        $grouped_attributes = array();

                        foreach ($ts_counts_map['attributes'] as $attr_str => $count) {
                            $parts = explode(':', $attr_str, 2);
                            if (count($parts) === 2) {
                                $name = trim($parts[0]);
                                $val = trim($parts[1]);
                                if (!isset($grouped_attributes[$name])) {
                                    $grouped_attributes[$name] = array();
                                }
                                $grouped_attributes[$name][] = array(
                                    'value' => $val,
                                    'count' => $count,
                                    'full' => $attr_str
                                );
                            }
                        }

                        foreach ($grouped_attributes as $attr_name => $terms):
                            $input_name = 'attributes[]';
                            $is_hair = false;
                            $found_tax = false;
                            $taxonomies = get_object_taxonomies('product', 'objects');
                            foreach ($taxonomies as $tax_slug => $tax_obj) {
                                if ($tax_obj->labels->singular_name === $attr_name || $tax_obj->label === $attr_name) {
                                    $input_name = $tax_slug . '[]';
                                    $found_tax = true;
                                    break;
                                }
                            }

                            if ($attr_name === 'Type de cheveux') {
                                $input_name = 'hair_type[]';
                                $is_hair = true;
                                $found_tax = true;
                            }
                            ?>
                            <div class="filter-section mb-4">
                                <h4 class="filter-title h6 mb-3 fw-bold"><?php echo esc_html($attr_name); ?></h4>
                                <ul class="list-unstyled filter-list">
                                    <?php foreach ($terms as $term):
                                        $term_slug = sanitize_title($term['value']);

                                        if ($found_tax) {
                                            $tax_slug = str_replace('[]', '', $input_name);
                                            $term_obj = get_term_by('name', $term['value'], $tax_slug);
                                            if ($term_obj) {
                                                $term_slug = $term_obj->slug;
                                            }
                                        }
                                        ?>
                                        <li class="mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    name="<?php echo esc_attr($input_name); ?>"
                                                    value="<?php echo esc_attr($term_slug); ?>"
                                                    id="<?php echo esc_attr($term_slug); ?>">
                                                <label class="form-check-label d-flex justify-content-between w-100"
                                                    for="<?php echo esc_attr($term_slug); ?>">
                                                    <span><?php echo esc_html($term['value']); ?></span>
                                                    <small class="text-muted ms-2">(<?php echo $term['count']; ?>)</small>
                                                </label>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach;
                    }
                    ?>
                </aside>

                <!-- Products Grid (Right Column) -->
                <div class="col-lg-9 col-md-8 catalog-products">

                    <!-- Sorting and View Options -->
                    <div
                        class="catalog-toolbar d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                        <div class="view-options btn-group me-3">
                            <button class="view-toggle btn btn-outline-secondary active" data-view="grid"
                                aria-label="<?php _e('Vue grille', 'woocommerce-typesense-search'); ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <rect x="3" y="3" width="8" height="8" rx="1" />
                                    <rect x="13" y="3" width="8" height="8" rx="1" />
                                    <rect x="13" y="13" width="8" height="8" rx="1" />
                                    <rect x="3" y="13" width="8" height="8" rx="1" />
                                </svg>
                            </button>
                            <button class="view-toggle btn btn-outline-secondary" data-view="list"
                                aria-label="<?php _e('Vue liste', 'woocommerce-typesense-search'); ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <rect x="3" y="4" width="4" height="4" rx="1" />
                                    <rect x="9" y="5" width="12" height="2" rx="1" />
                                    <rect x="3" y="10" width="4" height="4" rx="1" />
                                    <rect x="9" y="11" width="12" height="2" rx="1" />
                                    <rect x="3" y="16" width="4" height="4" rx="1" />
                                    <rect x="9" y="17" width="12" height="2" rx="1" />
                                </svg>
                            </button>
                        </div>

                        <div class="ms-auto">
                            <?php woocommerce_catalog_ordering(); ?>
                        </div>
                    </div>

                    <?php if (woocommerce_product_loop()): ?>

                        <?php
                        /**
                         * Only output notices (errors, messages) - not the duplicate count/ordering
                         * Count is in catalog-header, ordering is in catalog-toolbar
                         */
                        woocommerce_output_all_notices();
                        ?>

                        <div class="products-grid row g-4" data-view="grid">
                            <?php
                            if (wc_get_loop_prop('is_shortcode')) {
                                $columns = absint(wc_get_loop_prop('columns'));
                            } else {
                                $columns = wc_get_default_products_per_row();
                            }

                            woocommerce_product_loop_start();

                            if (wc_get_loop_prop('total')) {
                                while (have_posts()) {
                                    the_post();

                                    /**
                                     * Hook: woocommerce_shop_loop.
                                     */
                                    do_action('woocommerce_shop_loop');

                                    wc_get_template_part('content', 'product');
                                }
                            }

                            woocommerce_product_loop_end();
                            ?>
                        </div>

                        <?php
                        /**
                         * Hook: woocommerce_after_shop_loop.
                         *
                         * @hooked woocommerce_pagination - 10
                         */
                        do_action('woocommerce_after_shop_loop');
                        ?>

                    <?php else: ?>

                        <div class="no-products-found text-center my-5">
                            <div class="no-products-content">
                                <svg class="no-products-icon text-muted mb-3" width="80" height="80" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                <h3><?php _e('Aucun produit trouvé', 'woocommerce-typesense-search'); ?></h3>
                                <p class="text-muted">
                                    <?php _e('Essayez d\'ajuster vos filtres ou votre recherche.', 'woocommerce-typesense-search'); ?>
                                </p>
                                <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"
                                    class="btn btn-primary mt-3">
                                    <?php _e('Voir tous les produits', 'woocommerce-typesense-search'); ?>
                                </a>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </main>
</div>

<?php
/**
 * Hook: woocommerce_after_main_content.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action('woocommerce_after_main_content');

get_footer('shop');
