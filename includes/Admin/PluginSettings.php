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
 * Registers a dedicated "Raffle Ticket Settings" submenu page under WooCommerce
 * and exposes a single configurable field: the label used throughout the store
 * wherever raffle tickets are displayed (customer order details, admin order
 * view, report page, product meta box).
 *
 * Usage:
 *   - Retrieve the current label: PluginSettings::getLabel()
 *   - Register admin hooks:       (new PluginSettings())->register()
 */
class PluginSettings {

	/** WordPress option key for the ticket label. */
	const OPTION_KEY = 'wrt_ticket_label';

	/** Default label when the option has not been saved. */
	const DEFAULT_LABEL = 'Raffle Tickets';

	/** Options group name used by WordPress Settings API. */
	const OPTIONS_GROUP = 'wrt_settings_group';

	/** Admin page slug. */
	const PAGE_SLUG = 'raffle-ticket-settings';

	/** Settings section ID. */
	const SECTION_ID = 'wrt_main';

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
	 * Register WordPress admin hooks for this plugin's settings page.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'addMenuPage' ) );
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
	}

	/**
	 * Add a "Raffle Ticket Settings" submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function addMenuPage(): void {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Raffle Ticket Settings', 'wp-woocommerce-raffle-ticket' ),
			esc_html__( 'Raffle Ticket Settings', 'wp-woocommerce-raffle-ticket' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the setting and its field via the WordPress Settings API.
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

		add_settings_section( self::SECTION_ID, '', '__return_null', self::PAGE_SLUG );

		add_settings_field(
			self::OPTION_KEY,
			esc_html__( 'Ticket Label', 'wp-woocommerce-raffle-ticket' ),
			array( $this, 'renderLabelField' ),
			self::PAGE_SLUG,
			self::SECTION_ID
		);
	}

	/**
	 * Render the "Ticket Label" input field.
	 *
	 * @return void
	 */
	public function renderLabelField(): void {
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>"
			value="<?php echo esc_attr( self::getLabel() ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Label used throughout the store wherever raffle tickets are displayed (e.g. "Raffle Tickets", "Lottery Tickets", "Event Tickets").', 'wp-woocommerce-raffle-ticket' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the settings page HTML.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Raffle Ticket Settings', 'wp-woocommerce-raffle-ticket' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTIONS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
