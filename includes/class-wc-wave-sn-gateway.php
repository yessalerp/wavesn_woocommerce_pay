<?php
/**
 * Classe WC_Wave_SN_Gateway
 * Passerelle de paiement Wave Sénégal pour WooCommerce
 *
 * @package    WC_Wave_Senegal
 * @author     Jaxaay Group <jaxaay@jaxaaygroup.com>
 * @link       https://jaxaaygroup.com
 * @version    2.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Wave_SN_Gateway extends WC_Payment_Gateway {

    /** @var WC_Wave_SN_API */
    private WC_Wave_SN_API $api;

    public function __construct() {
        $this->id                 = 'wave_senegal';
        $this->icon               = WC_WAVE_SN_PLUGIN_URL . 'assets/images/wave-icon-32.png';
        $this->has_fields         = false;
        $this->method_title       = __( 'Wave Sénégal — Jaxaay Group', 'wc-wave-senegal' );
        $this->method_description = $this->get_admin_method_description();
        $this->supports           = [ 'products', 'refunds' ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title              = $this->get_option( 'title', __( 'Wave Mobile Money', 'wc-wave-senegal' ) );
        $this->description        = $this->get_option( 'description', __( 'Payez rapidement et en toute sécurité avec votre compte Wave.', 'wc-wave-senegal' ) );
        $this->enabled            = $this->get_option( 'enabled', 'no' );
        $this->api_key            = $this->get_option( 'api_key', '' );
        $this->signing_secret     = $this->get_option( 'signing_secret', '' );
        $this->webhook_secret     = $this->get_option( 'webhook_secret', '' );
        $this->debug_mode         = 'yes' === $this->get_option( 'debug_mode', 'no' );

        $this->init_api();

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
        add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
        add_action( 'wp_ajax_wave_sn_check_payment_status', [ $this, 'ajax_check_payment_status' ] );
        add_action( 'wp_ajax_nopriv_wave_sn_check_payment_status', [ $this, 'ajax_check_payment_status' ] );
        add_action( 'wp_ajax_wave_sn_validate_api_key', [ $this, 'ajax_validate_api_key' ] );
    }

    // =========================================================================
    // DESCRIPTION ADMIN (avec branding Jaxaay Group)
    // =========================================================================
    private function get_admin_method_description(): string {
        $logo_url    = WC_WAVE_SN_PLUGIN_URL . 'assets/images/wave-logo-checkout.png';
        $banner_url  = WC_WAVE_SN_PLUGIN_URL . 'assets/images/wave-banner.png';
        $author_url  = WC_WAVE_SN_AUTHOR_URL;
        $github_url  = WC_WAVE_SN_GITHUB_URL;
        $support_email = WC_WAVE_SN_AUTHOR_EMAIL;

        return '
        <div class="wave-sn-admin-description">
            <div class="wave-sn-banner-wrap">
                <img src="' . esc_url( $banner_url ) . '" alt="Wave Sénégal" class="wave-sn-admin-banner" />
            </div>
            <div class="wave-sn-admin-meta">
                <div class="wave-sn-admin-meta-left">
                    <img src="' . esc_url( $logo_url ) . '" alt="Wave" class="wave-sn-admin-logo" />
                    <div class="wave-sn-admin-text">
                        <p>' . esc_html__( 'Acceptez les paiements Wave Mobile Money directement sur votre boutique WooCommerce. Vos clients sont redirigés en toute sécurité vers l\'application Wave pour confirmer le paiement.', 'wc-wave-senegal' ) . '</p>
                        <p>
                            <span class="wave-sn-badge wave-sn-badge--blue">XOF</span>
                            <span class="wave-sn-badge wave-sn-badge--green">HTTPS</span>
                            <span class="wave-sn-badge wave-sn-badge--orange">Webhooks</span>
                            <span class="wave-sn-badge wave-sn-badge--purple">HMAC-SHA256</span>
                        </p>
                    </div>
                </div>
                <div class="wave-sn-admin-meta-right">
                    <div class="wave-sn-dev-card">
                        <p class="wave-sn-dev-card__title">🏢 ' . esc_html__( 'Développé par', 'wc-wave-senegal' ) . '</p>
                        <p class="wave-sn-dev-card__name"><strong>' . esc_html( WC_WAVE_SN_AUTHOR ) . '</strong></p>
                        <p>📍 ' . esc_html( WC_WAVE_SN_AUTHOR_ADDR ) . '</p>
                        <p>📧 <a href="mailto:' . esc_attr( $support_email ) . '">' . esc_html( $support_email ) . '</a></p>
                        <p>🌐 <a href="' . esc_url( $author_url ) . '" target="_blank">' . esc_html( $author_url ) . '</a></p>
                        <p>📞 <a href="tel:' . esc_attr( WC_WAVE_SN_AUTHOR_PHONE ) . '">' . esc_html( WC_WAVE_SN_AUTHOR_PHONE ) . '</a></p>
                        <p>🐙 <a href="' . esc_url( $github_url ) . '" target="_blank">GitHub</a></p>
                    </div>
                </div>
            </div>
        </div>';
    }

    private function init_api(): void {
        $this->api = new WC_Wave_SN_API( $this->api_key, $this->signing_secret );
    }

    // =========================================================================
    // CONFIGURATION
    // =========================================================================
    public function init_form_fields(): void {
        $webhook_url = WC_Wave_SN_Webhook::get_webhook_url();

        $this->form_fields = [
            // ACTIVATION
            'enabled' => [
                'title'   => __( 'Activer/Désactiver', 'wc-wave-senegal' ),
                'type'    => 'checkbox',
                'label'   => __( 'Activer la passerelle Wave Sénégal', 'wc-wave-senegal' ),
                'default' => 'no',
            ],

            // AFFICHAGE
            'display_section' => [
                'title'       => __( 'Affichage sur le Checkout', 'wc-wave-senegal' ),
                'type'        => 'title',
                'description' => __( 'Personnalisez le titre et la description affichés au client sur la page de paiement.', 'wc-wave-senegal' ),
            ],
            'title' => [
                'title'    => __( 'Titre', 'wc-wave-senegal' ),
                'type'     => 'text',
                'default'  => __( 'Wave Mobile Money', 'wc-wave-senegal' ),
                'desc_tip' => __( 'Nom de la méthode de paiement affiché au client.', 'wc-wave-senegal' ),
            ],
            'description' => [
                'title'    => __( 'Description', 'wc-wave-senegal' ),
                'type'     => 'textarea',
                'default'  => __( 'Payez rapidement et en toute sécurité avec votre compte Wave. Vous serez redirigé vers l\'application Wave pour confirmer votre paiement.', 'wc-wave-senegal' ),
                'desc_tip' => __( 'Description affichée sous le titre sur la page de paiement.', 'wc-wave-senegal' ),
            ],

            // CLÉS API
            'api_section' => [
                'title'       => __( 'Clés API Wave', 'wc-wave-senegal' ),
                'type'        => 'title',
                'description' => sprintf(
                    __( 'Obtenez vos clés API depuis le <a href="%s" target="_blank">Portail Business Wave</a>. Seul un administrateur peut accéder à la section Développeur. <br><strong>Besoin d\'aide ?</strong> Contactez <a href="mailto:%s">%s</a> — <a href="tel:%s">%s</a>', 'wc-wave-senegal' ),
                    'https://business.wave.com/dev-portal',
                    WC_WAVE_SN_AUTHOR_EMAIL, WC_WAVE_SN_AUTHOR_EMAIL,
                    WC_WAVE_SN_AUTHOR_PHONE, WC_WAVE_SN_AUTHOR_PHONE
                ),
            ],
            'api_key' => [
                'title'       => __( 'Clé API Wave', 'wc-wave-senegal' ),
                'type'        => 'password',
                'description' => __( 'Votre clé API Wave (format: wave_sn_prod_...). Ne la partagez jamais !', 'wc-wave-senegal' ),
                'placeholder' => 'wave_sn_prod_...',
                'desc_tip'    => true,
            ],
            'signing_secret' => [
                'title'       => __( 'Secret de Signature (recommandé)', 'wc-wave-senegal' ),
                'type'        => 'password',
                'description' => __( 'Secret HMAC-SHA256 pour signer les requêtes API. Activez la signature lors de la création de votre clé API. Format: wave_sn_AKS_...', 'wc-wave-senegal' ),
                'placeholder' => 'wave_sn_AKS_...',
                'desc_tip'    => true,
            ],
            'validate_api_key_button' => [
                'title'       => __( 'Test de connexion', 'wc-wave-senegal' ),
                'type'        => 'title',
                'description' => '<button type="button" id="wave-sn-validate-api" class="button button-secondary">' .
                                 esc_html__( '🔗 Tester la connexion API', 'wc-wave-senegal' ) .
                                 '</button> <span id="wave-sn-api-test-result"></span>',
            ],

            // WEBHOOK
            'webhook_section' => [
                'title'       => __( 'Configuration Webhook', 'wc-wave-senegal' ),
                'type'        => 'title',
                'description' => sprintf(
                    __( 'Configurez cette URL dans votre <a href="%s" target="_blank">Portail Business Wave</a> pour recevoir les confirmations de paiement en temps réel.<br>
                    <div class="wave-sn-webhook-url-container">
                        <code>%s</code>
                        <button type="button" class="button wave-sn-copy-btn" data-url="%s">%s</button>
                    </div>', 'wc-wave-senegal' ),
                    'https://business.wave.com/dev-portal',
                    esc_url( $webhook_url ),
                    esc_attr( $webhook_url ),
                    esc_html__( 'Copier', 'wc-wave-senegal' )
                ),
            ],
            'webhook_secret' => [
                'title'    => __( 'Secret Webhook', 'wc-wave-senegal' ),
                'type'     => 'password',
                'desc_tip' => __( 'Secret pour vérifier l\'authenticité des notifications webhook Wave.', 'wc-wave-senegal' ),
            ],

            // COMPORTEMENT
            'behaviour_section' => [
                'title' => __( 'Comportement des Commandes', 'wc-wave-senegal' ),
                'type'  => 'title',
            ],
            'order_status_on_payment' => [
                'title'    => __( 'Statut après paiement réussi', 'wc-wave-senegal' ),
                'type'     => 'select',
                'default'  => 'processing',
                'desc_tip' => __( 'Statut attribué à la commande après confirmation du paiement Wave.', 'wc-wave-senegal' ),
                'options'  => [
                    'processing' => __( 'En cours de traitement', 'wc-wave-senegal' ),
                    'completed'  => __( 'Terminée', 'wc-wave-senegal' ),
                ],
            ],
            'order_status_pending' => [
                'title'    => __( 'Statut en attente de paiement', 'wc-wave-senegal' ),
                'type'     => 'select',
                'default'  => 'on-hold',
                'desc_tip' => __( 'Statut attribué à la commande en attente de paiement Wave.', 'wc-wave-senegal' ),
                'options'  => [
                    'pending' => __( 'En attente de paiement', 'wc-wave-senegal' ),
                    'on-hold' => __( 'En attente', 'wc-wave-senegal' ),
                ],
            ],

            // AVANCÉ
            'advanced_section' => [
                'title' => __( 'Paramètres Avancés', 'wc-wave-senegal' ),
                'type'  => 'title',
            ],
            'debug_mode' => [
                'title'       => __( 'Mode Debug', 'wc-wave-senegal' ),
                'type'        => 'checkbox',
                'label'       => __( 'Activer les logs détaillés', 'wc-wave-senegal' ),
                'description' => sprintf(
                    __( 'Journalise toutes les requêtes/réponses API. <a href="%s">Voir les logs WooCommerce</a>', 'wc-wave-senegal' ),
                    admin_url( 'admin.php?page=wc-status&tab=logs' )
                ),
                'default'     => 'no',
            ],
        ];
    }

    // =========================================================================
    // NOTICES ADMIN
    // =========================================================================
    public function admin_notices(): void {
        if ( 'yes' !== $this->enabled ) return;

        if ( empty( $this->api_key ) ) {
            echo '<div class="error notice"><p>' .
                 sprintf(
                     esc_html__( 'Wave Sénégal (Jaxaay Group): Veuillez configurer votre clé API dans les %s.', 'wc-wave-senegal' ),
                     '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wave_senegal' ) ) . '">' .
                     esc_html__( 'paramètres', 'wc-wave-senegal' ) . '</a>'
                 ) . '</p></div>';
        }

        if ( ! is_ssl() ) {
            echo '<div class="notice notice-warning"><p>' .
                 esc_html__( 'Wave Sénégal: HTTPS est recommandé pour la production.', 'wc-wave-senegal' ) .
                 '</p></div>';
        }
    }

    // =========================================================================
    // PROCESS PAYMENT
    // =========================================================================
    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'Commande introuvable.', 'wc-wave-senegal' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        WC_Wave_SN_Logger::log_order( $order, 'Début du traitement du paiement Wave' );

        if ( empty( $this->api_key ) ) {
            WC_Wave_SN_Logger::error( 'Clé API Wave non configurée' );
            wc_add_notice( __( 'La passerelle Wave n\'est pas correctement configurée. Contactez le support Jaxaay Group : ' . WC_WAVE_SN_AUTHOR_EMAIL, 'wc-wave-senegal' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        $client_reference = $this->generate_client_reference( $order );
        $success_url      = add_query_arg( [
            'wc_order_id'    => $order_id,
            'wave_reference' => $client_reference,
            'wave_status'    => 'success',
        ], $this->get_return_url( $order ) );
        $error_url = add_query_arg( [
            'wc_order_id'    => $order_id,
            'wave_reference' => $client_reference,
            'wave_status'    => 'error',
            'wc_order_token' => $order->get_order_key(),
        ], wc_get_checkout_url() );

        $params = [
            'amount'           => WC_Wave_SN_API::format_amount( $order->get_total() ),
            'currency'         => 'XOF',
            'success_url'      => $success_url,
            'error_url'        => $error_url,
            'client_reference' => $client_reference,
        ];

        $session = $this->api->create_checkout_session( $params );

        if ( is_wp_error( $session ) ) {
            WC_Wave_SN_Logger::error( 'Erreur session Wave: ' . $session->get_error_message() );
            wc_add_notice( __( 'Erreur paiement Wave: ', 'wc-wave-senegal' ) . $session->get_error_message(), 'error' );
            return [ 'result' => 'failure' ];
        }

        $order->update_meta_data( '_wave_checkout_session_id', $session['id'] );
        $order->update_meta_data( '_wave_client_reference', $client_reference );
        $order->update_meta_data( '_wave_launch_url', $session['wave_launch_url'] );
        $order->update_meta_data( '_wave_session_created_at', current_time( 'mysql' ) );
        $order->update_meta_data( '_wave_amount', $session['amount'] );

        $pending_status = $this->get_option( 'order_status_pending', 'on-hold' );
        $order->update_status(
            $pending_status,
            sprintf( __( 'En attente de paiement Wave. Session: %s', 'wc-wave-senegal' ), $session['id'] )
        );
        $order->save();

        $this->save_transaction_record( $order, $session );

        WC_Wave_SN_Logger::log_order( $order, 'Session Wave créée: ' . $session['id'] );

        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $session['wave_launch_url'],
        ];
    }

    // =========================================================================
    // REMBOURSEMENTS
    // =========================================================================
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Commande introuvable.', 'wc-wave-senegal' ) );
        }

        $session_id = $order->get_meta( '_wave_checkout_session_id' );
        if ( empty( $session_id ) ) {
            return new WP_Error( 'no_session', __( 'ID de session Wave introuvable. Remboursement manuel requis.', 'wc-wave-senegal' ) );
        }

        $order_total = (float) $order->get_total();
        if ( null !== $amount && abs( $amount - $order_total ) > 0.01 ) {
            return new WP_Error( 'partial_refund', __( 'Wave ne supporte que les remboursements complets.', 'wc-wave-senegal' ) );
        }

        $result = $this->api->refund_checkout_session( $session_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $order->add_order_note( sprintf(
            __( 'Remboursement Wave effectué. Session: %s | Raison: %s', 'wc-wave-senegal' ),
            $session_id, $reason ?: __( 'Non spécifiée', 'wc-wave-senegal' )
        ) );

        return true;
    }

    // =========================================================================
    // THANK YOU PAGE
    // =========================================================================
    public function thankyou_page( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $wave_status = sanitize_text_field( $_GET['wave_status'] ?? '' );
        $session_id  = $order->get_meta( '_wave_checkout_session_id' );

        if ( 'success' === $wave_status && ! $order->is_paid() ) {
            $this->verify_payment_status( $order, $session_id );
        }

        $transaction_id = $order->get_meta( '_wave_transaction_id' );

        if ( $order->is_paid() ) {
            echo '<div class="wave-sn-payment-info woocommerce-info">';
            echo '<h3>✅ ' . esc_html__( 'Paiement Wave confirmé', 'wc-wave-senegal' ) . '</h3>';
            if ( $transaction_id ) {
                echo '<p><strong>' . esc_html__( 'ID de transaction:', 'wc-wave-senegal' ) . '</strong> <code>' . esc_html( $transaction_id ) . '</code></p>';
            }
            echo '<p class="wave-sn-powered-by">' . esc_html__( 'Paiement traité via Wave Sénégal · Plugin par Jaxaay Group', 'wc-wave-senegal' ) . '</p>';
            echo '</div>';
        } elseif ( 'error' === $wave_status ) {
            echo '<div class="wave-sn-payment-error woocommerce-error">';
            echo '<p>' . esc_html__( 'Le paiement Wave n\'a pas abouti. Vous pouvez réessayer.', 'wc-wave-senegal' ) . '</p>';
            echo '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '" class="button">' .
                 esc_html__( '🔄 Réessayer le paiement', 'wc-wave-senegal' ) . '</a>';
            echo '</div>';
        } else {
            echo '<div class="wave-sn-payment-pending woocommerce-info" data-order-id="' . esc_attr( $order_id ) . '">';
            echo '<div class="wave-sn-loader">⏳</div>';
            echo '<p>' . esc_html__( 'Vérification de votre paiement Wave en cours...', 'wc-wave-senegal' ) . '</p>';
            echo '</div>';
        }
    }

    private function verify_payment_status( WC_Order $order, string $session_id ): void {
        if ( empty( $session_id ) ) return;

        $session = $this->api->get_checkout_session( $session_id );
        if ( is_wp_error( $session ) ) return;

        $payment_status = $session['payment_status'] ?? '';
        $transaction_id = $session['transaction_id'] ?? '';

        $order->update_meta_data( '_wave_payment_status', $payment_status );
        $order->update_meta_data( '_wave_checkout_status', $session['checkout_status'] ?? '' );

        if ( 'succeeded' === $payment_status && ! $order->is_paid() ) {
            $order->update_meta_data( '_wave_transaction_id', $transaction_id );
            $order->payment_complete( $transaction_id );
            $order->add_order_note( sprintf(
                __( 'Paiement Wave confirmé. Transaction ID: %s', 'wc-wave-senegal' ),
                $transaction_id
            ) );
        }

        $order->save();
    }

    // =========================================================================
    // AJAX
    // =========================================================================
    public function ajax_check_payment_status(): void {
        check_ajax_referer( 'wave_sn_check_status', 'nonce' );
        $order = wc_get_order( absint( $_POST['order_id'] ?? 0 ) );
        if ( ! $order ) {
            wp_send_json_error( [ 'message' => __( 'Commande introuvable', 'wc-wave-senegal' ) ] );
        }
        $session_id = $order->get_meta( '_wave_checkout_session_id' );
        if ( $session_id ) {
            $this->verify_payment_status( $order, $session_id );
        }
        wp_send_json_success( [
            'is_paid'      => $order->is_paid(),
            'order_status' => $order->get_status(),
            'redirect_url' => $order->is_paid() ? $this->get_return_url( $order ) : '',
        ] );
    }

    public function ajax_validate_api_key(): void {
        check_ajax_referer( 'wave_sn_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission refusée', 'wc-wave-senegal' ) ] );
        }
        $api_key        = sanitize_text_field( $_POST['api_key'] ?? '' );
        $signing_secret = sanitize_text_field( $_POST['signing_secret'] ?? '' );
        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => __( 'Clé API vide', 'wc-wave-senegal' ) ] );
        }
        $test_api = new WC_Wave_SN_API( $api_key, $signing_secret );
        $result   = $test_api->validate_api_key();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ] );
        }
        wp_send_json_success( [ 'message' => __( '✅ Clé API valide ! Connexion Wave établie.', 'wc-wave-senegal' ) ] );
    }

    // =========================================================================
    // EMAIL INSTRUCTIONS
    // =========================================================================
    public function email_instructions( WC_Order $order, bool $sent_to_admin, bool $plain_text ): void {
        if ( $this->id !== $order->get_payment_method() ) return;

        $transaction_id = $order->get_meta( '_wave_transaction_id' );
        $session_id     = $order->get_meta( '_wave_checkout_session_id' );

        if ( ! $plain_text ) {
            echo '<h3>' . esc_html__( 'Informations de paiement Wave', 'wc-wave-senegal' ) . '</h3>';
            echo '<p>' . esc_html__( 'Paiement effectué via Wave Mobile Money.', 'wc-wave-senegal' ) . '</p>';
            if ( $transaction_id ) {
                echo '<p><strong>' . esc_html__( 'Transaction Wave:', 'wc-wave-senegal' ) . '</strong> ' . esc_html( $transaction_id ) . '</p>';
            }
            echo '<p><small>' . esc_html__( 'Plugin développé par Jaxaay Group — jaxaaygroup.com', 'wc-wave-senegal' ) . '</small></p>';
        } else {
            echo esc_html__( 'Paiement Wave Mobile Money.', 'wc-wave-senegal' ) . "\n";
            if ( $transaction_id ) {
                echo esc_html__( 'Transaction:', 'wc-wave-senegal' ) . ' ' . esc_html( $transaction_id ) . "\n";
            }
        }
    }

    // =========================================================================
    // ASSETS
    // =========================================================================
    public function enqueue_scripts(): void {
        if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) return;
        wp_enqueue_style( 'wc-wave-sn-frontend', WC_WAVE_SN_PLUGIN_URL . 'assets/css/wave-frontend.css', [], WC_WAVE_SN_VERSION );
        wp_enqueue_script( 'wc-wave-sn-frontend', WC_WAVE_SN_PLUGIN_URL . 'assets/js/wave-frontend.js', [ 'jquery' ], WC_WAVE_SN_VERSION, true );
        wp_localize_script( 'wc-wave-sn-frontend', 'waveSN', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wave_sn_check_status' ),
            'i18n'     => [
                'checking' => __( 'Vérification du paiement...', 'wc-wave-senegal' ),
                'success'  => __( 'Paiement confirmé ! Redirection en cours...', 'wc-wave-senegal' ),
                'error'    => __( 'Le paiement n\'a pas été confirmé.', 'wc-wave-senegal' ),
            ],
        ] );
    }

    public function admin_enqueue_scripts( string $hook ): void {
        if ( 'woocommerce_page_wc-settings' !== $hook ) return;
        wp_enqueue_script( 'wc-wave-sn-admin', WC_WAVE_SN_PLUGIN_URL . 'assets/js/wave-admin.js', [ 'jquery' ], WC_WAVE_SN_VERSION, true );
        wp_localize_script( 'wc-wave-sn-admin', 'waveSNAdmin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wave_sn_admin' ),
            'i18n'     => [
                'testing'  => __( 'Test en cours...', 'wc-wave-senegal' ),
                'copy_url' => __( 'Copier', 'wc-wave-senegal' ),
                'copied'   => __( '✓ Copié !', 'wc-wave-senegal' ),
            ],
        ] );
        wp_enqueue_style( 'wc-wave-sn-admin', WC_WAVE_SN_PLUGIN_URL . 'assets/css/wave-admin.css', [], WC_WAVE_SN_VERSION );
    }

    // =========================================================================
    // UTILITAIRES
    // =========================================================================
    private function generate_client_reference( WC_Order $order ): string {
        return sprintf( 'wc_order_%d_%s', $order->get_id(), substr( md5( $order->get_order_key() . $order->get_id() ), 0, 8 ) );
    }

    private function save_transaction_record( WC_Order $order, array $session ): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wave_sn_transactions',
            [
                'order_id'            => $order->get_id(),
                'checkout_session_id' => $session['id'],
                'amount'              => $session['amount'],
                'currency'            => $session['currency'] ?? 'XOF',
                'payment_status'      => 'pending',
                'checkout_status'     => $session['checkout_status'] ?? 'open',
                'client_reference'    => $session['client_reference'] ?? null,
                'wave_launch_url'     => $session['wave_launch_url'] ?? null,
                'raw_response'        => wp_json_encode( $session ),
                'created_at'          => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    public function is_available(): bool {
        if ( ! parent::is_available() ) return false;
        if ( empty( $this->api_key ) ) return false;
        if ( 'XOF' !== get_woocommerce_currency() ) return false;
        return true;
    }
}
