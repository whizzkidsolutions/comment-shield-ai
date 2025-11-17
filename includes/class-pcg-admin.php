<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PCG_Admin {

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

        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Comments list column.
        add_filter( 'manage_edit-comments_columns', array( $this, 'add_comment_column' ) );
        add_action( 'manage_comments_custom_column', array( $this, 'render_comment_column' ), 10, 2 );

        // Comment edit metabox.
        add_action( 'add_meta_boxes_comment', array( $this, 'add_comment_metabox' ) );
        add_action( 'edit_comment', array( $this, 'save_comment_meta' ) );
    }

    /**
     * Register settings page.
     */
    public function register_settings_page() {
        add_options_page(
            __( 'Comment Shield AI', PCG_TEXTDOMAIN ),
            __( 'Comment Shield AI', PCG_TEXTDOMAIN ),
            'manage_options',
            'pcg-settings',
            array( $this, 'settings_page_html' )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting(
            'pcg_settings_group',
            PCG_Plugin::OPTION_SETTINGS,
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'pcg_main_section',
            __( 'Perspective API settings', PCG_TEXTDOMAIN ),
            '__return_false',
            'pcg-settings'
        );

        add_settings_field(
            'pcg_api_key',
            __( 'Perspective API key', PCG_TEXTDOMAIN ),
            array( $this, 'field_api_key' ),
            'pcg-settings',
            'pcg_main_section'
        );

        add_settings_field(
            'pcg_threshold_spam',
            __( 'Toxicity threshold for spam', PCG_TEXTDOMAIN ),
            array( $this, 'field_threshold_spam' ),
            'pcg-settings',
            'pcg_main_section'
        );

        add_settings_field(
            'pcg_auto_approve_below',
            __( 'Auto-approve below score', PCG_TEXTDOMAIN ),
            array( $this, 'field_auto_approve_below' ),
            'pcg-settings',
            'pcg_main_section'
        );

        add_settings_field(
            'pcg_batch_size',
            __( 'Comments per cron run', PCG_TEXTDOMAIN ),
            array( $this, 'field_batch_size' ),
            'pcg-settings',
            'pcg_main_section'
        );

        add_settings_field(
            'pcg_languages',
            __( 'Languages (comma separated)', PCG_TEXTDOMAIN ),
            array( $this, 'field_languages' ),
            'pcg-settings',
            'pcg_main_section'
        );

        add_settings_field(
            'pcg_notification_mode',
            __( 'Notification frequency', PCG_TEXTDOMAIN ),
            array( $this, 'field_notification_mode' ),
            'pcg-settings',
            'pcg_main_section'
        );

        add_settings_field(
            'pcg_notification_recipients',
            __( 'Notification recipients', PCG_TEXTDOMAIN ),
            array( $this, 'field_notification_recipients' ),
            'pcg-settings',
            'pcg_main_section'
        );

        add_settings_field(
            'pcg_enable_logging',
            __( 'Enable error logging', PCG_TEXTDOMAIN ),
            array( $this, 'field_enable_logging' ),
            'pcg-settings',
            'pcg_main_section'
        );
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Raw input.
     * @return array
     */
    public function sanitize_settings(array $input ): array
    {
        $output = $this->plugin->get_settings();

        if ( isset( $input['api_key'] ) ) {
            $output['api_key'] = trim( sanitize_text_field( $input['api_key'] ) );
        }

        if ( isset( $input['threshold_spam'] ) ) {
            $output['threshold_spam'] = (float) $input['threshold_spam'];
            if ( $output['threshold_spam'] < 0 ) {
                $output['threshold_spam'] = 0;
            }
            if ( $output['threshold_spam'] > 1 ) {
                $output['threshold_spam'] = 1;
            }
        }

        if ( isset( $input['auto_approve_below'] ) ) {
            $output['auto_approve_below'] = (float) $input['auto_approve_below'];
            if ( $output['auto_approve_below'] < 0 ) {
                $output['auto_approve_below'] = 0;
            }
            if ( $output['auto_approve_below'] > 1 ) {
                $output['auto_approve_below'] = 1;
            }
        }

        if ( isset( $input['batch_size'] ) ) {
            $output['batch_size'] = max( 1, (int) $input['batch_size'] );
        }

        if ( isset( $input['languages'] ) ) {
            $langs = explode( ',', $input['languages'] );
            $langs = array_map( 'trim', $langs );
            $langs = array_filter( $langs );
            $output['languages'] = ! empty( $langs ) ? $langs : array( 'en', 'nl', 'es' );
        }

        if ( isset( $input['notification_mode'] ) ) {
            $valid = array( 'none', 'immediate', 'hourly', '12hours', 'daily', 'weekly' );
            $mode  = $input['notification_mode'];
            $output['notification_mode'] = in_array( $mode, $valid, true ) ? $mode : 'none';
        }

        if ( isset( $input['notification_recipients'] ) ) {
            $output['notification_recipients'] = sanitize_text_field( $input['notification_recipients'] );
        }

        $output['enable_logging'] = ! empty( $input['enable_logging'] );

        return $output;
    }

    /**
     * Settings page HTML.
     */
    public function settings_page_html(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = $this->plugin->get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Comment Shield AI – Perspective Spam Guard', PCG_TEXTDOMAIN ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'pcg_settings_group' );
                do_settings_sections( 'pcg-settings' );
                submit_button();
                ?>
            </form>

            <hr>
            <p>
                <?php esc_html_e( 'Comments with status “Pending” are scanned periodically via WP-Cron. Depending on the toxicity score they are approved, marked as spam, or left in moderation.', PCG_TEXTDOMAIN ); ?>
            </p>
        </div>
        <?php
    }

    public function field_api_key(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <input type="text"
               name="<?php echo esc_attr( PCG_Plugin::OPTION_SETTINGS ); ?>[api_key]"
               value="<?php echo esc_attr( $settings['api_key'] ); ?>"
               class="regular-text" />
        <p class="description"><?php esc_html_e( 'For instructions to get an API Key, visit: https://support.perspectiveapi.com/s/docs-enable-the-api', PCG_TEXTDOMAIN ); ?></p>
        <?php
    }

    public function field_threshold_spam(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <input type="number" step="0.01" min="0" max="1"
               name="<?php echo esc_attr( PCG_Plugin::OPTION_SETTINGS ); ?>[threshold_spam]"
               value="<?php echo esc_attr( $settings['threshold_spam'] ); ?>" />
        <p class="description">
            <?php esc_html_e( 'Score equal or above this value will be marked as spam.', PCG_TEXTDOMAIN ); ?>
        </p>
        <?php
    }

    public function field_auto_approve_below(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <input type="number" step="0.01" min="0" max="1"
               name="<?php echo esc_attr( PCG_Plugin::OPTION_SETTINGS ); ?>[auto_approve_below]"
               value="<?php echo esc_attr( $settings['auto_approve_below'] ); ?>" />
        <p class="description">
            <?php esc_html_e( 'Score equal or below this value will be auto-approved.', PCG_TEXTDOMAIN ); ?>
        </p>
        <?php
    }

    public function field_batch_size(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <input type="number" min="1"
               name="<?php echo esc_attr( PCG_Plugin::OPTION_SETTINGS ); ?>[batch_size]"
               value="<?php echo esc_attr( $settings['batch_size'] ); ?>" />
        <?php
    }

    public function field_languages(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <input type="text"
               name="<?php echo esc_attr( PCG_Plugin::OPTION_SETTINGS ); ?>[languages]"
               value="<?php echo esc_attr( implode( ', ', (array) $settings['languages'] ) ); ?>" />
        <p class="description">
            <?php esc_html_e( 'For example: en, nl, es', PCG_TEXTDOMAIN ); ?>
        </p>
        <?php
    }

    public function field_notification_mode(): void
    {
        $settings = $this->plugin->get_settings();
        $mode     = $settings['notification_mode'] ?? 'none';

        $options = array(
            'none'      => __( 'No emails', PCG_TEXTDOMAIN ),
            'immediate' => __( 'Immediately after each comment', PCG_TEXTDOMAIN ),
            'hourly'    => __( 'Every hour', PCG_TEXTDOMAIN ),
            '12hours'   => __( 'Every 12 hours', PCG_TEXTDOMAIN ),
            'daily'     => __( 'Every 24 hours', PCG_TEXTDOMAIN ),
            'weekly'    => __( 'Every week', PCG_TEXTDOMAIN ),
        );
        ?>
        <select name="<?php echo esc_attr( PCG_Plugin::OPTION_SETTINGS ); ?>[notification_mode]">
            <?php foreach ( $options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $mode, $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Choose how often you receive a summary of pending and approved comments.', PCG_TEXTDOMAIN ); ?>
        </p>
        <?php
    }

    public function field_notification_recipients(): void
    {
        $settings   = $this->plugin->get_settings();
        $recipients = $settings['notification_recipients'] ?? '';
        ?>
        <input type="text"
               name="<?php echo esc_attr( PCG_Plugin::OPTION_SETTINGS ); ?>[notification_recipients]"
               value="<?php echo esc_attr( $recipients ); ?>"
               class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Comma-separated list of email addresses. Leave empty to use the site admin email.', PCG_TEXTDOMAIN ); ?>
        </p>
        <?php
    }


    public function field_enable_logging(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr( PCG_Plugin::OPTION_SETTINGS ); ?>[enable_logging]"
                   value="1" <?php checked( $settings['enable_logging'], true ); ?> />
            <?php esc_html_e( 'Log Perspective API errors to error_log().', PCG_TEXTDOMAIN ); ?>
        </label>
        <?php
    }

    /**
     * Add toxicity column on comments screen.
     *
     * @param array $columns Columns.
     * @return array
     */
    public function add_comment_column(array $columns ): array
    {
        $columns['pcg_toxicity'] = __( 'Toxicity', PCG_TEXTDOMAIN );
        return $columns;
    }

    /**
     * Render toxicity column.
     *
     * @param string $column Column name.
     * @param int $comment_id Comment ID.
     */
    public function render_comment_column(string $column, int $comment_id ): void
    {
        if ( 'pcg_toxicity' !== $column ) {
            return;
        }

        $score = get_comment_meta( $comment_id, PCG_Plugin::META_SCORE, true );

        if ( '' === $score || null === $score ) {
            echo '<em>' . esc_html__( 'n/a', PCG_TEXTDOMAIN ) . '</em>';
            return;
        }

        $score = (float) $score;
        echo esc_html( number_format( $score, 2 ) );
    }

    /**
     * Add comment meta box.
     */
    public function add_comment_metabox(): void
    {
        add_meta_box(
            'pcg_comment_info',
            __( 'Comment Shield AI', PCG_TEXTDOMAIN ),
            array( $this, 'render_metabox' ),
            'comment',
            'normal',
            'default'
        );
    }

    /**
     * Render comment meta box.
     *
     * @param WP_Comment $comment Comment object.
     */
    public function render_metabox(WP_Comment $comment): void
    {
        $score          = get_comment_meta( $comment->comment_ID, PCG_Plugin::META_SCORE, true );
        $score_display  = ( '' !== $score && null !== $score ) ? number_format( (float) $score, 2 ) : __( 'N/A', PCG_TEXTDOMAIN );

        wp_nonce_field( 'pcg_comment_metabox', 'pcg_comment_metabox_nonce' );
        ?>
        <p>
            <strong><?php esc_html_e( 'Toxicity score:', PCG_TEXTDOMAIN ); ?></strong>
            <?php echo esc_html( $score_display ); ?>
        </p>
        <p>
            <label>
                <input type="checkbox" name="pcg_force_public" value="1" />
                <?php esc_html_e( 'Mark as approved (override AI)', PCG_TEXTDOMAIN ); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="pcg_force_spam" value="1" />
                <?php esc_html_e( 'Mark as spam (override AI)', PCG_TEXTDOMAIN ); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Save metabox changes.
     *
     * @param int $comment_id Comment ID.
     */
    public function save_comment_meta(int $comment_id ): void
    {
        if ( ! isset( $_POST['pcg_comment_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pcg_comment_metabox_nonce'] ) ), 'pcg_comment_metabox' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
            return;
        }

        $force_public = isset( $_POST['pcg_force_public'] );
        $force_spam   = isset( $_POST['pcg_force_spam'] );

        if ( $force_public && ! $force_spam ) {
            wp_set_comment_status( $comment_id, 'approve' );
        } elseif ( $force_spam && ! $force_public ) {
            wp_spam_comment( $comment_id );
        }
    }
}
