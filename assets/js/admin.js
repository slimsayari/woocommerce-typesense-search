/**
 * WooCommerce Typesense Search - Admin JavaScript
 */

(function ($) {
    'use strict';

    const WTSAdmin = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Test connection
            $('#wts-test-connection').on('click', this.testConnection);

            // Sync buttons
            $('#wts-sync-products').on('click', () => this.startSync('products'));
            $('#wts-sync-posts').on('click', () => this.startSync('posts'));
            $('#wts-sync-all').on('click', () => this.startSync('all'));

            // Export analytics
            $('#wts-export-analytics').on('click', this.exportAnalytics);
        },

        testConnection: function (e) {
            e.preventDefault();

            const $button = $(this);
            const $status = $('#wts-connection-status');

            $button.prop('disabled', true).text('Testing...');
            $status.html('');

            $.ajax({
                url: wtsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wts_test_connection',
                    nonce: wtsAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    } else {
                        $status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function () {
                    $status.html('<span style="color: red;">✗ Connection error</span>');
                },
                complete: function () {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },

        startSync: function (type) {
            const $progress = $('#wts-sync-progress');
            const $status = $('#wts-sync-status');
            const $progressBar = $('#wts-progress-bar');
            const $progressText = $('#wts-progress-text');

            // Disable all sync buttons
            $('#wts-sync-products, #wts-sync-posts, #wts-sync-all').prop('disabled', true);

            $progress.show();
            $progressBar.val(0);

            if (type === 'all') {
                this.syncAll();
            } else if (type === 'products') {
                this.syncType('products', 'Produits');
            } else {
                this.syncType('posts', 'Articles');
            }
        },

        syncAll: function () {
            const self = this;

            // First sync products, then posts
            self.syncType('products', 'Produits', function () {
                self.syncType('posts', 'Articles', function () {
                    self.completeSyncAll();
                });
            });
        },

        syncType: function (type, label, callback) {
            const self = this;
            const $status = $('#wts-sync-status');
            const $progressBar = $('#wts-progress-bar');
            const $progressText = $('#wts-progress-text');

            let offset = 0;
            let total = 0;
            let synced = 0;
            const batchSize = 50;

            $status.text('Synchronisation des ' + label + '...');

            function syncBatch() {
                $.ajax({
                    url: wtsAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wts_sync_all',
                        nonce: wtsAdmin.bulkSyncNonce,
                        type: type,
                        batch_size: batchSize,
                        offset: offset
                    },
                    success: function (response) {
                        if (response.success) {
                            // Check for batch errors
                            if (response.data.errors && response.data.errors.length > 0) {
                                console.log('Sync errors:', response.data.errors);
                                if (response.data.synced === 0) {
                                    self.showError('Erreur: ' + response.data.errors[0].error);
                                    return;
                                }
                            }

                            synced += response.data.synced;

                            if (total === 0) {
                                // First batch, estimate total
                                total = synced * 10; // Rough estimate
                            }

                            const progress = total > 0 ? Math.min((synced / total) * 100, 100) : 0;
                            $progressBar.val(progress);
                            $progressText.text(synced + ' / ~' + total + ' ' + label);

                            if (response.data.has_more) {
                                offset += batchSize;
                                syncBatch();
                            } else {
                                // Done
                                $status.html('<span style="color: green;">✓ ' + synced + ' ' + label + ' synchronisés</span>');
                                $progressBar.val(100);
                                $progressText.text(synced + ' / ' + synced + ' ' + label);

                                if (callback) {
                                    setTimeout(callback, 500);
                                } else {
                                    self.completeSync();
                                }
                            }
                        } else {
                            self.showError('Erreur lors de la synchronisation: ' + response.data.message);
                            // Stop recursion on error
                            return;
                        }
                    },
                    error: function () {
                        self.showError('Erreur de connexion lors de la synchronisation');
                    }
                });
            }

            syncBatch();
        },

        completeSyncAll: function () {
            const $status = $('#wts-sync-status');
            $status.html('<span style="color: green;">✓ Synchronisation complète terminée!</span>');
            this.completeSync();
        },

        completeSync: function () {
            // Re-enable buttons after 2 seconds
            setTimeout(function () {
                $('#wts-sync-products, #wts-sync-posts, #wts-sync-all').prop('disabled', false);
            }, 2000);
        },

        showError: function (message) {
            const $status = $('#wts-sync-status');
            $status.html('<span style="color: red;">✗ ' + message + '</span>');
            $('#wts-sync-products, #wts-sync-posts, #wts-sync-all').prop('disabled', false);
        },

        exportAnalytics: function (e) {
            e.preventDefault();

            window.location.href = wtsAdmin.ajaxUrl + '?action=wts_export_analytics&nonce=' + wtsAdmin.nonce;
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        WTSAdmin.init();
    });

})(jQuery);
