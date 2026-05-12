<?php
/**
 * Classe WC_Wave_SN_Webhook
 * Traitement des notifications webhook de Wave
 *
 * @package WC_Wave_Senegal
 */

defined( 'ABSPATH' ) || exit;

class WC_Wave_SN_Webhook {

    /**
     * Initialiser les hooks WordPress pour les webhooks
     */
    public static function init(): void {
        // Endpoint REST API pour les webhooks Wave
        add_action( 'woocommerce_api_wave_senegal_webhook', [ self::class, 'handle_webhook' ] );

        // Aussi disponible via ?wc-api=wave_senegal_webhook
        add_action( 'woocommerce_api_wc_wave_sn_gateway', [ self::class, 'handle_webhook' ] );
    }

    /**
     * URL du webhook pour ce plugin
     *
     * @return string
     */
    public static function get_webhook_url(): string {
        return WC()->api_request_url( 'wave_senegal_webhook' );
    }

    /**
     * Traiter les requêtes webhook entrantes
     */
    public static function handle_webhook(): void {
        $raw_body = file_get_contents( 'php://input' );

        WC_Wave_SN_Logger::log( 'Webhook Wave reçu: ' . $raw_body, 'debug' );

        if ( empty( $raw_body ) ) {
            WC_Wave_SN_Logger::error( 'Webhook Wave: Corps de requête vide' );
            http_response_code( 400 );
            exit( 'Corps vide' );
        }

        $data = json_decode( $raw_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
            WC_Wave_SN_Logger::error( 'Webhook Wave: JSON invalide' );
            http_response_code( 400 );
            exit( 'JSON invalide' );
        }

        // Vérifier la signature du webhook si configurée
        $gateway_settings   = get_option( 'woocommerce_wave_senegal_settings', [] );
        $webhook_secret     = $gateway_settings['webhook_secret'] ?? '';

        if ( ! empty( $webhook_secret ) ) {
            if ( ! self::verify_webhook_signature( $raw_body, $webhook_secret ) ) {
                WC_Wave_SN_Logger::error( 'Webhook Wave: Signature invalide' );
                http_response_code( 401 );
                exit( 'Signature invalide' );
            }
        }

        // Traiter le webhook selon son type
        self::process_webhook( $data );

        http_response_code( 200 );
        exit( 'OK' );
    }

    /**
     * Vérifier la signature d'un webhook Wave
     * (basée sur le même principe HMAC-SHA256 que la signature des requêtes)
     *
     * @param string $body           Corps brut de la requête
     * @param string $webhook_secret Secret du webhook
     * @return bool
     */
    private static function verify_webhook_signature( string $body, string $webhook_secret ): bool {
        $wave_signature = $_SERVER['HTTP_WAVE_SIGNATURE'] ?? '';

        if ( empty( $wave_signature ) ) {
            return false;
        }

        // Parser la signature: t={timestamp},v1={signature}
        $parts = [];
        foreach ( explode( ',', $wave_signature ) as $part ) {
            $kv            = explode( '=', $part, 2 );
            $parts[ $kv[0] ] = $kv[1] ?? '';
        }

        if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
            return false;
        }

        $timestamp = (int) $parts['t'];

        // Vérifier que le timestamp n'est pas trop ancien (tolérance 5 minutes)
        if ( abs( time() - $timestamp ) > 300 ) {
            WC_Wave_SN_Logger::warning( 'Webhook Wave: Timestamp expiré' );
            return false;
        }

