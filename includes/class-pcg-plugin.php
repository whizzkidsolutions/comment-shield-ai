<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCG_Plugin {

	const OPTION_SETTINGS = 'pcg_settings';
	const META_SCORE      = '_pcg_toxicity_score';
	const CRON_HOOK       = 'pcg_cron_check_comments';

	/**
	 * @var PCG_Plugin
	 */
	private static PCG_Plugin $instance;

	/**
	 * @var PCG_Notifications
	 */
	public PCG_Notifications $notifications;

	/**
	 * @var PCG_Admin
	 */
	public PCG_Admin $admin;

	/**
	 * @var PCG_Cron
	 */
	public PCG_Cron $cron;

	/**
	 * @var PCG_Perspective_Client
	 */
	public PCG_Perspective_Client $client;

	/**
	 * Singleton instance.
	 *
	 * @return PCG_Plugin
	 */
	public static function instance(): PCG_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->client        = new PCG_Perspective_Client( $this );
		$this->cron          = new PCG_Cron( $this );
		$this->notifications = new PCG_Notifications( $this );

		if ( is_admin() ) {
			$this->admin = new PCG_Admin( $this );
		}

		// Settings link on plugins screen.
		add_filter( 'plugin_action_links_' . PCG_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Plugin activation hook.
	 */
	public static function activate(): void {
		$plugin = self::instance();

		$defaults = array(
			'api_key'                 => '',
			'threshold_spam'          => 0.75,
			'auto_approve_below'      => 0.35,
			'batch_size'              => 20,
			'languages'               => array( 'en', 'nl' ),
			'enable_logging'          => false,
			'notification_mode'       => 'none',
			'notification_recipients' => '',
		);

		$current = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$settings = wp_parse_args( $current, $defaults );
		update_option( self::OPTION_SETTINGS, $settings, false );

		$plugin->cron->schedule();
	}

	/**
	 * Plugin deactivation hook.
	 */
	public static function deactivate() {
		$plugin = self::instance();
		$plugin->cron->clear();
	}

	/**
	 * Get plugin settings with sane defaults.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		$defaults = array(
			'api_key'            => '',
			'threshold_spam'     => 0.75,
			'auto_approve_below' => 0.35,
			'batch_size'         => 20,
			'languages'          => array( 'en', 'nl' ),
			'enable_logging'     => false,
		);

		$settings = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, $defaults );

		$settings['threshold_spam']          = (float) $settings['threshold_spam'];
		$settings['auto_approve_below']      = (float) $settings['auto_approve_below'];
		$settings['batch_size']              = max( 1, (int) $settings['batch_size'] );
		$settings['notification_mode']       = isset( $settings['notification_mode'] ) ? $settings['notification_mode'] : 'none';
		$settings['notification_recipients'] = isset( $settings['notification_recipients'] ) ? (string) $settings['notification_recipients'] : '';

		if ( empty( $settings['languages'] ) || ! is_array( $settings['languages'] ) ) {
			$settings['languages'] = array( 'en', 'nl', 'es' );
		}

		$settings['enable_logging'] = ! empty( $settings['enable_logging'] );

		return $settings;
	}

	/**
	 * Add a settings link on plugin list.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function add_settings_link( array $links ): array {
		$url     = admin_url( 'options-general.php?page=pcg-settings' );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', PCG_TEXTDOMAIN ) . '</a>';
		return $links;
	}
}
