<?php
/**
 * Plugin Name:       Wave Sénégal - Passerelle de Paiement WooCommerce
 * Plugin URI:        https://github.com/yessalerp/wavesn_woocommerce_pay
 * Description:       Intégration officielle de Wave Mobile Money comme passerelle de paiement pour WooCommerce. Acceptez les paiements Wave directement sur votre boutique en ligne en toute sécurité.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Jaxaay Group
 * Author URI:        https://jaxaaygroup.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-wave-senegal
 * Domain Path:       /languages
 * WC requires at least: 6.0
 * WC tested up to:   8.5
 *
 * @package    WC_Wave_Senegal
 * @author     Jaxaay Group <jaxaay@jaxaaygroup.com>
 * @copyright  2024 Jaxaay Group
 * @license    GPL-2.0-or-later
 *
 * Développé par  : Jaxaay Group
 * Adresse        : Parcelles Assainies U22, Villa N°529 - Dakar, Sénégal
 * Email          : jaxaay@jaxaaygroup.com
 * Site web       : https://jaxaaygroup.com
 * Téléphone      : +221 78 651 15 15
 * GitHub         : https://github.com/yessalerp/wavesn_woocommerce_pay
 */

defined( 'ABSPATH' ) || exit;

// ============================================================================
// CONSTANTES
// ============================================================================
define( 'WC_WAVE_SN_VERSION',         '2.0.0' );
define( 'WC_WAVE_SN_PLUGIN_FILE',     __FILE__ );
define( 'WC_WAVE_SN_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'WC_WAVE_SN_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'WC_WAVE_SN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WC_WAVE_SN_AUTHOR',          'Jaxaay Group' );
define( 'WC_WAVE_SN_AUTHOR_EMAIL',    'jaxaay@jaxaaygroup.com' );
define( 'WC_WAVE_SN_AUTHOR_URL',      'https://jaxaaygroup.com' );
define( 'WC_WAVE_SN_AUTHOR_PHONE',    '+221 78 651 15 15' );
define( 'WC_WAVE_SN_AUTHOR_ADDR',     'Parcelles Assainies U22, Villa N°529 - Dakar, Sénégal' );
define( 'WC_WAVE_SN_GITHUB_URL',      'https://github.com/yessalerp/wavesn_woocommerce_pay' );

// ============================================================================
// PRÉREQUIS
// ============================================================================
function wc_wave_sn_check_requirements(): bool {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wc_wave_sn_woocommerce_missing_notice' );
        return false;
    }
    return true;
}

function wc_wave_sn_woocommerce_missing_notice(): void {
    echo '<div class="error notice"><p><strong>' .
         esc_html__( 'Wave Sénégal — Jaxaay Group', 'wc-wave-senegal' ) .
         '</strong> — ' .
         esc_html__( 'WooCommerce doit être installé et activé pour utiliser ce plugin.', 'wc-wave-senegal' ) .
         '</p></div>';
}

// ============================================================================
// INITIALISATION
// ============================================================================
function wc_wave_sn_init(): void {
    if ( ! wc_wave_sn_check_requirements() ) {
        return;
    }
    load_plugin_textdomain( 'wc-wave-senegal', false, dirname( WC_WAVE_SN_PLUGIN_BASENAME ) . '/languages' );
    require_once WC_WAVE_SN_PLUGIN_DIR . 'includes/class-wc-wave-sn-logger.php';
    require_once WC_WAVE_SN_PLUGIN_DIR . 'includes/class-wc-wave-sn-api.php';
    require_once WC_WAVE_SN_PLUGIN_DIR . 'includes/class-wc-wave-sn-webhook.php';
    require_once WC_WAVE_SN_PLUGIN_DIR . 'includes/class-wc-wave-sn-gateway.php';
    WC_Wave_SN_Webhook::init();
}
add_action( 'plugins_loaded', 'wc_wave_sn_init' );

// Compatibilité HPOS WooCommerce
add_action( 'before_woocommerce_init', function (): void {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
    }
} );

// Enregistrer la passerelle
function wc_wave_sn_add_gateway( array $gateways ): array {
    $gateways[] = 'WC_Wave_SN_Gateway';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_wave_sn_add_gateway' );

// ============================================================================
// LIENS DU PLUGIN
// ============================================================================
function wc_wave_sn_plugin_action_links( array $links ): array {
    return array_merge( [
        '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wave_senegal' ) ) . '">' .
            esc_html__( 'Paramètres', 'wc-wave-senegal' ) . '</a>',
        '<a href="' . esc_url( WC_WAVE_SN_GITHUB_URL ) . '" target="_blank">GitHub</a>',
    ], $links );
}
add_filter( 'plugin_action_links_' . WC_WAVE_SN_PLUGIN_BASENAME, 'wc_wave_sn_plugin_action_links' );

function wc_wave_sn_plugin_row_meta( array $links, string $file ): array {
    if ( WC_WAVE_SN_PLUGIN_BASENAME !== $file ) {
        return $links;
    }
    $links[] = '<a href="https://docs.wave.com/checkout" target="_blank">' .
               esc_html__( 'Documentation API Wave', 'wc-wave-senegal' ) . '</a>';
    $links[] = '<a href="mailto:' . esc_attr( WC_WAVE_SN_AUTHOR_EMAIL ) . '">' .
               esc_html__( 'Support Jaxaay Group', 'wc-wave-senegal' ) . '</a>';
    return $links;
}
add_filter( 'plugin_row_meta', 'wc_wave_sn_plugin_row_meta', 10, 2 );

// ============================================================================
// ACTIVATION
// ============================================================================
function wc_wave_sn_activate(): void {
    global $wpdb;
    $table           = $wpdb->prefix . 'wave_sn_transactions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id             BIGINT(20) UNSIGNED NOT NULL,
        checkout_session_id  VARCHAR(50)  NOT NULL,
        transaction_id       VARCHAR(100) DEFAULT NULL,
        amount               VARCHAR(20)  NOT NULL,
        currency             VARCHAR(10)  DEFAULT 'XOF',
        payment_status       VARCHAR(20)  DEFAULT 'pending',
        checkout_status      VARCHAR(20)  DEFAULT 'open',
        client_reference     VARCHAR(255) DEFAULT NULL,
        wave_launch_url      TEXT         DEFAULT NULL,
        raw_response         LONGTEXT     DEFAULT NULL,
        created_at           DATETIME     DEFAULT CURRENT_TIMESTAMP,
        updated_at           DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id            (order_id),
        KEY checkout_session_id (checkout_session_id),
        KEY transaction_id      (transaction_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    update_option( 'wc_wave_sn_version',      WC_WAVE_SN_VERSION );
    update_option( 'wc_wave_sn_installed_at', current_time( 'mysql' ) );
}
register_activation_hook( __FILE__, 'wc_wave_sn_activate' );

function wc_wave_sn_deactivate(): void {}
register_deactivation_hook( __FILE__, 'wc_wave_sn_deactivate' );
