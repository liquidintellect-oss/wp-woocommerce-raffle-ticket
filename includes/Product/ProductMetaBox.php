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
 * Adds a "Raffle Ticket Settings" meta box to the WooCommerce product editor,
 * allowing store admins to enable ticket assignment and configure the prefix
 * and sequence range on a per-product basis.
 */
class ProductMetaBox {

	/** Nonce action string used when saving the meta box. */
	const NONCE_ACTION = 'raffle_ticket_product_meta';

	/** Nonce hidden form-field name. */
	const NONCE_FIELD = '_raffle_ticket_nonce';

	/**
	 * Register the meta box on the product edit screen.
	 *
	 * @return void
	 */
	public function register(): void {
		add_meta_box(
			'raffle_ticket_settings',
			esc_html__( 'Raffle Ticket Settings', 'wp-woocommerce-raffle-ticket' ),
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
				<?php esc_html_e( 'Enable raffle ticket assignment for this product', 'wp-woocommerce-raffle-ticket' ); ?>
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
		<p>
			<label for="raffle_ticket_min_sequence">
				<?php esc_html_e( 'Min Sequence', 'wp-woocommerce-raffle-ticket' ); ?>
			</label><br />
			<input
				type="number"
				id="raffle_ticket_min_sequence"
				name="raffle_ticket_min_sequence"
				value="<?php echo esc_attr( (string) $settings->getMinSequence() ); ?>"
				min="1"
			/>
		</p>
		<p>
			<label for="raffle_ticket_max_sequence">
				<?php esc_html_e( 'Max Sequence', 'wp-woocommerce-raffle-ticket' ); ?>
			</label><br />
			<input
				type="number"
				id="raffle_ticket_max_sequence"
				name="raffle_ticket_max_sequence"
				value="<?php echo esc_attr( (string) $settings->getMaxSequence() ); ?>"
				min="1"
			/>
			<span class="description">
				<?php esc_html_e( 'The raffle is considered sold out once this number is reached.', 'wp-woocommerce-raffle-ticket' ); ?>
			</span>
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

		// Min sequence.
		$min = isset( $_POST['raffle_ticket_min_sequence'] )
			? absint( wp_unslash( $_POST['raffle_ticket_min_sequence'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: 1;
		update_post_meta( $post_id, ProductSettings::META_MIN_SEQUENCE, $min );

		// Max sequence.
		$max = isset( $_POST['raffle_ticket_max_sequence'] )
			? absint( wp_unslash( $_POST['raffle_ticket_max_sequence'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: 9999;
		update_post_meta( $post_id, ProductSettings::META_MAX_SEQUENCE, $max );
	}
}