        $expected_signature = hash_hmac( 'sha256', $timestamp . $body, $webhook_secret );
        return hash_equals( $expected_signature, $parts['v1'] );
    }

    /**
     * Traiter un webhook Wave et mettre à jour la commande correspondante
     *
     * @param array $data Données du webhook
     */
    private static function process_webhook( array $data ): void {
        // Le webhook Wave envoie l'objet checkout session mis à jour
        $checkout_session_id = $data['id'] ?? '';
        $payment_status      = $data['payment_status'] ?? '';
        $checkout_status     = $data['checkout_status'] ?? '';
        $client_reference    = $data['client_reference'] ?? '';

        WC_Wave_SN_Logger::log( sprintf(
            'Traitement webhook - Session: %s | Payment: %s | Checkout: %s | Ref: %s',
            $checkout_session_id,
            $payment_status,
            $checkout_status,
            $client_reference
        ), 'info' );

        // Trouver la commande par la référence client (order ID)
        $order = self::find_order_by_reference( $client_reference, $checkout_session_id );

        if ( ! $order ) {
            WC_Wave_SN_Logger::warning( 'Webhook Wave: Commande introuvable pour la session ' . $checkout_session_id );
            return;
        }

        // Mettre à jour le statut de la commande
        self::update_order_status( $order, $data );
    }

    /**
     * Trouver une commande WooCommerce par la référence Wave
     *
     * @param string $client_reference Référence client Wave (contient l'order ID)
     * @param string $session_id       ID de session Wave (fallback)
     * @return WC_Order|false
     */
    private static function find_order_by_reference( string $client_reference, string $session_id ) {
        // La référence client est formatée comme: wc_order_{order_id}_{hash}
        if ( ! empty( $client_reference ) && preg_match( '/^wc_order_(\d+)_/', $client_reference, $matches ) ) {
            $order_id = (int) $matches[1];
            $order    = wc_get_order( $order_id );
            if ( $order ) {
                return $order;
            }
        }

        // Fallback: chercher par le meta de session
        if ( ! empty( $session_id ) ) {
            $orders = wc_get_orders( [
                'meta_key'   => '_wave_checkout_session_id',
                'meta_value' => $session_id,
                'limit'      => 1,
            ] );
            if ( ! empty( $orders ) ) {
                return $orders[0];
            }
        }

        return false;
    }

    /**
     * Mettre à jour le statut d'une commande en fonction des données Wave
     *
     * @param WC_Order $order Commande WooCommerce
     * @param array    $data  Données du webhook Wave
     */
    private static function update_order_status( WC_Order $order, array $data ): void {
        $payment_status  = $data['payment_status'] ?? '';
        $checkout_status = $data['checkout_status'] ?? '';
        $transaction_id  = $data['transaction_id'] ?? '';

        // Éviter de traiter une commande déjà payée
        if ( $order->is_paid() ) {
            WC_Wave_SN_Logger::log( 'Commande #' . $order->get_id() . ' déjà payée, webhook ignoré', 'info' );
            return;
        }

        // Mettre à jour les métadonnées
        $order->update_meta_data( '_wave_checkout_status', $checkout_status );
        $order->update_meta_data( '_wave_payment_status', $payment_status );
        $order->update_meta_data( '_wave_webhook_data', wp_json_encode( $data ) );
        $order->update_meta_data( '_wave_webhook_received_at', current_time( 'mysql' ) );

        if ( ! empty( $transaction_id ) ) {
            $order->update_meta_data( '_wave_transaction_id', $transaction_id );
        }

        switch ( $payment_status ) {
            case 'succeeded':
                // Paiement réussi
                $order->payment_complete( $transaction_id );
                $order->add_order_note( sprintf(
                    __( 'Paiement Wave confirmé via webhook. Transaction ID: %s | Session: %s', 'wc-wave-senegal' ),
                    $transaction_id,
                    $data['id'] ?? ''
                ) );
                WC_Wave_SN_Logger::log_order( $order, 'Paiement confirmé via webhook. Transaction: ' . $transaction_id );

                // Mettre à jour la table de transactions
                self::update_transaction_record( $order->get_id(), $data );
                break;

            case 'cancelled':
                $error_message = '';
                if ( isset( $data['last_payment_error']['message'] ) ) {
                    $error_message = ' | Raison: ' . $data['last_payment_error']['message'];
                }
                $order->update_status(
                    'failed',
                    __( 'Paiement Wave annulé via webhook.', 'wc-wave-senegal' ) . $error_message
                );
                WC_Wave_SN_Logger::log_order( $order, 'Paiement annulé via webhook' );
                break;

            case 'processing':
                // Paiement en cours
                $order->update_status(
                    'pending',
                    __( 'Paiement Wave en cours de traitement.', 'wc-wave-senegal' )
                );
                break;
        }

        // Gérer l'expiration du checkout
        if ( 'expired' === $checkout_status && ! $order->is_paid() ) {
            $order->update_status(
                'cancelled',
                __( 'Session de paiement Wave expirée.', 'wc-wave-senegal' )
            );
        }

        $order->save();
    }

    /**
     * Mettre à jour l'enregistrement de transaction dans la base de données
     *
     * @param int   $order_id ID de commande
     * @param array $data     Données Wave
     */
    private static function update_transaction_record( int $order_id, array $data ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wave_sn_transactions';

        $wpdb->update(
            $table,
            [
                'transaction_id'  => $data['transaction_id'] ?? null,
                'payment_status'  => $data['payment_status'] ?? null,
                'checkout_status' => $data['checkout_status'] ?? null,
                'raw_response'    => wp_json_encode( $data ),
                'updated_at'      => current_time( 'mysql' ),
            ],
            [
                'checkout_session_id' => $data['id'] ?? '',
                'order_id'            => $order_id,
            ],
            [ '%s', '%s', '%s', '%s', '%s' ],
            [ '%s', '%d' ]
        );
    }
}
