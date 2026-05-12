/**
 * Wave Sénégal - Admin JavaScript
 * Test de la clé API et fonctionnalités d'administration
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // ================================================================
        // Test de la clé API
        // ================================================================
        $('#wave-sn-validate-api').on('click', function () {
            const $btn    = $(this);
            const $result = $('#wave-sn-api-test-result');

            const apiKey        = $('#woocommerce_wave_senegal_api_key').val();
            const signingSecret = $('#woocommerce_wave_senegal_signing_secret').val();

            if (!apiKey) {
                $result
                    .removeClass('success')
                    .addClass('error')
                    .text('❌ Veuillez saisir une clé API.');
                return;
            }

            $btn.prop('disabled', true).text(waveSNAdmin.i18n.testing);
            $result.removeClass('success error').text('');

            $.ajax({
                url: waveSNAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wave_sn_validate_api_key',
                    api_key: apiKey,
                    signing_secret: signingSecret,
                    nonce: waveSNAdmin.nonce,
                },
                success: function (response) {
                    if (response.success) {
                        $result
                            .removeClass('error')
                            .addClass('success')
                            .text(response.data.message);
                    } else {
                        $result
                            .removeClass('success')
                            .addClass('error')
                            .text('❌ ' + response.data.message);
                    }
                },
                error: function () {
                    $result
                        .removeClass('success')
                        .addClass('error')
                        .text('❌ Erreur de connexion. Vérifiez votre connexion internet.');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('🔗 Tester la clé API');
                }
            });
        });

        // ================================================================
        // Copie de l'URL du webhook
        // ================================================================
        $(document).on('click', '.wave-sn-copy-btn', function () {
            const $btn  = $(this);
            const url   = $(this).data('url') || $(this).prev('code').text();

            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    $btn.text(waveSNAdmin.i18n.copied);
                    setTimeout(function () {
                        $btn.text(waveSNAdmin.i18n.copy_url);
                    }, 2000);
                });
            } else {
                // Fallback pour les navigateurs plus anciens
                const $temp = $('<input>');
                $('body').append($temp);
                $temp.val(url).select();
                document.execCommand('copy');
                $temp.remove();
                $btn.text(waveSNAdmin.i18n.copied);
                setTimeout(function () {
                    $btn.text(waveSNAdmin.i18n.copy_url);
                }, 2000);
            }
        });

    });

})(jQuery);
