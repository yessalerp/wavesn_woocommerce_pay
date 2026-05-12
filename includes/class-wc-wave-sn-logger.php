<?php
/**
 * Classe WC_Wave_SN_Logger
 * Système de journalisation pour le plugin Wave Sénégal
 *
 * @package WC_Wave_Senegal
 */

defined( 'ABSPATH' ) || exit;

class WC_Wave_SN_Logger {

    /**
     * Source de log WooCommerce
     */
    const LOG_SOURCE = 'woocommerce-wave-senegal';

    /**
     * Instance du logger WooCommerce
     *
     * @var WC_Logger|null
     */
    private static ?WC_Logger $logger = null;

    /**
     * Obtenir l'instance du logger WooCommerce
     *
     * @return WC_Logger
     */
    private static function get_logger(): WC_Logger {
        if ( null === self::$logger ) {
            self::$logger = wc_get_logger();
        }
        return self::$logger;
    }

    /**
     * Écrire un message dans les logs
     *
     * @param string $message Message à logger
     * @param string $level   Niveau: debug, info, notice, warning, error, critical, alert, emergency
     */
    public static function log( string $message, string $level = 'info' ): void {
        $gateway_settings = get_option( 'woocommerce_wave_senegal_settings', [] );
        $debug_mode       = isset( $gateway_settings['debug_mode'] ) && 'yes' === $gateway_settings['debug_mode'];

        // Ne logger en debug que si le mode debug est activé
        if ( 'debug' === $level && ! $debug_mode ) {
            return;
        }

        self::get_logger()->log(
            $level,
            '[Wave SN] ' . $message,
            [ 'source' => self::LOG_SOURCE ]
        );
    }

    /**
     * Logger une erreur
     *
     * @param string $message
     */
    public static function error( string $message ): void {
        self::log( $message, 'error' );
    }

    /**
     * Logger une information
     *
     * @param string $message
     */
    public static function info( string $message ): void {
        self::log( $message, 'info' );
    }

    /**
     * Logger un avertissement
     *
     * @param string $message
     */
    public static function warning( string $message ): void {
        self::log( $message, 'warning' );
    }

    /**
     * Logger les détails d'une commande
     *
     * @param WC_Order $order   Commande WooCommerce
     * @param string   $message Message contextuel
     */
    public static function log_order( WC_Order $order, string $message ): void {
        self::log( sprintf( 'Commande #%d - %s', $order->get_id(), $message ), 'info' );
    }
}
