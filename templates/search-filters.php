<?php
/**
 * Search Filters Template
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Variables passed from WTS_Faceted_Search::render_filters()
// $price_range, $categories
?>

<div class="wts-filters-sidebar">
    <div class="wts-filters-header">
        <h3><?php _e('Filtres', 'woocommerce-typesense-search'); ?></h3>
        <button type="button" class="wts-clear-filters" style="display:none;">
            <?php _e('Réinitialiser', 'woocommerce-typesense-search'); ?>
        </button>
    </div>

    <!-- Price Filter -->
    <div class="wts-filter-group">
        <h4 class="wts-filter-title"><?php _e('Prix', 'woocommerce-typesense-search'); ?></h4>
        <div class="wts-filter-price">
            <div class="wts-price-inputs">
                <label>
                    <span><?php _e('Min', 'woocommerce-typesense-search'); ?></span>
                    <input type="number" 
                           name="wts_min_price" 
                           class="wts-price-min" 
                           min="<?php echo esc_attr($price_range['min']); ?>" 
                           max="<?php echo esc_attr($price_range['max']); ?>"
                           step="1" 
                           placeholder="<?php echo esc_attr($price_range['min']); ?>">
                </label>
                <span class="wts-price-separator">-</span>
                <label>
                    <span><?php _e('Max', 'woocommerce-typesense-search'); ?></span>
                    <input type="number" 
                           name="wts_max_price" 
                           class="wts-price-max" 
                           min="<?php echo esc_attr($price_range['min']); ?>" 
                           max="<?php echo esc_attr($price_range['max']); ?>"
                           step="1" 
                           placeholder="<?php echo esc_attr($price_range['max']); ?>">
                </label>
            </div>
            
            <!-- Price Ranges -->
            <div class="wts-price-ranges">
                <button type="button" class="wts-price-range" data-min="0" data-max="25">0€ - 25€</button>
                <button type="button" class="wts-price-range" data-min="25" data-max="50">25€ - 50€</button>
                <button type="button" class="wts-price-range" data-min="50" data-max="100">50€ - 100€</button>
                <button type="button" class="wts-price-range" data-min="100" data-max="999999">100€+</button>
            </div>
        </div>
    </div>

    <!-- Categories Filter -->
    <?php if (!empty($categories)) : ?>
    <div class="wts-filter-group">
        <h4 class="wts-filter-title"><?php _e('Catégories', 'woocommerce-typesense-search'); ?></h4>
        <div class="wts-category-facets">
            <?php foreach ($categories as $category) : ?>
                <label class="wts-facet-item">
                    <input type="checkbox" 
                           name="wts_categories[]" 
                           value="<?php echo esc_attr($category['value']); ?>"
                           class="wts-category-filter">
                    <span class="wts-facet-label"><?php echo esc_html($category['value']); ?></span>
                    <span class="wts-facet-count">(<?php echo esc_html($category['count']); ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stock Status Filter -->
    <div class="wts-filter-group">
        <h4 class="wts-filter-title"><?php _e('Disponibilité', 'woocommerce-typesense-search'); ?></h4>
        <label class="wts-facet-item">
            <input type="checkbox" name="wts_in_stock" value="1" class="wts-stock-filter">
            <span class="wts-facet-label"><?php _e('En stock uniquement', 'woocommerce-typesense-search'); ?></span>
        </label>
    </div>

    <!-- Special Offers Filter -->
    <div class="wts-filter-group">
        <h4 class="wts-filter-title"><?php _e('Promotions', 'woocommerce-typesense-search'); ?></h4>
        <label class="wts-facet-item">
            <input type="checkbox" name="wts_on_sale" value="1" class="wts-sale-filter">
            <span class="wts-facet-label"><?php _e('En promotion', 'woocommerce-typesense-search'); ?></span>
        </label>
    </div>

    <!-- Rating Filter -->
    <div class="wts-filter-group">
        <h4 class="wts-filter-title"><?php _e('Note minimum', 'woocommerce-typesense-search'); ?></h4>
        <div class="wts-rating-filters">
            <?php for ($i = 5; $i >= 1; $i--) : ?>
                <label class="wts-facet-item wts-rating-item">
                    <input type="radio" 
                           name="wts_min_rating" 
                           value="<?php echo $i; ?>" 
                           class="wts-rating-filter">
                    <span class="wts-rating-stars">
                        <?php for ($j = 0; $j < $i; $j++) : ?>
                            ⭐
                        <?php endfor; ?>
                        <?php if ($i < 5) : ?>
                            <span class="wts-rating-text"><?php _e('et plus', 'woocommerce-typesense-search'); ?></span>
                        <?php endif; ?>
                    </span>
                </label>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Apply Filters Button (Mobile) -->
    <div class="wts-filter-actions wts-mobile-only">
        <button type="button" class="wts-apply-filters button">
            <?php _e('Appliquer les filtres', 'woocommerce-typesense-search'); ?>
        </button>
    </div>
</div>
