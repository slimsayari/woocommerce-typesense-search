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

            // Input events - use the new class for header search
            $(document).on('input', '.wts-autocomplete-input', function () {
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
            $(document).on('keydown', '.wts-autocomplete-input', function (e) {
                const $input = $(this);
                const $dropdown = $input.closest('.wts-header-search-form, .wts-search-form').find('.wts-autocomplete-dropdown');
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
                        // Otherwise let form submit normally
                        break;

                    case 27: // Escape
                        self.hideDropdown($input);
                        break;
                }
            });

            // Click outside to close
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.wts-header-search-wrapper, .wts-search-container').length) {
                    $('.wts-autocomplete-dropdown').hide();
                }
            });

            // Focus input
            $(document).on('focus', '.wts-autocomplete-input', function () {
                const $input = $(this);
                if ($input.val().trim().length >= wtsAutocomplete.minChars) {
                    $input.closest('.wts-header-search-form, .wts-search-form').find('.wts-autocomplete-dropdown').show();
                }
            });
        },

        search: function (query, $input) {
            const self = this;
            const $dropdown = $input.closest('.wts-header-search-form, .wts-search-form').find('.wts-autocomplete-dropdown');
            const $loading = $dropdown.find('.wts-autocomplete-loading');
            const $results = $dropdown.find('.wts-autocomplete-results');

            console.log('WTS Autocomplete: Searching for:', query);

            // Show loading
            $results.hide();
            $loading.show();
            $dropdown.show();

            // Check cache
            if (this.cache[query]) {
                console.log('WTS Autocomplete: Using cached results');
                this.renderResults(this.cache[query], $input);
                return;
            }

            console.log('WTS Autocomplete: Sending AJAX request to:', wtsAutocomplete.ajaxUrl);

            $.ajax({
                url: wtsAutocomplete.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wts_autocomplete',
                    nonce: wtsAutocomplete.nonce,
                    query: query
                },
                success: function (response) {
                    console.log('WTS Autocomplete: Response received:', response);

                    if (response.success) {
                        console.log('WTS Autocomplete: Success! Data:', response.data);
                        self.cache[query] = response.data;
                        self.renderResults(response.data, $input);
                    } else {
                        console.error('WTS Autocomplete: Error in response:', response.data);
                        $loading.hide();
                        $results.html('<div class="wts-autocomplete-no-results">Erreur de recherche</div>').show();
                    }
                },
                error: function (xhr, status, error) {
                    console.error('WTS Autocomplete: AJAX error:', error, 'Status:', status, 'Response:', xhr.responseText);
                    $loading.hide();
                    $results.html('<div class="wts-autocomplete-no-results">Erreur de connexion</div>').show();
                }
            });
        },

        renderResults: function (data, $input) {
            console.log('WTS Autocomplete: Rendering results with data:', data);

            const $dropdown = $input.closest('.wts-header-search-form, .wts-search-form').find('.wts-autocomplete-dropdown');
            const $resultsContainer = $dropdown.find('.wts-autocomplete-results');
            let html = '';

            console.log('WTS Autocomplete: Products count:', data.products ? data.products.length : 0);
            console.log('WTS Autocomplete: Posts count:', data.posts ? data.posts.length : 0);

            // Products
            if (data.products && data.products.length > 0) {
                html += '<div class="wts-autocomplete-section">';
                html += '<h4 class="wts-autocomplete-header">Produits</h4>';

                data.products.forEach(function (product) {
                    console.log('WTS Autocomplete: Rendering product:', product.title);
                    html += `<a href="${product.url}" class="wts-autocomplete-item wts-autocomplete-result" data-type="product">
                        <div class="wts-autocomplete-result-image">
                            ${product.image ? `<img src="${product.image}" alt="${product.title}">` : '<span class="wts-no-image"></span>'}
                        </div>
                        <div class="wts-autocomplete-result-info">
                            <span class="wts-autocomplete-result-title">${product.title}</span>
                            <span class="wts-autocomplete-result-price">${product.price} €</span>
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
                    console.log('WTS Autocomplete: Rendering post:', post.title);
                    html += `<a href="${post.url}" class="wts-autocomplete-item wts-autocomplete-result" data-type="post">
                        <div class="wts-autocomplete-result-info">
                            <span class="wts-autocomplete-result-title">${post.title}</span>
                        </div>
                    </a>`;
                });

                html += '</div>';
            }

            // No results
            if (!html) {
                console.log('WTS Autocomplete: No results found');
                html = '<div class="wts-autocomplete-no-results">Aucun résultat trouvé</div>';
            } else {
                // View all link
                const searchUrl = $input.closest('form').attr('action') + '?s=' + encodeURIComponent($input.val()) + '&post_type=product';
                html += `<a href="${searchUrl}" class="wts-autocomplete-view-all">Voir tous les résultats</a>`;
            }

            console.log('WTS Autocomplete: Final HTML length:', html.length);

            $resultsContainer.html(html);
            $dropdown.find('.wts-autocomplete-loading').hide();
            $resultsContainer.show();
            $dropdown.show();

            console.log('WTS Autocomplete: Results displayed');
        },

        hideDropdown: function ($input) {
            $input.closest('.wts-header-search-form, .wts-search-form').find('.wts-autocomplete-dropdown').hide();
        }
    };

    $(document).ready(function () {
        WTSAutocomplete.init();
    });

})(jQuery);
