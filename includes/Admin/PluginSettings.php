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
 * Registers a "Raffle Tickets" section under WooCommerce > Settings > Products
 * and exposes a single configurable field: the label used throughout the store
 * wherever raffle tickets are displayed (customer order details, admin order
 * view, report page, product meta box).
 *
 * Usage:
 *   - Retrieve the current label: PluginSettings::getLabel()
 *   - Register WooCommerce settings hooks: (new PluginSettings())->register()
 */
class PluginSettings {

	/** WordPress option key for the ticket label. */
	const OPTION_KEY = 'wrt_ticket_label';

	/** Default label when the option has not been saved. */
	const DEFAULT_LABEL = 'Raffle Tickets';

	/** WooCommerce settings section ID. */
	const SECTION_ID = 'raffle_tickets';

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
	 * Register WooCommerce settings filters for this plugin's settings section.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_get_sections_products', array( $this, 'addSection' ) );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'addSettings' ), 10, 2 );
	}

	/**
	 * Add a "Raffle Tickets" section to the WooCommerce Products settings tab.
	 *
	 * @param array $sections Existing sections keyed by slug.
	 *
	 * @return array
	 */
	public function addSection( array $sections ): array {
		$sections[ self::SECTION_ID ] = esc_html__( 'Raffle Tickets', 'wp-woocommerce-raffle-ticket' );
		return $sections;
	}

	/**
	 * Provide the settings fields for the Raffle Tickets section.
	 *
	 * Returns the existing $settings unchanged when a different section is active.
	 *
	 * @param array  $settings        Current settings fields for the active section.
	 * @param string $current_section The slug of the currently active section.
	 *
	 * @return array
	 */
	public function addSettings( array $settings, string $current_section ): array {
		if ( self::SECTION_ID !== $current_section ) {
			return $settings;
		}

		return array(
			array(
				'title' => esc_html__( 'Raffle Ticket Settings', 'wp-woocommerce-raffle-ticket' ),
				'type'  => 'title',
				'id'    => 'wrt_settings_title',
			),
			array(
				'title'   => esc_html__( 'Ticket Label', 'wp-woocommerce-raffle-ticket' ),
				'desc'    => esc_html__( 'Label used throughout the store for raffle tickets (e.g. "Raffle Tickets", "Lottery Tickets", "Event Tickets").', 'wp-woocommerce-raffle-ticket' ),
				'id'      => self::OPTION_KEY,
				'type'    => 'text',
				'default' => self::DEFAULT_LABEL,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wrt_settings_end',
			),
		);
	}
}
