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
?>

<div class="wts-filters">
    <div class="wts-filter-group">
        <div class="wts-filter-title"><?php _e('Categories', 'woocommerce-typesense-search'); ?></div>
        <div class="wts-category-facets"></div>
    </div>

    <div class="wts-filter-group">
        <div class="wts-filter-title"><?php _e('Price Range', 'woocommerce-typesense-search'); ?></div>
        <div class="wts-filter-price">
            <label>
                <?php _e('Min:', 'woocommerce-typesense-search'); ?>
                <input type="number" class="wts-filter-price" data-type="min" min="0" step="1" placeholder="0">
            </label>
            <label>
                <?php _e('Max:', 'woocommerce-typesense-search'); ?>
                <input type="number" class="wts-filter-price" data-type="max" min="0" step="1" placeholder="1000">
            </label>
        </div>
    </div>

    <div class="wts-filter-group">
        <div class="wts-filter-title"><?php _e('Availability', 'woocommerce-typesense-search'); ?></div>
        <label class="wts-facet-item">
            <input type="checkbox" class="wts-filter-stock">
            <?php _e('In Stock Only', 'woocommerce-typesense-search'); ?>
        </label>
    </div>

    <div class="wts-filter-group">
        <div class="wts-filter-title"><?php _e('Special Offers', 'woocommerce-typesense-search'); ?></div>
        <label class="wts-facet-item">
            <input type="checkbox" class="wts-filter-sale">
            <?php _e('On Sale', 'woocommerce-typesense-search'); ?>
        </label>
    </div>
</div>
