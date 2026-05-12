<?php
/**
 * Classe WC_Wave_SN_API
 * Gère toutes les interactions avec l'API Wave Checkout
 *
 * @package WC_Wave_Senegal
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Wave_SN_API {

    /**
     * URL de base de l'API Wave
     */
    const API_BASE_URL = 'https://api.wave.com';

    /**
     * Version de l'API
     */
    const API_VERSION = 'v1';

    /**
     * Clé API Wave
     *
     * @var string
     */
    private string $api_key;

    /**
     * Secret de signature des requêtes (optionnel)
     *
     * @var string
     */
    private string $signing_secret;

    /**
     * Mode de l'environnement (sandbox ou production)
     *
     * @var bool
     */
    private bool $is_test_mode;

    /**
     * Timeout des requêtes cURL (secondes)
     *
     * @var int
     */
    private int $timeout = 30;

    /**
     * Constructeur
     *
     * @param string $api_key        Clé API Wave
     * @param string $signing_secret Secret de signature (optionnel)
     * @param bool   $is_test_mode   Mode test
     */
    public function __construct( string $api_key, string $signing_secret = '', bool $is_test_mode = false ) {
        $this->api_key        = $api_key;
        $this->signing_secret = $signing_secret;
        $this->is_test_mode   = $is_test_mode;
    }

    /**
     * Obtenir l'URL complète d'un endpoint
     *
     * @param string $endpoint
     * @return string
     */
    private function get_endpoint_url( string $endpoint ): string {
        return self::API_BASE_URL . '/' . self::API_VERSION . '/' . ltrim( $endpoint, '/' );
    }

    /**
     * Générer la signature HMAC-SHA256 pour une requête
     *
     * @param int    $timestamp Unix timestamp
     * @param string $body      Corps de la requête (JSON)
     * @return string
     */
    private function generate_signature( int $timestamp, string $body ): string {
        $payload   = $timestamp . $body;
        $signature = hash_hmac( 'sha256', $payload, $this->signing_secret );
        return "t={$timestamp},v1={$signature}";
    }

    /**
     * Construire les headers HTTP pour une requête
     *
     * @param string $body Corps de la requête (pour la signature)
     * @param bool   $is_get Est-ce une requête GET ?
     * @return array
     */
    private function build_headers( string $body = '', bool $is_get = false ): array {
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'User-Agent'    => 'WooCommerce-Wave-SN/' . WC_WAVE_SN_VERSION,
        ];

        // Ajouter la signature si le secret est configuré
        if ( ! empty( $this->signing_secret ) ) {
            $timestamp         = time();
            $signing_body      = $is_get ? '' : $body; // GET = corps vide pour la signature
            $wave_signature    = $this->generate_signature( $timestamp, $signing_body );
            $headers['Wave-Signature'] = $wave_signature;
        }

        return $headers;
    }

    /**
     * Effectuer une requête POST vers l'API Wave
     *
     * @param string $endpoint Point de terminaison de l'API
     * @param array  $data     Données à envoyer
     * @return array|WP_Error
     */
    private function post( string $endpoint, array $data = [] ) {
        $url  = $this->get_endpoint_url( $endpoint );
        $body = wp_json_encode( $data );

        $response = wp_remote_post( $url, [
            'headers' => $this->build_headers( $body ),
            'body'    => $body,
            'timeout' => $this->timeout,
        ] );

        return $this->handle_response( $response );
    }

    /**
     * Effectuer une requête GET vers l'API Wave
     *
     * @param string $endpoint Point de terminaison de l'API
     * @param array  $params   Paramètres GET
     * @return array|WP_Error
     */
    private function get( string $endpoint, array $params = [] ) {
        $url = $this->get_endpoint_url( $endpoint );
        if ( ! empty( $params ) ) {
            $url .= '?' . http_build_query( $params );
        }

        $response = wp_remote_get( $url, [
            'headers' => $this->build_headers( '', true ),
            'timeout' => $this->timeout,
        ] );

        return $this->handle_response( $response );
    }

    /**
     * Traiter la réponse de l'API
     *
     * @param array|WP_Error $response
     * @return array|WP_Error
     */
    private function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            WC_Wave_SN_Logger::log( 'Erreur de connexion à l\'API Wave: ' . $response->get_error_message(), 'error' );
            return $response;
        }

        $status_code  = wp_remote_retrieve_response_code( $response );
        $body         = wp_remote_retrieve_body( $response );
        $decoded_body = json_decode( $body, true );

        WC_Wave_SN_Logger::log( sprintf( 'Réponse API Wave - Status: %d | Body: %s', $status_code, $body ), 'debug' );

        if ( $status_code >= 400 ) {
            $error_message = isset( $decoded_body['error']['message'] )
                ? $decoded_body['error']['message']
                : __( 'Erreur inconnue de l\'API Wave', 'wc-wave-senegal' );
            $error_code = isset( $decoded_body['error']['code'] )
                ? $decoded_body['error']['code']
                : 'api_error';

            return new WP_Error( $error_code, $error_message, [
                'status_code' => $status_code,
                'response'    => $decoded_body,
            ] );
        }

        return $decoded_body ?? [];
    }

    // =========================================================================
    // ENDPOINTS CHECKOUT API
    // =========================================================================

    /**
     * Créer une session de paiement Wave
     *
     * @param array $params {
     *     @type string $amount           Montant en XOF (sans décimales)
     *     @type string $currency         Devise (XOF par défaut)
     *     @type string $success_url      URL de redirection après succès
     *     @type string $error_url        URL de redirection en cas d'erreur
     *     @type string $client_reference Référence unique dans votre système (optionnel)
     *     @type string $restrict_payer_mobile Numéro Wave spécifique autorisé (optionnel)
     * }
     * @return array|WP_Error Session de paiement ou erreur
     */
    public function create_checkout_session( array $params ) {
        $required = [ 'amount', 'currency', 'success_url', 'error_url' ];
        foreach ( $required as $field ) {
            if ( empty( $params[ $field ] ) ) {
                return new WP_Error(
                    'missing_parameter',
                    sprintf( __( 'Paramètre requis manquant: %s', 'wc-wave-senegal' ), $field )
                );
            }
        }

        WC_Wave_SN_Logger::log( 'Création session Wave - Paramètres: ' . wp_json_encode( $params ), 'info' );

        $result = $this->post( 'checkout/sessions', $params );

        if ( ! is_wp_error( $result ) ) {
            WC_Wave_SN_Logger::log( 'Session Wave créée: ' . ( $result['id'] ?? 'inconnu' ), 'info' );
        }

        return $result;
    }

    /**
     * Récupérer une session de paiement par son ID
     *
     * @param string $session_id ID de la session (ex: cos-18qq25rgr100a)
     * @return array|WP_Error
     */
    public function get_checkout_session( string $session_id ) {
        return $this->get( 'checkout/sessions/' . $session_id );
    }

    /**
     * Récupérer une session de paiement par l'ID de transaction
     *
     * @param string $transaction_id ID de transaction Wave
     * @return array|WP_Error
     */
    public function get_checkout_session_by_transaction( string $transaction_id ) {
        return $this->get( 'checkout/sessions', [ 'transaction_id' => $transaction_id ] );
    }

    /**
     * Rechercher des sessions par référence client
     *
     * @param string $client_reference Référence client
     * @return array|WP_Error
     */
    public function search_checkout_sessions( string $client_reference ) {
        return $this->get( 'checkout/sessions/search', [ 'client_reference' => $client_reference ] );
    }

    /**
     * Effectuer un remboursement
     *
     * @param string $session_id ID de la session à rembourser
     * @return array|WP_Error
     */
    public function refund_checkout_session( string $session_id ) {
        WC_Wave_SN_Logger::log( 'Remboursement session Wave: ' . $session_id, 'info' );
        return $this->post( 'checkout/sessions/' . $session_id . '/refund' );
    }

    /**
     * Expirer une session de paiement
     *
     * @param string $session_id ID de la session à expirer
     * @return array|WP_Error
     */
    public function expire_checkout_session( string $session_id ) {
        WC_Wave_SN_Logger::log( 'Expiration session Wave: ' . $session_id, 'info' );
        return $this->post( 'checkout/sessions/' . $session_id . '/expire' );
    }

    // =========================================================================
    // UTILITAIRES
    // =========================================================================

    /**
     * Vérifier la validité de la clé API (test de connectivité)
     *
     * @return bool|WP_Error
     */
    public function validate_api_key() {
        // On tente de récupérer une liste de sessions pour valider la clé
        $result = $this->get( 'checkout/sessions/search', [ 'client_reference' => 'wc_api_test_' . time() ] );

        if ( is_wp_error( $result ) ) {
            $code = $result->get_error_code();
            // 'checkout-session-not-found' = clé valide mais pas de résultat
            if ( in_array( $code, [ 'checkout-session-not-found', 'request-validation-error' ], true ) ) {
                return true;
            }
            return $result;
        }

        return true;
    }

    /**
     * Formater un montant WooCommerce pour l'API Wave (XOF = pas de décimales)
     *
     * @param float|string $amount Montant WooCommerce
     * @return string Montant formaté pour Wave
     */
    public static function format_amount( $amount ): string {
        return (string) intval( round( (float) $amount ) );
    }
}
