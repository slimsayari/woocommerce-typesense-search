(function($) {
    'use strict';

    /**
     * Admin Scripts
     */
    class WTSAdmin {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Test connection
            $('#wts-test-connection').on('click', (e) => {
                e.preventDefault();
                this.testConnection();
            });

            // Bulk sync
            $('#wts-bulk-sync').on('click', (e) => {
                e.preventDefault();
                this.bulkSync();
            });

            // Export analytics
            $('#wts-export-analytics').on('click', (e) => {
                e.preventDefault();
                this.exportAnalytics();
            });
        }

        testConnection() {
            const button = $('#wts-test-connection');
            const status = $('#wts-connection-status');

            button.prop('disabled', true).text('Testing...');
            status.html('');

            $.ajax({
                url: wtsAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wts_test_connection',
                    nonce: wtsAdmin.nonce,
                },
                success: (response) => {
                    if (response.success) {
                        status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    } else {
                        status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }
                },
                error: (xhr) => {
                    status.html('<span style="color: red;">✗ Connection failed</span>');
                },
                complete: () => {
                    button.prop('disabled', false).text('Test Connection');
                }
            });
        }

        bulkSync() {
            const button = $('#wts-bulk-sync');
            const progressContainer = $('#wts-sync-progress');
            const progressBar = $('#wts-progress-bar');
            const progressText = $('#wts-progress-text');

            if (!confirm('This will sync all products with Typesense. Continue?')) {
                return;
            }

            button.prop('disabled', true);
            progressContainer.show();

            // Get total products
            $.ajax({
                url: wtsAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wts_get_sync_progress',
                    nonce: wtsAdmin.bulkSyncNonce,
                },
                success: (response) => {
                    if (response.success) {
                        const total = response.data.total;
                        this.syncBatch(0, total, 50, progressBar, progressText, button, progressContainer);
                    }
                },
                error: () => {
                    alert('Failed to get product count');
                    button.prop('disabled', false);
                    progressContainer.hide();
                }
            });
        }

        syncBatch(offset, total, batchSize, progressBar, progressText, button, progressContainer) {
            $.ajax({
                url: wtsAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wts_bulk_sync',
                    nonce: wtsAdmin.bulkSyncNonce,
                    offset: offset,
                    batch_size: batchSize,
                },
                success: (response) => {
                    if (response.success) {
                        const indexed = offset + response.data.indexed;
                        const progress = Math.min((indexed / total) * 100, 100);

                        progressBar.val(progress);
                        progressText.text(`${indexed} / ${total}`);

                        if (indexed < total) {
                            // Continue with next batch
                            this.syncBatch(indexed, total, batchSize, progressBar, progressText, button, progressContainer);
                        } else {
                            // Sync complete
                            progressText.text(`Sync complete! ${total} products indexed.`);
                            setTimeout(() => {
                                button.prop('disabled', false);
                                progressContainer.hide();
                            }, 2000);
                        }
                    } else {
                        alert('Sync failed: ' + (response.data?.message || 'Unknown error'));
                        button.prop('disabled', false);
                        progressContainer.hide();
                    }
                },
                error: (xhr) => {
                    alert('Sync failed: ' + (xhr.responseJSON?.message || 'Network error'));
                    button.prop('disabled', false);
                    progressContainer.hide();
                }
            });
        }

        exportAnalytics() {
            window.location.href = wtsAdmin.ajaxUrl + '?action=wts_export_analytics&nonce=' + wtsAdmin.nonce;
        }
    }

    // Initialize on document ready
    $(document).ready(() => {
        if ($('#wts-test-connection').length || $('#wts-bulk-sync').length) {
            new WTSAdmin();
        }
    });

})(jQuery);
