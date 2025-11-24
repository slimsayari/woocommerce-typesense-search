/**
 * WooCommerce Typesense Search - Autocomplete
 */

(function ($) {
    'use strict';

    const WTSAutocomplete = {
        init: function () {
            this.cache = {};
            this.timer = null;
            this.bindEvents();
        },

        bindEvents: function () {
            const self = this;

            // Input events
            $(document).on('input', '.wts-search-input', function () {
                const $input = $(this);
                const query = $input.val().trim();

                if (query.length < wtsAutocomplete.minChars) {
                    self.hideDropdown($input);
                    return;
                }

                clearTimeout(self.timer);
                self.timer = setTimeout(function () {
                    self.search(query, $input);
                }, wtsAutocomplete.delay);
            });

            // Keyboard navigation
            $(document).on('keydown', '.wts-search-input', function (e) {
                const $input = $(this);
                const $dropdown = $input.closest('.wts-search-form').find('.wts-autocomplete-dropdown');
                const $items = $dropdown.find('.wts-autocomplete-item');
                const $active = $dropdown.find('.wts-autocomplete-item.active');

                if (!$dropdown.is(':visible')) return;

                switch (e.which) {
                    case 38: // Up
                        e.preventDefault();
                        if ($active.length) {
                            $active.removeClass('active').prev().addClass('active');
                        } else {
                            $items.last().addClass('active');
                        }
                        break;

                    case 40: // Down
                        e.preventDefault();
                        if ($active.length) {
                            $active.removeClass('active').next().addClass('active');
                        } else {
                            $items.first().addClass('active');
                        }
                        break;

                    case 13: // Enter
                        if ($active.length) {
                            e.preventDefault();
                            window.location.href = $active.attr('href');
                        }
                        break;

                    case 27: // Escape
                        self.hideDropdown($input);
                        break;
                }
            });

            // Click outside to close
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.wts-search-container').length) {
                    $('.wts-autocomplete-dropdown').hide();
                }
            });

            // Focus input
            $(document).on('focus', '.wts-search-input', function () {
                const $input = $(this);
                if ($input.val().trim().length >= wtsAutocomplete.minChars) {
                    $input.closest('.wts-search-form').find('.wts-autocomplete-dropdown').show();
                }
            });
        },

        search: function (query, $input) {
            const self = this;

            // Check cache
            if (this.cache[query]) {
                this.renderResults(this.cache[query], $input);
                return;
            }

            $.ajax({
                url: wtsAutocomplete.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wts_autocomplete',
                    nonce: wtsAutocomplete.nonce,
                    query: query
                },
                success: function (response) {
                    if (response.success) {
                        self.cache[query] = response.data;
                        self.renderResults(response.data, $input);
                    }
                }
            });
        },

        renderResults: function (data, $input) {
            const $dropdown = $input.closest('.wts-search-form').find('.wts-autocomplete-dropdown');
            let html = '';

            // Products
            if (data.products && data.products.length > 0) {
                html += '<div class="wts-autocomplete-section">';
                html += '<h4 class="wts-autocomplete-header">Produits</h4>';

                data.products.forEach(function (product) {
                    html += `<a href="${product.url}" class="wts-autocomplete-item" data-type="product">
                        <div class="wts-autocomplete-thumb">
                            ${product.image ? `<img src="${product.image}" alt="${product.title}">` : '<span class="wts-no-image"></span>'}
                        </div>
                        <div class="wts-autocomplete-info">
                            <span class="wts-autocomplete-title">${product.title}</span>
                            <span class="wts-autocomplete-price">${product.price} €</span>
                        </div>
                    </a>`;
                });

                html += '</div>';
            }

            // Posts
            if (data.posts && data.posts.length > 0) {
                html += '<div class="wts-autocomplete-section">';
                html += '<h4 class="wts-autocomplete-header">Articles</h4>';

                data.posts.forEach(function (post) {
                    html += `<a href="${post.url}" class="wts-autocomplete-item" data-type="post">
                        <div class="wts-autocomplete-info">
                            <span class="wts-autocomplete-title">${post.title}</span>
                        </div>
                    </a>`;
                });

                html += '</div>';
            }

            // No results
            if (!html) {
                html = '<div class="wts-autocomplete-no-results">Aucun résultat trouvé</div>';
            } else {
                // View all link
                const searchUrl = $input.closest('form').attr('action') + '?s=' + encodeURIComponent($input.val());
                html += `<a href="${searchUrl}" class="wts-autocomplete-view-all">Voir tous les résultats</a>`;
            }

            $dropdown.html(html).show();
        },

        hideDropdown: function ($input) {
            $input.closest('.wts-search-form').find('.wts-autocomplete-dropdown').hide();
        }
    };

    $(document).ready(function () {
        WTSAutocomplete.init();
    });

})(jQuery);
