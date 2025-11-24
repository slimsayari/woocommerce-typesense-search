/**
 * WooCommerce Typesense Search - Voice Search
 */

(function ($) {
    'use strict';

    const WTSVoice = {
        init: function () {
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                $('.wts-voice-trigger').hide();
                console.log('Voice search not supported');
                return;
            }

            this.recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
            this.recognition.lang = 'fr-FR'; // Default to French, could be dynamic
            this.recognition.continuous = false;
            this.recognition.interimResults = false;

            this.bindEvents();
        },

        bindEvents: function () {
            const self = this;

            $(document).on('click', '.wts-voice-trigger', function (e) {
                e.preventDefault();
                self.startListening($(this));
            });

            this.recognition.onstart = function () {
                self.showModal(wtsVoice.i18n.listening);
            };

            this.recognition.onend = function () {
                // Modal will be closed by result handler or error
            };

            this.recognition.onresult = function (event) {
                const transcript = event.results[0][0].transcript;
                self.handleResult(transcript);
            };

            this.recognition.onerror = function (event) {
                console.error('Voice error', event.error);
                self.updateModal(wtsVoice.i18n.error);
                setTimeout(self.closeModal, 2000);
            };
        },

        startListening: function ($trigger) {
            this.$currentInput = $trigger.closest('.wts-search-input-wrapper').find('.wts-search-input');
            this.recognition.start();
        },

        handleResult: function (transcript) {
            const self = this;

            if (wtsVoice.analyzeIntent === '1' || wtsVoice.analyzeIntent === true) {
                this.updateModal(wtsVoice.i18n.processing);

                $.ajax({
                    url: wtsVoice.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wts_analyze_intent',
                        nonce: wtsVoice.nonce,
                        query: transcript
                    },
                    success: function (response) {
                        self.closeModal();
                        if (response.success) {
                            self.applyIntent(response.data);
                        } else {
                            // Fallback to simple search
                            self.fillInput(transcript);
                        }
                    },
                    error: function () {
                        self.closeModal();
                        self.fillInput(transcript);
                    }
                });
            } else {
                this.closeModal();
                this.fillInput(transcript);
            }
        },

        fillInput: function (text) {
            if (this.$currentInput) {
                this.$currentInput.val(text).trigger('input').focus();
                // Optionally submit form
                // this.$currentInput.closest('form').submit();
            }
        },

        applyIntent: function (intent) {
            // Fill search term
            if (intent.q) {
                this.fillInput(intent.q);
            }

            // Apply filters (redirect with params)
            // This is complex because we need to map intent filters to URL params
            // For now, we just fill the input with the original transcript or extracted query
            // and let the user refine.
            // Ideally, we would construct the URL here.

            // Example URL construction:
            // let url = window.location.pathname + '?s=' + encodeURIComponent(intent.q || '');
            // if (intent.filters.price_max) url += '&wts_max_price=' + intent.filters.price_max;
            // ...
            // window.location.href = url;
        },

        showModal: function (text) {
            if ($('#wts-voice-modal').length === 0) {
                $('body').append(`
                    <div id="wts-voice-modal" class="wts-voice-modal">
                        <div class="wts-voice-content">
                            <div class="wts-voice-icon">🎤</div>
                            <p class="wts-voice-text"></p>
                        </div>
                    </div>
                `);
            }

            $('#wts-voice-modal .wts-voice-text').text(text);
            $('#wts-voice-modal').addClass('active');
        },

        updateModal: function (text) {
            $('#wts-voice-modal .wts-voice-text').text(text);
        },

        closeModal: function () {
            $('#wts-voice-modal').removeClass('active');
        }
    };

    $(document).ready(function () {
        WTSVoice.init();
    });

})(jQuery);
