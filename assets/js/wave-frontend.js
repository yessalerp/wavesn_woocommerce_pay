/**
 * Wave Sénégal - Frontend JavaScript
 * Vérification du statut de paiement en temps réel
 */

(function ($) {
    'use strict';

    const WaveSN = {

        pollInterval: null,
        pollCount: 0,
        maxPolls: 20, // 20 × 5s = 1 minute 40s max
        pollDelay: 5000, // 5 secondes

        init: function () {
            const $pendingBox = $('.wave-sn-payment-pending');

            if ($pendingBox.length) {
                const orderId = $pendingBox.data('order-id');
                if (orderId) {
                    WaveSN.startPolling(orderId, $pendingBox);
                }
            }
        },

        startPolling: function (orderId, $container) {
            WaveSN.pollInterval = setInterval(function () {
                WaveSN.checkStatus(orderId, $container);
            }, WaveSN.pollDelay);

            // Premier check après 3 secondes
            setTimeout(function () {
                WaveSN.checkStatus(orderId, $container);
            }, 3000);
        },

        checkStatus: function (orderId, $container) {
            WaveSN.pollCount++;

            if (WaveSN.pollCount > WaveSN.maxPolls) {
                clearInterval(WaveSN.pollInterval);
                $container.html(
                    '<p>' + waveSN.i18n.error + '</p>' +
                    '<p><a href="' + window.location.href + '" class="button">' +
                    'Rafraîchir la page</a></p>'
                );
                return;
            }

            $.ajax({
                url: waveSN.ajax_url,
                type: 'POST',
                data: {
                    action: 'wave_sn_check_payment_status',
                    order_id: orderId,
                    nonce: waveSN.nonce,
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;

                        if (data.is_paid) {
                            clearInterval(WaveSN.pollInterval);
                            $container.html(
                                '<p style="color:#1a9e5c; font-weight:bold;">✅ ' +
                                waveSN.i18n.success + '</p>'
                            );
                            setTimeout(function () {
                                if (data.redirect_url) {
                                    window.location.href = data.redirect_url;
                                } else {
                                    window.location.reload();
                                }
                            }, 1500);
                        }
                    }
                },
                error: function () {
                    // Continuer à essayer silencieusement
                }
            });
        }
    };

    $(document).ready(function () {
        WaveSN.init();
    });

})(jQuery);
