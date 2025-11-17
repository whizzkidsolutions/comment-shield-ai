<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PCG_Notifications {

    const OPTION_LAST_NOTIFICATION = 'pcg_last_notification';

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

        // Directe notificaties na een nieuwe reactie (afhankelijk van mode).
        add_action( 'comment_post', array( $this, 'maybe_send_immediate' ), 20, 2 );

        // Periodieke notificaties meeliften op bestaande cron.
        add_action( PCG_Plugin::CRON_HOOK, array( $this, 'maybe_send_scheduled_summary' ), 20 );
    }

    /**
     * Mogelijke modes:
     * none, immediate, hourly, 12hours, daily, weekly.
     *
     * @param array $settings Settings.
     * @return string
     */
    private function get_mode( array $settings ): string
    {
        $valid = array( 'none', 'immediate', 'hourly', '12hours', 'daily', 'weekly' );
        $mode  = $settings['notification_mode'] ?? 'none';

        if ( ! in_array( $mode, $valid, true ) ) {
            $mode = 'none';
        }
        return $mode;
    }

    /**
     * Haal e-mailontvangers op.
     *
     * @param array $settings Settings.
     * @return string[] Array met e-mailadressen.
     */
    private function get_recipients( array $settings ): array
    {
        $raw = isset( $settings['notification_recipients'] ) ? trim( $settings['notification_recipients'] ) : '';

        if ( '' === $raw ) {
            $admin_email = get_option( 'admin_email' );
            return is_email( $admin_email ) ? array( $admin_email ) : array();
        }

        $parts = array_map( 'trim', explode( ',', $raw ) );
        $emails = array();

        foreach ( $parts as $p ) {
            if ( is_email( $p ) ) {
                $emails[] = $p;
            }
        }

        if ( empty( $emails ) ) {
            $admin_email = get_option( 'admin_email' );
            if ( is_email( $admin_email ) ) {
                $emails[] = $admin_email;
            }
        }

        return array_unique( $emails );
    }

    /**
     * Stuur evt. directe mail na nieuwe reactie.
     *
     * @param int $comment_id Comment ID.
     * @param int $comment_approved Approved status.
     */
    public function maybe_send_immediate( $comment_id, $comment_approved ): void
    {
        $settings = $this->plugin->get_settings();
        $mode     = $this->get_mode( $settings );

        if ( 'immediate' !== $mode ) {
            return;
        }

        $recipients = $this->get_recipients( $settings );
        if ( empty( $recipients ) ) {
            return;
        }

        // Voorkom dubbele mail voor dezelfde reactie.
        $transient_key = 'pcg_notif_sent_' . (int) $comment_id;
        if ( get_transient( $transient_key ) ) {
            return;
        }

        $now  = current_time( 'timestamp', true );
        $last = (int) get_option( self::OPTION_LAST_NOTIFICATION, 0 );

        if ( $last <= 0 ) {
            // Eerste keer: neem gewoon laatste uur als 'periode'.
            $last = $now - HOUR_IN_SECONDS;
        }

        $counts = $this->get_counts_between( $last, $now );

        list( $subject, $body, $headers ) = $this->build_email(
            $counts,
            $last,
            $now,
            __( 'Immediate comment notification', PCG_TEXTDOMAIN )
        );

        wp_mail( $recipients, $subject, $body, $headers );

        // Update laatste notificatie-tijd.
        update_option( self::OPTION_LAST_NOTIFICATION, $now, false );

        // Markeer deze comment als gemaild.
        set_transient( $transient_key, 1, HOUR_IN_SECONDS );
    }

    /**
     * Mogelijk periodieke samenvatting sturen (hookt op bestaande cron).
     */
    public function maybe_send_scheduled_summary(): void
    {
        $settings = $this->plugin->get_settings();
        $mode     = $this->get_mode( $settings );

        if ( in_array( $mode, array( 'none', 'immediate' ), true ) ) {
            // Geen periodieke mails in deze modes.
            return;
        }

        $recipients = $this->get_recipients( $settings );
        if ( empty( $recipients ) ) {
            return;
        }

        $now  = current_time( 'timestamp', true );
        $last = (int) get_option( self::OPTION_LAST_NOTIFICATION, 0 );

        $interval = $this->get_interval_for_mode( $mode );

        if ( $last <= 0 ) {
            // Nog nooit gestuurd → start gewoon 1 interval terug.
            $last = $now - $interval;
        }

        // Niet tijd? weg.
        if ( ( $now - $last ) < ( $interval - 60 ) ) {
            return;
        }

        $counts = $this->get_counts_between( $last, $now );

        list( $subject, $body, $headers ) = $this->build_email(
            $counts,
            $last,
            $now,
            $this->get_subject_for_mode( $mode )
        );

        wp_mail( $recipients, $subject, $body, $headers );

        update_option( self::OPTION_LAST_NOTIFICATION, $now, false );
    }

    /**
     * Interval in seconden voor mode.
     *
     * @param string $mode Mode.
     * @return int
     */
    private function get_interval_for_mode( string $mode ): float|int
    {
        switch ( $mode ) {
            case 'hourly':
                return HOUR_IN_SECONDS;
            case '12hours':
                return 12 * HOUR_IN_SECONDS;
            case 'weekly':
                return WEEK_IN_SECONDS;
            default:
                return DAY_IN_SECONDS;
        }
    }

    /**
     * Subject per mode.
     *
     * @param string $mode Mode.
     * @return string
     */
    private function get_subject_for_mode(string $mode): string
    {
        switch ( $mode ) {
            case 'hourly':
                return __( 'Hourly comment summary', PCG_TEXTDOMAIN );
            case '12hours':
                return __( '12-hour comment summary', PCG_TEXTDOMAIN );
            case 'daily':
                return __( 'Daily comment summary', PCG_TEXTDOMAIN );
            case 'weekly':
                return __( 'Weekly comment summary', PCG_TEXTDOMAIN );
            default:
                return __( 'Comment summary', PCG_TEXTDOMAIN );
        }
    }

    /**
     * Tel reacties in an interval [start, end].
     *
     * @param int $start_ts Start timestamp (GMT).
     * @param int $end_ts   End timestamp (GMT).
     * @return array
     */
    private function get_counts_between( $start_ts, $end_ts ): array
    {
        $start = gmdate( 'Y-m-d H:i:s', $start_ts );
        $end   = gmdate( 'Y-m-d H:i:s', $end_ts );

        $args  = array(
            'date_query' => array(
                array(
                    'column'    => 'comment_date_gmt',
                    'after'     => $start,
                    'before'    => $end,
                    'inclusive' => false,
                ),
            ),
            'status'     => 'all',
            'type'       => 'comment',
            'number'     => 0, // all
        );

        $comments = get_comments( $args );

        $counts = array(
            'total'    => 0,
            'approved' => 0,
            'pending'  => 0,
            'spam'     => 0,
        );

        foreach ( $comments as $comment ) {
            $counts['total']++;

            $st = (string) $comment->comment_approved;

            if ( '1' === $st || 'approve' === $st ) {
                $counts['approved']++;
            } elseif ( '0' === $st || 'hold' === $st ) {
                $counts['pending']++;
            } elseif ( 'spam' === $st ) {
                $counts['spam']++;
            }
        }

        return $counts;
    }

    /**
     * Bouw een multipart (text/plain + text/html) mail.
     *
     * @param array  $counts   Counts.
     * @param int    $start_ts Start ts.
     * @param int    $end_ts   End ts.
     * @param string $subject_base Subject base.
     *
     * @return array [ subject, body, headers ]
     */
    private function build_email( array $counts, $start_ts, $end_ts, $subject_base ): array
    {
        $start_local = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $start_ts ), 'Y-m-d H:i' );
        $end_local   = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $end_ts ), 'Y-m-d H:i' );

        $subject = sprintf(
            '%s (%s – %s)',
            $subject_base,
            $start_local,
            $end_local
        );

        $admin_url = admin_url( 'edit-comments.php' );

        $plain = "Comment summary from {$start_local} to {$end_local}\n\n" .
            "Total comments: {$counts['total']}\n" .
            "Approved: {$counts['approved']}\n" .
            "Pending: {$counts['pending']}\n" .
            "Spam: {$counts['spam']}\n\n" .
            "Review comments: {$admin_url}\n";

        $html  = '<html><body>';
        $html .= '<h2>Comment summary</h2>';
        $html .= '<p><strong>Period:</strong> ' . esc_html( $start_local ) . ' – ' . esc_html( $end_local ) . '</p>';
        $html .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">';
        $html .= '<tr><th align="left">Total</th><td>' . (int) $counts['total'] . '</td></tr>';
        $html .= '<tr><th align="left">Approved</th><td>' . (int) $counts['approved'] . '</td></tr>';
        $html .= '<tr><th align="left">Pending</th><td>' . (int) $counts['pending'] . '</td></tr>';
        $html .= '<tr><th align="left">Spam</th><td>' . (int) $counts['spam'] . '</td></tr>';
        $html .= '</table>';
        $html .= '<p><a href="' . esc_url( $admin_url ) . '">Review comments in WordPress</a></p>';
        $html .= '</body></html>';

        // Multipart/alternative.
        $boundary = 'pcg_boundary_' . wp_generate_uuid4();

        $headers   = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body  = '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= $plain . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $html . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        return array( $subject, $body, $headers );
    }
}
