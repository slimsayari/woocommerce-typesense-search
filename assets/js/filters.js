/**
 * WooCommerce Typesense Search - Front-End Filters JavaScript
 * Single source of truth for all product filtering via AJAX
 * 
 * @package WooCommerce_Typesense_Search
 */

(function ($) {
    'use strict';

    const WTSFilters = {
        isLoading: false,
        debounceTimer: null,
        initialized: false,

        /**
         * Initialize the filter system
         */
        init: function () {
            if (this.initialized) {
                console.log('WTS Filters: Already initialized, skipping');
                return;
            }

            console.log('WTS Filters: Initializing...');
            this.initialized = true;
            this.bindEvents();
            this.loadFiltersFromURL();
            this.applyInitialFiltersFromURL();
        },

        /**
         * Bind all filter events
         */
        bindEvents: function () {
            const self = this;
            let lastCategoryChange = 0;

            // === Category Filters (single handler to prevent duplicates) ===
            $(document).on('change', 'input[id^="cat-"], .wts-category-filter, .widget_product_categories input[type="checkbox"]', function (e) {
                e.stopImmediatePropagation(); // Prevent other handlers from firing

                // Prevent duplicate fires within 100ms
                const now = Date.now();
                if (now - lastCategoryChange < 100) {
                    console.log('WTS Filters: Ignoring duplicate category change event');
                    return;
                }
                lastCategoryChange = now;
                console.log('WTS Filters: Category checkbox changed');
                self.handleFilterChange('category');
            });

            // === Price Filters (AJAX on input change) ===
            $(document).on('change keyup', '.wts-price-min, .wts-price-max, .price-input, input[name="price_min"], input[name="price_max"], input[name="min_price"], input[name="max_price"]', function () {
                self.debounceApplyFilters();
            });

            $(document).on('click', '.wts-price-range', this.setPriceRange.bind(this));

            // === Stock/Sale/Rating Filters ===
            $(document).on('change', '.wts-stock-filter, input[name="stock_status"]', function () {
                self.handleFilterChange('stock');
            });

            $(document).on('change', '.wts-sale-filter', function () {
                self.handleFilterChange('sale');
            });

            $(document).on('change', '.wts-rating-filter', function () {
                self.handleFilterChange('rating');
            });

            // === Attribute Filters ===
            $(document).on('change', '[class*="wts-attribute-filter"], [name^="pa_"]', function () {
                self.handleFilterChange('attribute');
            });

            // === Clear Filters ===
            $(document).on('click', '.wts-clear-filters, .clear-filters', this.clearFilters.bind(this));

            // === Sorting ===
            $(document).on('change', '.woocommerce-ordering .orderby, select.orderby', function () {
                self.handleFilterChange('sort');
            });

            // === Pagination ===
            $(document).on('click', '.woocommerce-pagination a, .page-numbers a', this.handlePagination.bind(this));

            // === Live Search Input (AJAX on typing) - EXCLUDE HEADER SEARCH ===
            let searchDebounce = null;
            $(document).on('input keyup', '.wts-search-input:not(.wts-header-search-input), .search-field:not(.wts-header-search-input), input[name="s"]:not(.wts-header-search-input)', function (e) {
                // Don't trigger on Enter (let form submit handle it if present)
                if (e.keyCode === 13) return;

                const searchTerm = $(this).val().trim();
                clearTimeout(searchDebounce);
                searchDebounce = setTimeout(function () {
                    console.log('WTS Filters: Live search for:', searchTerm || '(empty - clearing search)');
                    // Always apply filters, even if search is empty (to clear the search)
                    self.applyFilters(null, 1, searchTerm || '');
                }, 500); // 500ms debounce
            });

            // === Search Form Submission (fallback) - EXCLUDE HEADER FORM ===
            $(document).on('submit', '.woocommerce-product-search:not(.wts-header-search-form), .search-form:not(.wts-header-search-form), form[role="search"]:not(.wts-header-search-form)', function (e) {
                const $form = $(this);
                const searchTerm = $form.find('input[name="s"], input[type="search"]').val();

                // Only intercept if we're on a shop/product page
                if ($('body').hasClass('woocommerce') || $('body').hasClass('post-type-archive-product') || $('ul.products').length) {
                    e.preventDefault();
                    self.applyFilters(null, 1, searchTerm);
                }
            });
        },

        /**
         * Handle filter change with debounce protection
         */
        handleFilterChange: function (type) {
            console.log('WTS Filters: Filter changed -', type);
            this.applyFilters();
        },

        /**
         * Debounced filter application
         */
        debounceApplyFilters: function () {
            const self = this;
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(function () {
                self.applyFilters();
            }, 400);
        },

        /**
         * Set price range from quick buttons
         */
        setPriceRange: function (e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const min = $button.data('min');
            const max = $button.data('max');

            // Update all price inputs
            $('.wts-price-min, .price-input[placeholder*="Min"], input[name="min_price"]').val(min);
            $('.wts-price-max, .price-input[placeholder*="Max"], input[name="max_price"]').val(max);

            this.applyFilters();
        },

        /**
         * Handle pagination clicks
         */
        handlePagination: function (e) {
            e.preventDefault();
            const url = $(e.currentTarget).attr('href');

            if (!url) return;

            // Extract page number
            let page = 1;
            const pageMatch = url.match(/\/page\/(\d+)/);
            if (pageMatch) {
                page = parseInt(pageMatch[1]);
            } else {
                const urlParams = new URLSearchParams(new URL(url, window.location.origin).search);
                page = parseInt(urlParams.get('paged')) || parseInt(urlParams.get('page')) || 1;
            }

            console.log('WTS Filters: Pagination to page', page);
            this.applyFilters(null, page);

            // Scroll to products
            const $products = $('ul.products, .products-grid');
            if ($products.length) {
                $('html, body').animate({
                    scrollTop: $products.offset().top - 100
                }, 300);
            }
        },

        /**
         * Apply all current filters via AJAX
         */
        applyFilters: function (e, page, searchOverride) {
            if (this.isLoading) {
                console.log('WTS Filters: Already loading, queued');
                return;
            }

            const self = this;
            const paged = page || 1;

            // Build request data
            const data = {
                action: 'wts_ajax_filter_products',
                paged: paged,
                per_page: 16
            };

            // === Search Term - EXCLUDE HEADER SEARCH ===
            let searchTerm = searchOverride !== undefined ? searchOverride :
                ($('input[name="s"]:not(.wts-header-search-input)').val() ||
                    this.getURLParam('s') ||
                    '');

            // Only add search term if it's not empty
            if (searchTerm && searchTerm.trim()) {
                data.s = searchTerm.trim();
            }

            // === Price Filters ===
            let minPrice = $('.wts-price-min').val() ||
                $('input[name="price_min"]').val() ||
                $('.price-input[placeholder*="Min"]').val() ||
                $('input[name="min_price"]').val();
            let maxPrice = $('.wts-price-max').val() ||
                $('input[name="price_max"]').val() ||
                $('.price-input[placeholder*="Max"]').val() ||
                $('input[name="max_price"]').val();

            if (minPrice) data.price_min = minPrice;
            if (maxPrice) data.price_max = maxPrice;

            // === Category Filters ===
            const categories = [];

            // WTS category filters
            $('.wts-category-filter:checked').each(function () {
                const val = $(this).val();
                if (val && categories.indexOf(val) === -1) categories.push(val);
            });

            // Theme category checkboxes (cat-*)
            $('input[id^="cat-"]:checked').each(function () {
                const slug = $(this).attr('id').replace('cat-', '');
                if (slug && categories.indexOf(slug) === -1) categories.push(slug);
            });

            // Form check inputs for product_cat
            $('input[name="product_cat[]"]:checked, .form-check-input[name*="product_cat"]:checked').each(function () {
                const val = $(this).val();
                if (val && categories.indexOf(val) === -1) categories.push(val);
            });

            if (categories.length > 0) {
                data.product_cat = categories;
                console.log('WTS Filters: Categories:', categories);
            }

            // === Stock Filter ===
            if ($('.wts-stock-filter:checked').length || $('input[name="stock_status"]:checked').length) {
                data.stock_status = 'instock';
            }

            // === Sale Filter ===
            if ($('.wts-sale-filter:checked').length) {
                data.on_sale = '1';
            }

            // === Rating Filter ===
            const rating = $('.wts-rating-filter:checked').val();
            if (rating) {
                data.min_rating = rating;
            }

            // === Attribute Filters ===
            $('[class*="wts-attribute-filter"]:checked, [name^="pa_"]:checked').each(function () {
                const $input = $(this);
                const attrName = $input.data('taxonomy') || $input.attr('name').replace('[]', '');
                if (attrName) {
                    if (!data[attrName]) data[attrName] = [];
                    const val = $input.val();
                    if (data[attrName].indexOf(val) === -1) {
                        data[attrName].push(val);
                    }
                }
            });

            // === Sorting ===
            const orderby = $('.woocommerce-ordering .orderby').val() ||
                $('select.orderby').val() ||
                'menu_order';
            data.orderby = orderby;

            console.log('WTS Filters: Sending request:', data);

            this.showLoading();
            this.isLoading = true;
            this.updateURL(data);

            $.ajax({
                url: wtsFilters.ajaxUrl,
                type: 'POST',
                data: data,
                success: function (response) {
                    console.log('WTS Filters: Response:', response);
                    if (response.success) {
                        self.updateDisplay(response.data);
                    } else {
                        console.error('WTS Filters: Error:', response.data?.message || 'Unknown error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('WTS Filters: AJAX error:', error);
                },
                complete: function () {
                    self.isLoading = false;
                    self.hideLoading();
                }
            });
        },

        /**
         * Update the display with new data
         */
        updateDisplay: function (data) {
            console.log('WTS Filters: Updating display with', data.count, 'products');

            // === Update Products ===
            this.updateProducts(data.html, data.count);

            // === Update Result Counts ===
            this.updateResultCounts(data.count);

            // === Update Pagination ===
            this.updatePagination(data);

            // === Update Facets ===
            if (data.facets) {
                this.updateFacets(data.facets);
            }

            // === Show/Hide Clear Button ===
            if (this.hasActiveFilters()) {
                $('.wts-clear-filters, .clear-filters').show();
            } else {
                $('.wts-clear-filters, .clear-filters').hide();
            }

            // Trigger events
            $(document).trigger('wts_filters_updated', [data]);
            $(document.body).trigger('post-load');
        },

        /**
         * Update product grid
         */
        updateProducts: function (html, count) {
            // Find product container
            const $productsGrid = $('.products-grid');
            const $ulProducts = $('ul.products');

            if (count === 0) {
                // No products found
                const noResultsHtml = '<li class="no-products-found"><p class="woocommerce-info">Aucun produit ne correspond à votre sélection.</p></li>';
                if ($ulProducts.length) {
                    $ulProducts.html(noResultsHtml);
                } else if ($productsGrid.length) {
                    $productsGrid.html('<ul class="products">' + noResultsHtml + '</ul>');
                }
                return;
            }

            // Parse the HTML response
            const $newHtml = $(html);

            if ($ulProducts.length) {
                // Update the ul.products content
                if ($newHtml.is('ul.products')) {
                    $ulProducts.html($newHtml.html());
                } else if ($newHtml.find('ul.products').length) {
                    $ulProducts.html($newHtml.find('ul.products').html());
                } else {
                    // Just list items
                    $ulProducts.html($newHtml);
                }
            } else if ($productsGrid.length) {
                $productsGrid.html(html);
            }

            console.log('WTS Filters: Products updated');
        },

        /**
         * Update all result count displays
         */
        updateResultCounts: function (count) {
            const countText = this.formatResultCount(count);
            console.log('WTS Filters: Updating result counts to:', count, '- Text:', countText);

            // Standard selectors
            const $resultCount = $('.woocommerce-result-count');
            const $catalogCount = $('.catalog-count');

            console.log('WTS Filters: Found .woocommerce-result-count:', $resultCount.length, 'elements');
            console.log('WTS Filters: Found .catalog-count:', $catalogCount.length, 'elements');

            $resultCount.text(countText);
            $catalogCount.text(countText);
            $('.products-count, .product-count, .results-count').text(countText);

            // Update header elements with count pattern
            $('h1, .page-title, .archive-title, .shop-title').each(function () {
                const $el = $(this);
                const text = $el.text();
                if (/\d+\s*(produits?|résultats?|products?)/i.test(text)) {
                    const newText = text.replace(/\d+\s*(produits?|résultats?|products?)/gi, count + ' produits');
                    if (newText !== text) {
                        console.log('WTS Filters: Updating header element from', text, 'to', newText);
                        $el.text(newText);
                    }
                }
            });

            // Update text nodes containing count patterns (outside of filters)
            $('body').find('*').not('.sidebar, .sidebar *, .widget, .widget *, .filter, .filter *, label, label *, .form-check-label, .form-check-label *').contents().filter(function () {
                return this.nodeType === Node.TEXT_NODE &&
                    /\d+\s*(produits?|résultats?)\s*(trouvés?)?/i.test(this.nodeValue);
            }).each(function () {
                const oldValue = this.nodeValue;
                const newValue = oldValue.replace(/\d+\s*produits?\s*trouvés?/gi, count + ' produits trouvés')
                    .replace(/\d+\s*résultats?/gi, count + ' résultats')
                    .replace(/\d+\s*produits?(?!\s*trouvés)/gi, count + ' produits');
                if (newValue !== oldValue) {
                    this.nodeValue = newValue;
                }
            });

            console.log('WTS Filters: Updated counts to', count);
        },

        /**
         * Format result count text
         */
        formatResultCount: function (count) {
            if (count === 0) {
                return wtsFilters.i18n?.noResults || 'Aucun produit trouvé';
            } else if (count === 1) {
                return wtsFilters.i18n?.oneResult || '1 produit trouvé';
            } else {
                return count + ' ' + (wtsFilters.i18n?.results || 'produits trouvés');
            }
        },

        /**
         * Update pagination
         */
        updatePagination: function (data) {
            const $pagination = $('.woocommerce-pagination');

            if (!data.max_num_pages || data.max_num_pages <= 1) {
                $pagination.hide();
                return;
            }

            const currentPage = parseInt(this.getURLParam('paged')) || 1;
            const maxPages = data.max_num_pages;

            let html = '<nav class="woocommerce-pagination"><ul class="page-numbers">';

            // Previous
            if (currentPage > 1) {
                html += '<li><a class="prev page-numbers" href="' + this.buildPaginationURL(currentPage - 1) + '">←</a></li>';
            }

            // Page numbers
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(maxPages, currentPage + 2);

            if (startPage > 1) {
                html += '<li><a class="page-numbers" href="' + this.buildPaginationURL(1) + '">1</a></li>';
                if (startPage > 2) html += '<li><span class="page-numbers dots">…</span></li>';
            }

            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += '<li><span class="page-numbers current">' + i + '</span></li>';
                } else {
                    html += '<li><a class="page-numbers" href="' + this.buildPaginationURL(i) + '">' + i + '</a></li>';
                }
            }

            if (endPage < maxPages) {
                if (endPage < maxPages - 1) html += '<li><span class="page-numbers dots">…</span></li>';
                html += '<li><a class="page-numbers" href="' + this.buildPaginationURL(maxPages) + '">' + maxPages + '</a></li>';
            }

            // Next
            if (currentPage < maxPages) {
                html += '<li><a class="next page-numbers" href="' + this.buildPaginationURL(currentPage + 1) + '">→</a></li>';
            }

            html += '</ul></nav>';

            if ($pagination.length) {
                $pagination.replaceWith(html);
            } else {
                $('ul.products').after(html);
            }

            $('.woocommerce-pagination').show();
        },

        /**
         * Build pagination URL
         */
        buildPaginationURL: function (page) {
            const params = new URLSearchParams(window.location.search);
            if (page > 1) {
                params.set('paged', page);
            } else {
                params.delete('paged');
            }
            return window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        },

        /**
         * Update facet counts and visibility
         */
        updateFacets: function (facets) {
            console.log('WTS Filters: Updating facets', facets);

            // === Update Categories ===
            if (facets.categories && Array.isArray(facets.categories)) {
                const categoryMap = {};
                facets.categories.forEach(function (cat) {
                    categoryMap[cat.value] = cat.count;
                    categoryMap[cat.value.toLowerCase()] = cat.count;
                    // Slug format
                    const slug = cat.value.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
                    categoryMap[slug] = cat.count;
                });

                console.log('WTS Filters: Category map:', categoryMap);

                // Count how many checkboxes we find
                const $categoryCheckboxes = $('input[id^="cat-"]');
                console.log('WTS Filters: Found', $categoryCheckboxes.length, 'category checkboxes');

                // Update all category filter labels
                $categoryCheckboxes.each(function () {
                    const $checkbox = $(this);
                    const id = $checkbox.attr('id');
                    const slug = id.replace('cat-', '');
                    const $label = $('label[for="' + id + '"]');
                    // Prioritize li element for wrapper to ensure proper hiding
                    let $wrapper = $checkbox.closest('li');
                    if (!$wrapper.length) {
                        $wrapper = $checkbox.closest('.form-check, .wts-facet-item');
                    }

                    // Find count from map
                    const count = categoryMap[slug] || 0;
                    const isChecked = $checkbox.is(':checked');

                    console.log('WTS Filters: Category', slug, '- count:', count, 'checked:', isChecked, 'wrapper found:', $wrapper.length);

                    if ($label.length) {
                        // Try to find the small/count element first
                        const $countEl = $label.find('small, .count, .text-muted');

                        if ($countEl.length) {
                            // Update just the count element
                            $countEl.text('(' + count + ')');
                            console.log('WTS Filters: Updated small element for', slug);
                        } else {
                            // Fallback: count is plain text in label, use regex
                            const labelText = $label.text();
                            const newText = labelText.replace(/\(\d+\)/, '(' + count + ')');
                            if (newText !== labelText) {
                                $label.text(newText);
                                console.log('WTS Filters: Updated label text for', slug);
                            }
                        }
                    }

                    // Hide/show based on count (hide if 0 and not checked)
                    if (count === 0 && !isChecked) {
                        $wrapper.addClass('wts-filter-empty').hide();
                        console.log('WTS Filters: HIDING category', slug);
                    } else {
                        $wrapper.removeClass('wts-filter-empty').show();
                        console.log('WTS Filters: SHOWING category', slug);
                    }
                });

                // Also update WTS category filters
                $('.wts-category-filter').each(function () {
                    const $checkbox = $(this);
                    const value = $checkbox.val();
                    const count = categoryMap[value] || categoryMap[value.toLowerCase()] || 0;
                    const $label = $checkbox.closest('label, .wts-facet-item');
                    const $countSpan = $label.find('.wts-facet-count, .count');

                    if ($countSpan.length) {
                        $countSpan.text('(' + count + ')');
                    }

                    if (count === 0 && !$checkbox.is(':checked')) {
                        $label.addClass('wts-filter-empty').hide();
                    } else {
                        $label.removeClass('wts-filter-empty').show();
                    }
                });
            }

            // === Update Stock Status ===
            if (facets.stock_status && Array.isArray(facets.stock_status)) {
                const stockMap = {};
                facets.stock_status.forEach(function (s) {
                    stockMap[s.value] = s.count;
                });

                const instockCount = stockMap['instock'] || 0;

                $('.wts-stock-filter').each(function () {
                    const $checkbox = $(this);
                    const $label = $checkbox.closest('label, .wts-facet-item');
                    const $countSpan = $label.find('.wts-facet-count, .count');

                    if ($countSpan.length) {
                        $countSpan.text('(' + instockCount + ')');
                    }
                });
            }

            // === Update Attributes ===
            if (facets.attributes && Array.isArray(facets.attributes)) {
                const attrMap = {};
                facets.attributes.forEach(function (attr) {
                    attrMap[attr.value] = attr.count;
                });

                $('[class*="wts-attribute-filter"], [name^="pa_"]').each(function () {
                    const $checkbox = $(this);
                    const value = $checkbox.val();
                    const count = attrMap[value] || 0;
                    const $label = $checkbox.closest('label, .wts-facet-item, .form-check');
                    const $countSpan = $label.find('.wts-facet-count, .count');

                    if ($countSpan.length) {
                        $countSpan.text('(' + count + ')');
                    }

                    if (count === 0 && !$checkbox.is(':checked')) {
                        $label.addClass('wts-filter-empty').hide();
                    } else {
                        $label.removeClass('wts-filter-empty').show();
                    }
                });
            }
        },

        /**
         * Check if any filters are active
         */
        hasActiveFilters: function () {
            // Check URL params
            const params = new URLSearchParams(window.location.search);
            const filterParams = ['s', 'price_min', 'price_max', 'product_cat', 'stock_status', 'on_sale', 'min_rating'];

            for (const param of filterParams) {
                if (params.has(param) && params.get(param)) return true;
            }

            for (const [key] of params) {
                if (key.startsWith('pa_') || key.startsWith('filter_')) return true;
            }

            // Check UI state
            if ($('.wts-category-filter:checked, input[id^="cat-"]:checked').length > 0) return true;
            if ($('.wts-stock-filter:checked, .wts-sale-filter:checked').length > 0) return true;
            if ($('.wts-price-min').val() || $('.wts-price-max').val()) return true;
            if ($('.price-input').filter(function () { return $(this).val(); }).length > 0) return true;
            if ($('input[name="s"]:not(.wts-header-search-input)').val()) return true;

            return false;
        },

        /**
         * Update URL with current filter state
         */
        updateURL: function (data) {
            const params = new URLSearchParams();

            // Only add search param if it's not empty
            if (data.s && data.s.trim()) params.set('s', data.s.trim());
            if (data.price_min) params.set('price_min', data.price_min);
            if (data.price_max) params.set('price_max', data.price_max);
            if (data.product_cat && data.product_cat.length) {
                params.set('product_cat', data.product_cat.join(','));
            }
            if (data.stock_status) params.set('stock_status', data.stock_status);
            if (data.on_sale) params.set('on_sale', data.on_sale);
            if (data.min_rating) params.set('min_rating', data.min_rating);
            if (data.orderby && data.orderby !== 'menu_order') params.set('orderby', data.orderby);
            if (data.paged && data.paged > 1) params.set('paged', data.paged);

            // Attribute filters
            Object.keys(data).forEach(function (key) {
                if (key.startsWith('pa_') && data[key] && data[key].length) {
                    params.set(key, data[key].join(','));
                }
            });

            const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState({}, '', newURL);
        },

        /**
         * Get URL parameter
         */
        getURLParam: function (param) {
            return new URLSearchParams(window.location.search).get(param);
        },

        /**
         * Clear all filters
         */
        clearFilters: function (e) {
            if (e) e.preventDefault();
            console.log('WTS Filters: Clearing all filters');

            // Clear all inputs
            $('.wts-price-min, .wts-price-max, .price-input').val('');
            $('input[name="min_price"], input[name="max_price"]').val('');
            $('.wts-category-filter, .wts-stock-filter, .wts-sale-filter, .wts-rating-filter').prop('checked', false);
            $('[class*="wts-attribute-filter"], [name^="pa_"]').prop('checked', false);
            $('input[id^="cat-"], input[name="product_cat[]"]').prop('checked', false);
            $('input[name="s"]:not(.wts-header-search-input)').val('');

            // Reset sorting
            $('.woocommerce-ordering .orderby, select.orderby').val('menu_order');

            // Clear URL
            window.history.replaceState({}, '', window.location.pathname);

            // Reload without filters
            this.applyFilters();
        },

        /**
         * Load filter states from URL on page load
         */
        loadFiltersFromURL: function () {
            const params = new URLSearchParams(window.location.search);

            // Search term - EXCLUDE HEADER
            if (params.has('s')) {
                $('input[name="s"]:not(.wts-header-search-input)').val(params.get('s'));
            }

            // Price
            if (params.has('price_min')) {
                const val = params.get('price_min');
                $('.wts-price-min').val(val);
                $('.price-input[placeholder*="Min"]').val(val);
            }
            if (params.has('price_max')) {
                const val = params.get('price_max');
                $('.wts-price-max').val(val);
                $('.price-input[placeholder*="Max"]').val(val);
            }

            // Categories
            if (params.has('product_cat')) {
                const categories = params.get('product_cat').split(',');
                categories.forEach(function (cat) {
                    $('.wts-category-filter[value="' + cat + '"]').prop('checked', true);
                    $('input#cat-' + cat).prop('checked', true);
                    $('input[name="product_cat[]"][value="' + cat + '"]').prop('checked', true);
                });
            }

            // Stock
            if (params.has('stock_status')) {
                $('.wts-stock-filter').prop('checked', true);
                $('input[name="stock_status"]').prop('checked', true);
            }

            // Sale
            if (params.has('on_sale')) {
                $('.wts-sale-filter').prop('checked', true);
            }

            // Rating
            if (params.has('min_rating')) {
                $('.wts-rating-filter[value="' + params.get('min_rating') + '"]').prop('checked', true);
            }

            // Show clear button if filters active
            if (this.hasActiveFilters()) {
                $('.wts-clear-filters, .clear-filters').show();
            }
        },

        /**
         * Apply filters from URL on initial page load
         */
        applyInitialFiltersFromURL: function () {
            // If there are filter params in URL, apply them
            const params = new URLSearchParams(window.location.search);
            const hasFilters = params.has('s') || params.has('product_cat') || params.has('price_min') ||
                params.has('price_max') || params.has('stock_status') || params.has('on_sale');

            if (hasFilters) {
                console.log('WTS Filters: URL has filters, applying on load');
                // Small delay to ensure DOM is ready
                setTimeout(() => {
                    this.applyFilters();
                }, 100);
            }
        },

        /**
         * Show loading overlay
         */
        showLoading: function () {
            const $products = $('ul.products, .products-grid');
            if ($products.length) {
                $products.addClass('wts-loading').css('position', 'relative');

                if (!$products.find('.wts-loading-overlay').length) {
                    $products.append('<div class="wts-loading-overlay"><div class="wts-spinner"></div></div>');
                }
            }
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function () {
            const $products = $('ul.products, .products-grid');
            $products.removeClass('wts-loading');
            $products.find('.wts-loading-overlay').remove();
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        // Create wtsFilters config if not available
        if (typeof wtsFilters === 'undefined') {
            console.warn('WTS Filters: Config not found, using defaults');
            window.wtsFilters = {
                ajaxUrl: (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp/wp-admin/admin-ajax.php',
                i18n: {
                    oneResult: '1 produit trouvé',
                    results: 'produits trouvés',
                    noResults: 'Aucun produit trouvé'
                }
            };
        }

        // Initialize on relevant pages
        if ($('.wts-filters-sidebar').length > 0 ||
            $('body').hasClass('woocommerce') ||
            $('body').hasClass('post-type-archive-product') ||
            $('body').hasClass('tax-product_cat') ||
            $('ul.products').length > 0 ||
            $('.products-grid').length > 0) {
            WTSFilters.init();
        }
    });

})(jQuery);
