<?php
/**
 * Search Form Template
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$placeholder = isset($atts['placeholder']) ? $atts['placeholder'] : __('Search products...', 'woocommerce-typesense-search');
$show_filters = isset($atts['show_filters']) && $atts['show_filters'] === 'yes';
$show_voice = isset($atts['show_voice']) && $atts['show_voice'] === 'yes' && WooCommerce_Typesense_Search::get_setting('voice_search_enabled', false);
$show_image = isset($atts['show_image']) && $atts['show_image'] === 'yes' && WooCommerce_Typesense_Search::get_setting('image_search_enabled', false);
?>

<div class="wts-search-wrapper">
    <form class="wts-search-form" role="search">
        <input type="text" 
               class="wts-search-input" 
               placeholder="<?php echo esc_attr($placeholder); ?>"
               autocomplete="off"
               aria-label="<?php esc_attr_e('Search products', 'woocommerce-typesense-search'); ?>">
        
        <?php if ($show_voice) : ?>
            <button type="button" class="wts-voice-button" title="<?php esc_attr_e('Voice Search', 'woocommerce-typesense-search'); ?>">
                <span class="dashicons dashicons-microphone"></span>
                <span class="wts-voice-status" style="display:none;"><?php _e('Click to start', 'woocommerce-typesense-search'); ?></span>
            </button>
        <?php endif; ?>
        
        <?php if ($show_image) : ?>
            <button type="button" class="wts-image-button" title="<?php esc_attr_e('Search by Image', 'woocommerce-typesense-search'); ?>">
                <span class="dashicons dashicons-camera"></span>
            </button>
            <input type="file" class="wts-image-input" accept="image/*" style="display:none;">
        <?php endif; ?>
        
        <button type="submit" class="wts-search-button">
            <?php _e('Search', 'woocommerce-typesense-search'); ?>
        </button>
    </form>

    <div class="wts-suggestions"></div>

    <?php if ($show_image) : ?>
        <div class="wts-image-preview-container"></div>
    <?php endif; ?>
</div>

<?php if ($show_filters) : ?>
    <?php wc_get_template('search-filters.php', array(), 'woocommerce-typesense-search', WTS_PLUGIN_DIR . 'templates/'); ?>
<?php endif; ?>

<div class="wts-sort-wrapper">
    <div class="wts-result-count"></div>
    <select class="wts-sort-select">
        <option value="relevance"><?php _e('Relevance', 'woocommerce-typesense-search'); ?></option>
        <option value="price_asc"><?php _e('Price: Low to High', 'woocommerce-typesense-search'); ?></option>
        <option value="price_desc"><?php _e('Price: High to Low', 'woocommerce-typesense-search'); ?></option>
        <option value="date_desc"><?php _e('Newest First', 'woocommerce-typesense-search'); ?></option>
        <option value="rating"><?php _e('Highest Rated', 'woocommerce-typesense-search'); ?></option>
    </select>
</div>

<div class="wts-loading"></div>

<div class="wts-results"></div>

<button type="button" class="wts-load-more" style="display:none;">
    <?php _e('Load More', 'woocommerce-typesense-search'); ?>
</button>
