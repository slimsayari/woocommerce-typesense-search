/**
 * WooCommerce Typesense Search - Image Search
 */

(function ($) {
    'use strict';

    const WTSImage = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            const self = this;

            $(document).on('click', '.wts-image-trigger', function (e) {
                e.preventDefault();
                self.showModal($(this));
            });

            // File input change
            $(document).on('change', '#wts-image-input', function () {
                if (this.files && this.files[0]) {
                    self.analyzeImage(this.files[0]);
                }
            });

            // Drag & Drop
            $(document).on('dragover', '.wts-image-dropzone', function (e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });

            $(document).on('dragleave', '.wts-image-dropzone', function (e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });

            $(document).on('drop', '.wts-image-dropzone', function (e) {
                e.preventDefault();
                $(this).removeClass('dragover');

                if (e.originalEvent.dataTransfer.files && e.originalEvent.dataTransfer.files[0]) {
                    self.analyzeImage(e.originalEvent.dataTransfer.files[0]);
                }
            });

            // Close modal
            $(document).on('click', '.wts-image-modal-close', function () {
                self.closeModal();
            });

            $(document).on('click', function (e) {
                if ($(e.target).hasClass('wts-image-modal')) {
                    self.closeModal();
                }
            });
        },

        showModal: function ($trigger) {
            this.$currentInput = $trigger.closest('.wts-search-input-wrapper').find('.wts-search-input');

            if ($('#wts-image-modal').length === 0) {
                $('body').append(`
                    <div id="wts-image-modal" class="wts-image-modal">
                        <div class="wts-image-content">
                            <span class="wts-image-modal-close">&times;</span>
                            <h3>Recherche par Image</h3>
                            <div class="wts-image-dropzone">
                                <div class="wts-image-icon">📷</div>
                                <p>${wtsImage.i18n.dropHere}</p>
                                <input type="file" id="wts-image-input" accept="image/*" style="display:none;">
                                <button type="button" class="button" onclick="document.getElementById('wts-image-input').click()">
                                    Sélectionner un fichier
                                </button>
                            </div>
                            <div class="wts-image-preview" style="display:none;">
                                <img src="" alt="Preview">
                                <p class="wts-image-status"></p>
                            </div>
                        </div>
                    </div>
                `);
            }

            $('#wts-image-modal').addClass('active');
            this.resetModal();
        },

        resetModal: function () {
            $('.wts-image-dropzone').show();
            $('.wts-image-preview').hide();
            $('#wts-image-input').val('');
        },

        analyzeImage: function (file) {
            const self = this;

            if (file.size > wtsImage.maxSize) {
                alert(wtsImage.i18n.fileTooBig);
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = function (e) {
                $('.wts-image-dropzone').hide();
                $('.wts-image-preview img').attr('src', e.target.result);
                $('.wts-image-preview').show();
                $('.wts-image-status').text(wtsImage.i18n.analyzing);
            };
            reader.readAsDataURL(file);

            // Upload
            const formData = new FormData();
            formData.append('action', 'wts_analyze_image');
            formData.append('nonce', wtsImage.nonce);
            formData.append('image', file);

            $.ajax({
                url: wtsImage.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        self.closeModal();
                        self.handleResult(response.data);
                    } else {
                        $('.wts-image-status').text(wtsImage.i18n.error + ' ' + (response.data.message || ''));
                        setTimeout(self.resetModal.bind(self), 3000);
                    }
                },
                error: function () {
                    $('.wts-image-status').text(wtsImage.i18n.error);
                    setTimeout(self.resetModal.bind(self), 3000);
                }
            });
        },

        handleResult: function (data) {
            if (this.$currentInput) {
                const query = data.q || '';
                this.$currentInput.val(query).trigger('input').focus();

                // Optionally apply filters if we had a way to do it cleanly
                // For now, just searching with the description is powerful enough with semantic search
            }
        },

        closeModal: function () {
            $('#wts-image-modal').removeClass('active');
        }
    };

    $(document).ready(function () {
        WTSImage.init();
    });

})(jQuery);
