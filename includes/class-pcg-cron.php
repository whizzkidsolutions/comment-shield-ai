<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PCG_Cron {

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

        add_action( PCG_Plugin::CRON_HOOK, array( $this, 'run' ) );
    }

    /**
     * Schedule cron event.
     */
    public function schedule() {
        if ( ! wp_next_scheduled( PCG_Plugin::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'hourly', PCG_Plugin::CRON_HOOK );
        }
    }

    /**
     * Clear scheduled hook.
     */
    public function clear() {
        wp_clear_scheduled_hook( PCG_Plugin::CRON_HOOK );
    }

    /**
     * Cron callback.
     */
    public function run() {
        $settings = $this->plugin->get_settings();

        if ( empty( $settings['api_key'] ) ) {
            return;
        }

        $args = array(
            'status'     => 'hold',
            'number'     => (int) $settings['batch_size'],
            'meta_query' => array(
                array(
                    'key'     => PCG_Plugin::META_SCORE,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        $comments = get_comments( $args );

        if ( empty( $comments ) ) {
            return;
        }

        foreach ( $comments as $comment ) {
            $score = $this->plugin->client->get_toxicity_score( $comment->comment_content, $settings );

            if ( null === $score ) {
                continue;
            }

            update_comment_meta( $comment->comment_ID, PCG_Plugin::META_SCORE, $score );

            if ( $score >= (float) $settings['threshold_spam'] ) {
                wp_spam_comment( $comment->comment_ID );
            } elseif ( $score <= (float) $settings['auto_approve_below'] ) {
                wp_set_comment_status( $comment->comment_ID, 'approve' );
            }
        }
    }
}
