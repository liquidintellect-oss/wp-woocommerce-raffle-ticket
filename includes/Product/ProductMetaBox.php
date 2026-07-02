<?php
/**
 * Product raffle settings meta box.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProductMetaBox
 *
 * Adds a "{label} Settings" meta box to the WooCommerce product editor,
 * allowing store admins to enable ticket assignment and configure the prefix
 * on a per-product basis.
 *
 * Physical ticket rolls (which define the number ranges) are managed separately
 * via the Ticket Rolls admin page.
 */
class ProductMetaBox {

	/** Nonce action string used when saving the meta box. */
	const NONCE_ACTION = 'raffle_ticket_product_meta';

	/** Nonce hidden form-field name. */
	const NONCE_FIELD = '_raffle_ticket_nonce';

	/**
	 * Constructor.
	 *
	 * @param string $label Configurable display label (e.g. "Raffle Tickets").
	 */
	public function __construct( private string $label ) {}

	/**
	 * Register the meta box on the product edit screen.
	 *
	 * @return void
	 */
	public function register(): void {
		/* translators: %s: configurable ticket label, e.g. "Raffle Tickets" */
		$meta_box_title = esc_html( sprintf( __( '%s Settings', 'wp-woocommerce-raffle-ticket' ), $this->label ) );

		add_meta_box(
			'raffle_ticket_settings',
			$meta_box_title,
			array( $this, 'render' ),
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * Render the meta box HTML.
	 *
	 * @param \WP_Post $post The current product post object.
	 *
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		$settings = ProductSettings::forProduct( $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<p>
			<label>
				<input
					type="checkbox"
					name="raffle_ticket_enabled"
					value="1"
					<?php checked( $settings->isEnabled() ); ?>
				/>
				<?php
				/* translators: %s: configurable ticket label, e.g. "Raffle Tickets" */
				echo esc_html( sprintf( __( 'Enable %s assignment for this product', 'wp-woocommerce-raffle-ticket' ), $this->label ) );
				?>
			</label>
		</p>
		<p>
			<label for="raffle_ticket_prefix">
				<?php esc_html_e( 'Ticket Prefix', 'wp-woocommerce-raffle-ticket' ); ?>
			</label><br />
			<input
				type="text"
				id="raffle_ticket_prefix"
				name="raffle_ticket_prefix"
				value="<?php echo esc_attr( $settings->getPrefix() ); ?>"
				style="width:100%;"
			/>
			<span class="description">
				<?php esc_html_e( 'Text prepended to every ticket number (e.g. "RAFFLE2024-").', 'wp-woocommerce-raffle-ticket' ); ?>
			</span>
		</p>
		<p class="description">
			<?php
			esc_html_e(
				'Ticket number ranges are defined by the physical rolls assigned to this product. Manage rolls under WooCommerce → Ticket Rolls.',
				'wp-woocommerce-raffle-ticket'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Save the raffle settings from the meta box POST data.
	 *
	 * Performs nonce verification and capability checks before writing meta.
	 *
	 * @param int $post_id The product post ID.
	 *
	 * @return void
	 */
	public function save( int $post_id ): void {
		// Nonce verification.
		$nonce = isset( $_POST[ self::NONCE_FIELD ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Enabled flag.
		$enabled = isset( $_POST['raffle_ticket_enabled'] )
			&& '1' === wp_unslash( $_POST['raffle_ticket_enabled'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_post_meta( $post_id, ProductSettings::META_ENABLED, $enabled ? '1' : '' );

		// Prefix.
		$prefix = isset( $_POST['raffle_ticket_prefix'] )
			? sanitize_text_field( wp_unslash( $_POST['raffle_ticket_prefix'] ) )
			: '';
		update_post_meta( $post_id, ProductSettings::META_PREFIX, $prefix );
	}
}
