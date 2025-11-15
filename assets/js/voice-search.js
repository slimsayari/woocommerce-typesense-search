(function($) {
    'use strict';

    /**
     * Voice Search Class
     */
    class VoiceSearch {
        constructor() {
            this.recognition = null;
            this.isListening = false;
            this.init();
        }

        init() {
            // Check for browser support
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                console.warn('Speech recognition not supported in this browser');
                $('.wts-voice-button').hide();
                return;
            }

            // Initialize speech recognition
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            this.recognition.lang = this.getLanguage();

            this.bindEvents();
        }

        bindEvents() {
            $(document).on('click', '.wts-voice-button', (e) => {
                e.preventDefault();
                this.toggleListening();
            });

            if (this.recognition) {
                this.recognition.onstart = () => {
                    this.onStart();
                };

                this.recognition.onresult = (event) => {
                    this.onResult(event);
                };

                this.recognition.onerror = (event) => {
                    this.onError(event);
                };

                this.recognition.onend = () => {
                    this.onEnd();
                };
            }
        }

        toggleListening() {
            if (this.isListening) {
                this.stopListening();
            } else {
                this.startListening();
            }
        }

        startListening() {
            if (!this.recognition) return;

            try {
                this.recognition.start();
            } catch (error) {
                console.error('Error starting speech recognition:', error);
            }
        }

        stopListening() {
            if (!this.recognition) return;

            try {
                this.recognition.stop();
            } catch (error) {
                console.error('Error stopping speech recognition:', error);
            }
        }

        onStart() {
            this.isListening = true;
            $('.wts-voice-button').addClass('listening');
            $('.wts-voice-status').text(wtsConfig.i18n.voiceListening);
            
            // Visual feedback
            this.addPulseAnimation();
        }

        onResult(event) {
            const transcript = event.results[0][0].transcript;
            
            // Set the search input
            $('.wts-search-input').val(transcript).trigger('input');
            
            // Show notification
            this.showNotification(`Searching for: "${transcript}"`);
        }

        onError(event) {
            console.error('Speech recognition error:', event.error);
            
            let message = 'Voice search error';
            switch (event.error) {
                case 'no-speech':
                    message = 'No speech detected. Please try again.';
                    break;
                case 'audio-capture':
                    message = 'No microphone found. Please check your device.';
                    break;
                case 'not-allowed':
                    message = 'Microphone access denied. Please allow microphone access.';
                    break;
                default:
                    message = `Voice search error: ${event.error}`;
            }
            
            this.showNotification(message, 'error');
        }

        onEnd() {
            this.isListening = false;
            $('.wts-voice-button').removeClass('listening');
            $('.wts-voice-status').text(wtsConfig.i18n.voiceStart);
            
            // Remove visual feedback
            this.removePulseAnimation();
        }

        addPulseAnimation() {
            $('.wts-voice-button').append('<span class="wts-pulse"></span>');
        }

        removePulseAnimation() {
            $('.wts-pulse').remove();
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

        getLanguage() {
            // Get language from HTML lang attribute or default to French
            const htmlLang = $('html').attr('lang');
            if (htmlLang) {
                return htmlLang;
            }
            return 'fr-FR';
        }
    }

    // Initialize on document ready
    $(document).ready(() => {
        if (wtsConfig.voiceEnabled && $('.wts-voice-button').length) {
            new VoiceSearch();
        }
    });

})(jQuery);
