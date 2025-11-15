(function($) {
    'use strict';

    /**
     * Typesense Search Class
     */
    class TypesenseSearch {
        constructor() {
            this.searchInput = null;
            this.resultsContainer = null;
            this.debounceTimer = null;
            this.currentPage = 1;
            this.isLoading = false;
            this.filters = {
                categories: [],
                minPrice: null,
                maxPrice: null,
                inStock: false,
                onSale: false,
            };
            this.sortBy = 'relevance';
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.setupInfiniteScroll();
        }

        bindEvents() {
            $(document).on('input', '.wts-search-input', (e) => {
                this.handleSearchInput(e);
            });

            $(document).on('click', '.wts-filter-category', (e) => {
                this.handleCategoryFilter(e);
            });

            $(document).on('change', '.wts-filter-price', (e) => {
                this.handlePriceFilter(e);
            });

            $(document).on('change', '.wts-filter-stock', (e) => {
                this.handleStockFilter(e);
            });

            $(document).on('change', '.wts-filter-sale', (e) => {
                this.handleSaleFilter(e);
            });

            $(document).on('change', '.wts-sort-select', (e) => {
                this.handleSort(e);
            });

            $(document).on('click', '.wts-product-item', (e) => {
                this.trackClick(e);
            });

            $(document).on('click', '.wts-load-more', (e) => {
                e.preventDefault();
                this.loadMore();
            });

            // Suggestion handling
            $(document).on('input', '.wts-search-input', (e) => {
                this.handleSuggestions(e);
            });

            $(document).on('click', '.wts-suggestion-item', (e) => {
                this.selectSuggestion(e);
            });

            // Close suggestions when clicking outside
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.wts-search-wrapper').length) {
                    $('.wts-suggestions').hide();
                }
            });
        }

        handleSearchInput(e) {
            const query = $(e.target).val();
            
            clearTimeout(this.debounceTimer);
            
            if (query.length < 2) {
                this.clearResults();
                return;
            }

            this.debounceTimer = setTimeout(() => {
                this.currentPage = 1;
                this.search(query);
            }, 300);
        }

        handleSuggestions(e) {
            const query = $(e.target).val();
            
            if (query.length < 2) {
                $('.wts-suggestions').hide();
                return;
            }

            clearTimeout(this.suggestionTimer);
            
            this.suggestionTimer = setTimeout(() => {
                this.getSuggestions(query);
            }, 150);
        }

        getSuggestions(query) {
            $.ajax({
                url: wtsConfig.restUrl + 'suggest',
                method: 'GET',
                data: { q: query, limit: 5 },
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', wtsConfig.nonce);
                },
                success: (data) => {
                    this.displaySuggestions(data);
                },
                error: (xhr) => {
                    console.error('Suggestion error:', xhr);
                }
            });
        }

        displaySuggestions(suggestions) {
            const container = $('.wts-suggestions');
            
            if (!suggestions || suggestions.length === 0) {
                container.hide();
                return;
            }

            let html = '';
            suggestions.forEach((item) => {
                html += `
                    <div class="wts-suggestion-item" data-id="${item.id}" data-title="${item.title}">
                        ${item.image ? `<img src="${item.image}" alt="${item.title}" class="wts-suggestion-image">` : ''}
                        <div class="wts-suggestion-content">
                            <div class="wts-suggestion-title">${item.title}</div>
                            ${item.price ? `<div class="wts-suggestion-price">${this.formatPrice(item.price)}</div>` : ''}
                        </div>
                    </div>
                `;
            });

            container.html(html).show();
        }

        selectSuggestion(e) {
            const title = $(e.currentTarget).data('title');
            $('.wts-search-input').val(title);
            $('.wts-suggestions').hide();
            this.search(title);
        }

        search(query, append = false) {
            if (this.isLoading) return;

            this.isLoading = true;
            this.showLoading();

            const params = {
                q: query,
                page: this.currentPage,
                per_page: 12,
                sort_by: this.sortBy,
            };

            // Add filters
            if (this.filters.categories.length > 0) {
                params.categories = this.filters.categories.join(',');
            }
            if (this.filters.minPrice) {
                params.min_price = this.filters.minPrice;
            }
            if (this.filters.maxPrice) {
                params.max_price = this.filters.maxPrice;
            }
            if (this.filters.inStock) {
                params.in_stock = true;
            }
            if (this.filters.onSale) {
                params.on_sale = true;
            }

            $.ajax({
                url: wtsConfig.restUrl + 'search',
                method: 'GET',
                data: params,
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', wtsConfig.nonce);
                },
                success: (data) => {
                    this.displayResults(data, append);
                    this.displayFacets(data.facets);
                },
                error: (xhr) => {
                    this.showError(xhr.responseJSON?.message || 'Search failed');
                },
                complete: () => {
                    this.isLoading = false;
                    this.hideLoading();
                }
            });
        }

        displayResults(data, append = false) {
            const container = $('.wts-results');
            
            if (!data.products || data.products.length === 0) {
                if (!append) {
                    container.html(`<div class="wts-no-results">${wtsConfig.i18n.noResults}</div>`);
                }
                $('.wts-load-more').hide();
                return;
            }

            let html = '';
            data.products.forEach((product) => {
                html += this.renderProduct(product);
            });

            if (append) {
                container.append(html);
            } else {
                container.html(html);
            }

            // Show/hide load more button
            const totalPages = Math.ceil(data.total / data.per_page);
            if (this.currentPage < totalPages) {
                $('.wts-load-more').show();
            } else {
                $('.wts-load-more').hide();
            }

            // Update result count
            $('.wts-result-count').text(`${data.total} ${data.total === 1 ? 'product' : 'products'} found`);
        }

        renderProduct(product) {
            const stockClass = product.stock_status === 'instock' ? 'in-stock' : 'out-of-stock';
            const stockText = product.stock_status === 'instock' ? 'In Stock' : 'Out of Stock';
            
            return `
                <div class="wts-product-item" data-id="${product.id}">
                    <a href="${product.permalink}" class="wts-product-link">
                        ${product.image ? `<img src="${product.image}" alt="${product.title}" class="wts-product-image">` : ''}
                        <div class="wts-product-content">
                            <h3 class="wts-product-title">${product.title}</h3>
                            ${product.short_description ? `<div class="wts-product-description">${this.truncate(product.short_description, 100)}</div>` : ''}
                            <div class="wts-product-meta">
                                <span class="wts-product-price">${this.formatPrice(product.price)}</span>
                                ${product.on_sale ? '<span class="wts-product-badge wts-badge-sale">Sale</span>' : ''}
                                <span class="wts-product-stock ${stockClass}">${stockText}</span>
                            </div>
                            ${product.rating ? `<div class="wts-product-rating">${this.renderRating(product.rating)}</div>` : ''}
                        </div>
                    </a>
                </div>
            `;
        }

        displayFacets(facets) {
            if (!facets || facets.length === 0) return;

            facets.forEach((facet) => {
                if (facet.field_name === 'categories') {
                    this.renderCategoryFacets(facet.counts);
                }
            });
        }

        renderCategoryFacets(counts) {
            const container = $('.wts-category-facets');
            if (!container.length) return;

            let html = '';
            counts.forEach((item) => {
                html += `
                    <label class="wts-facet-item">
                        <input type="checkbox" class="wts-filter-category" value="${item.value}" ${this.filters.categories.includes(item.value) ? 'checked' : ''}>
                        ${item.value} (${item.count})
                    </label>
                `;
            });

            container.html(html);
        }

        handleCategoryFilter(e) {
            const category = $(e.target).val();
            const checked = $(e.target).is(':checked');

            if (checked) {
                this.filters.categories.push(category);
            } else {
                this.filters.categories = this.filters.categories.filter(c => c !== category);
            }

            this.currentPage = 1;
            const query = $('.wts-search-input').val();
            if (query) {
                this.search(query);
            }
        }

        handlePriceFilter(e) {
            const type = $(e.target).data('type');
            const value = parseFloat($(e.target).val());

            if (type === 'min') {
                this.filters.minPrice = value || null;
            } else {
                this.filters.maxPrice = value || null;
            }

            this.currentPage = 1;
            const query = $('.wts-search-input').val();
            if (query) {
                this.search(query);
            }
        }

        handleStockFilter(e) {
            this.filters.inStock = $(e.target).is(':checked');
            this.currentPage = 1;
            const query = $('.wts-search-input').val();
            if (query) {
                this.search(query);
            }
        }

        handleSaleFilter(e) {
            this.filters.onSale = $(e.target).is(':checked');
            this.currentPage = 1;
            const query = $('.wts-search-input').val();
            if (query) {
                this.search(query);
            }
        }

        handleSort(e) {
            this.sortBy = $(e.target).val();
            this.currentPage = 1;
            const query = $('.wts-search-input').val();
            if (query) {
                this.search(query);
            }
        }

        loadMore() {
            this.currentPage++;
            const query = $('.wts-search-input').val();
            this.search(query, true);
        }

        setupInfiniteScroll() {
            // Optional: implement infinite scroll
        }

        trackClick(e) {
            const productId = $(e.currentTarget).data('id');
            const searchTerm = $('.wts-search-input').val();

            if (!searchTerm) return;

            $.ajax({
                url: wtsConfig.restUrl + 'track-click',
                method: 'POST',
                data: {
                    search_term: searchTerm,
                    product_id: productId,
                },
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', wtsConfig.nonce);
                }
            });
        }

        formatPrice(price) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(price);
        }

        renderRating(rating) {
            const stars = Math.round(rating);
            let html = '<div class="wts-stars">';
            for (let i = 1; i <= 5; i++) {
                html += i <= stars ? '★' : '☆';
            }
            html += `</div><span class="wts-rating-value">${rating}</span>`;
            return html;
        }

        truncate(text, length) {
            if (text.length <= length) return text;
            return text.substring(0, length) + '...';
        }

        showLoading() {
            $('.wts-loading').show();
        }

        hideLoading() {
            $('.wts-loading').hide();
        }

        clearResults() {
            $('.wts-results').empty();
            $('.wts-result-count').empty();
            $('.wts-load-more').hide();
        }

        showError(message) {
            $('.wts-results').html(`<div class="wts-error">${message}</div>`);
        }
    }

    // Initialize on document ready
    $(document).ready(() => {
        if ($('.wts-search-wrapper').length) {
            new TypesenseSearch();
        }
    });

})(jQuery);
