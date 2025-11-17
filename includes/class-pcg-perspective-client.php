<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PCG_Perspective_Client {

    /**
     * @var PCG_Plugin
     */
    private PCG_Plugin $plugin;

    /**
     * Constructor.
     *
     * @param PCG_Plugin $plugin Plugin instance.
     */
    public function __construct( PCG_Plugin $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Log error message if logging is enabled.
     *
     * @param string $msg Message.
     */
    private function log_error( $msg ) {
        $settings = $this->plugin->get_settings();
        if ( ! empty( $settings['enable_logging'] ) ) {
            error_log( '[Comment Shield AI] ' . $msg );
        }
    }

    /**
     * Get toxicity score from Perspective API.
     *
     * @param string $text     Comment text.
     * @param array  $settings Plugin settings.
     * @return float|null
     */
    public function get_toxicity_score( $text, array $settings ): ?float
    {
        $api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';

        if ( empty( $api_key ) || empty( $text ) ) {
            return null;
        }

        $url = add_query_arg(
            'key',
            rawurlencode( $api_key ),
            'https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze'
        );

        $languages = isset( $settings['languages'] ) && is_array( $settings['languages'] )
            ? array_values( $settings['languages'] )
            : array( 'en', 'nl' );

        $body = array(
            'comment'             => array( 'text' => $text ),
            'languages'           => $languages,
            'requestedAttributes' => array( 'TOXICITY' => new stdClass() ),
        );

        $response = wp_remote_post(
            $url,
            array(
                'body'    => wp_json_encode( $body ),
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log_error( 'WP_Error during API call: ' . $response->get_error_message() );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            $this->log_error( 'Perspective API returned HTTP ' . $code . '.' );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            $this->log_error( 'Invalid Perspective API response: not an array.' );
            return null;
        }

        if (
            isset( $data['attributeScores']['TOXICITY']['summaryScore']['value'] ) &&
            is_numeric( $data['attributeScores']['TOXICITY']['summaryScore']['value'] )
        ) {
            return (float) $data['attributeScores']['TOXICITY']['summaryScore']['value'];
        }

        $this->log_error( 'Invalid Perspective API response: toxicity score missing.' );
        return null;
    }
}
