<?php
/**
 * Enhanced Search Form for Header
 * Includes autocomplete, voice search, and image search
 *
 * @package WooCommerce_Typesense_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$show_voice = WooCommerce_Typesense_Search::get_setting('voice_search_enabled', false);
$show_image = WooCommerce_Typesense_Search::get_setting('image_search_enabled', false);
?>

<div class="wts-header-search-wrapper">
    <form role="search" method="get" class="wts-header-search-form" action="<?php echo esc_url(home_url('/')); ?>">
        <input type="hidden" name="post_type" value="product">

        <div class="wts-search-input-container">
            <span class="wts-search-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                </svg>
            </span>

            <input type="search" class="wts-header-search-input wts-autocomplete-input" name="s"
                placeholder="<?php _e('Rechercher des produits...', 'woocommerce-typesense-search'); ?>"
                autocomplete="off" value="">

            <div class="wts-search-actions">
                <?php if ($show_voice): ?>
                    <button type="button" class="wts-voice-trigger wts-action-btn"
                        title="<?php _e('Recherche vocale', 'woocommerce-typesense-search'); ?>"
                        aria-label="<?php _e('Recherche vocale', 'woocommerce-typesense-search'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                            <line x1="12" y1="19" x2="12" y2="23" />
                            <line x1="8" y1="23" x2="16" y2="23" />
                        </svg>
                        <span class="wts-voice-status"></span>
                    </button>
                <?php endif; ?>

                <?php if ($show_image): ?>
                    <button type="button" class="wts-image-trigger wts-action-btn"
                        title="<?php _e('Recherche par image', 'woocommerce-typesense-search'); ?>"
                        aria-label="<?php _e('Recherche par image', 'woocommerce-typesense-search'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                            <circle cx="8.5" cy="8.5" r="1.5" />
                            <polyline points="21 15 16 10 5 21" />
                        </svg>
                    </button>
                    <input type="file" class="wts-image-input" accept="image/*" style="display:none;">
                <?php endif; ?>
            </div>
        </div>

        <!-- Autocomplete Dropdown -->
        <div class="wts-autocomplete-dropdown" style="display:none;">
            <div class="wts-autocomplete-loading">
                <span class="wts-spinner"></span>
                <?php _e('Recherche en cours...', 'woocommerce-typesense-search'); ?>
            </div>
            <div class="wts-autocomplete-results"></div>
        </div>

        <!-- Image Preview Container -->
        <?php if ($show_image): ?>
            <div class="wts-image-preview-container" style="display:none;">
                <div class="wts-image-preview">
                    <button type="button" class="wts-image-remove"
                        aria-label="<?php _e('Supprimer l\'image', 'woocommerce-typesense-search'); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                    <img class="wts-image-preview-img" src="" alt="">
                    <div class="wts-image-analyzing">
                        <span class="wts-spinner"></span>
                        <?php _e('Analyse de l\'image...', 'woocommerce-typesense-search'); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<style>
    .wts-header-search-wrapper {
        position: relative;
        width: 100%;
        max-width: 600px;
    }

    .wts-header-search-form {
        position: relative;
    }

    .wts-search-input-container {
        position: relative;
        display: flex;
        align-items: center;
        background: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }

    .wts-search-input-container:focus-within {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }

    .wts-search-icon {
        display: flex;
        align-items: center;
        color: #666;
        margin-right: 0.75rem;
    }

    .wts-header-search-input {
        flex: 1;
        border: none;
        outline: none;
        font-size: 1rem;
        padding: 0.25rem 0;
        background: transparent;
    }

    .wts-search-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-left: 0.5rem;
    }

    .wts-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        padding: 0.5rem;
        cursor: pointer;
        color: #666;
        border-radius: 4px;
        transition: all 0.2s ease;
    }

    .wts-action-btn:hover {
        background: #f5f5f5;
        color: #007bff;
    }

    .wts-action-btn.active {
        color: #dc3545;
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    .wts-voice-status {
        position: absolute;
        bottom: -20px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 0.75rem;
        white-space: nowrap;
        color: #dc3545;
    }

    .wts-autocomplete-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
    }

    .wts-autocomplete-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        gap: 0.75rem;
        color: #666;
    }

    .wts-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #e0e0e0;
        border-top-color: #007bff;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .wts-autocomplete-results {
        padding: 0.5rem 0;
    }

    .wts-image-preview-container {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        z-index: 1000;
    }

    .wts-image-preview {
        position: relative;
        text-align: center;
    }

    .wts-image-remove {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #dc3545;
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 1;
    }

    .wts-image-preview-img {
        max-width: 100%;
        max-height: 200px;
        border-radius: 4px;
    }

    .wts-image-analyzing {
        display: none;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        margin-top: 1rem;
        color: #666;
    }

    .wts-image-analyzing.active {
        display: flex;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .wts-header-search-wrapper {
            max-width: 100%;
        }

        .wts-search-input-container {
            padding: 0.4rem 0.75rem;
        }

        .wts-header-search-input {
            font-size: 0.9rem;
        }

        .wts-action-btn {
            padding: 0.4rem;
        }

        .wts-action-btn svg {
            width: 16px;
            height: 16px;
        }
    }
</style>