/**
 * WooCommerce Typesense Search - Front-End Filters JavaScript
 */

(function ($) {
    'use strict';

    const WTSFilters = {
        init: function () {
            this.bindEvents();
            this.loadFiltersFromURL();
        },

        bindEvents: function () {
            // Price filters
            $('.wts-price-min, .wts-price-max').on('change', this.applyFilters.bind(this));
            $('.wts-price-range').on('click', this.setPriceRange.bind(this));

            // Category filters
            $('.wts-category-filter').on('change', this.applyFilters.bind(this));

            // Stock filter
            $('.wts-stock-filter').on('change', this.applyFilters.bind(this));

            // Sale filter
            $('.wts-sale-filter').on('change', this.applyFilters.bind(this));

            // Rating filter
            $('.wts-rating-filter').on('change', this.applyFilters.bind(this));

            // Clear filters
            $('.wts-clear-filters').on('click', this.clearFilters.bind(this));

            // Apply filters button (mobile)
            $('.wts-apply-filters').on('click', this.applyFilters.bind(this));
        },

        setPriceRange: function (e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const min = $button.data('min');
            const max = $button.data('max');

            $('.wts-price-min').val(min);
            $('.wts-price-max').val(max);

            this.applyFilters();
        },

        applyFilters: function () {
            const params = new URLSearchParams(window.location.search);

            // Price
            const minPrice = $('.wts-price-min').val();
            const maxPrice = $('.wts-price-max').val();

            if (minPrice) {
                params.set('wts_min_price', minPrice);
            } else {
                params.delete('wts_min_price');
            }

            if (maxPrice) {
                params.set('wts_max_price', maxPrice);
            } else {
                params.delete('wts_max_price');
            }

            // Categories
            const categories = [];
            $('.wts-category-filter:checked').each(function () {
                categories.push($(this).val());
            });

            if (categories.length > 0) {
                params.set('wts_categories', categories.join(','));
            } else {
                params.delete('wts_categories');
            }

            // Stock
            if ($('.wts-stock-filter').is(':checked')) {
                params.set('wts_in_stock', '1');
            } else {
                params.delete('wts_in_stock');
            }

            // Sale
            if ($('.wts-sale-filter').is(':checked')) {
                params.set('wts_on_sale', '1');
            } else {
                params.delete('wts_on_sale');
            }

            // Rating
            const rating = $('.wts-rating-filter:checked').val();
            if (rating) {
                params.set('wts_min_rating', rating);
            } else {
                params.delete('wts_min_rating');
            }

            // Reset page to 1
            params.delete('paged');

            // Reload page with new params
            const newURL = window.location.pathname + '?' + params.toString();
            window.location.href = newURL;
        },

        clearFilters: function (e) {
            e.preventDefault();

            // Clear all inputs
            $('.wts-price-min, .wts-price-max').val('');
            $('.wts-category-filter, .wts-stock-filter, .wts-sale-filter').prop('checked', false);
            $('.wts-rating-filter').prop('checked', false);

            // Reload without filters
            window.location.href = window.location.pathname;
        },

        loadFiltersFromURL: function () {
            const params = new URLSearchParams(window.location.search);

            // Price
            if (params.has('wts_min_price')) {
                $('.wts-price-min').val(params.get('wts_min_price'));
            }
            if (params.has('wts_max_price')) {
                $('.wts-price-max').val(params.get('wts_max_price'));
            }

            // Categories
            if (params.has('wts_categories')) {
                const categories = params.get('wts_categories').split(',');
                categories.forEach(function (cat) {
                    $('.wts-category-filter[value="' + cat + '"]').prop('checked', true);
                });
            }

            // Stock
            if (params.has('wts_in_stock')) {
                $('.wts-stock-filter').prop('checked', true);
            }

            // Sale
            if (params.has('wts_on_sale')) {
                $('.wts-sale-filter').prop('checked', true);
            }

            // Rating
            if (params.has('wts_min_rating')) {
                $('.wts-rating-filter[value="' + params.get('wts_min_rating') + '"]').prop('checked', true);
            }

            // Show clear button if any filter is active
            if (params.toString().includes('wts_')) {
                $('.wts-clear-filters').show();
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        if ($('.wts-filters-sidebar').length > 0) {
            WTSFilters.init();
        }
    });

})(jQuery);
