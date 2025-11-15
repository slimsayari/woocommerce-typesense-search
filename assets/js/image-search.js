(function($) {
    'use strict';

    /**
     * Image Search Class
     */
    class ImageSearch {
        constructor() {
            this.selectedFile = null;
            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            $(document).on('click', '.wts-image-button', (e) => {
                e.preventDefault();
                $('.wts-image-input').trigger('click');
            });

            $(document).on('change', '.wts-image-input', (e) => {
                this.handleFileSelect(e);
            });

            // Drag and drop support
            $(document).on('dragover', '.wts-image-drop-zone', (e) => {
                e.preventDefault();
                $(e.currentTarget).addClass('dragover');
            });

            $(document).on('dragleave', '.wts-image-drop-zone', (e) => {
                e.preventDefault();
                $(e.currentTarget).removeClass('dragover');
            });

            $(document).on('drop', '.wts-image-drop-zone', (e) => {
                e.preventDefault();
                $(e.currentTarget).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    this.processFile(files[0]);
                }
            });
        }

        handleFileSelect(e) {
            const files = e.target.files;
            if (files.length > 0) {
                this.processFile(files[0]);
            }
        }

        processFile(file) {
            // Validate file type
            if (!file.type.match('image.*')) {
                this.showNotification('Please select an image file', 'error');
                return;
            }

            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                this.showNotification('Image size must be less than 5MB', 'error');
                return;
            }

            this.selectedFile = file;
            this.showPreview(file);
            this.searchByImage(file);
        }

        showPreview(file) {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                const preview = `
                    <div class="wts-image-preview">
                        <img src="${e.target.result}" alt="Search image">
                        <button type="button" class="wts-image-remove">&times;</button>
                    </div>
                `;
                
                $('.wts-image-preview-container').html(preview);
            };
            
            reader.readAsDataURL(file);

            // Remove preview on click
            $(document).on('click', '.wts-image-remove', () => {
                $('.wts-image-preview-container').empty();
                $('.wts-image-input').val('');
                this.selectedFile = null;
            });
        }

        searchByImage(file) {
            const formData = new FormData();
            formData.append('image', file);

            this.showLoading();

            $.ajax({
                url: wtsConfig.restUrl + 'image-search',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', wtsConfig.nonce);
                },
                success: (data) => {
                    this.handleSearchResults(data);
                },
                error: (xhr) => {
                    this.handleError(xhr);
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        }

        handleSearchResults(data) {
            if (!data.products || data.products.length === 0) {
                this.showNotification('No similar products found', 'info');
                return;
            }

            // Update search input with the generated query
            if (data.query) {
                $('.wts-search-input').val(data.query);
            }

            // Display results
            this.displayResults(data.products);

            // Show notification
            this.showNotification(`Found ${data.total} similar products`, 'success');
        }

        displayResults(products) {
            const container = $('.wts-results');
            let html = '';

            products.forEach((product) => {
                html += this.renderProduct(product);
            });

            container.html(html);
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
                        </div>
                    </a>
                </div>
            `;
        }

        handleError(xhr) {
            let message = 'Image search failed';
            
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }

            this.showNotification(message, 'error');
        }

        showLoading() {
            $('.wts-loading').show();
            $('.wts-image-button').prop('disabled', true);
        }

        hideLoading() {
            $('.wts-loading').hide();
            $('.wts-image-button').prop('disabled', false);
        }

        showNotification(message, type = 'info') {
            const notification = $(`
                <div class="wts-notification wts-notification-${type}">
                    ${message}
                </div>
            `);

            $('body').append(notification);

            setTimeout(() => {
                notification.addClass('show');
            }, 10);

            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        formatPrice(price) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(price);
        }

        truncate(text, length) {
            if (text.length <= length) return text;
            return text.substring(0, length) + '...';
        }
    }

    // Initialize on document ready
    $(document).ready(() => {
        if (wtsConfig.imageEnabled && $('.wts-image-button').length) {
            new ImageSearch();
        }
    });

})(jQuery);
