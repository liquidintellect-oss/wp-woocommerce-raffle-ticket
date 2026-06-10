<?php
/**
 * Plugin-wide settings — configurable ticket label.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginSettings
 *
 * Owns the plugin-wide "ticket label" option: exposes a static getter used
 * throughout the plugin and registers the WordPress setting so that the
 * Settings API handles sanitisation and saving.
 *
 * The settings UI is rendered inline on the ReportPage rather than on a
 * dedicated page, keeping the WooCommerce admin menu compact.
 *
 * Usage:
 *   - Retrieve the current label: PluginSettings::getLabel()
 *   - Register the WP setting:    (new PluginSettings())->register()
 */
class PluginSettings {

	/** WordPress option key for the ticket label. */
	const OPTION_KEY = 'wrt_ticket_label';

	/** Default label when the option has not been saved. */
	const DEFAULT_LABEL = 'Raffle Tickets';

	/** Options group name used by the WordPress Settings API. */
	const OPTIONS_GROUP = 'wrt_settings_group';

	/**
	 * Return the configured ticket label, falling back to the default.
	 *
	 * @return string
	 */
	public static function getLabel(): string {
		$value = get_option( self::OPTION_KEY, self::DEFAULT_LABEL );
		$value = is_string( $value ) && '' !== trim( $value ) ? $value : self::DEFAULT_LABEL;
		return $value;
	}

	/**
	 * Register the WordPress admin hooks needed for settings persistence.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
	}

	/**
	 * Register the setting with the WordPress Settings API.
	 *
	 * Handles sanitisation and saving via options.php; no separate
	 * add_settings_section / add_settings_field calls are required because the
	 * field is rendered directly inside ReportPage::render().
	 *
	 * @return void
	 */
	public function registerSettings(): void {
		register_setting(
			self::OPTIONS_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => self::DEFAULT_LABEL,
			)
		);
	}
}
